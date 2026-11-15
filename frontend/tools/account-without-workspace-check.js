import { ownedDefaultTopology } from './fixtures/owned-topology.js';
import { fixtureResponse } from './fixtures/verification.js';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { mkdirSync, writeFileSync } from 'node:fs';
import assert from 'node:assert/strict';
ownedDefaultTopology();
const base = `http://127.0.0.1:${Number(process.env.E2E_FRONTEND_PORT || 5199)}`;
let server, browser;
try {
  server = await createServer({ server: { host: '127.0.0.1', port: Number(new URL(base).port), strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const context = await browser.newContext({ locale: 'zh-TW' }), page = await context.newPage(), errors = [], calls = [];
  page.on('pageerror', error => errors.push(error.message)); page.on('request', r => { if (r.url().includes('/api/')) calls.push(new URL(r.url()).pathname); });
  async function call(operation, input = {}, group = 'auth') {
    const csrf = await (await context.request.get(base + '/api/csrf-cookie')).json();
    const response = await context.request.post(`${base}/api/${group}/${operation}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), 200, await response.text()); return (await fixtureResponse(context.request, response)).data;
  }
  const email = `detached-${crypto.randomUUID()}@example.test`, password = 'isolated-detached-password';
  const actor = await call('register', { name: '測試成員', email, password, workspaceName: '原工作區' });
  const project = await call('createProject', { name: '原專案', code: 'DETACH' }, 'rpc');
  await page.goto(`${base}/app/#/projects/${project.id}`); await page.getByRole('heading', { name: '原專案', exact: true }).waitFor(); await page.waitForLoadState('networkidle');
  execFileSync('docker', ['compose', '-p', 'tasktracker-codex-test', '-f', resolve('../docker-compose.mysql-test.yml'), 'exec', '-T', 'backend', 'php', 'tools/detach-workspace-fixture.php', email], { cwd: resolve('..'), stdio: 'pipe' });
  calls.length = 0;
  await page.getByRole('button', { name: '重新整理工作空間', exact: true }).click();
  const home = page.getByRole('region', { name: '無隸屬工作區首頁', exact: true }); await home.waitFor(); calls.length = 0;
  await page.waitForTimeout(700);
  assert.ok(!calls.some(path => path.startsWith('/api/v1/') || path.startsWith('/api/rpc/')), JSON.stringify(calls));
  assert.equal(await page.getByRole('heading', { name: '原專案', exact: true }).count(), 0);
  await page.getByRole('button', { name: '帳號選單', exact: true }).click();
  assert.equal(await page.getByRole('menuitem', { name: '個人 API Token', exact: true }).count(), 0);
  await page.getByRole('menuitem', { name: '個人資料', exact: true }).click();
  const profile = page.getByRole('dialog', { name: '個人資料', exact: true });
  await profile.getByLabel('顯示名稱', { exact: true }).fill('新的成員名稱'); await profile.getByRole('button', { name: '儲存個人資料', exact: true }).click(); await profile.waitFor({ state: 'hidden' });
  assert.equal((await call('getProfile')).name, '新的成員名稱');
  mkdirSync('../artifacts/tt358', { recursive: true });
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 }); await page.waitForTimeout(150);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({ path: `../artifacts/tt358/empty-${width}.png`, fullPage: true });
    await home.getByRole('button', { name: '建立工作區', exact: true }).click();
    const picker = page.getByRole('dialog', { name: '選擇工作空間', exact: true });
    await picker.getByRole('button', { name: '建立工作空間', exact: true }).waitFor();
    assert.equal(await picker.getByRole('button', { name: /^選擇工作空間 / }).count(), 0);
    await page.screenshot({ path: `../artifacts/tt358/picker-${width}.png`, fullPage: true });
    await picker.getByRole('button', { name: '建立工作空間', exact: true }).click();
    await picker.getByLabel('工作空間名稱', { exact: true }).fill('我的工作區');
    assert.equal(await picker.getByLabel('工作空間名稱', { exact: true }).evaluate(e => e === document.activeElement), true);
    await page.keyboard.press('Escape'); await picker.waitFor({ state: 'hidden' }); assert.equal(await home.getByRole('button', { name: '建立工作區', exact: true }).evaluate(e => e === document.activeElement), true);
  }
  await call('logout'); await call('login', { email, password });
  await page.reload(); await home.waitFor();
  await home.getByRole('button', { name: '建立工作區', exact: true }).click();
  const picker = page.getByRole('dialog', { name: '選擇工作空間', exact: true });
  await picker.getByRole('button', { name: '建立工作空間', exact: true }).click();
  await picker.getByLabel('工作空間名稱', { exact: true }).fill('我的工作區');
  await picker.getByRole('button', { name: '建立工作空間', exact: true }).click(); await picker.waitFor({ state: 'hidden' });
  const current = await call('current'); assert.equal(current.user.id, actor.user.id); assert.equal(current.role, 'owner'); assert.equal(current.workspace.name, '我的工作區');
  assert.deepEqual(errors, []);
  writeFileSync('../artifacts/tt358/browser-result.json', JSON.stringify({ passed: true, widths: [1440, 768, 390], revokedScopeCleared: true, noWorkspaceBackgroundCalls: true, detachedProfile: true, samePicker: true, freshLogin: true, createsOwnerWorkspace: true, errors }, null, 2));
  console.log('PASS detached account home, profile, same picker, three widths, clean scoped requests, fresh login and own workspace');
} finally { await browser?.close(); await server?.close(); }
