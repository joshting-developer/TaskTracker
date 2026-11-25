import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
const port = Number(process.env.E2E_FRONTEND_PORT || 5199), apiUrl = process.env.E2E_API_URL;
assert.equal(process.env.E2E_ISOLATED, '1'); assert.ok(apiUrl);
let server, browser;
async function call(page, operation, input = {}, group = 'rpc') {
  const result = await page.evaluate(async ({ operation, input, group }) => {
    const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
    return (await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
  }, { operation, input, group });
  assert.equal(result.ok, true, JSON.stringify(result));
  if (result.data?.verificationRequired) return call(page, 'verify-email', { code: 'local666' }, 'auth');
  return result.data;
}
try {
  server = await preview({ preview: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': apiUrl } } });
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage({ locale: 'zh-TW', viewport: { width: 1440, height: 900 } });
  page.setDefaultTimeout(15000);
  const errors = [], reads = []; page.on('pageerror', e => errors.push(e.message));
  await page.goto(`http://127.0.0.1:${port}/login`);
  const identity = await call(page, 'register', { name: '摘要驗證', email: `dashboard-${crypto.randomUUID()}@example.test`, password: 'dashboard-isolated-password', workspaceName: '本人工作' }, 'auth');
  const project = await call(page, 'createProject', { name: '產品整理', code: 'DASH' });
  const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Taipei' }).format(new Date());
  for (let i = 0; i < 7; i++) await call(page, 'createIssue', { projectId: project.id, title: `摘要任務 ${i}`, assignee: identity.user.id, startDate: today, dueDate: today });
  await call(page, 'createIssue', { projectId: project.id, title: '不屬於我的任務' });
  await call(page, 'createTodo', { content: '檢查摘要與紙條入口', assigneeId: identity.user.id });
  page.on('request', r => { if (r.url().includes('/api/')) reads.push(r.url()); });
  await page.goto(`http://127.0.0.1:${port}/app/#/`);
  mkdirSync('artifacts/personal-dashboard', { recursive: true });
  if (process.env.DASHBOARD_BEFORE === '1') {
    await page.locator('.app-shell').waitFor();
    for (const width of [1440, 768, 390]) { await page.setViewportSize({ width, height: 900 }); await page.screenshot({ path: `artifacts/personal-dashboard/before-${width}.png`, fullPage: true }); }
  } else {
    const mine = page.getByRole('region', { name: '我的未結案任務', exact: true });
    await mine.getByRole('link', { name: /摘要任務/ }).first().waitFor();
    // TT-442 起每頁筆數依小工具尺寸計算, 從實際請求讀出再驗分頁, 不寫死筆數
    const perPage = Number(new URL(reads.find(url => url.includes('/personal-dashboard?') && url.includes('kind=mine'))).searchParams.get('perPage'));
    const lastPage = Math.ceil(7 / perPage), lastCount = 7 - perPage * (lastPage - 1);
    assert.ok(perPage >= 2 && perPage <= 20 && lastPage > 1, String(perPage));
    assert.equal(await mine.getByRole('link', { name: /摘要任務/ }).count(), perPage);
    assert.equal(await page.getByText('不屬於我的任務', { exact: true }).count(), 0);
    const pageText = text => page.waitForFunction(value => document.querySelector('[aria-label="我的未結案任務"]')?.getAttribute('aria-busy') === 'false' && document.querySelector('[aria-label="我的未結案任務"]')?.textContent.includes(value), text);
    for (let n = 2; n <= lastPage; n++) { await mine.getByRole('button', { name: '下一頁' }).click(); await pageText(`${n} / ${lastPage}`); }
    assert.equal(await mine.getByRole('link', { name: /摘要任務/ }).count(), lastCount);
    for (let n = lastPage - 1; n >= 1; n--) { await mine.getByRole('button', { name: '上一頁' }).click(); await pageText(`${n} / ${lastPage}`); }
    assert.equal(reads.some(url => /\/projects\/[^/]+\/issues/.test(url)), false);
    for (const url of reads.filter(url => url.includes('/personal-dashboard?'))) assert.ok(Number(new URL(url).searchParams.get('perPage')) <= 20, '每頁仍有上限');
    for (const width of [1440, 768, 390]) { await page.setViewportSize({ width, height: 900 }); if (width === 390) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1); assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false); await page.screenshot({ path: `artifacts/personal-dashboard/after-${width}.png`, fullPage: true }); }
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.route('**/api/v1/personal-dashboard?**', route => route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { code: 'INTERNAL_ERROR', message: '暫時無法讀取摘要' } }) }));
    await page.getByRole('button', { name: '重新整理', exact: true }).click(); await mine.getByRole('alert').waitFor();
    await page.unroute('**/api/v1/personal-dashboard?**'); await mine.getByRole('button', { name: '重試' }).click(); await mine.getByRole('link', { name: /摘要任務/ }).first().waitFor();
    await mine.getByRole('link', { name: /摘要任務/ }).first().click(); await page.getByRole('heading', { name: /摘要任務/ }).first().waitFor();
    assert.deepEqual(errors, []); console.log('PASS Dashboard: personal data, bounded requests, pagination, retry, issue link and three widths');
  }
} finally { await browser?.close(); await new Promise(resolve => server ? server.httpServer.close(resolve) : resolve()); }
