import assert from 'node:assert/strict';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { createServer } from 'vite';
import { chromium } from 'playwright';
const e2ePort = Number(process.env.E2E_FRONTEND_PORT || 5175); // TT-335：Claude 5198、Codex 5199, 見.agents/skills/tasktracker-test-environments
// TT-180：產品程式不呼叫整包getWorkspace／getTodos，所有一般頁面與背景輪詢以Network證明0次；只連隔離E2E服務。
if (!process.env.E2E_API_URL) throw new Error('必須指定隔離E2E_API_URL');

// 靜態：src內除API定義外不得呼叫整包入口（測試驗證仍可直接打RPC）。
const root = new URL('../src', import.meta.url).pathname;
const walk = dir => readdirSync(dir).flatMap(name => { const path = join(dir, name); return statSync(path).isDirectory() ? walk(path) : [path]; });
const offenders = walk(root).filter(path => /\.(js|vue)$/.test(path) && !path.endsWith('/api/index.js'))
  .filter(path => /\.(getWorkspace|getTodos)\s*\(|['"](getWorkspace|getTodos)['"]/.test(readFileSync(path, 'utf8')));
assert.deepEqual(offenders, [], '產品程式不得呼叫整包入口');

let server, browser;
try {
  server = await createServer({ root: new URL('..', import.meta.url).pathname, server: { host: '127.0.0.1', port: e2ePort, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } }), errors = [], requests = [];
  page.setDefaultTimeout(10000);
  page.on('pageerror', error => errors.push(error.message));
  page.on('request', request => { const url = new URL(request.url()); if (url.pathname.startsWith('/api/')) requests.push(url.pathname); });
  await page.goto(`http://127.0.0.1:${e2ePort}/login`);
  async function call(op, input = {}, group = 'rpc') {
    return page.evaluate(async ({ op, input, group }) => {
      const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
      const result = await (await fetch(`/api/${group}/${op}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
      if (!result.ok) throw new Error(`${op}: ${result.error.message}`);
      if (result.data?.verificationRequired) { const csrf = await (await fetch('/api/csrf-cookie')).json(); const verified = await fetch('/api/auth/verify-email', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: JSON.stringify({ code: 'local666' }) }); if (!verified.ok) throw new Error('Fixture email verification failed'); result.data = (await verified.json()).data; } return result.data;
    }, { op, input, group });
  }
  await call('register', { name: 'Payload Owner', email: `payload-${crypto.randomUUID()}@example.test`, password: 'isolated-test-password', workspaceName: 'Payload' }, 'auth');
  const project = await call('createProject', { name: 'Payload', code: 'PAY' });
  const issue = await call('createIssue', { projectId: project.id, title: '頁面巡檢卡' });
  const board = await call('createBoard', { projectId: project.id, name: '巡檢看板', selectedIssueIds: [issue.id] });
  await call('logTime', { issueId: issue.id, workDate: '2026-10-05', minutes: 30, note: 'n' });
  await call('createTodo', { title: '巡檢待辦' }).catch(() => {});

  await page.clock.install();
  await page.reload();
  const p = `#/projects/${project.id}`;
  const routes = [
    ['#/', '首頁'], ['#/projects', '專案列表'], [p, '任務列表'], [`${p}/boards`, '看板列表'], [`${p}/board`, 'Default看板'],
    [`${p}/board/${board.id}`, '自訂看板'], [`${p}/issues/${issue.id}?detail=1`, '任務完整頁'], [`${p}/settings`, '專案設定'],
    [`${p}/members`, '專案成員'], [`${p}/notifications`, '通知預覽'], [`${p}/time`, '工時月曆'],
    ['#/workspace/settings', '工作區設定'], ['#/workspace/settings/members', '工作區成員'],
    ['#/time-analysis', '工時分析'], ['#/time-analysis/members', '成員投入'], ['#/time-analysis/projects', '專案投入'], ['#/time-analysis/trend', '時間趨勢'],
    [`#/issue/${issue.id}`, '舊任務連結'],
  ];
  const visited = [];
  for (const [hash, label] of routes) {
    const before = requests.length;
    await page.evaluate(target => { location.hash = target; }, hash);
    await page.waitForTimeout(700);
    visited.push({ label, requests: requests.length - before });
  }
  // 舊連結解析回Project內的任務位置。
  assert.ok(await page.evaluate(id => location.hash.includes(`/issues/${id}`), issue.id), '舊任務連結導向Project位置');
  // 看板背景輪詢兩輪。
  await page.evaluate(target => { location.hash = target; }, `${p}/board`);
  await page.waitForTimeout(500);
  await page.clock.fastForward(70000);
  await page.waitForTimeout(500);

  const bulk = requests.filter(path => /\/api\/rpc\/(getWorkspace|getTodos)$/.test(path));
  assert.deepEqual(bulk, [], '一般頁面與背景輪詢不讀整包Workspace／Todos');
  assert.deepEqual(errors, []);
  console.log('workspace payload PASS', JSON.stringify({ pages: visited.length, bulkReads: bulk.length, totalRequests: requests.length }));
} finally {
  await browser?.close();
  await server?.close();
}
