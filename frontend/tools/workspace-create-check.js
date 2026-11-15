import { ownedDefaultTopology } from './fixtures/owned-topology.js';
import { fixtureResponse } from './fixtures/verification.js';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
ownedDefaultTopology();
const port = Number(process.env.E2E_FRONTEND_PORT || 5199), base = `http://127.0.0.1:${port}`;
let server, browser;
try {
  server = await createServer({ server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const owner = await browser.newContext(), member = await browser.newContext();
  async function call(context, operation, input = {}, group = 'rpc') {
    const csrf = await (await context.request.get(base + '/api/csrf-cookie')).json();
    const response = await context.request.post(`${base}/api/${group}/${operation}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), 200); return (await fixtureResponse(context.request, response, input)).data;
  }
  const email = `member-create-${crypto.randomUUID()}@example.test`;
  const old = await call(owner, 'register', { name: '原空間管理者', email: `owner-${crypto.randomUUID()}@example.test`, password: 'isolated-creation-only', workspaceName: '原工作空間' }, 'auth');
  const invite = await call(owner, 'inviteMember', { email });
  const identity = await call(member, 'register', { name: '建立空間成員', email, password: 'isolated-creation-only', inviteToken: invite.token }, 'auth');
  assert.equal(identity.role, 'user');
  const page = await member.newPage(); const errors = []; page.on('pageerror', e => errors.push(e.message));
  mkdirSync('../artifacts/tt326', { recursive: true });
  await page.goto(base + '/app/#/'); await page.locator('.app-shell').waitFor();
  async function openPicker() {
    const nav = page.getByRole('button', { name: '開啟導覽', exact: true });
    if (await nav.isVisible() && await nav.getAttribute('aria-expanded') !== 'true') await nav.click();
    await page.getByRole('button', { name: '切換工作空間', exact: true }).click();
    await page.getByRole('button', { name: '建立工作空間', exact: true }).waitFor();
  }
  await openPicker(); await page.getByRole('button', { name: '建立工作空間', exact: true }).click();
  const picker = page.getByRole('dialog', { name: '選擇工作空間', exact: true });
  await page.getByLabel('工作空間名稱', { exact: true }).fill('我的新空間');
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 }); await page.waitForTimeout(250);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({ path: `../artifacts/tt326/create-${width}.png`, fullPage: true });
  }
  const failed = page.waitForResponse(r => r.url().endsWith('/api/auth/createWorkspace'));
  let attempts = 0, droppedId;
  await page.route('**/api/auth/createWorkspace', async route => {
    attempts++; const data = route.request().postDataJSON();
    if (attempts === 1) { droppedId = data.requestId; await route.fetch(); await route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { code: 'UNAVAILABLE', message: '回應暫時中斷, 請重試' } }) }); }
    else { assert.equal(data.requestId, droppedId); await route.continue(); }
  });
  await picker.getByRole('button', { name: '建立工作空間', exact: true }).click(); await failed;
  await picker.getByRole('alert').waitFor();
  const done = page.waitForResponse(r => r.url().endsWith('/api/auth/createWorkspace') && r.status() === 200);
  await picker.getByRole('button', { name: '建立工作空間', exact: true }).click(); await done; await picker.waitFor({ state: 'hidden' });
  await page.unroute('**/api/auth/createWorkspace');
  const current = await call(member, 'current', {}, 'auth'); assert.equal(current.role, 'owner'); assert.equal(current.user.id, identity.user.id); assert.equal(current.workspace.name, '我的新空間');
  const workspaces = await call(member, 'workspaces', {}, 'auth'); assert.equal(workspaces.workspaces.length, 2);
  await openPicker(); await page.getByRole('button', { name: '選擇工作空間 原工作空間', exact: true }).click(); await picker.waitFor({ state: 'hidden' });
  const original = await call(member, 'current', {}, 'auth'); assert.equal(original.workspace.id, old.workspace.id); assert.equal(original.role, 'user');
  assert.deepEqual(errors, []);
  writeFileSync('../artifacts/tt326/browser-result.json', JSON.stringify({ passed: true, widths: [1440, 768, 390], sameAccount: true, ownerInNew: true, userInOriginal: true, responseLostRetryCreatedOnlyOnce: true, errors }, null, 2));
  console.log('PASS: member creates own workspace, response-loss retry idempotent, same user, old role preserved, three widths');
} finally { await browser?.close(); await server?.close(); }
