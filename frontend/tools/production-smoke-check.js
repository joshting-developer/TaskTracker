import assert from 'node:assert/strict';
import { chromium } from 'playwright';
// TT-151：直接對正式runtime容器（Nginx＋PHP-FPM＋Vite靜態檔）驗證同源頁面、API、並行與快取標頭；只連隔離測試資料庫。
const base = process.env.PROD_BASE_URL;
if (!base) throw new Error('必須指定隔離正式runtime的PROD_BASE_URL');
let browser;
try {
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } }), errors = [], bulk = [];
  page.setDefaultTimeout(15000);
  page.on('pageerror', error => errors.push(error.message));
  page.on('request', request => { if (/\/api\/rpc\/(getWorkspace|getTodos)$/.test(new URL(request.url()).pathname)) bulk.push(request.url()); });
  await page.goto(base);
  await page.getByRole('button', { name: '登入', exact: true }).waitFor();
  async function call(op, input = {}, group = 'rpc') {
    return page.evaluate(async ({ op, input, group }) => {
      const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
      const result = await (await fetch(`/api/${group}/${op}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
      if (!result.ok) throw new Error(`${op}: ${result.error.message}`);
      return result.data;
    }, { op, input, group });
  }
  await call('register', { name: 'Prod Owner', email: `prod-${crypto.randomUUID()}@example.test`, password: 'isolated-test-password', workspaceName: 'Prod' }, 'auth');
  const project = await call('createProject', { name: 'Prod', code: 'PRD' });
  const issue = await call('createIssue', { projectId: project.id, title: '正式runtime卡片' });
  await page.goto(`${base}/#/projects/${project.id}/board`);
  await page.reload();
  await page.locator(`.issue-card[data-issue="${issue.identifier}"]`).waitFor();
  await page.goto(`${base}/#/projects/${project.id}/issues/${issue.id}?detail=1`);
  await page.reload();
  await page.getByRole('region', { name: '任務完整頁', exact: true }).getByText('正式runtime卡片').first().waitFor();

  // 並行：FPM多worker同時處理，不像artisan serve逐一排隊。
  const timings = await page.evaluate(async id => {
    const one = async () => { const start = performance.now(); const response = await fetch(`/api/v1/projects/${id}/issues?page=1&perPage=20`); return { ok: response.ok, ms: performance.now() - start }; };
    const serialStart = performance.now(); for (let n = 0; n < 6; n++) await one(); const serial = performance.now() - serialStart;
    const parallelStart = performance.now(); const results = await Promise.all(Array.from({ length: 6 }, one)); const parallel = performance.now() - parallelStart;
    return { serial: Math.round(serial), parallel: Math.round(parallel), allOk: results.every(item => item.ok) };
  }, project.id);
  assert.ok(timings.allOk, '並行請求全部成功');
  assert.deepEqual(bulk, []);
  assert.deepEqual(errors, []);
  console.log('production smoke PASS', JSON.stringify(timings));
} finally {
  await browser?.close();
}
