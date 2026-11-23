import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { requireIsolatedApi } from './fixtures/isolated-api.js';
import { fixtureResponse } from './fixtures/verification.js';

requireIsolatedApi({ requireIsolatedFlag: true });
const before = process.env.E2E_BEFORE === '1';
const root = new URL('../../', import.meta.url);
const folder = new URL(`frontend/artifacts/tt419/${before ? 'before' : 'after'}/`, root);
const fixturePath = new URL('.local/tt419-fixture.json', root);
mkdirSync(folder, { recursive: true });
const port = Number(process.env.E2E_FRONTEND_PORT || 5199), base = `http://127.0.0.1:${port}`;
let server, browser;
try {
  server = await createServer({ server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  let fixture = existsSync(fixturePath) ? JSON.parse(readFileSync(fixturePath)) : null;
  if (fixture) assert.equal(fixture.api, process.env.E2E_API_URL);
  const context = await browser.newContext({ locale: 'zh-TW', viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce', ...(fixture ? { storageState: fixture.state } : {}) });
  const page = await context.newPage(), errors = [], reads = [];
  page.setDefaultTimeout(15000);
  page.on('pageerror', error => errors.push(error.message));
  page.on('response', async response => {
    const url = new URL(response.url());
    if (response.ok() && /\/api\/v1\/projects\/[^/]+\/(?:boards\/[^/]+\/)?issues$/.test(url.pathname)) {
      const result = await response.json();
      reads.push({ path: url.pathname, query: [...url.searchParams], total: result.data.total, count: result.data.items.length, ids: result.data.items.map(issue => issue.id) });
    }
  });
  async function call(path, input, method = 'POST') {
    const csrf = await (await context.request.get(base + '/api/csrf-cookie')).json();
    const response = await context.request.fetch(base + path, { method, headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), 200, await response.text());
    return (await fixtureResponse(context.request, response, input)).data;
  }
  // Full backend tests recreate the isolated DB, invalidating saved browser sessions.
  if (fixture && !(await context.request.get(base + `/api/v1/projects/${fixture.project.id}`)).ok()) fixture = null;
  if (!fixture) {
    await call('/api/auth/register', { name: '標籤查詢', email: `tag-filter-${crypto.randomUUID()}@example.test`, password: 'isolated-tag-filter-password', workspaceName: '標籤查詢' });
    const project = await call('/api/rpc/createProject', { name: '標籤查詢', code: 'TAGF' });
    const tags = [];
    for (const name of ['Season 1', 'Security', 'Empty']) tags.push(await call(`/api/v1/projects/${project.id}/tags`, { name }));
    const issues = [];
    for (let index = 0; index < 26; index++) {
      const issue = await call('/api/rpc/createIssue', { projectId: project.id, title: index < 2 ? `修補 ${index === 0 ? '安全回歸' : '交付'}` : `排程工作 ${String(index).padStart(2, '0')}`, state: index === 1 ? 'closed' : 'open' });
      issues.push(issue);
      if (index < 25) await call(`/api/v1/projects/${project.id}/issues/${issue.id}/tags`, { expectedRevision: 1, addTagIds: tags.slice(0, index < 2 ? 2 : 1).map(tag => tag.id) }, 'PATCH');
    }
    const board = await call('/api/rpc/createBoard', { projectId: project.id, name: '指定收錄', selectedIssueIds: [issues[0].id, issues[25].id] });
    const other = await call('/api/rpc/createProject', { name: '其他專案', code: 'TAGO' });
    await call('/api/rpc/createIssue', { projectId: other.id, title: '其他專案任務' });
    const otherTag = await call(`/api/v1/projects/${other.id}/tags`, { name: 'Other only' });
    fixture = { api: process.env.E2E_API_URL, project, tags, issues, board, other, otherTag, state: await context.storageState() };
    writeFileSync(fixturePath, JSON.stringify(fixture), { mode: 0o600 });
  }
  const url = `${base}/app/#/projects/${fixture.project.id}`;
  const picker = page.getByLabel('篩選標籤', { exact: true });
  async function expand() {
    const toggle = page.getByRole('button', { name: '任務篩選', exact: true });
    if (await toggle.getAttribute('aria-expanded') !== 'true') await toggle.click();
  }
  async function choose(tag) {
    await expand(); await picker.focus();
    await picker.locator(`option[value="${tag.id}"]`).waitFor({ state: 'attached' });
    await picker.selectOption(tag.id);
  }
  async function count(expected, board = false) {
    if (!board) await page.getByRole('status').filter({ hasText: new RegExp(`^${expected} 筆結果`) }).waitFor();
    await page.waitForFunction(({ expected, board }) => document.querySelectorAll(board ? '.issue-card' : '.issue-table tbody tr').length === expected, { expected: board ? expected : Math.min(expected, 20), board });
    await page.waitForLoadState('networkidle');
    assert.equal(await page.locator(board ? '.issue-card' : '.issue-table tbody tr').count(), board ? expected : Math.min(expected, 20));
  }
  async function clear() { await page.getByRole('button', { name: '清除全部篩選', exact: true }).click(); }
  async function capture(name) {
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 1000 });
      if (width === 390) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
      await page.evaluate(async () => { scrollTo(0, 0); document.activeElement?.blur(); await document.fonts.ready; await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); });
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
      await page.locator('main.main').screenshot({ path: new URL(`${name}-${width}.png`, folder).pathname, animations: 'disabled', caret: 'hide' });
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
  }
  await page.goto(url); await count(26);
  await choose(fixture.tags[0]); await count(25);
  await page.getByRole('button', { name: '下一頁', exact: true }).click();
  await page.getByText('2 / 2', { exact: true }).waitFor();
  assert.equal(await page.locator('.issue-table tbody tr').count(), 5);
  assert.equal(await page.getByRole('button', { name: '移除標籤 Season 1', exact: true }).count(), 1);
  await choose(fixture.tags[1]); await count(2);
  if (!before) await page.getByText('同時符合所有選取標籤', { exact: true }).waitFor();
  await capture('list');
  await page.getByLabel('搜尋任務', { exact: true }).fill('修補'); await count(2);
  await page.getByLabel('篩選狀態', { exact: true }).click();
  await page.getByRole('checkbox', { name: '篩選狀態 已關閉', exact: true }).check(); await count(1);
  await clear(); await count(26);
  await choose(fixture.tags[2]); await count(0);
  await clear(); await count(26);
  // 篩選只存在當前瀏覽流程, 切專案與重新載入不沿用另一範圍的 Tag ID
  await choose(fixture.tags[0]); await count(25);
  await page.goto(`${base}/app/#/projects/${fixture.other.id}`); await count(1); await expand(); await picker.focus();
  await picker.locator(`option[value="${fixture.otherTag.id}"]`).waitFor({ state: 'attached' });
  assert.equal(await picker.locator(`option[value="${fixture.tags[0].id}"]`).count(), 0);
  await page.goto(url + '/board'); await count(26, true);
  const filtered = !before ? page.waitForResponse(response => response.ok() && response.url().includes('/boards/default/issues?') && new URL(response.url()).searchParams.getAll('tagIds[]').length === 2) : null;
  await choose(fixture.tags[0]); await choose(fixture.tags[1]);
  if (filtered) {
    const result = await (await filtered).json();
    assert.equal(result.data.total, 2, '伺服器只回多 Tag 交集');
    assert.equal(result.data.items.length, 2);
  }
  await count(2, true);
  await capture('board');
  await page.getByLabel('搜尋任務', { exact: true }).fill('不存在的文字'); await count(0, true);
  await clear(); await count(26, true);
  await page.goto(url + `/board/${fixture.board.id}`); await count(2, true);
  await choose(fixture.tags[0]); await count(1, true);
  await choose(fixture.tags[2]); await count(0, true);
  await clear(); await count(2, true);
  await page.setViewportSize({ width: 390, height: 844 });
  await choose(fixture.tags[0]); await count(1, true);
  await clear(); await count(2, true);
  assert.deepEqual(errors, []);
  assert.ok(reads.every(read => read.count <= (read.path.includes('/boards/') ? 100 : 20)), '回應筆數有界');
  if (!before) {
    const matching = reads.filter(read => read.path.includes('/boards/') && read.query.filter(([key]) => key === 'tagIds[]').length === 2 && read.total === 2);
    assert.ok(matching.length, '看板實際 Network 已送雙 Tag 並回交集');
    assert.ok(matching.every(read => read.ids.every(id => fixture.issues.slice(0, 2).some(issue => issue.id === id))));
  }
  writeFileSync(new URL('result.json', folder), JSON.stringify({ before, reads, errors, passed: true }, null, 2));
  console.log('PASS: Tag AND, keyword/state, pagination, project/board scope, clear/empty, mobile and bounded Network');
} finally { await browser?.close(); await server?.close(); }
