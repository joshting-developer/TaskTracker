import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fixtureResponse } from './fixtures/verification.js';
assert.equal(process.env.E2E_ISOLATED, '1');
assert.equal(process.env.E2E_API_URL, 'http://127.0.0.1:13199');
const phase = process.env.E2E_CAPTURE_PHASE || 'after';
assert.ok(['before', 'after', 'compare'].includes(phase));
const root = new URL('../../', import.meta.url), folder = new URL(`artifacts/tt397/${phase}/`, root);
const fixturePath = new URL('.local/tt397-browser-fixture.json', root);
const port = Number(process.env.E2E_FRONTEND_PORT || 5199), base = `http://127.0.0.1:${port}`;
mkdirSync(folder, { recursive: true });
let server, browser;
try {
  server = await createServer({ root: new URL('../', import.meta.url).pathname,
    plugins: [{ name: 'tt397-before', load(id) {
      if (phase === 'before' && ['/src/domain/todoContent.js', '/src/components/TodoConversation.vue'].some(path => id.endsWith(path)))
        return execFileSync('git', ['show', `ef4a893:frontend${id.slice(id.indexOf('/src/'))}`], { cwd: root, encoding: 'utf8' });
    } }], server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  let fixture = existsSync(fixturePath) ? JSON.parse(readFileSync(fixturePath)) : null;
  if (fixture) assert.equal(fixture.api, process.env.E2E_API_URL);
  const context = await browser.newContext({ locale: 'zh-TW', viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce', ...(fixture ? { storageState: fixture.state } : {}) });
  const page = await context.newPage(), errors = []; page.on('pageerror', e => errors.push(e.message)); page.setDefaultTimeout(12000);
  async function rpc(op, input, auth = false) {
    const csrf = await (await context.request.get(base + '/api/csrf-cookie')).json();
    const response = await context.request.post(`${base}/api/${auth ? 'auth' : 'rpc'}/${op}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), 200); return (await fixtureResponse(context.request, response)).data;
  }
  if (!fixture) {
    await rpc('register', { name: '時間驗收', email: `seconds-${crypto.randomUUID()}@example.test`, password: 'isolated-timestamp-password', workspaceName: '紙條時間' }, true);
    const todo = await rpc('createTodo', { content: '確認今天的更新時間' });
    await rpc('commentTodo', { id: todo.id, expectedRevision: todo.revision, content: '已確認可以顯示到秒' });
    fixture = { api: process.env.E2E_API_URL, state: await context.storageState() };
    writeFileSync(fixturePath, JSON.stringify(fixture), { mode: 0o600 });
  }
  await page.goto(base + '/app/#/notes');
  const row = page.locator('.notes-list li').filter({ hasText: '確認今天的更新時間' }); await row.waitFor();
  assert.equal(/\d{2}:\d{2}:\d{2}/.test(await row.locator('time').textContent()), phase !== 'before');
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1000 });
    if (width <= 700) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    await page.evaluate(() => document.fonts.ready); await page.waitForLoadState('networkidle');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({ path: new URL(`notes-${width}.png`, folder).pathname });
    await row.getByRole('button').first().click();
    const drawer = page.getByRole('dialog', { name: '紙條', exact: true });
    await drawer.getByText('已確認可以顯示到秒', { exact: true }).waitFor();
    await page.evaluate(() => Promise.all(document.getAnimations().map(a => a.finished.catch(() => {}))));
    await drawer.screenshot({ path: new URL(`conversation-${width}.png`, folder).pathname });
    await drawer.getByRole('button', { name: '關閉紙條', exact: true }).click(); await drawer.waitFor({ state: 'hidden' });
  }
  assert.deepEqual(errors, []);
  writeFileSync(new URL('result.json', folder), JSON.stringify({ phase, passed: true, screenshots: 6, errors }));
  console.log(`PASS: TT-397 ${phase}, list and conversation at three widths`);
} finally { await browser?.close(); await server?.close(); }
