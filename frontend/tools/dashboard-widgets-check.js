import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
// TT-186：首頁小工具可上移、下移、隱藏、加入與恢復預設, 偏好存在伺服器, 重新整理與重新登入後還原。
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
  const errors = []; page.on('pageerror', e => errors.push(e.message));
  await page.goto(`http://127.0.0.1:${port}/login`);
  await call(page, 'register', { name: '小工具', email: `widgets-${crypto.randomUUID()}@example.test`, password: 'widgets-password', workspaceName: '首頁小工具' }, 'auth');
  await page.goto(`http://127.0.0.1:${port}/app/#/`); await page.locator('.app-shell').waitFor();
  const dashboard = page.getByRole('region', { name: '個人工作摘要' });
  const titles = () => dashboard.locator('.dashboard-grid h2').allInnerTexts().then(list => list.map(text => text.replace(/\s*\d+$/, '').trim()));
  await dashboard.getByRole('button', { name: '自訂' }).waitFor(); await page.waitForFunction(() => !document.querySelector('.dashboard-actions button:disabled'));
  assert.deepEqual(await titles(), ['我的未結案任務', '七天內到期與逾期', '七天內開始', '最近更新', '待處理紙條']);
  await dashboard.getByRole('button', { name: '自訂' }).click();
  await dashboard.getByRole('group', { name: '調整七天內到期與逾期' }).getByRole('button', { name: '上移' }).click();
  await dashboard.getByRole('group', { name: '調整最近更新' }).getByRole('button', { name: '隱藏' }).click();
  assert.equal(await dashboard.getByRole('group', { name: '調整七天內到期與逾期' }).getByRole('button', { name: '上移' }).isDisabled(), true, '第一個不能再上移');
  await dashboard.getByRole('region', { name: '可加入的小工具' }).getByRole('button', { name: /最近更新/ }).waitFor();
  mkdirSync('artifacts/dashboard-widgets', { recursive: true });
  await page.screenshot({ path: 'artifacts/dashboard-widgets/customize-1440.png', fullPage: true });
  await dashboard.getByRole('button', { name: '儲存', exact: true }).click(); await dashboard.getByText('首頁設定已儲存').waitFor();
  assert.deepEqual(await titles(), ['七天內到期與逾期', '我的未結案任務', '七天內開始', '待處理紙條']);
  await page.reload(); await dashboard.getByRole('button', { name: '自訂' }).waitFor(); await page.waitForFunction(() => document.querySelectorAll('.dashboard-grid h2').length === 4);
  assert.deepEqual(await titles(), ['七天內到期與逾期', '我的未結案任務', '七天內開始', '待處理紙條'], '重新整理後還原');
  // 取消不保存；加入後取消仍維持原樣。
  await dashboard.getByRole('button', { name: '自訂' }).click();
  await dashboard.getByRole('region', { name: '可加入的小工具' }).getByRole('button', { name: /最近更新/ }).click();
  await dashboard.getByRole('button', { name: '取消', exact: true }).click();
  assert.equal((await titles()).length, 4);
  for (const width of [768, 390]) { await page.setViewportSize({ width, height: 900 }); if (width === 390) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1); assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${width} overflow`); await page.screenshot({ path: `artifacts/dashboard-widgets/after-${width}.png`, fullPage: true }); }
  await page.setViewportSize({ width: 1440, height: 900 });
  await dashboard.getByRole('button', { name: '自訂' }).click(); await dashboard.getByRole('button', { name: '恢復預設' }).click(); await dashboard.getByText('首頁設定已儲存').waitFor();
  assert.deepEqual(await titles(), ['我的未結案任務', '七天內到期與逾期', '七天內開始', '最近更新', '待處理紙條']);
  assert.deepEqual(errors, []); console.log('PASS dashboard widgets: move/hide/add/cancel/restore, server persistence across reload and three widths');
} finally { await browser?.close(); await new Promise(resolve => server ? server.httpServer.close(resolve) : resolve()); }
