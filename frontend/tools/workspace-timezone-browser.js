import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, existsSync, readFileSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fixtureResponse } from './fixtures/verification.js';
assert.equal(process.env.E2E_ISOLATED, '1');
assert.equal(process.env.E2E_API_URL, 'http://127.0.0.1:13199');
const phase = process.env.E2E_CAPTURE_PHASE || 'after';
assert.ok(['before', 'after', 'compare'].includes(phase));
const root = new URL('../../', import.meta.url), folder = new URL(`artifacts/tt398/${phase}/`, root), fixturePath = new URL('.local/tt398-browser-fixture.json', root);
const port = Number(process.env.E2E_FRONTEND_PORT || 5199), base = `http://127.0.0.1:${port}`;
mkdirSync(folder, { recursive: true });
const previous = new Set(execFileSync('git', ['diff', '--name-only', '51a5577', '--', 'frontend/src'], { cwd: root, encoding: 'utf8' }).trim().split('\n').map(p => new URL(p, root).pathname));
let server, browser;
try {
  server = await createServer({ root: new URL('../', import.meta.url).pathname,
    plugins: [{ name: 'tt398-before', load(id) {
      if (phase === 'before' && previous.has(id)) return execFileSync('git', ['show', `51a5577:frontend${id.slice(id.indexOf('/src/'))}`], { cwd: root, encoding: 'utf8' });
    } }], server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  let fixture = existsSync(fixturePath) ? JSON.parse(readFileSync(fixturePath)) : null;
  if (fixture) assert.equal(fixture.api, process.env.E2E_API_URL);
  const options = { locale: 'zh-TW', viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' };
  const ownerContext = await browser.newContext({ ...options, ...(fixture ? { storageState: fixture.owner } : {}) });
  const memberContext = await browser.newContext({ ...options, ...(fixture ? { storageState: fixture.member } : {}) });
  async function rpc(context, op, input = {}, auth = false, expected = 200) {
    const csrf = await (await context.request.get(base + '/api/csrf-cookie')).json();
    const response = await context.request.post(`${base}/api/${auth ? 'auth' : 'rpc'}/${op}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), expected); return expected === 200 ? (await fixtureResponse(context.request, response, input)).data : null;
  }
  const settingsData = async () => (await (await ownerContext.request.get(base + '/api/v1/workspace/settings')).json()).data.workspace;
  if (!fixture) {
    await rpc(ownerContext, 'register', { name: '交辦人', email: `zone-owner-${crypto.randomUUID()}@example.test`, password: 'isolated-zone-password', workspaceName: '時區驗收' }, true);
    const invite = await rpc(ownerContext, 'inviteMember', { email: `zone-member-${crypto.randomUUID()}@example.test` });
    const member = await rpc(memberContext, 'register', { name: '承辦人', email: invite.email, password: 'isolated-zone-password', inviteToken: invite.token }, true);
    await rpc(ownerContext, 'createTodo', { content: '確認通知與紙條的同一瞬間', assigneeId: member.user.id });
    fixture = { api: process.env.E2E_API_URL, owner: await ownerContext.storageState(), member: await memberContext.storageState() };
    writeFileSync(fixturePath, JSON.stringify(fixture), { mode: 0o600 });
  }
  let current = await settingsData();
  await rpc(ownerContext, 'updateWorkspace', { name: current.name, timeZone: 'UTC', expectedRevision: current.revision });
  const owner = await ownerContext.newPage(), member = await memberContext.newPage(), errors = [];
  for (const page of [owner, member]) { page.setDefaultTimeout(15000); page.on('pageerror', e => errors.push(e.message)); }
  await owner.goto(base + '/app/#/workspace/settings');
  const settings = owner.getByRole('region', { name: '工作空間設定', exact: true });
  await settings.getByLabel('工作空間名稱', { exact: true }).waitFor();
  const picker = settings.getByLabel('顯示時區', { exact: true });
  if (phase !== 'before') {
    assert.equal(await picker.inputValue(), 'UTC');
    await picker.selectOption('Asia/Taipei');
    current = await settingsData();
    await rpc(ownerContext, 'updateWorkspace', { name: current.name, timeZone: 'UTC', expectedRevision: current.revision });
    await settings.getByRole('button', { name: '儲存工作空間設定', exact: true }).click();
    await settings.getByRole('alert').waitFor(); assert.equal(await picker.inputValue(), 'Asia/Taipei', 'Conflict preserves timezone draft');
    owner.once('dialog', dialog => dialog.accept());
    await settings.getByRole('button', { name: '載入最新設定', exact: true }).click();
    await picker.selectOption('Asia/Taipei');
    await settings.getByRole('button', { name: '儲存工作空間設定', exact: true }).click();
    await settings.getByRole('status').filter({ hasText: '工作空間設定已儲存' }).waitFor();
    await owner.reload(); await picker.waitFor(); assert.equal(await picker.inputValue(), 'Asia/Taipei');
    const saved = await settingsData();
    await rpc(memberContext, 'updateWorkspace', { name: saved.name, timeZone: 'UTC', expectedRevision: saved.revision }, false, 403);
    assert.equal((await settingsData()).timeZone, 'Asia/Taipei');
  }
  async function capture(page, name, target) {
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 1000 });
      if (width <= 700) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
      await page.evaluate(() => document.fonts.ready); await page.waitForLoadState('networkidle');
      await page.evaluate(() => Promise.all(document.getAnimations().map(a => a.finished.catch(() => {}))));
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
      await target.screenshot({ path: new URL(`${name}-${width}.png`, folder).pathname });
    }
  }
  await capture(owner, 'settings', settings);
  async function notification(zone, name) {
    const saved = await settingsData();
    await rpc(ownerContext, 'updateWorkspace', { name: saved.name, timeZone: zone, expectedRevision: saved.revision });
    await member.goto(base + '/app/#/notes');
    await member.reload();
    await member.getByRole('button', { name: /^通知/ }).click();
    const inbox = member.getByRole('dialog', { name: '通知', exact: true });
    await inbox.getByText('確認通知與紙條的同一瞬間', { exact: true }).waitFor();
    const notice = (await (await memberContext.request.get(base + '/api/v1/todo-notifications?perPage=20')).json()).data.items[0];
    assert.match(notice.createdAt, /Z$/);
    const expected = await member.evaluate(({ at, zone }) => new Date(at).toLocaleString('zh-TW', { timeZone: zone }), { at: notice.createdAt, zone: phase === 'before' ? 'Asia/Taipei' : zone });
    assert.equal(await inbox.locator('time').first().textContent(), expected);
    await capture(member, name, inbox);
    await inbox.getByRole('button', { name: '關閉通知', exact: true }).click();
  }
  if (phase !== 'before') await notification('UTC', 'notification-utc');
  await notification('Asia/Taipei', 'notification-taipei');
  assert.deepEqual(errors, []);
  writeFileSync(new URL('result.json', folder), JSON.stringify({ phase, passed: true, errors, screenshots: phase === 'before' ? 6 : 9, checks: ['UTC default', 'timezone persistence', 'conflict retains draft', 'member denied', 'notice ISO instant preserved', 'UTC/Taipei rendering', 'three widths'] }));
  console.log(`PASS: TT-398 ${phase}, settings and notification timezone at three widths`);
} finally { await browser?.close(); await server?.close(); }
