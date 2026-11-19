// TT-428：看板拖曳自訂順序。接隔離後端的真實 Session 與 MySQL, 驗證重新整理、編輯、跨欄、不同看板、鍵盤與新卡片。
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
import { requireIsolatedApi } from './fixtures/isolated-api.js';
import { fixtureResponse } from './fixtures/verification.js';

requireIsolatedApi({ requireIsolatedFlag: true });
const port = Number(process.env.E2E_FRONTEND_PORT || 5199), base = `http://127.0.0.1:${port}`;
const folder = 'artifacts/tt428';
mkdirSync(folder, { recursive: true });
let server, browser;
try {
  server = await preview({ preview: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const context = await browser.newContext({ locale: 'zh-TW', viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
  const page = await context.newPage(), errors = [], reorders = [];
  page.setDefaultTimeout(15000);
  page.on('pageerror', error => errors.push(error.message));
  page.on('request', req => { if (req.url().endsWith('/api/rpc/reorderBoardCards')) reorders.push(req.postDataJSON()); });
  async function call(path, input = {}, method = 'POST') {
    const csrf = await (await context.request.get(base + '/api/csrf-cookie')).json();
    const response = await context.request.fetch(base + path, { method, headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), 200, await response.text());
    return (await fixtureResponse(context.request, response, input)).data;
  }
  await call('/api/auth/register', { name: '看板排序驗收', email: `order-${crypto.randomUUID()}@example.test`, password: 'isolated-board-order', workspaceName: '看板排序驗收' });
  const project = await call('/api/rpc/createProject', { name: '排序驗收', code: 'ORD' });
  const epic = await call('/api/rpc/createIssue', { projectId: project.id, title: '上線 Epic', type: 'epic' });
  const cards = [];
  for (const title of ['整理需求', '設計畫面', '實作 API', '補測試']) cards.push(await call('/api/rpc/createIssue', { projectId: project.id, title }));
  const [a, b, c, d] = cards;
  const ids = column => page.locator(`.board-lane:has(.lane-header:has-text("其他卡片")) [data-column="${column}"] .issue-card`).evaluateAll(list => list.map(card => card.dataset.issue));
  async function open(board = '', visible = a) {
    await page.goto(`${base}/app/#/projects/${project.id}/board${board ? `/${board}` : ''}`);
    await page.locator(`[data-issue="${visible.identifier}"]`).waitFor();
  }
  // 拖到目標卡片的上半部, 代表插在那張卡前面；沒有目標卡時放在欄位底部
  async function drag(identifier, column, before) {
    const card = page.locator(`.issue-card[data-issue="${identifier}"]`);
    const lane = page.locator('.board-lane').filter({ has: page.locator('.lane-header', { hasText: '其他卡片' }) });
    const target = before ? lane.locator(`.issue-card[data-issue="${before}"]`) : lane.locator(`[data-column="${column}"]`);
    const crossing = (await card.evaluate(el => el.closest('[data-column]').dataset.column)) !== column;
    const box = await target.boundingBox();
    const saved = page.waitForResponse(res => res.url().endsWith('/api/rpc/reorderBoardCards'));
    const moved = crossing ? page.waitForResponse(res => res.url().endsWith('/api/rpc/updateIssue')) : null;
    await card.dragTo(target, { sourcePosition: { x: 20, y: 12 }, targetPosition: before ? { x: 20, y: 6 } : { x: 20, y: box.height - 6 } });
    assert.equal((await saved).status(), 200, `${identifier} 排序要保存`);
    if (moved) assert.equal((await moved).status(), 200, `${identifier} 跨欄要改狀態`);
    await page.locator('.issue-card.moving').waitFor({ state: 'detached' });
    await page.waitForLoadState('networkidle');
  }


  await open();
  assert.equal(await page.getByLabel('排序方式').inputValue(), 'manual', '看板預設自訂順序');
  assert.deepEqual(await ids('open'), [a, b, c, d].map(x => x.identifier), '還沒排過時照建立先後');

  // 1. 同欄最下方拖到最上方, 重新整理後不變
  await drag(d.identifier, 'open', a.identifier);
  assert.deepEqual(await ids('open'), [d, a, b, c].map(x => x.identifier));
  await page.reload(); await page.locator(`[data-issue="${a.identifier}"]`).waitFor();
  assert.deepEqual(await ids('open'), [d, a, b, c].map(x => x.identifier), '重新整理後順序不變');

  // 2. 編輯卡片、改優先級後順序不變
  const fresh = (await (await context.request.get(`${base}/api/v1/projects/${project.id}/issues/${b.id}`)).json()).data;
  await call('/api/rpc/updateIssue', { id: b.id, title: '設計畫面（改）', priority: 'urgent', expectedRevision: fresh.revision });
  await page.reload(); await page.locator(`[data-issue="${a.identifier}"]`).waitFor();
  assert.deepEqual(await ids('open'), [d, a, b, c].map(x => x.identifier), '更新卡片不洗掉順序');

  // 3. 跨欄放到指定位置：先把 c 放進進行中, 再把 a 插到 c 前面
  await drag(c.identifier, 'inprogress');
  await drag(a.identifier, 'inprogress', c.identifier);
  assert.deepEqual(await ids('inprogress'), [a, c].map(x => x.identifier));
  await page.reload(); await page.locator(`[data-issue="${a.identifier}"]`).waitFor();
  assert.deepEqual(await ids('inprogress'), [a, c].map(x => x.identifier), '跨欄位置重新整理後正確');
  assert.deepEqual(await ids('open'), [d, b].map(x => x.identifier));

  // 4. 鍵盤：Alt＋↓ 把 d 往下移一張, 螢幕閱讀器有提示
  await page.locator(`.issue-card[data-issue="${d.identifier}"] .card-title`).focus();
  await page.keyboard.press('Alt+ArrowDown');
  await page.locator('[role="status"]').filter({ hasText: `${d.identifier} 移到第 2 張` }).waitFor();
  await page.locator('.issue-card.moving').waitFor({ state: 'detached' });
  await page.waitForLoadState('networkidle');
  assert.deepEqual(await ids('open'), [b, d].map(x => x.identifier));
  assert.equal(await page.evaluate(() => document.activeElement?.closest('.issue-card')?.dataset.issue), d.identifier, '移動後焦點留在同一張卡');

  // 5. 新卡片出現在欄位最下方, 既有順序不重設
  const e = await call('/api/rpc/createIssue', { projectId: project.id, title: '新加入的卡' });
  await page.reload(); await page.locator(`[data-issue="${e.identifier}"]`).waitFor();
  assert.deepEqual(await ids('open'), [b, d, e].map(x => x.identifier), '新卡放在最後, 原順序保留');

  // 6. 自訂看板各自排序, Default 不受影響；Epic 區塊照常顯示
  const board = await call('/api/rpc/createBoard', { projectId: project.id, name: '本次交付', selectedIssueIds: [b.id, d.id, e.id, epic.id] });
  await open(board.id, b);
  await page.locator(`[data-issue="${e.identifier}"]`).waitFor();
  assert.deepEqual(await ids('open'), [b, d, e].map(x => x.identifier), '新看板還沒排過時照建立先後');
  await drag(e.identifier, 'open', b.identifier);
  assert.deepEqual(await ids('open'), [e, b, d].map(x => x.identifier));
  await open();
  assert.deepEqual(await ids('open'), [b, d, e].map(x => x.identifier), 'Default 看板順序不受自訂看板影響');
  assert.equal(await page.locator(`.board-lane:has(.lane-header:has-text("Epic")) [data-issue="${epic.identifier}"]`).count(), 1);

  // 7. 切到其他排序時不送排序請求, 列表沒有自訂順序選項
  await page.getByLabel('排序方式').selectOption('priority');
  const before = reorders.length;
  await page.locator(`.issue-card[data-issue="${d.identifier}"] .card-title`).focus();
  await page.keyboard.press('Alt+ArrowUp');
  await page.waitForTimeout(300);
  assert.equal(reorders.length, before, '非自訂順序時鍵盤不改順序');
  await page.goto(`${base}/app/#/projects/${project.id}`);
  await page.getByLabel('排序方式').waitFor();
  assert.equal(await page.getByLabel('排序方式').locator('option[value="manual"]').count(), 0, '任務列表不提供自訂順序');
  assert.equal(await page.getByLabel('排序方式').inputValue(), 'updated', '列表維持原本預設排序');

  // 8. 觸控：手機寬度用卡片上的下移按鈕, 不靠拖曳
  const phone = await browser.newContext({ locale: 'zh-TW', viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, reducedMotion: 'reduce', storageState: await context.storageState() });
  const touch = await phone.newPage();
  touch.on('pageerror', error => errors.push(error.message));
  await touch.goto(`${base}/app/#/projects/${project.id}/board`);
  const down = touch.getByRole('button', { name: `${b.identifier} 下移`, exact: true });
  await down.scrollIntoViewIfNeeded();
  const box = await down.boundingBox();
  assert.ok(box.width >= 44 && box.height >= 44, '觸控按鈕至少 44px');
  const saved = touch.waitForResponse(res => res.url().endsWith('/api/rpc/reorderBoardCards'));
  await down.tap();
  assert.equal((await saved).status(), 200);
  await touch.reload(); await touch.locator(`[data-issue="${b.identifier}"]`).waitFor();
  assert.deepEqual(await touch.locator('.board-lane:has(.lane-header:has-text("其他卡片")) [data-column="open"] .issue-card').evaluateAll(list => list.map(card => card.dataset.issue)), [d, b, e].map(x => x.identifier), '觸控下移後保存');
  assert.equal(await touch.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
  await touch.locator(`[data-issue="${b.identifier}"]`).screenshot({ path: `${folder}/touch-card-390.png` });
  await phone.close();
  // 一般滑鼠環境不顯示上移／下移按鈕
  assert.equal(await page.getByRole('button', { name: `${b.identifier} 下移`, exact: true }).isVisible().catch(() => false), false);

  for (const payload of reorders) assert.ok(payload.requestId && payload.boardId && payload.issueIds.length <= 500);
  assert.deepEqual(errors, []);
  await page.goto(`${base}/app/#/projects/${project.id}/board`); await page.locator(`[data-issue="${a.identifier}"]`).waitFor();
  await page.screenshot({ path: `${folder}/board-1440.png` });
  console.log(`PASS: reload, edit, cross-column position, keyboard, new card, per-board order, list unaffected (${reorders.length} reorder writes)`);
} finally { await browser?.close(); await server?.close(); }
