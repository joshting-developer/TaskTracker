import assert from 'node:assert/strict';
import { createServer, preview as previewServer } from 'vite';
import { chromium, request } from 'playwright';
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { requireIsolatedApi } from './fixtures/isolated-api.js';
import { fixtureResponse } from './fixtures/verification.js';

requireIsolatedApi({ requireIsolatedFlag: true });
const before = process.env.E2E_BEFORE === '1', root = new URL('../../', import.meta.url);
const folder = new URL(`frontend/artifacts/tt420/${before ? 'before' : 'after'}/`, root);
mkdirSync(folder, { recursive: true });
const port = Number(process.env.E2E_FRONTEND_PORT || 5199), base = `http://127.0.0.1:${port}`;
let server, browser, bearer;
try {
  server = process.env.E2E_BUILD === '1' ? await previewServer({ preview: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }) : await createServer({ plugins: [{ name: 'board-rules-before', enforce: 'pre', load(id) {
    if (before && id.endsWith('/src/components/BoardDialog.vue')) return execFileSync('git', ['show', 'a759979:frontend/src/components/BoardDialog.vue'], { cwd: root, encoding: 'utf8' });
  } }], server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  if (process.env.E2E_BUILD !== '1') await server.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  let fixture = null;
  if (fixture) assert.equal(fixture.api, process.env.E2E_API_URL);
  const context = await browser.newContext({ locale: 'zh-TW', viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce', ...(fixture ? { storageState: fixture.state } : {}) });
  const page = await context.newPage(), errors = [], saves = [];
  page.setDefaultTimeout(15000);
  page.on('pageerror', error => errors.push(error.message));
  page.on('request', req => { if (req.url().endsWith('/api/rpc/updateBoard')) saves.push(req.postDataJSON()); });
  async function call(path, input, method = 'POST') {
    const csrf = await (await context.request.get(base + '/api/csrf-cookie')).json();
    const response = await context.request.fetch(base + path, { method, headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), 200, await response.text());
    return (await fixtureResponse(context.request, response, input)).data;
  }
  async function get(path) {
    const response = await context.request.get(base + path); assert.equal(response.status(), 200, await response.text()); return (await response.json()).data;
  }
  if (!fixture) {
    await call('/api/auth/register', { name: '收錄驗收', email: `rules-${crypto.randomUUID()}@example.test`, password: 'isolated-board-rule-browser', workspaceName: '收錄驗收' });
    const project = await call('/api/rpc/createProject', { name: '條件看板', code: 'RULEUI' });
    const epic = await call('/api/rpc/createIssue', { projectId: project.id, title: '手動交付 Epic', type: 'epic' });
    const child = await call('/api/rpc/createIssue', { projectId: project.id, title: '已完成的 Epic 子卡片', epicId: epic.id, state: 'closed' });
    const task = await call('/api/rpc/createIssue', { projectId: project.id, title: '安全修補', type: 'task' });
    const bug = await call('/api/rpc/createIssue', { projectId: project.id, title: '下一輪修正', type: 'bug' });
    const tags = [];
    for (const name of ['Season 1', 'Security']) tags.push(await call(`/api/v1/projects/${project.id}/tags`, { name }));
    await call(`/api/v1/projects/${project.id}/issues/${task.id}/tags`, { expectedRevision: 1, addTagIds: tags.map(tag => tag.id) }, 'PATCH');
    await call(`/api/v1/projects/${project.id}/issues/${bug.id}/tags`, { expectedRevision: 1, addTagIds: [tags[0].id] }, 'PATCH');
    const board = await call('/api/rpc/createBoard', { projectId: project.id, name: '手動交付', selectedIssueIds: [epic.id] });
    fixture = { api: process.env.E2E_API_URL, project, epic, child, task, bug, tags, board, state: await context.storageState() };
  }
  const apiRoot = `/api/v1/projects/${fixture.project.id}`, boardPath = `${apiRoot}/boards/${fixture.board.id}`;
  const current = await get(boardPath);
  await call('/api/rpc/updateBoard', { id: fixture.board.id, name: '手動交付', description: '', selectedIssueIds: [fixture.epic.id], autoInclude: null, excludedIssueIds: [], expectedRevision: current.revision });
  const taskNow = await get(`${apiRoot}/issues/${fixture.task.id}`);
  await call(`${apiRoot}/issues/${fixture.task.id}/tags`, { expectedRevision: taskNow.revision, addTagIds: fixture.tags.map(tag => tag.id) }, 'PATCH');
  const boardUrl = `${base}/app/#/projects/${fixture.project.id}/board/${fixture.board.id}`;
  await page.goto(boardUrl); await page.locator('.issue-card').first().waitFor();
  await page.getByRole('button', { name: '編輯看板', exact: true }).click();
  const editor = page.getByRole('dialog', { name: '編輯看板', exact: true }); await editor.waitFor();
  const preview = editor.getByRole('region', { name: '收錄預覽', exact: true });
  async function previewCount(total) { await preview.getByRole('status').filter({ hasText: `符合 ${total} 張卡片 ·` }).waitFor(); }
  async function capture(name, bottom = false) {
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 1000 });
      await editor.evaluate((el, bottom) => el.scrollTop = bottom ? el.scrollHeight : 0, bottom);
      await page.evaluate(async () => { document.activeElement?.blur(); await document.fonts.ready; await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); });
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
      assert.equal(await editor.evaluate(el => el.scrollWidth <= el.clientWidth), true);
      await editor.screenshot({ path: new URL(`${name}-${width}.png`, folder).pathname, animations: 'disabled', caret: 'hide' });
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
    if (name === 'automatic' || (before && name === 'manual')) {
      let html = await page.evaluate(() => {
        const body = document.body.cloneNode(true);
        const controls = document.body.querySelectorAll('input, textarea, select');
        body.querySelectorAll('input, textarea, select').forEach((copy, index) => {
          const original = controls[index];
          if (original.tagName === 'SELECT') [...copy.options].forEach((option, i) => option.toggleAttribute('selected', original.options[i].selected));
          else if (original.tagName === 'TEXTAREA') copy.textContent = original.value;
          else { copy.setAttribute('value', original.value); copy.toggleAttribute('checked', original.checked); }
        });
        body.querySelectorAll('script').forEach(script => script.remove());
        const css = [...document.styleSheets].map(sheet => [...sheet.cssRules].map(rule => rule.cssText).join('\n')).join('\n');
        return `<!doctype html><html lang="zh-TW"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><body>${body.innerHTML}<script>document.querySelectorAll('script').forEach(s => { if (s !== document.currentScript) s.remove(); }); const d=document.querySelector('dialog.board-dialog');d.removeAttribute('open');d.showModal();<\/script></body></html>`;
      });
      html = html.replace(/src="http:\/\/127\.0\.0\.1:\d+(\/assets\/[^" ]+\.svg)"/g, (_match, path) => `src="data:image/svg+xml;base64,${readFileSync(new URL(`frontend/dist${path}`, root)).toString('base64')}"`);
      writeFileSync(new URL('rendered-editor.html', folder), html);
    }
  }
  await capture('manual');
  if (!before) {
    await previewCount(2);
    assert.equal(await editor.getByLabel('收錄方式', { exact: true }).inputValue(), 'false');
    await editor.getByLabel('看板名稱', { exact: true }).fill('Season 1');
    await editor.getByLabel('收錄方式', { exact: true }).selectOption({ label: '自動收錄條件' });
    const picker = editor.getByLabel('符合標籤', { exact: true });
    for (const tag of fixture.tags) {
      await picker.focus(); await picker.locator(`option[value="${tag.id}"]`).waitFor({ state: 'attached' }); await picker.selectOption(tag.id);
    }
    await editor.getByLabel('自動收錄類型 Task', { exact: true }).check();
    await editor.getByLabel('自動收錄狀態 待規劃', { exact: true }).check();
    await editor.getByLabel(`排除 ${fixture.child.identifier}`, { exact: true }).check();
    await previewCount(2);
    await capture('automatic'); await capture('preview', true);
    page.once('dialog', dialog => dialog.dismiss());
    await editor.getByRole('button', { name: '關閉看板設定', exact: true }).click(); await editor.waitFor();
    assert.equal(await editor.getByLabel('看板名稱', { exact: true }).inputValue(), 'Season 1', '取消離開仍保留草稿');
    let failedOnce = false;
    await page.route('**/api/rpc/updateBoard', async route => {
      if (!failedOnce) { failedOnce = true; await route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { code: 'SERVICE_UNAVAILABLE', message: '暫時無法儲存' } }) }); }
      else await route.continue();
    });
    const save = editor.getByRole('button', { name: '儲存看板', exact: true });
    await save.click(); await editor.getByRole('alert').filter({ hasText: '暫時無法儲存' }).waitFor();
    assert.equal(await editor.getByLabel('看板名稱', { exact: true }).inputValue(), 'Season 1');
    assert.equal(await editor.getByLabel(`排除 ${fixture.child.identifier}`, { exact: true }).isChecked(), true);
    await save.click({ clickCount: 2 }); await editor.waitFor({ state: 'detached' });
    assert.equal(saves.length, 2, '失敗一次與重試一次, 雙擊不多送');
    assert.equal(saves[0].requestId, saves[1].requestId, '相同草稿失敗重送沿用 requestId');
    await page.unroute('**/api/rpc/updateBoard');
    let saved = await get(boardPath);
    assert.equal(saved.issueCount, 2); assert.equal(saved.closedCount, 0);
    assert.deepEqual(new Set(saved.autoInclude.tagIds), new Set(fixture.tags.map(tag => tag.id)));
    await page.getByRole('button', { name: '編輯看板', exact: true }).click(); await previewCount(2);
    assert.equal(await editor.getByText('已移除標籤', { exact: true }).count(), 0, '已選規則 Tag 開啟時即有正確名稱');
    await editor.getByLabel('看板名稱', { exact: true }).fill('保留的規則草稿');
    await call('/api/rpc/updateBoard', { id: fixture.board.id, expectedRevision: saved.revision, name: saved.name, description: '另一個人的修改', selectedIssueIds: saved.selectedIssueIds });
    await editor.getByRole('button', { name: '儲存看板', exact: true }).click();
    await editor.getByRole('alert').filter({ hasText: '看板已被更新' }).waitFor();
    assert.equal(await editor.getByLabel('看板名稱', { exact: true }).inputValue(), '保留的規則草稿');
    await capture('conflict');
    page.once('dialog', dialog => dialog.accept()); await editor.getByRole('button', { name: '關閉看板設定', exact: true }).click();
    // API 新卡貼標後, 正常重讀自動加入；不需要手動選取新卡。
    const fresh = await call('/api/rpc/createIssue', { projectId: fixture.project.id, title: '自動新增的卡片' });
    await call(`${apiRoot}/issues/${fresh.id}/tags`, { expectedRevision: 1, addTagIds: fixture.tags.map(tag => tag.id) }, 'PATCH');
    await page.reload(); await page.locator(`[data-issue="${fresh.identifier}"]`).waitFor();
    saved = await get(boardPath); assert.equal(saved.issueCount, 3);
    const original = await get(`${apiRoot}/issues/${fixture.task.id}`);
    await call(`${apiRoot}/issues/${fixture.task.id}/tags`, { expectedRevision: original.revision, removeTagIds: fixture.tags.map(tag => tag.id) }, 'PATCH');
    await page.reload(); await page.locator(`[data-issue="${fresh.identifier}"]`).waitFor();
    assert.equal(await page.locator(`[data-issue="${fixture.task.identifier}"]`).count(), 0);
    assert.equal((await get(boardPath)).issueCount, 2);
    // 純 Bearer context 沒有 Session cookie 或 CSRF, 實際網路預覽同一組已儲存條件。
    const token = await call('/api/v1/personal-tokens', { name: 'Board preview fixture', abilities: ['read'] });
    bearer = await request.newContext({ extraHTTPHeaders: { Authorization: `Bearer ${token.plainTextToken}` } });
    const response = await bearer.post(`${base}/api/public/v1/projects/${fixture.project.id}/boards/preview`, { data: { autoInclude: saved.autoInclude, selectedIssueIds: saved.selectedIssueIds, excludedIssueIds: saved.excludedIssueIds } });
    assert.equal(response.status(), 200); assert.equal((await response.json()).data.total, 2);
    assert.equal(response.headers()['set-cookie'], undefined);
    await call(`/api/v1/personal-tokens/${token.token.id}/revoke`, {});
    assert.equal((await bearer.post(`${base}/api/public/v1/projects/${fixture.project.id}/boards/preview`, { data: {} })).status(), 401);
  }
  assert.deepEqual(errors, []);
  writeFileSync(new URL('result.json', folder), JSON.stringify({ before, errors, passed: true, saves: saves.length, screenshots: before ? 3 : 12 }, null, 2));
  console.log(`PASS: ${before ? 'three-width original editor' : 'automatic rules/manual Epic/exclusions, preview, retry/409/drafts, dynamic tag membership, pure Bearer/revocation, 12 three-width images'}`);
} finally { await bearer?.dispose(); await browser?.close(); if (server?.close) await server.close(); else if (server) await new Promise(resolve => server.httpServer.close(resolve)); }
