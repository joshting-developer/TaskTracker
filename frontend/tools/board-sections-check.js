import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createServer, preview } from 'vite';
import { chromium } from 'playwright';
import { requireIsolatedApi } from './fixtures/isolated-api.js';
import { fixtureResponse } from './fixtures/verification.js';
import { moveBoardCard } from './board-actions.js';

requireIsolatedApi({ requireIsolatedFlag: true });
const before = process.env.E2E_BEFORE === '1', built = process.env.E2E_BUILD === '1';
const port = Number(process.env.E2E_FRONTEND_PORT || 5199), base = `http://127.0.0.1:${port}`;
const folder = `artifacts/tt421/${before ? 'before' : 'after'}`;
mkdirSync(folder, { recursive: true });
let server, browser;
try {
  const config = { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } };
  server = built ? await preview({ preview: config }) : await createServer({ plugins: [{ name: 'original-board', enforce: 'pre', load(id) { if (before && id.endsWith('/src/components/ProjectBoard.vue')) return execFileSync('git', ['show', 'HEAD:frontend/src/components/ProjectBoard.vue'], { encoding: 'utf8' }); } }], server: config });
  if (!built) await server.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const context = await browser.newContext({ locale: 'zh-TW', viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
  const page = await context.newPage(), errors = [], moves = [], queries = [];
  page.setDefaultTimeout(15000);
  page.on('pageerror', error => errors.push(error.message));
  page.on('request', req => {
    if (req.url().endsWith('/api/rpc/moveIssue')) moves.push(req.postDataJSON());
    if (/\/boards\/[^/]+\/issues\?/.test(req.url())) queries.push(new URL(req.url()).searchParams.get('page'));
  });
  async function call(path, input = {}, method = 'POST') {
    const csrf = await (await context.request.get(base + '/api/csrf-cookie')).json();
    const response = await context.request.fetch(base + path, { method, headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), 200, await response.text());
    return (await fixtureResponse(context.request, response, input)).data;
  }
  async function get(path) { const r = await context.request.get(base + path); assert.equal(r.status(), 200); return (await r.json()).data; }
  const identity = await call('/api/auth/register', { name: '看板分區驗收', email: `sections-${crypto.randomUUID()}@example.test`, password: 'isolated-board-sections', workspaceName: '看板分區驗收' });
  const project = await call('/api/rpc/createProject', { name: '產品交付', code: 'SECT' });
  const epic = await call('/api/rpc/createIssue', { projectId: project.id, title: '會員功能', type: 'epic' });
  const child = await call('/api/rpc/createIssue', { projectId: project.id, title: '登入頁與權限提示', epicId: epic.id, description: '保留所屬 Epic, 拖曳只改狀態' });
  const bug = await call('/api/rpc/createIssue', { projectId: project.id, title: '修正窄螢幕版面', type: 'bug' });
  const tags = [];
  for (const [name, color] of [['Season 1', '#3867a5'], ['ABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJK長標籤', '#5f4ca5'], ['Security', '#b85f65'], ['手機驗收', '#397e71']]) tags.push({ name, color, ...await call(`/api/v1/projects/${project.id}/tags`, { name, color }) });
  const prefix = `/api/v1/projects/${project.id}`;
  await call(`${prefix}/issues/${child.id}/tags`, { expectedRevision: 1, addTagIds: tags.map(tag => tag.id) }, 'PATCH');
  const boardUrl = `${base}/app/#/projects/${project.id}/board`;
  await page.goto(boardUrl); await page.locator(`[data-issue="${child.identifier}"]`).waitFor();
  const board = page.locator('.visual-board');
  async function capture(name) {
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: width === 390 ? 844 : 1000 });
      await board.scrollIntoViewIfNeeded();
      await page.evaluate(async () => { await document.fonts.ready; await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r))); });
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
      const card = board.locator(`[data-issue="${child.identifier}"]`);
      if (await card.count()) {
        assert.equal(await card.locator('.tag-label').count(), 3);
        assert.ok((await card.locator('.tag-label').allTextContents()).includes(tags[1].name), '長標籤實際可見, 不只藏在剩餘數量');
        assert.equal(await card.locator('.tag-overflow').innerText(), '+1');
        assert.deepEqual(new Set([...await card.locator('.tag-label').allTextContents(), await card.locator('.tag-overflow').getAttribute('title')]), new Set(tags.map(tag => tag.name)));
        assert.equal(await card.evaluate(el => el.scrollWidth <= el.clientWidth), true, '長標籤不撐破卡片');
      }
      await page.screenshot({ path: `${folder}/${name}-${width}.png`, fullPage: true, animations: 'disabled', caret: 'hide' });
      if (name === 'comfortable' && width === 1440) {
        let html = await page.evaluate(() => {
          const body = document.body.cloneNode(true), controls = document.querySelectorAll('input, select, textarea');
          body.querySelectorAll('input, select, textarea').forEach((copy, index) => {
            const original = controls[index];
            if (original.tagName === 'SELECT') [...copy.options].forEach((option, i) => option.toggleAttribute('selected', original.options[i].selected));
            else if (original.tagName === 'TEXTAREA') copy.textContent = original.value;
            else { copy.setAttribute('value', original.value); copy.toggleAttribute('checked', original.checked); }
          });
          body.querySelectorAll('script').forEach(script => script.remove());
          const css = [...document.styleSheets].map(sheet => [...sheet.cssRules].map(rule => rule.cssText).join('\n')).join('\n');
          return `<!doctype html><html lang="zh-TW"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><body>${body.innerHTML}</body></html>`;
        });
        if (built) html = html.replace(/src="http:\/\/127\.0\.0\.1:\d+(\/assets\/[^" ]+\.svg)"/g, (_match, path) => `src="data:image/svg+xml;base64,${readFileSync(new URL(`../dist${path}`, import.meta.url)).toString('base64')}"`);
        writeFileSync(`${folder}/rendered-board.html`, html);
      }
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
  }
  await capture('comfortable');
  await page.getByLabel('看板卡片密度').selectOption('compact'); await capture('compact');
  if (!before) {
    const epicLane = page.getByRole('region', { name: '泳道 Epic', exact: true });
    const otherLane = page.getByRole('region', { name: '泳道 其他卡片', exact: true });
    assert.equal(await page.getByLabel('看板泳道').inputValue(), 'type');
    assert.equal(await epicLane.locator('.issue-card').count(), 1);
    assert.equal(await otherLane.locator('.issue-card').count(), 2);
    assert.equal(await epicLane.locator(`[data-issue="${child.identifier}"]`).count(), 0);
    assert.equal(await otherLane.locator(`[data-issue="${epic.identifier}"]`).count(), 0);
    assert.equal(await epicLane.locator('.column-header b').first().innerText(), '1');
    assert.equal(await otherLane.locator('.column-header b').first().innerText(), '2');
    const collapse = epicLane.getByRole('button', { name: '收合或展開 Epic', exact: true });
    await collapse.click(); assert.equal(await epicLane.locator('.issue-card').count(), 0); assert.equal(await otherLane.locator('.issue-card').count(), 2);
    await page.reload(); await collapse.waitFor(); assert.equal(await collapse.getAttribute('aria-expanded'), 'false'); await collapse.click();
    // 跨類型區拒絕落下, 同區拖曳保留Epic關聯。
    const childCard = page.locator(`[data-issue="${child.identifier}"]`);
    await childCard.dragTo(epicLane.locator('[data-column="inprogress"]'), { sourcePosition: { x: 10, y: 10 }, targetPosition: { x: 10, y: 10 } });
    assert.equal((await get(`${prefix}/issues/${child.id}`)).state, 'open');
    await moveBoardCard(page, child.identifier, 'inprogress');
    const moved = await get(`${prefix}/issues/${child.id}`);
    assert.equal(moved.state, 'inprogress'); assert.equal(moved.epicId, epic.id); assert.equal(moved.type, 'task');
    await page.getByLabel('看板卡片密度').selectOption('comfortable');
    await childCard.locator('.card-title').dblclick();
    const detail = page.getByRole('region', { name: '任務完整頁', exact: true }); await detail.waitFor();
    await detail.getByRole('button', { name: '返回看板', exact: true }).click();
    await childCard.locator('.card-edit').click();
    const editor = page.getByRole('dialog', { name: '任務詳細內容', exact: true }); await editor.waitFor();
    await editor.getByRole('button', { name: '關閉任務', exact: true }).click();
    // 新增Epic按鈕預填正確類型。
    await epicLane.getByRole('button', { name: '在Epic新增待規劃任務', exact: true }).first().click();
    const newEditor = page.getByRole('dialog', { name: '新增一個任務', exact: true }); await newEditor.waitFor();
    assert.equal(await newEditor.getByLabel('類型', { exact: true }).inputValue(), 'epic');
    await newEditor.getByRole('button', { name: '關閉任務', exact: true }).click();
    await page.getByRole('button', { name: '任務篩選', exact: true }).click();
    await page.getByLabel('篩選類型', { exact: true }).selectOption('bug');
    await epicLane.locator('.lane-empty').filter({ hasText: '沒有符合條件的卡片' }).waitFor();
    assert.equal(await otherLane.locator('.issue-card').count(), 1); await capture('filtered');
    await page.getByRole('button', { name: '清除全部篩選', exact: true }).click();
    await page.getByRole('button', { name: '任務篩選', exact: true }).click();
    for (const group of ['none', 'epic', 'type']) { await page.getByLabel('看板泳道').selectOption(group); await page.reload(); await page.locator('.issue-card').first().waitFor(); assert.equal(await page.getByLabel('看板泳道').inputValue(), group); }
    const key = `tasktracker-board:${identity.user.id}:${project.id}`;
    await page.evaluate(key => localStorage.setItem(key, JSON.stringify({ group: 'none', density: 'compact' })), key);
    await page.reload(); await page.locator('.issue-card').first().waitFor();
    assert.equal(await page.getByLabel('看板泳道').inputValue(), 'type'); assert.equal(await page.getByLabel('看板卡片密度').inputValue(), 'compact');
    // 智慧看板只收符合條件的子卡, 不憑關聯補進未收錄的Epic。
    const smart = await call('/api/rpc/createBoard', { projectId: project.id, name: 'Season 1', autoInclude: { tagIds: [tags[0].id], types: [], states: [] }, selectedIssueIds: [] });
    await page.goto(`${boardUrl}/${smart.id}`); await childCard.waitFor();
    assert.equal(await epicLane.locator('.issue-card').count(), 0); assert.equal(await otherLane.locator('.issue-card').count(), 1);
    await capture('smart');
    const current = await get(`${prefix}/issues/${child.id}`);
    await call(`${prefix}/issues/${child.id}/tags`, { expectedRevision: current.revision, removeTagIds: [tags[0].id] }, 'PATCH');
    await page.reload(); await epicLane.locator('.lane-empty').filter({ hasText: '沒有符合條件的卡片' }).waitFor();
    await otherLane.locator('.lane-empty').filter({ hasText: '沒有符合條件的卡片' }).waitFor();
    assert.equal(await board.locator('.issue-card').count(), 0);
    await page.evaluate(() => localStorage.setItem('tasktracker:locale', 'en')); await page.reload();
    await page.getByRole('region', { name: 'Swimlane Other cards', exact: true }).waitFor();
    assert.equal(await page.locator('.board-tools select').first().locator('option:checked').innerText(), 'Epic and other cards');
    await page.evaluate(() => localStorage.setItem('tasktracker:locale', 'zh-TW')); await page.reload();
    // 101張以上仍沿用伺服器100筆分頁與分批掛載, 不漏分區或重複。
    for (let n = 0; n < 101; n++) await call('/api/rpc/createIssue', { projectId: project.id, title: `批次驗收 ${n + 1}`, type: n === 100 ? 'epic' : 'task' });
    queries.length = 0; await page.goto(boardUrl);
    await page.waitForFunction(() => document.querySelectorAll('.issue-card').length === 104);
    assert.equal(await epicLane.locator('.issue-card').count(), 2); assert.equal(await otherLane.locator('.issue-card').count(), 102);
    assert.ok(queries.includes('2'), '沿用100筆分頁取得第2頁');
    assert.equal(new Set(await board.locator('.issue-card').evaluateAll(cards => cards.map(card => card.dataset.issue))).size, 104);
  }
  assert.deepEqual(errors, []);
  writeFileSync(`${folder}/result.json`, JSON.stringify({ before, passed: true, errors, queries }, null, 2));
  console.log(`PASS: ${before ? 'original board and tags in both densities, three widths' : 'two sections, counts/collapse/preferences, guarded drag, TT-414 actions, create Epic, filters, smart membership, 104 cards/server pagination, tags in both densities/three widths'}`);
} finally { await browser?.close(); if (built && server) await new Promise(resolve => server.httpServer.close(resolve)); else await server?.close(); }
