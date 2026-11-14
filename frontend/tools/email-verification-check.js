import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
if (process.env.E2E_ISOLATED !== '1' || process.env.E2E_API_URL !== 'http://127.0.0.1:13199') throw Error('Owned isolated backend required');
const port = Number(process.env.E2E_FRONTEND_PORT || 5199), base = `http://127.0.0.1:${port}`, artifacts = new URL('../../artifacts/tt338/', import.meta.url);
mkdirSync(artifacts, { recursive: true });
let server, browser;
try {
  server = await createServer({ root: new URL('../', import.meta.url).pathname, server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage(), errors = [], authRequests = []; page.setDefaultTimeout(15000); page.on('pageerror', e => errors.push(e.message));
  page.on('request', r => { if (new URL(r.url()).pathname.startsWith('/api/auth/')) authRequests.push({ path: new URL(r.url()).pathname, method: r.method() }); });
  await page.goto(`${base}/login?mode=register`);
  const email = `verify-${crypto.randomUUID()}@example.test`;
  await page.getByLabel('你的名字').fill('驗證測試'); await page.getByLabel('Email', { exact: true }).fill(email); await page.getByLabel('密碼', { exact: true }).fill('isolated-verification'); await page.getByLabel('工作空間名稱').fill('驗證測試');
  await page.getByRole('button', { name: '建立工作空間', exact: true }).click();
  await page.getByRole('heading', { name: '驗證信箱', exact: true }).waitFor();
  assert.equal(await page.getByLabel('驗證碼', { exact: true }).count(), 0);
  await page.getByText('請點擊信件中的啟用連結', { exact: true }).waitFor();
  assert.ok(await page.getByRole('button', { name: /重寄啟用信/ }).isDisabled());
  const projects = await context.request.get(`${base}/api/v1/projects`); assert.equal(projects.status(), 401);
  await page.reload(); await page.getByRole('heading', { name: '驗證信箱', exact: true }).waitFor();
  for (const width of [1440, 768, 390]) { await page.setViewportSize({ width, height: 1000 }); assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)); await page.screenshot({ path: new URL(`verification-${width}.png`, artifacts).pathname }); }
  // Only read this synthetic fixture from the owned test schema; keep the secret in process memory.
  const program = `if(getenv('APP_ENV')!=='testing'||getenv('TASKTRACKER_MYSQL_TEST')!=='1'||getenv('DB_DATABASE')!=='tasktracker_test')exit(3);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();$user=App\\Models\\User::where('email',getenv('VERIFY_FIXTURE_EMAIL'))->firstOrFail();$row=App\\Models\\MailDelivery::where('user_id',$user->id)->where('kind','verification')->firstOrFail();preg_match('~/activate/([a-f0-9]{64})~',$row->payload['body'],$m);echo json_encode(['token'=>$m[1]]);`;
  const args = ['compose','-p','tasktracker-codex-test','-f','docker-compose.mysql-test.yml','-f','/tmp/tt318-test.yml','exec','-T','-e',`VERIFY_FIXTURE_EMAIL=${email}`,'backend','php','-r',program];
  const fixtureToken = fixtureEmail => {
    const fixtureArgs = args.map(value => value.startsWith('VERIFY_FIXTURE_EMAIL=') ? `VERIFY_FIXTURE_EMAIL=${fixtureEmail}` : value);
    return JSON.parse(execFileSync('docker', fixtureArgs, { cwd: new URL('../..', import.meta.url).pathname, env: { ...process.env, TASKTRACKER_TEST_PORT: '13199', TASKTRACKER_TEST_IMAGE: 'tasktracker-codex-tt318' }, encoding: 'utf8' })).token;
  };
  const token = fixtureToken(email);
  const activation = await context.newPage();
  await activation.goto(`${base}/app/#/activate/${token}`);
  await activation.getByRole('button', { name: '啟用帳號', exact: true }).waitFor();
  assert.equal(authRequests.filter(r => r.path.endsWith('/verify-email')).length, 0, 'Link GET sends no activation POST');
  await activation.getByRole('button', { name: '啟用帳號', exact: true }).click();
  await activation.locator('.app-shell').waitFor();
  await page.bringToFront();
  await page.locator('.app-shell').waitFor();
  await page.reload(); await page.locator('.app-shell').waitFor();
  // A separate browser cookie jar cannot share the activation login or localStorage event.
  const secondContext = await browser.newContext(), thirdContext = await browser.newContext();
  const second = await secondContext.newPage(), third = await thirdContext.newPage();
  const otherEmail = `verify-other-${crypto.randomUUID()}@example.test`;
  await second.goto(`${base}/login?mode=register`);
  await second.getByLabel('你的名字').fill('另一瀏覽器');
  await second.getByLabel('Email', { exact: true }).fill(otherEmail);
  await second.getByLabel('密碼', { exact: true }).fill('isolated-verification');
  await second.getByLabel('工作空間名稱').fill('另一瀏覽器');
  await second.getByRole('button', { name: '建立工作空間', exact: true }).click();
  await second.getByRole('heading', { name: '驗證信箱', exact: true }).waitFor();
  // A storage signal alone must never promote an unverified pending account.
  await second.evaluate(() => window.dispatchEvent(new StorageEvent('storage', { key: 'tasktracker:auth-updated', newValue: 'forged' })));
  await second.getByText('請點擊信件中的啟用連結', { exact: true }).waitFor();
  assert.equal((await secondContext.request.get(`${base}/api/v1/projects`)).status(), 401);
  const otherToken = fixtureToken(otherEmail);
  await third.goto(`${base}/app/#/activate/${otherToken}`);
  await third.getByRole('button', { name: '啟用帳號', exact: true }).click();
  await third.locator('.app-shell').waitFor();
  await third.bringToFront(); await second.bringToFront();
  // Headless Chrome can keep both contexts visible: explicitly send the same focus event.
  await second.evaluate(() => window.dispatchEvent(new Event('focus')));
  await second.locator('.app-shell').waitFor();
  await second.reload(); await second.locator('.app-shell').waitFor();
  const csrf = await (await context.request.get(`${base}/api/csrf-cookie`)).json();
  assert.equal((await context.request.post(`${base}/api/auth/verify-email`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { token } })).status(), 400);
  const stranger = await browser.newContext();
  assert.equal((await stranger.request.get(`${base}/api/v1/projects`)).status(), 401);
  await stranger.close(); await secondContext.close(); await thirdContext.close();
  assert.deepEqual(errors, []);
  writeFileSync(new URL('browser-result.json', artifacts), JSON.stringify({ passed: true, widths: [1440,768,390], checks: ['pending registration', 'no workspace access', 'reload preserves verification', 'no OTP input', 'resend cooldown', 'link GET no side effects', 'explicit activation POST', 'original tab sync and reload', 'separate browser pending session sync', 'forged storage signal denied', 'single use link', 'unrelated browser denied'] }, null, 2));
  console.log('PASS: link-only signup, original tab login sync, reload, cooldown, single-use token, unrelated browser denied and three widths');
} finally { await browser?.close(); await server?.close(); }
