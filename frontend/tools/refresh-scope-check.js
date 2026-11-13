import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { moveBoardCard } from './board-actions.js';
// TT-219：寫入與導覽只刷新目前頁需要的資料；報表不因一般刷新重讀。只連隔離E2E服務。
if (!process.env.E2E_API_URL) throw new Error('必須指定隔離E2E_API_URL');
let server, browser;
try {
  server = await createServer({ root: new URL('..', import.meta.url).pathname, server: { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } }), errors = [];
  let requests = [];
  page.setDefaultTimeout(10000);
  page.on('pageerror', error => errors.push(error.message));
  page.on('request', request => { const url = new URL(request.url()); if (url.pathname.startsWith('/api/') && url.pathname !== '/api/csrf-cookie') requests.push(url.pathname); });
  await page.goto('http://127.0.0.1:5175/login');
  async function call(op, input = {}, group = 'rpc') {
    return page.evaluate(async ({ op, input, group }) => {
      const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
      const result = await (await fetch(`/api/${group}/${op}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
      if (!result.ok) throw new Error(`${op}: ${result.error.message}`);
      return result.data;
    }, { op, input, group });
  }
  await call('register', { name: 'Scope Owner', email: `scope-${crypto.randomUUID()}@example.test`, password: 'isolated-test-password', workspaceName: 'Scope' }, 'auth');
  const project = await call('createProject', { name: 'Scope', code: 'SCP' });
  const other = await call('createProject', { name: 'Other', code: 'OTH' });
  const issue = await call('createIssue', { projectId: project.id, title: '範圍卡' });
  await call('createIssue', { projectId: other.id, title: '別的專案卡' });
  await call('logTime', { issueId: issue.id, workDate: '2026-10-05', minutes: 30, note: 'n' });
  await page.reload();
  const settle = () => page.waitForTimeout(800);
  const take = () => { const taken = requests; requests = []; return taken; };
  const go = async hash => { await page.evaluate(target => { location.hash = target; }, hash); await settle(); };
  const p = `/api/v1/projects/${project.id}`;
  const outside = paths => paths.filter(path => path.includes('/projects/') && !path.includes(project.id));

  // 1. 報表：切換分析分頁只讀一次報表，導覽後的App刷新不重複讀。
  await go('#/time-analysis'); take();
  await go('#/time-analysis/members');
  await page.getByRole('heading', { name: '成員投入' }).first().waitFor().catch(() => {});
  await settle();
  const members = take();
  assert.equal(members.filter(path => path.endsWith('/getTimeReport')).length, 1, `成員投入只讀一次報表：${members}`);
  await go('#/time-analysis/projects');
  assert.equal(take().filter(path => path.endsWith('/getTimeReport')).length, 1, '專案投入只讀一次報表');

  // 2. 看板寫入：只重讀目前Project／看板，不讀其他Project、報表、通知或整包資料。
  await go(`#/projects/${project.id}/board`);
  await page.locator('.issue-card[data-issue="SCP-1"]').waitFor(); take();
  await moveBoardCard(page, 'SCP-1', 'inprogress');
  await settle();
  const moved = take();
  assert.ok(moved.includes('/api/rpc/updateIssue'), '送出移動');
  assert.ok(moved.some(path => path.startsWith(`${p}/boards/default/issues`)), '重讀目前看板卡片');
  assert.deepEqual(outside(moved), [], '不讀其他Project');
  assert.deepEqual(moved.filter(path => /getTimeReport|getWorkspace|getTodos|notifications|time-calendar|status-usage/.test(path)), [], `不讀無關頁面資料：${moved}`);

  // 3. 狀態設定：只重讀目前Project與狀態使用摘要。
  await go(`#/projects/${project.id}/settings`);
  await page.getByLabel('狀態 3 名稱', { exact: true }).waitFor(); take();
  await page.getByLabel('狀態 3 名稱', { exact: true }).fill('完成了');
  await page.getByRole('button', { name: '儲存狀態設定' }).click();
  await page.getByRole('status').filter({ hasText: '狀態設定已儲存' }).waitFor();
  await settle();
  const saved = take();
  assert.ok(saved.includes('/api/rpc/updateStatuses'));
  assert.deepEqual(outside(saved), [], '不讀其他Project');
  assert.deepEqual(saved.filter(path => /issues|boards|getTimeReport|getWorkspace|getTodos|time-calendar/.test(path)), [], `狀態設定不讀卡片或其他頁：${saved}`);

  assert.deepEqual(errors, []);
  console.log('refresh scope PASS', JSON.stringify({ analysisReads: 1, boardWrite: moved.length, statusWrite: saved.length }));
} finally {
  await browser?.close();
  await server?.close();
}
