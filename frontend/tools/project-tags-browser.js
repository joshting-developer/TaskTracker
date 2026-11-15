import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync, readFileSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fixtureResponse } from './fixtures/verification.js';
if (process.env.E2E_ISOLATED !== '1' || !process.env.E2E_API_URL) throw Error('Explicit isolated API required');
const root = new URL('../../', import.meta.url), port = Number(process.env.E2E_FRONTEND_PORT || 5199), base = `http://127.0.0.1:${port}`;
const phase = process.env.E2E_CAPTURE_PHASE || 'after', folder = new URL(`artifacts/tt193/${phase}/`, root), fixturePath = new URL('.local/tt193-browser-fixture.json', root);
mkdirSync(folder, { recursive: true });
let server, browser;
try {
  server = await createServer({ root: new URL('../', import.meta.url).pathname, plugins: [{ name: 'tt193-before', load(id) {
    if (phase === 'before' && ['/src/App.vue', '/src/components/IssueDetail.vue', '/src/components/IssueList.vue', '/src/components/ProjectBoard.vue', '/src/components/IssuePanel.vue', '/src/components/IssueFilters.vue', '/src/composables/useIssueQuery.js'].some(path => id.endsWith(path)))
      return execFileSync('git', ['show', `323f787:frontend${id.slice(id.indexOf('/src/'))}`], { cwd: root, encoding: 'utf8' });
  } }], server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  let fixture = existsSync(fixturePath) ? JSON.parse(readFileSync(fixturePath)) : null;
  if (fixture) assert.equal(fixture.api, process.env.E2E_API_URL);
  const context = await browser.newContext({ locale: 'zh-TW', viewport: { width: 1440, height: 1000 }, ...(fixture ? { storageState: fixture.state } : {}) });
  const page = await context.newPage(), errors = []; page.on('pageerror', error => errors.push(error.message)); page.setDefaultTimeout(15000);
  async function call(path, input, method = 'POST') {
    const csrf = await (await context.request.get(base + '/api/csrf-cookie')).json();
    const response = await context.request.fetch(base + path, { method, headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), 200, await response.text()); return (await fixtureResponse(context.request, response)).data;
  }
  if (!fixture) {
    await call('/api/auth/register', { name: '標籤驗證', email: `tags-${crypto.randomUUID()}@example.test`, password: 'isolated-tag-browser-password', workspaceName: '標籤驗證' });
    const project = await call('/api/rpc/createProject', { name: '標籤流程', code: 'TAG' });
    const issue = await call('/api/rpc/createIssue', { projectId: project.id, title: '安排下一次交付', description: '以穩定 ID 追蹤任務標籤' });
    const second = await call('/api/rpc/createIssue', { projectId: project.id, title: '尚未分類的任務' });
    const tag = await call(`/api/v1/projects/${project.id}/tags`, { name: 'Season 0', color: '#008866', description: '本期交付與驗收' });
    await call(`/api/v1/projects/${project.id}/issues/${issue.id}/tags`, { expectedRevision: 1, addTagIds: [tag.id] }, 'PATCH');
    fixture = { api: process.env.E2E_API_URL, project: project.id, issue: issue.id, second: second.id, tag: tag.id, state: await context.storageState() };
    writeFileSync(fixturePath, JSON.stringify(fixture), { mode: 0o600 });
  }
  const projectUrl = `${base}/app/#/projects/${fixture.project}`;
  if (phase !== 'before') await page.goto(projectUrl + '/tags');
  const manager = page.getByRole('region', { name: '標籤管理', exact: true });
  if (phase !== 'before') await manager.getByText('Season 0', { exact: true }).waitFor();
  if (phase === 'after') {
    const existing = await context.request.get(`${base}/api/v1/projects/${fixture.project}/tags?perPage=100`);
    for (const tag of (await existing.json()).data.items.filter(tag => ['Future', 'Future work'].includes(tag.name))) await call(`/api/v1/projects/${fixture.project}/tags/${tag.id}`, { expectedRevision: tag.revision }, 'DELETE');
    await manager.getByRole('button', { name: '新增標籤', exact: true }).click();
    let form = page.getByRole('dialog', { name: '新增標籤', exact: true });
    await form.getByLabel('標籤名稱', { exact: true }).fill('Future');
    await form.getByLabel('標籤說明', { exact: true }).fill('只記錄, 暫不自動領取');
    await form.getByRole('button', { name: '儲存標籤', exact: true }).click(); await form.waitFor({ state: 'hidden' });
    await manager.getByRole('button', { name: '編輯標籤 Future', exact: true }).click();
    form = page.getByRole('dialog', { name: '編輯標籤定義', exact: true });
    await form.getByLabel('標籤名稱', { exact: true }).fill('Future work');
    await form.getByRole('button', { name: '儲存標籤', exact: true }).click(); await form.waitFor({ state: 'hidden' });
    await page.goto(`${projectUrl}/issues/${fixture.issue}?detail=1`);
    const detail = page.getByRole('region', { name: '任務完整頁', exact: true });
    await detail.getByRole('button', { name: '編輯標籤', exact: true }).click();
    const editor = page.getByRole('dialog', { name: '編輯標籤', exact: true });
    await editor.getByLabel('標籤', { exact: true }).focus();
    await editor.getByLabel('標籤', { exact: true }).locator('option', { hasText: 'Future work' }).waitFor({ state: 'attached' });
    const option = await editor.getByLabel('標籤', { exact: true }).locator('option', { hasText: 'Future work' }).getAttribute('value');
    await editor.getByLabel('標籤', { exact: true }).selectOption(option);
    await editor.getByRole('button', { name: '套用標籤', exact: true }).click(); await editor.waitFor({ state: 'hidden' });
    await detail.getByText('Future work', { exact: true }).waitFor();
    // No silent loss of a pending selection when navigating away.
    await detail.getByRole('button', { name: '編輯標籤', exact: true }).click();
    await editor.getByRole('button', { name: '移除標籤 Future work', exact: true }).click();
    page.once('dialog', dialog => dialog.dismiss()); await editor.getByRole('button', { name: '取消', exact: true }).click(); await editor.waitFor();
    page.once('dialog', dialog => dialog.accept()); await editor.getByRole('button', { name: '取消', exact: true }).click();
    await page.goto(projectUrl);
    const row = page.locator('tr', { hasText: '安排下一次交付' }); await row.getByText('Future work', { exact: true }).waitFor();
    await page.getByRole('button', { name: /篩選/, exact: false }).first().click();
    const filter = page.getByLabel('篩選標籤', { exact: true }); await filter.focus();
    await filter.locator(`option[value="${fixture.tag}"]`).waitFor({ state: 'attached' }); await filter.selectOption(fixture.tag);
    await page.getByText('1 筆結果', { exact: true }).waitFor(); assert.equal(await page.locator('tr', { hasText: '尚未分類的任務' }).count(), 0);
    await page.goto(projectUrl + '/board'); await page.locator('.issue-card', { hasText: '安排下一次交付' }).getByText('Future work', { exact: true }).waitFor();
    // Retiring a definition removes links while retaining its historical audit snapshot.
    await page.goto(projectUrl + '/tags'); await manager.getByRole('button', { name: '編輯標籤 Future work', exact: true }).click();
    form = page.getByRole('dialog', { name: '編輯標籤定義', exact: true }); page.once('dialog', dialog => dialog.accept()); await form.getByRole('button', { name: '刪除標籤', exact: true }).click(); await form.waitFor({ state: 'hidden' });
    assert.equal(await manager.getByText('Future work', { exact: true }).count(), 0);
    const current = (await (await context.request.get(`${base}/api/v1/projects/${fixture.project}/issues/${fixture.issue}`)).json()).data;
    assert.deepEqual(current.tags.map(tag => tag.id), [fixture.tag]);
  }
  async function capture(name, locator, width) {
    await page.setViewportSize({ width, height: 1000 });
    if (width <= 700) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    await page.waitForLoadState('networkidle'); await page.evaluate(async () => { await document.fonts.ready; await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); document.activeElement?.blur(); await Promise.all(document.getAnimations().filter(animation => Number.isFinite(animation.effect.getComputedTiming().endTime)).map(animation => animation.finished.catch(() => {}))); });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await locator.screenshot({ path: new URL(`${name}-${width}.png`, folder).pathname });
  }
  for (const width of [1440, 768, 390]) {
    if (phase !== 'before') {
    await page.goto(projectUrl + '/tags'); await manager.getByText('Season 0', { exact: true }).waitFor(); await capture('manager', manager, width);
    await manager.getByRole('button', { name: '編輯標籤 Season 0', exact: true }).click();
    const form = page.getByRole('dialog', { name: '編輯標籤定義', exact: true }); await capture('definition', form, width); await form.getByRole('button', { name: '取消', exact: true }).click();
    }
    await page.goto(projectUrl); await page.getByRole('heading', { name: '標籤流程', exact: true }).waitFor(); await page.locator('tr', { hasText: '安排下一次交付' }).waitFor(); await capture('list', page.locator('main.main'), width);
    await page.goto(projectUrl + '/board'); await page.locator('.issue-card', { hasText: '安排下一次交付' }).waitFor(); await capture('board', page.locator('main.main'), width);
    await page.goto(`${projectUrl}/issues/${fixture.issue}?detail=1`); const detail = page.getByRole('region', { name: '任務完整頁', exact: true }); await detail.getByRole('heading', { name: '安排下一次交付', exact: true }).waitFor();
    if (width <= 700) await detail.getByRole('button', { name: '任務資訊', exact: true }).click();
    await capture('detail', detail, width);
  }
  assert.deepEqual(errors, []); writeFileSync(new URL('result.json', folder), JSON.stringify({ phase, screenshots: phase === 'before' ? 9 : 15, errors, definitionCrud: true, deltaApply: true, filter: true, board: true }));
  console.log(`PASS tags ${phase}: definition create/rename/delete, issue delta, preserved draft, SQL filter, list/board/detail responsive screenshots`);
} finally { await browser?.close(); await server?.close(); }
