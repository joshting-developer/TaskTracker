import { moveBoardCard, changeBoardCardStateInDetail } from './board-actions.js';
import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createServer, preview } from 'vite';
import { chromium } from 'playwright';
if (!process.env.E2E_API_URL) throw new Error('請設定隔離E2E_API_URL，不可使用業務資料庫');
const before = process.env.E2E_BEFORE === '1', built = process.env.E2E_PREVIEW === '1';
const artifacts = `artifacts/board-ux-${before ? 'before' : 'after'}`;
mkdirSync(artifacts, { recursive: true });
let server, browser;
async function call(page, operation, input = {}, group = 'rpc') {
  return page.evaluate(async ({ operation, input, group }) => {
    const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
    const response = await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify(input) });
    const result = await response.json(); if (!result.ok) throw new Error(result.error?.message); return result.data;
  }, { operation, input, group });
}
try {
  const config = { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } };
  server = built ? await preview({ preview: config }) : await createServer({ server: config });
  if (!built) await server.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  page.setDefaultTimeout(15000); const errors = []; page.on('pageerror', error => errors.push(error.message));
  await page.goto('http://127.0.0.1:5175');
  const identity = await call(page, 'register', { name: '看板試用者', email: `board-ux-${crypto.randomUUID()}@example.test`, password: 'board-ux-test-password', workspaceName: '看板協作' }, 'auth');
  const project = await call(page, 'createProject', { name: '長期產品開發', code: 'UXBOARD', requestId: crypto.randomUUID() });
  const epic = await call(page, 'createIssue', { projectId: project.id, type: 'epic', title: '會員功能第一版', requestId: crypto.randomUUID() });
  const otherEpic = await call(page, 'createIssue', { projectId: project.id, type: 'epic', title: '下一輪交付', requestId: crypto.randomUUID() });
  const tasks = [];
  for (let i = 1; i <= 18; i++) tasks.push(await call(page, 'createIssue', { projectId: project.id, title: `執行工作 ${String(i).padStart(2, '0')}`, type: i === 2 ? 'bug' : 'task', epicId: epic.id, assignee: identity.user.id, estimateMinutes: 120, dueDate: '2026-12-10', description: i === 1 ? '這是比較卡片顯示密度的描述，預期詳細會顯示，精簡會隱藏。' : '', requestId: crypto.randomUUID() }));
  const boardUrl = `http://127.0.0.1:5175/#/projects/${project.id}/board`;
  const key = `tasktracker-board:${identity.user.id}:${project.id}`;
  await page.goto(boardUrl); await page.reload(); await page.locator('.issue-card').first().waitFor();
  const board = page.locator('.visual-board'), first = page.locator(`[data-issue="${tasks[0].identifier}"]`);
  const measurements = [];
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: width === 390 ? 844 : 1000 });
    if (width === 390) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    await page.evaluate(() => window.scrollTo(0, 0)); await page.screenshot({ path: `${artifacts}/top-${width}.png` });
    const m = await board.locator('.board-scroll').evaluate(el => ({ height: el.clientHeight, scrollHeight: el.scrollHeight, horizontal: el.scrollWidth > el.clientWidth }));
    measurements.push({ width, ...m });
    if (!before) {
      await page.locator('.board-scroll').hover({ position: { x: 80, y: 70 } });
      await page.mouse.wheel(0, 500); await page.waitForFunction(() => scrollY > 0);
      assert.ok(m.scrollHeight <= m.height + 1, '看板沒有第二層垂直捲動');
      await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
      const last = page.locator('.board-cell').first().locator('.issue-card').last();
      const box = await last.boundingBox(); assert.ok(box.y >= 0 && box.y + box.height <= (width === 390 ? 844 : 1000), JSON.stringify(box));
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
      await page.screenshot({ path: `${artifacts}/bottom-${width}.png` });
    }
  }
  if (!before) {
    await page.setViewportSize({ width: 1440, height: 1000 });
    assert.equal(await page.getByLabel('看板泳道').inputValue(), 'none');
    assert.equal(await board.locator(`[data-issue="${epic.identifier}"]`).count(), 1, 'Default顯示Epic本身');
    assert.ok((await board.locator(`[data-issue="${epic.identifier}"] .context-hours`).innerText()).includes('36 h'), 'Epic工時／估時依所屬卡片加總');
    await page.getByRole('button', { name: '任務篩選', exact: true }).click();
    await page.getByLabel('篩選類型').selectOption('epic'); assert.equal(await board.locator('.issue-card').count(), 2);
    await page.getByLabel('篩選 Epic').selectOption(epic.id); assert.equal(await board.locator('.issue-card').count(), 1);
    await page.getByRole('button', { name: '清除全部篩選', exact: true }).click();
    await page.getByRole('button', { name: '任務篩選', exact: true }).click();
    await page.getByLabel('看板泳道').selectOption('epic'); await page.reload(); await page.getByLabel('看板泳道').waitFor();
    assert.equal(await page.getByLabel('看板泳道').inputValue(), 'epic');
    const lane = page.getByRole('region', { name: '泳道 會員功能第一版', exact: true });
    assert.equal(await lane.locator(`[data-issue="${epic.identifier}"]`).count(), 1);
    for (const width of [1440, 390]) {
      await page.setViewportSize({ width, height: width === 390 ? 844 : 1000 });
      if (width === 390) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
      await page.evaluate(() => scrollTo(0, 0)); await page.screenshot({ path: `${artifacts}/grouped-${width}.png` });
      assert.ok(await board.locator('.board-scroll').evaluate(el => el.scrollHeight <= el.clientHeight + 1));
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.getByLabel('看板泳道').selectOption('none'); await page.reload(); assert.equal(await page.getByLabel('看板泳道').inputValue(), 'none');
    await page.evaluate(key => localStorage.setItem(key, '{invalid'), key); await page.reload(); assert.equal(await page.getByLabel('看板泳道').inputValue(), 'none');
    await page.getByLabel('看板卡片密度').selectOption('comfortable');
    const detailed = await first.boundingBox(); await first.locator('.issue-context').waitFor();
    await page.getByLabel('看板卡片密度').selectOption('compact');
    const compact = await first.boundingBox(); assert.ok(compact.height < detailed.height * .78, JSON.stringify({ compact, detailed }));
    assert.equal(await first.locator('.issue-context, .board-card-description, .due-date, .comment-count').count(), 0);
    assert.equal(await board.locator('.issue-card select, .card-state').count(), 0, '卡片不提供重複的狀態控制');
    await page.reload(); assert.equal(await page.getByLabel('看板卡片密度').inputValue(), 'compact');
    assert.equal(await board.getByText('快速編輯', { exact: true }).count(), 0);
    await first.locator('.card-title').dblclick();
    const editor = page.getByRole('dialog', { name: '任務詳細內容', exact: true }); await editor.waitFor();
    assert.equal(await page.getByRole('region', { name: '任務完整頁', exact: true }).count(), 0, '雙擊不先跳詳細頁');
    await editor.getByRole('button', { name: '關閉任務', exact: true }).click();
    await first.focus(); await first.press('F2'); await editor.waitFor(); await editor.getByRole('button', { name: '關閉任務', exact: true }).click();
    await first.locator('.card-title').click(); await page.getByRole('region', { name: '任務完整頁', exact: true }).waitFor();
    await page.getByRole('button', { name: '返回看板', exact: true }).click();
    await page.getByLabel('看板卡片密度').selectOption('comfortable');
    for (const density of ['comfortable', 'compact']) { await page.getByLabel('看板卡片密度').selectOption(density); await first.screenshot({ path: `${artifacts}/card-${density}.png` }); }
    await page.setViewportSize({ width: 390, height: 844 });
    await first.getByRole('button', { name: `編輯 ${tasks[0].identifier}`, exact: true }).click(); await editor.waitFor(); await editor.getByRole('button', { name: '關閉任務', exact: true }).click();
    await changeBoardCardStateInDetail(page, tasks[0].identifier, 'inprogress');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.getByLabel('看板泳道').selectOption('epic');
    await moveBoardCard(page, epic.identifier, 'inprogress');
    await board.locator(`[data-column="inprogress"] [data-issue="${epic.identifier}"]`).waitFor();
    const saved = (await call(page, 'getWorkspace')).issues.find(issue => issue.id === epic.id); assert.equal(saved.epicId, ''); assert.equal(saved.state, 'inprogress');
    const custom = await call(page, 'createBoard', { projectId: project.id, name: '單卡收錄', selectedIssueIds: [tasks[0].id], requestId: crypto.randomUUID() });
    await page.goto(`${boardUrl}/${custom.id}`); await page.locator('.issue-card').waitFor();
    assert.equal(await page.locator('.issue-card').count(), 1); assert.equal(await page.locator(`[data-issue="${epic.identifier}"]`).count(), 0, '單卡收錄不引入未收錄Epic');
    const dragBoard = await call(page, 'createBoard', { projectId: project.id, name: '短泳道拖曳', selectedIssueIds: [otherEpic.id, tasks[0].id], requestId: crypto.randomUUID() });
    await page.goto(`${boardUrl}/${dragBoard.id}`); await page.getByLabel('看板泳道').selectOption('epic');
    const destination = page.getByRole('region', { name: '泳道 會員功能第一版', exact: true }).locator('[data-column="inprogress"]');
    await page.locator(`[data-issue="${otherEpic.identifier}"]`).dragTo(destination, { sourcePosition: { x: 10, y: 10 }, targetPosition: { x: 5, y: 5 } });
    await page.locator(`[data-column="inprogress"] [data-issue="${otherEpic.identifier}"]`).waitFor();
    const moved = (await call(page, 'getWorkspace')).issues.find(issue => issue.id === otherEpic.id); assert.equal(moved.epicId, ''); assert.equal(moved.state, 'inprogress');
    await page.goto(boardUrl); await page.getByLabel('看板泳道').waitFor(); assert.equal(await page.getByLabel('看板泳道').inputValue(), 'epic', '看板偏好獨立保存');
    await page.addInitScript(() => { Storage.prototype.getItem = () => { throw new Error('storage disabled'); }; Storage.prototype.setItem = () => { throw new Error('storage disabled'); }; });
    await page.reload(); assert.equal(await page.getByLabel('看板泳道').inputValue(), 'none'); await page.getByLabel('看板泳道').selectOption('epic');
    assert.deepEqual(errors, []);
  }
  writeFileSync(`${artifacts}/measurements.json`, JSON.stringify({ measurements, errors }, null, 2));
  console.log(`PASS: ${before ? '看板before畫面' : '單一垂直捲動／底卡、Epic收錄／狀態、偏好儲存／拒絕回退、密度、雙擊／鍵盤／手機編輯'}，三尺寸`);
} finally { await browser?.close(); if (built) await new Promise(resolve => server?.httpServer.close(resolve)); else await server?.close(); }
