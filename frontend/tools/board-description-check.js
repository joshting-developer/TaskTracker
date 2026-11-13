// TT-309：看板簡述顯示（列表兩行截斷＋tooltip、看板頁完整）、「編輯」視窗改名稱／簡述／收錄、三尺寸。只接受隔離API。
import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';
const api = process.env.E2E_API_URL || '';
if (!/^http:\/\/127\.0\.0\.1:13\d{3}$/.test(api)) throw new Error('請設定隔離E2E_API_URL（127.0.0.1:13xxx），不可使用業務資料庫');
const folder = 'artifacts/tt309';
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
  await page.goto('http://127.0.0.1:5175/login');
  await call(page, 'register', { name: '簡述驗收', email: `tt309-${crypto.randomUUID()}@example.test`, password: 'tt309-test-password', workspaceName: '簡述驗收' }, 'auth');
  const project = await call(page, 'createProject', { name: '看板簡述', code: 'TTDSC' });
  const issue = await call(page, 'createIssue', { projectId: project.id, title: '量測列表查詢' });
  const longText = '只收已經量測過、有前後數據的效能改善，例如查詢次數、回應時間、首屏載入；單純畫面調整或還沒量測的想法不收，請放到實際功能或結構優化看板。';
  const board = await call(page, 'createBoard', { projectId: project.id, name: '效能改進', description: longText, selectedIssueIds: [issue.id] });
  const root = `http://127.0.0.1:5175/app/#/projects/${project.id}`;

  await page.goto(`${root}/boards`);
  const list = page.getByRole('region', { name: '看板列表' });
  const summary = list.locator('.board-summary-description');
  await summary.waitFor();
  assert.equal(await summary.getAttribute('title'), longText, '列表tooltip是完整簡述');
  assert.equal(await list.locator('.board-summary-card').filter({ hasText: 'Default' }).locator('.board-summary-description').count(), 0, '沒簡述不顯示空白');
  for (const [width, height] of [[1440, 900], [768, 1024], [390, 844]]) {
    await page.setViewportSize({ width, height }); await page.waitForTimeout(100);
    const row = await page.evaluate(() => { const el = document.querySelector('.board-summary-description'); return { overflow: document.documentElement.scrollWidth > innerWidth, clamped: el.scrollHeight > el.clientHeight + 1 }; });
    measurements.push({ view: 'list', width, ...row }); assert.equal(row.overflow, false, `${width} 列表無橫向溢出`);
    await page.screenshot({ path: `${folder}/${width}-list.png`, animations: 'disabled', caret: 'hide' });
  }
  assert.ok(measurements.some(row => row.clamped), '長簡述在列表上截斷');
  await page.setViewportSize({ width: 1440, height: 900 });

  // 看板頁顯示完整簡述；「編輯」可以改名稱和簡述。
  await list.getByRole('button', { name: '開啟看板 效能改進' }).click();
  await page.locator('.selected-board-description').filter({ hasText: longText }).waitFor();
  await page.getByRole('button', { name: '編輯看板', exact: true }).click();
  const dialog = page.getByRole('dialog', { name: '編輯看板', exact: true });
  await dialog.getByLabel('看板名稱').fill('效能量測');
  await dialog.getByLabel('簡述').fill('只收有前後數據的效能改善');
  assert.match(await dialog.locator('#board-description-hint').innerText(), /12 \/ 300/);
  await page.screenshot({ path: `${folder}/1440-dialog.png`, animations: 'disabled', caret: 'hide' });
  await dialog.getByRole('button', { name: /儲存/ }).click();
  await page.getByRole('heading', { name: '效能量測' }).waitFor();
  await page.locator('.selected-board-description').filter({ hasText: '只收有前後數據的效能改善' }).waitFor();
  const fresh = await page.evaluate(async ({ project, board }) => (await (await fetch(`/api/v1/projects/${project}/boards/${board}`)).json()).data, { project: project.id, board: board.id });
  assert.deepEqual([fresh.name, fresh.description, fresh.selectedIssueIds], ['效能量測', '只收有前後數據的效能改善', [issue.id]], '名稱、簡述改了, 收錄不變');
  await page.setViewportSize({ width: 390, height: 844 }); await page.waitForTimeout(100);
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, '390 看板頁無橫向溢出');
  await page.screenshot({ path: `${folder}/390-board.png`, animations: 'disabled', caret: 'hide' });
  assert.deepEqual(errors, []);
  writeFileSync(`${folder}/measurements.json`, JSON.stringify(measurements, null, 2));
  console.log('PASS: board description in list (clamped + tooltip) and board page, edit name/description keeps inclusion, 3 viewports');
} finally {
  await browser?.close();
  await server?.close();
}
