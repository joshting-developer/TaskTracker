// TT-250：任務完整頁永久刪除流程（確認、成功返回、409原因、三尺寸版面）；只接受隔離API。
import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';
const api = process.env.E2E_API_URL || '';
if (!/^http:\/\/127\.0\.0\.1:13\d{3}$/.test(api)) throw new Error('請設定隔離E2E_API_URL（127.0.0.1:13xxx），不可使用業務資料庫');
const folder = 'artifacts/tt250';
mkdirSync(folder, { recursive: true });
let server, browser;
const errors = [], measurements = [];
async function call(page, operation, input = {}, group = 'rpc') {
  return page.evaluate(async ({ operation, input, group }) => {
    const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
    const result = await (await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
    if (!result.ok) throw new Error(result.error?.message || operation);
    return result.data;
  }, { operation, input, group });
}
try {
  server = await createServer({ server: { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': api } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, locale: 'zh-TW', reducedMotion: 'reduce' });
  page.setDefaultTimeout(15000);
  page.on('pageerror', error => errors.push(error.message));
  await page.goto('http://127.0.0.1:5175');
  await call(page, 'register', { name: '刪除驗收', email: `tt250-${crypto.randomUUID()}@example.test`, password: 'tt250-test-password', workspaceName: '刪除驗收' }, 'auth');
  const project = await call(page, 'createProject', { name: '刪除流程', code: 'TTDEL' });
  const doomed = await call(page, 'createIssue', { projectId: project.id, title: '誤建的卡片' });
  const timed = await call(page, 'createIssue', { projectId: project.id, title: '已填工時的卡片' });
  await call(page, 'logTime', { issueId: timed.id, workDate: new Date(Date.now() + 8 * 3600e3).toISOString().slice(0, 10), minutes: 30 });
  const root = `http://127.0.0.1:5175/#/projects/${project.id}`;
  const detail = page.getByRole('region', { name: '任務完整頁', exact: true });

  await page.goto(`${root}/issues/${timed.id}?detail=1`);
  await page.reload();
  await detail.getByRole('heading', { name: '已填工時的卡片', exact: true }).waitFor();
  for (const [width, height] of [[1440, 900], [768, 1024], [390, 844]]) {
    await page.setViewportSize({ width, height });
    await page.waitForTimeout(100);
    const row = await page.evaluate(() => {
      const button = [...document.querySelectorAll('.issue-detail-actions button')].find(element => element.textContent.includes('刪除卡片'));
      const box = button.getBoundingClientRect();
      return { width: innerWidth, overflow: document.documentElement.scrollWidth > innerWidth, height: box.height, visible: box.width > 0 && box.right <= innerWidth };
    });
    measurements.push(row);
    assert.equal(row.overflow, false, `${width} 無橫向溢出`);
    assert.ok(row.visible, `${width} 刪除按鈕可見`);
    await page.screenshot({ path: `${folder}/${width}-detail.png`, animations: 'disabled', caret: 'hide' });
  }
  await page.setViewportSize({ width: 1440, height: 900 });
  page.once('dialog', dialog => dialog.dismiss());
  await detail.getByRole('button', { name: '刪除卡片', exact: true }).click();
  await detail.getByRole('heading', { name: '已填工時的卡片', exact: true }).waitFor();
  page.once('dialog', dialog => { assert.match(dialog.message(), /無法復原/); dialog.accept(); });
  await detail.getByRole('button', { name: '刪除卡片', exact: true }).click();
  await page.getByRole('alert').filter({ hasText: '已有工時紀錄' }).waitFor();

  await page.goto(`${root}/issues/${doomed.id}?detail=1`);
  await detail.getByRole('heading', { name: '誤建的卡片', exact: true }).waitFor();
  page.once('dialog', dialog => dialog.accept());
  await detail.getByRole('button', { name: '刪除卡片', exact: true }).click();
  await detail.waitFor({ state: 'detached' });
  await page.getByText('已填工時的卡片', { exact: true }).first().waitFor();
  assert.equal(await page.getByText('誤建的卡片', { exact: true }).count(), 0, '刪除後列表不再出現');
  assert.equal(await page.getByRole('alert').filter({ hasText: '已有工時紀錄' }).count(), 0, '成功後清除舊錯誤');
  await page.screenshot({ path: `${folder}/1440-after-delete.png`, animations: 'disabled', caret: 'hide' });
  assert.deepEqual(errors, []);
  writeFileSync(`${folder}/measurements.json`, JSON.stringify(measurements, null, 2));
  console.log('PASS: detail delete confirm/cancel, CONFLICT message for timed card, success returns to list, 3 viewports');
} finally {
  await browser?.close();
  await server?.close();
}
