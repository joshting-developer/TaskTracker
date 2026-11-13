// TT-281：任務列表列尾改成鉛筆／箭頭圖示按鈕；可及名稱、tooltip、F2捷徑、三尺寸點擊面積與無溢出。只接受隔離API。
import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';
const api = process.env.E2E_API_URL || '';
if (!/^http:\/\/127\.0\.0\.1:13\d{3}$/.test(api)) throw new Error('請設定隔離E2E_API_URL（127.0.0.1:13xxx），不可使用業務資料庫');
const folder = 'artifacts/tt281';
mkdirSync(folder, { recursive: true });
let server, browser;
const errors = [], measurements = [];
async function call(page, operation, input = {}, group = 'rpc') {
  return page.evaluate(async ({ operation, input, group }) => {
    const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
    const result = await (await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
    if (!result.ok) throw new Error(result.error?.message || operation);
    if (result.data?.verificationRequired) { const csrf = await (await fetch('/api/csrf-cookie')).json(); const verified = await fetch('/api/auth/verify-email', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: JSON.stringify({ code: 'local666' }) }); if (!verified.ok) throw new Error('Fixture email verification failed'); result.data = (await verified.json()).data; } return result.data;
  }, { operation, input, group });
}
try {
  server = await createServer({ server: { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': api } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, locale: 'zh-TW', reducedMotion: 'reduce' });
  page.setDefaultTimeout(15000);
  page.on('pageerror', error => errors.push(error.message));
  await page.goto('http://127.0.0.1:5175/login');
  await call(page, 'register', { name: '列表驗收', email: `tt281-${crypto.randomUUID()}@example.test`, password: 'tt281-test-password', workspaceName: '列表驗收' }, 'auth');
  const project = await call(page, 'createProject', { name: '列表動作', code: 'TTROW' });
  const issue = await call(page, 'createIssue', { projectId: project.id, title: '這是一張標題比較長的卡片，用來看列尾按鈕會不會被擠掉' });
  await call(page, 'createIssue', { projectId: project.id, title: '第二張卡片' });
  await page.goto(`http://127.0.0.1:5175/app/#/projects/${project.id}`);
  const row = page.locator(`tr[data-issue="${issue.identifier}"]`);
  await row.waitFor();

  const edit = row.getByRole('button', { name: `快速編輯 ${issue.identifier}`, exact: true });
  assert.equal(await edit.getAttribute('title'), '快速編輯（F2）');
  assert.equal(await page.locator('.issue-table').getByText('快速編輯', { exact: true }).count(), 0, '列表不再顯示快速編輯文字');
  for (const [width, height] of [[1440, 900], [768, 1024], [390, 844]]) {
    await page.setViewportSize({ width, height });
    await page.waitForTimeout(100);
    const box = await edit.boundingBox(), openButton = row.getByRole('button', { name: `開啟完整頁 ${issue.identifier}` });
    // 手機只留鉛筆, 完整頁點標題。
    const open = width <= 700 ? (assert.equal(await openButton.isVisible(), false), { width: 44, height: 44 }) : await openButton.boundingBox();
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
    measurements.push({ width, edit: [box.width, box.height], open: [open.width, open.height], overflow });
    assert.equal(overflow, false, `${width} 無整頁橫向溢出`);
    assert.ok(box.height >= (width <= 700 ? 44 : 36) && open.height >= (width <= 700 ? 44 : 36), `${width} 點擊面積`);
    await page.screenshot({ path: `${folder}/${width}-list.png`, animations: 'disabled', caret: 'hide' });
  }
  await page.setViewportSize({ width: 1440, height: 900 });

  // 鉛筆開快速編輯抽屜；標題仍開閱讀頁；F2從列上的焦點開抽屜。
  await edit.click();
  await page.getByRole('dialog').filter({ hasText: issue.identifier }).waitFor();
  await page.keyboard.press('Escape');
  await page.getByRole('dialog').filter({ hasText: issue.identifier }).waitFor({ state: 'detached' });
  await row.locator('.task-title').focus();
  await page.keyboard.press('F2');
  await page.getByRole('dialog').filter({ hasText: issue.identifier }).waitFor();
  assert.ok(!new URL(page.url()).hash.includes('detail=1'), 'F2是快速編輯, 不是完整頁');
  await page.keyboard.press('Escape');
  await row.locator('.task-title').click();
  await page.getByRole('region', { name: '任務完整頁', exact: true }).waitFor();
  assert.deepEqual(errors, []);
  writeFileSync(`${folder}/measurements.json`, JSON.stringify(measurements, null, 2));
  console.log('PASS: pencil/open icon actions, accessible names + tooltip, F2 shortcut, title opens reader, 36px desktop / 44px touch, no overflow');
} finally {
  await browser?.close();
  await server?.close();
}
