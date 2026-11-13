// TT-283：看板封存→已封存清單→唯讀查看→還原（看板頁與清單兩個入口）；三尺寸版面。只接受隔離API。
import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';
const api = process.env.E2E_API_URL || '';
if (!/^http:\/\/127\.0\.0\.1:13\d{3}$/.test(api)) throw new Error('請設定隔離E2E_API_URL（127.0.0.1:13xxx），不可使用業務資料庫');
const folder = 'artifacts/tt283';
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
  await call(page, 'register', { name: '封存驗收', email: `tt283-${crypto.randomUUID()}@example.test`, password: 'tt283-test-password', workspaceName: '封存驗收' }, 'auth');
  const project = await call(page, 'createProject', { name: '封存流程', code: 'TTARC' });
  const issue = await call(page, 'createIssue', { projectId: project.id, title: '留在看板裡的卡片' });
  const board = await call(page, 'createBoard', { projectId: project.id, name: '第一階段', selectedIssueIds: [issue.id] });
  const root = `http://127.0.0.1:5175/app/#/projects/${project.id}`;

  // 從看板頁封存：文案是「封存」, 確認後回清單。
  await page.goto(`${root}/board/${board.id}`);
  await page.getByRole('heading', { name: '第一階段' }).waitFor();
  page.once('dialog', dialog => { assert.match(dialog.message(), /封存看板「第一階段」.*保留/); dialog.accept(); });
  await page.getByRole('button', { name: '封存看板', exact: true }).click();
  const list = page.getByRole('region', { name: '看板列表' });
  await list.waitFor();
  assert.equal(await list.getByRole('button', { name: '開啟看板 第一階段' }).count(), 0, '使用中清單不再顯示');

  // 已封存清單：顯示封存日期, 沒有新增看板與編輯收錄。
  await list.getByRole('button', { name: '已封存', exact: true }).click();
  await list.getByRole('button', { name: '開啟看板 第一階段' }).waitFor();
  assert.equal(await list.getByRole('button', { name: '已封存' }).getAttribute('aria-pressed'), 'true');
  assert.match(await list.locator('.board-summary-archived').innerText(), /封存於/);
  assert.equal(await list.getByRole('button', { name: '新增看板' }).count(), 0);
  assert.equal(await list.getByRole('button', { name: /^編輯看板/ }).count(), 0);
  for (const [width, height] of [[1440, 900], [768, 1024], [390, 844]]) {
    await page.setViewportSize({ width, height }); await page.waitForTimeout(100);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
    measurements.push({ view: 'archived-list', width, overflow }); assert.equal(overflow, false, `${width} 清單無橫向溢出`);
    await page.screenshot({ path: `${folder}/${width}-archived-list.png`, animations: 'disabled', caret: 'hide' });
  }
  await page.setViewportSize({ width: 1440, height: 900 });

  // 唯讀查看：有提示、卡片照常顯示、不能新增或拖曳、沒有編輯收錄／封存。
  await list.getByRole('button', { name: '開啟看板 第一階段' }).click();
  const note = page.getByRole('status').filter({ hasText: '這個看板已封存' });
  await note.waitFor();
  await page.locator(`.issue-card[data-issue="${issue.identifier}"]`).waitFor();
  assert.equal(await page.locator(`.issue-card[data-issue="${issue.identifier}"]`).getAttribute('draggable'), 'false');
  assert.equal(await page.locator('.lane-add').count(), 0, '封存看板不能新增卡片');
  assert.equal(await page.getByRole('button', { name: '封存看板' }).count(), 0);
  for (const [width, height] of [[1440, 900], [390, 844]]) {
    await page.setViewportSize({ width, height }); await page.waitForTimeout(100);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
    measurements.push({ view: 'archived-board', width, overflow }); assert.equal(overflow, false, `${width} 看板頁無橫向溢出`);
    await page.screenshot({ path: `${folder}/${width}-archived-board.png`, animations: 'disabled', caret: 'hide' });
  }
  await page.setViewportSize({ width: 1440, height: 900 });

  // 從看板頁還原：提示消失, 恢復編輯收錄與封存。
  await note.getByRole('button', { name: '還原看板' }).click();
  await page.getByRole('button', { name: '封存看板', exact: true }).waitFor();
  assert.equal(await note.count(), 0);

  // 再封存一次, 改從清單還原, 回到使用中。
  page.once('dialog', dialog => dialog.accept());
  await page.getByRole('button', { name: '封存看板', exact: true }).click();
  await list.waitFor();
  await list.getByRole('button', { name: '已封存', exact: true }).click();
  await list.getByRole('button', { name: '還原看板 第一階段' }).click();
  await list.getByText('目前沒有封存的看板').waitFor();
  await list.getByRole('button', { name: '使用中', exact: true }).click();
  await list.getByRole('button', { name: '開啟看板 第一階段' }).waitFor();
  assert.deepEqual(errors, []);
  writeFileSync(`${folder}/measurements.json`, JSON.stringify(measurements, null, 2));
  console.log('PASS: archive copy, archived list with date, read-only archived board, restore from board and from list, 3 viewports');
} finally {
  await browser?.close();
  await server?.close();
}
