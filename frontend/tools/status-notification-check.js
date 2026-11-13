// TT-314：通知預覽要把「已關閉」「待驗收」的狀態通知標成重要。
// 後端誰會收到通知由PHPUnit驗證；這裡用真的後端建工作空間與專案, 只在通知列表回應裡加兩筆人造通知, 檢查瀏覽器上的標示與版面。
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
import { openProjectList } from './navigation.js';
if (!process.env.E2E_API_URL) throw new Error('請設定隔離E2E_API_URL，不可使用業務資料庫');
let server, browser;
try {
  server = await preview({ preview: { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } }), errors = [];
  page.on('pageerror', error => errors.push(error.message)); page.setDefaultTimeout(10000);
  await page.goto('http://127.0.0.1:5175/login');
  await page.getByRole('button', { name: '第一次使用？建立工作空間', exact: true }).click();
  await page.getByLabel('你的名字', { exact: true }).fill('狀態通知檢查者');
  await page.getByLabel('Email', { exact: true }).fill(`status-${crypto.randomUUID()}@example.test`);
  await page.getByLabel('密碼', { exact: true }).fill('status-test-only-password');
  await page.getByLabel('工作空間名稱', { exact: true }).fill('通知檢查');
  await page.getByRole('button', { name: '建立工作空間', exact: true }).click();
  await openProjectList(page);
  await page.getByRole('button', { name: '建立專案', exact: true }).click();
  const dialog = page.getByRole('dialog', { name: '建立專案', exact: true });
  await dialog.getByLabel(/專案名稱/).fill('通知專案'); await dialog.getByLabel(/專案代碼/).fill('NOTE');
  await dialog.getByRole('button', { name: '建立專案', exact: true }).click();
  await page.getByRole('button', { name: '進入專案：通知專案', exact: true }).click();

  const now = new Date().toISOString();
  const fake = [
    { id: 'fake-important', subject: '[NOTE-1] 已關閉', email: 'owner@example.test', status: 'preview', kind: 'status_important', createdAt: now, issueId: 'fake-issue', identifier: 'NOTE-1' },
    { id: 'fake-general', subject: '[NOTE-2] 狀態調整', email: 'owner@example.test', status: 'preview', kind: 'status', createdAt: now, issueId: 'fake-issue-2', identifier: 'NOTE-2' },
  ];
  await page.route(/\/api\/v1\/projects\/[^/]+\/notifications(\?|$)/, async route => {
    const response = await route.fetch(), body = await response.json();
    const target = Array.isArray(body.items) ? body : body.data;
    assert.ok(Array.isArray(target?.items), '通知列表回應格式改了, 請更新這支檢查');
    target.items = [...fake, ...target.items];
    await route.fulfill({ response, json: body });
  });
  await page.getByRole('button', { name: '通知預覽', exact: true }).click();
  const important = page.locator('.notification-card.important');
  await important.waitFor();
  assert.equal(await important.count(), 1, '只有重要狀態那筆要特別標示');
  await important.getByText('重要狀態', { exact: true }).waitFor();
  assert.equal(await page.locator('.notification-card').filter({ hasText: '[NOTE-2] 狀態調整' }).locator('.important-tag').count(), 0);
  const tag = await important.locator('.important-tag').evaluate(element => { const style = getComputedStyle(element); return { color: style.color, background: style.backgroundColor }; });
  assert.notEqual(tag.color, tag.background);

  mkdirSync('artifacts/status-notification', { recursive: true });
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${width}寬不能出現橫向捲動`);
    await important.scrollIntoViewIfNeeded();
    await page.screenshot({ path: `artifacts/status-notification/notifications-${width}.png` });
  }
  assert.deepEqual(errors, []);
  console.log('PASS: status_important notifications show the 重要狀態 tag; other kinds do not; no horizontal scroll at 1440/768/390');
} finally {
  await browser?.close();
  await new Promise(resolve => server ? server.httpServer.close(resolve) : resolve());
}
