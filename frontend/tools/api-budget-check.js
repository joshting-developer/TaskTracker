import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
// TT-226：各頁允許的API、初次請求數／回應bytes門檻與背景請求回歸；500卡隔離資料，只連隔離E2E服務。
// 門檻來自本工具在隔離MySQL的實測值再留餘裕（見docs/api-budgets-2026-11-12.md），不是正式SLO。
if (!process.env.E2E_API_URL) throw new Error('必須指定隔離E2E_API_URL');
const CARDS = Number(process.env.BUDGET_CARDS || 500);
// 實測（隔離MySQL、500卡）：首頁6次／720 B、列表2次／7.9 KB、詳細3次／3.7 KB、看板8次／310 KB（約620 B／卡）、報表1次／1 KB；
// 60卡時除看板外數值相同。門檻＝實測再留餘裕，看板依卡數線性。
const BUDGETS = {
  home: { requests: 8, bytes: 4000, allow: [/^\/api\/auth\/current$/, /\/personal-version$/, /\/todo-notifications\/unread$/, /\/todos$/, /\/todos\/counts$/, /\/todo-notifications$/, /\/todo-assignees$/, /\/project-options$/] },
  list: { requests: 4, bytes: 15000, allow: [/^\/api\/auth\/current$/, /\/projects\/[^/]+$/, /\/projects\/[^/]+\/issues$/, /\/member-options$/, /\/issue-options$/] },
  detail: { requests: 5, bytes: 10000, allow: [/^\/api\/auth\/current$/, /\/projects\/[^/]+$/, /\/issues\/[^/]+$/, /\/issues\/[^/]+\/comments$/, /\/member-options$/, /\/issue-options$/] },
  board: { requests: 4 + Math.ceil(CARDS / 100), bytes: 700 * CARDS + 10000, allow: [/^\/api\/auth\/current$/, /\/projects\/[^/]+$/, /\/boards\/[^/]+$/, /\/boards\/[^/]+\/issues$/, /\/boards\/[^/]+\/version$/, /\/realtime\/config$/] },
  report: { requests: 2, bytes: 3000, allow: [/^\/api\/auth\/current$/, /\/getTimeReport$/] },
};
const MEASURE = process.env.BUDGET_MEASURE === '1';
const FORBIDDEN = /\/api\/rpc\/(getWorkspace|getTodos)$/;
let server, browser;
try {
  server = await createServer({ root: new URL('..', import.meta.url).pathname, server: { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } }), errors = [];
  let log = [];
  page.setDefaultTimeout(20000);
  page.on('pageerror', error => errors.push(error.message));
  page.on('response', async response => {
    const path = new URL(response.url()).pathname;
    if (!path.startsWith('/api/') || path === '/api/csrf-cookie' || response.request().method() !== 'GET' && !path.startsWith('/api/rpc/getTimeReport')) return;
    const item = { path, bytes: 0 };
    log.push(item);
    try { item.bytes = (await response.body()).length; } catch { /* 導覽後不可取 */ }
  });
  await page.goto('http://127.0.0.1:5175');
  async function call(op, input = {}, group = 'rpc') {
    return page.evaluate(async ({ op, input, group }) => {
      const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
      const result = await (await fetch(`/api/${group}/${op}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
      if (!result.ok) throw new Error(`${op}: ${result.error.message}`);
      return result.data;
    }, { op, input, group });
  }
  const identity = await call('register', { name: 'Budget Owner', email: `budget-${crypto.randomUUID()}@example.test`, password: 'isolated-test-password', workspaceName: 'Budget' }, 'auth');
  const project = await call('createProject', { name: 'Budget', code: 'BUD' });
  const epics = [];
  for (let n = 0; n < 5; n++) epics.push(await call('createIssue', { projectId: project.id, title: `Epic ${n + 1}`, type: 'epic' }));
  let sample;
  for (let n = 0; n < CARDS - 5; n++) {
    const issue = await call('createIssue', { projectId: project.id, title: `Budget ${n}`, description: 'x'.repeat(500), epicId: epics[n % 5].id, state: ['open', 'inprogress', 'closed'][n % 3], assignee: n % 2 ? identity.user.id : '', estimateMinutes: 30 });
    if (n === 0) sample = issue;
  }
  let revision = sample.revision;
  for (let n = 0; n < 12; n++) revision = (await call('addComment', { id: sample.id, content: `comment ${n}`, expectedRevision: revision })).revision;
  for (let n = 0; n < 60; n++) await call('logTime', { issueId: sample.id, workDate: `2026-${String(1 + n % 10).padStart(2, '0')}-${String(1 + n % 27).padStart(2, '0')}`, minutes: 10, note: 'n' });

  await page.clock.install();
  await page.reload();
  await page.waitForTimeout(1000);
  const settle = () => page.waitForTimeout(1200);
  const go = async hash => { await page.evaluate(target => { location.hash = target; }, hash); await settle(); };
  const results = {};
  function check(name) {
    const taken = log; log = [];
    const budget = BUDGETS[name], bytes = taken.reduce((sum, item) => sum + item.bytes, 0);
    results[name] = { requests: taken.length, bytes, paths: [...new Set(taken.map(item => item.path.replace(/[0-9a-f-]{36}/g, ':id')))] };
    assert.deepEqual(taken.filter(item => FORBIDDEN.test(item.path)), [], `${name}不得讀整包入口`);
    if (MEASURE) return taken;
    assert.deepEqual(taken.filter(item => !budget.allow.some(pattern => pattern.test(item.path))).map(item => item.path), [], `${name}只呼叫允許的endpoint`);
    assert.ok(taken.length <= budget.requests, `${name}請求數 ${taken.length} > ${budget.requests}`);
    assert.ok(bytes <= budget.bytes, `${name}回應 ${bytes} B > ${budget.bytes} B`);
    return taken;
  }
  const p = `#/projects/${project.id}`;
  await go('#/'); log = [];
  await page.reload(); await settle(); check('home');
  await go(p); check('list');
  await go(`${p}/issues/${sample.id}?detail=1`);
  const detail = check('detail');
  assert.equal(detail.filter(item => /\/history$/.test(item.path)).length, 0, '未開異動紀錄不讀歷史');
  await page.getByRole('tab', { name: /異動紀錄/ }).click(); await settle();
  assert.ok(log.some(item => /\/history$/.test(item.path)), '開啟異動紀錄才讀歷史'); log = [];
  await go(`${p}/board`);
  const board = check('board');
  assert.ok(board.filter(item => /\/boards\/[^/]+\/issues$/.test(item.path)).length <= Math.ceil(CARDS / 100), '卡片頁數依100筆分頁');
  await go('#/time-analysis/members'); check('report');

  // 背景：隱藏不送；版本不變只送version；事件風暴受最短間隔與不重疊保護。
  await go(`${p}/board`); log = [];
  const hide = hidden => page.evaluate(value => { Object.defineProperty(document, 'hidden', { configurable: true, get: () => value }); document.dispatchEvent(new Event('visibilitychange')); }, hidden);
  await hide(true);
  await page.clock.fastForward(180000); await settle();
  results.hidden = log.length;
  assert.equal(log.length, 0, '隱藏時0請求'); log = [];
  await hide(false); await settle(); log = [];
  await page.clock.fastForward(100000); await settle();
  const idle = log; log = [];
  results.idleVersionOnly = idle.map(item => item.path.replace(/[0-9a-f-]{36}/g, ':id'));
  assert.ok(idle.length >= 2, '可見時持續輪詢');
  assert.deepEqual(idle.filter(item => !/\/version$|\/personal-version$/.test(item.path)).map(item => item.path), [], '版本不變只送版本檢查');
  await page.evaluate(() => { for (let n = 0; n < 20; n++) window.dispatchEvent(new Event('focus')); });
  for (let n = 0; n < 5; n++) { await hide(true); await hide(false); }
  await settle();
  results.storm = log.length;
  assert.ok(log.length <= 4, `事件風暴請求數 ${log.length} > 4`);
  assert.deepEqual(errors, []);
  console.log('api budget PASS', JSON.stringify({ cards: CARDS, ...results }));
} finally {
  await browser?.close();
  await server?.close();
}
