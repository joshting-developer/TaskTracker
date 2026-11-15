import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { expandIssueProperties } from './issue-form-actions.js';
import { fixtureResponse } from './fixtures/verification.js';
if (process.env.E2E_ISOLATED !== '1' || !process.env.E2E_API_URL) throw Error('Explicit isolated API required');
const phase = process.env.E2E_CAPTURE_PHASE || 'after';
assert.ok(['before', 'after', 'compare'].includes(phase));
const root = new URL('../../', import.meta.url), folder = new URL(`artifacts/tt368/${phase}/`, root);
const fixturePath = new URL('.local/tt368-browser-fixture.json', root);
const port = Number(process.env.E2E_FRONTEND_PORT || 5199), base = `http://127.0.0.1:${port}`;
mkdirSync(folder, { recursive: true });
let server, browser;
try {
  server = await createServer({ root: new URL('../', import.meta.url).pathname,
    plugins: [{ name: 'tt368-before', load(id) {
      if (phase === 'before' && ['/src/components/IssueDetail.vue', '/src/components/IssuePanel.vue'].some(path => id.endsWith(path)))
        return execFileSync('git', ['show', `f44b306:frontend${id.slice(id.indexOf('/src/'))}`], { cwd: root, encoding: 'utf8' });
    } }], server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  let fixture = existsSync(fixturePath) ? JSON.parse(readFileSync(fixturePath)) : null;
  if (fixture) assert.equal(fixture.api, process.env.E2E_API_URL);
  const context = await browser.newContext({ locale: 'zh-TW', viewport: { width: 1440, height: 1000 }, ...(fixture ? { storageState: fixture.state } : {}) });
  const page = await context.newPage(), errors = []; page.on('pageerror', e => errors.push(e.message)); page.setDefaultTimeout(12000);
  async function rpc(op, input, auth = false) {
    const csrf = await (await context.request.get(base + '/api/csrf-cookie')).json();
    const response = await context.request.post(`${base}/api/${auth ? 'auth' : 'rpc'}/${op}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), 200); return (await fixtureResponse(context.request, response)).data;
  }
  if (!fixture) {
    await rpc('register', { name: '日期驗證', email: `dates-${crypto.randomUUID()}@example.test`, password: 'isolated-date-browser-password', workspaceName: '日期驗證' }, true);
    const project = await rpc('createProject', { name: '計畫日期', code: 'DATE' });
    const issue = await rpc('createIssue', { projectId: project.id, title: '確認計畫日期與交付期限', description: '日期可分別設定與清除', startDate: '2028-02-29', endDate: '2028-03-02', dueDate: '2028-03-10' });
    fixture = { api: process.env.E2E_API_URL, project: project.id, issue: issue.id, state: await context.storageState() };
    writeFileSync(fixturePath, JSON.stringify(fixture), { mode: 0o600 });
  }
  const url = `${base}/app/#/projects/${fixture.project}/issues/${fixture.issue}?detail=1`;
  await page.goto(url);
  const detail = page.getByRole('region', { name: '任務完整頁', exact: true });
  await detail.getByRole('heading', { name: '確認計畫日期與交付期限', exact: true }).waitFor();
  if (phase === 'after') {
    await detail.getByRole('button', { name: '編輯任務', exact: true }).click();
    const form = page.locator('.issue-editor-inline'); await expandIssueProperties(form);
    await form.getByLabel('計畫開始日', { exact: true }).fill('2028-03-01');
    await form.getByLabel('計畫結束日', { exact: true }).fill('2028-03-03');
    await form.getByRole('button', { name: '儲存修改', exact: true }).click(); await form.waitFor({ state: 'hidden' });
    const response = await context.request.get(`${base}/api/v1/projects/${fixture.project}/issues/${fixture.issue}`);
    const saved = (await response.json()).data; assert.equal(saved.startDate, '2028-03-01'); assert.equal(saved.endDate, '2028-03-03'); assert.equal(saved.dueDate, '2028-03-10');
    await rpc('updateIssue', { id: fixture.issue, expectedRevision: saved.revision, startDate: null, endDate: '' });
    const cleared = (await (await context.request.get(`${base}/api/v1/projects/${fixture.project}/issues/${fixture.issue}`)).json()).data;
    assert.equal(cleared.startDate, ''); assert.equal(cleared.endDate, ''); assert.equal(cleared.dueDate, '2028-03-10');
    await rpc('updateIssue', { id: fixture.issue, expectedRevision: cleared.revision, startDate: '2028-02-29', endDate: '2028-03-02' });
    await page.reload(); await detail.getByRole('heading', { name: '確認計畫日期與交付期限', exact: true }).waitFor();
  }
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1000 });
    if (width <= 700) {
      await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
      await detail.getByRole('button', { name: '任務資訊', exact: true }).click();
    }
    await page.evaluate(() => document.fonts.ready); await page.waitForLoadState('networkidle');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await detail.screenshot({ path: new URL(`detail-${width}.png`, folder).pathname });
    await detail.getByRole('button', { name: '編輯任務', exact: true }).click();
    const form = page.locator('.issue-editor-inline'); await expandIssueProperties(form);
    if (phase !== 'before') { assert.equal(await form.getByLabel('計畫開始日', { exact: true }).inputValue(), '2028-02-29'); assert.equal(await form.getByLabel('計畫結束日', { exact: true }).inputValue(), '2028-03-02'); }
    await page.mouse.move(1, 1);
    await page.evaluate(async () => {
      await document.fonts.ready;
      await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
      document.activeElement?.blur();
      await Promise.all(document.getAnimations().filter(animation => Number.isFinite(animation.effect.getComputedTiming().endTime)).map(animation => animation.finished.catch(() => {})));
    });
    await form.screenshot({ path: new URL(`form-${width}.png`, folder).pathname });
    await form.getByRole('button', { name: '取消', exact: true }).click(); await form.waitFor({ state: 'hidden' });
  }
  assert.deepEqual(errors, []); writeFileSync(new URL('result.json', folder), JSON.stringify({ phase, errors, screenshots: 6, api: true }));
  console.log(`PASS planned dates browser ${phase}: six screenshots, persisted dates, no overflow or runtime errors`);
} finally { await browser?.close(); await server?.close(); }
