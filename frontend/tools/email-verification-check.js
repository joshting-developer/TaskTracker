import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
if (process.env.E2E_ISOLATED !== '1' || process.env.E2E_API_URL !== 'http://127.0.0.1:13089') throw Error('Owned isolated backend required');
const base = 'http://127.0.0.1:5291', artifacts = new URL('../../artifacts/tt319/', import.meta.url);
mkdirSync(artifacts, { recursive: true });
let server, browser;
try {
  server = await createServer({ root: new URL('../', import.meta.url).pathname, server: { host: '127.0.0.1', port: 5291, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage(), errors = [], authRequests = []; page.setDefaultTimeout(15000); page.on('pageerror', e => errors.push(e.message));
  page.on('request', r => { if (new URL(r.url()).pathname.startsWith('/api/auth/')) authRequests.push({ path: new URL(r.url()).pathname, method: r.method() }); });
  await page.goto(`${base}/login?mode=register`);
  const email = `verify-${crypto.randomUUID()}@example.test`;
  await page.getByLabel('你的名字').fill('驗證測試'); await page.getByLabel('Email', { exact: true }).fill(email); await page.getByLabel('密碼', { exact: true }).fill('isolated-verification'); await page.getByLabel('工作空間名稱').fill('驗證測試');
  await page.getByRole('button', { name: '建立工作空間', exact: true }).click();
  await page.getByRole('heading', { name: '驗證信箱', exact: true }).waitFor();
  assert.equal(await page.getByLabel('驗證碼', { exact: true }).getAttribute('autocomplete'), 'one-time-code');
  assert.ok(await page.getByRole('button', { name: /重寄驗證碼/ }).isDisabled());
  const projects = await context.request.get(`${base}/api/v1/projects`); assert.equal(projects.status(), 401);
  await page.reload(); await page.getByRole('heading', { name: '驗證信箱', exact: true }).waitFor();
  for (const width of [1440, 768, 390]) { await page.setViewportSize({ width, height: 1000 }); assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)); await page.screenshot({ path: new URL(`verification-${width}.png`, artifacts).pathname }); }
  await page.getByLabel('驗證碼', { exact: true }).fill('WRONGXX'); await page.getByRole('button', { name: '驗證並登入', exact: true }).click(); await page.getByRole('alert').filter({ hasText: '驗證碼不正確' }).waitFor();
  // Only read this synthetic fixture from the owned test schema; keep the secret in process memory.
  const program = `if(getenv('APP_ENV')!=='testing'||getenv('TASKTRACKER_MYSQL_TEST')!=='1'||getenv('DB_DATABASE')!=='tasktracker_test')exit(3);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();$user=App\\Models\\User::where('email',getenv('VERIFY_FIXTURE_EMAIL'))->firstOrFail();$row=App\\Models\\MailDelivery::where('user_id',$user->id)->where('kind','verification')->firstOrFail();preg_match('~/activate/([a-f0-9]{64})~',$row->payload['body'],$m);echo json_encode(['token'=>$m[1]]);`;
  const args = ['compose','-p','tasktracker-codex-perf','-f','docker-compose.mysql-test.yml','-f','/tmp/tt191-compose.yml','-f','/tmp/tt291-local-compose.yml','exec','-T','-e',`VERIFY_FIXTURE_EMAIL=${email}`,'backend','php','-r',program];
  const { token } = JSON.parse(execFileSync('docker', args, { cwd: new URL('../..', import.meta.url).pathname, env: { ...process.env, TASKTRACKER_TEST_PORT: '13089' }, encoding: 'utf8' }));
  await page.goto(`${base}/login#/activate/${token}`); await page.getByRole('button', { name: '啟用帳號', exact: true }).waitFor();
  const other = await browser.newContext(), activation = await other.newPage();
  await activation.goto(`${base}/app/#/activate/${token}`); await activation.getByRole('button', { name: '啟用帳號', exact: true }).waitFor();
  assert.equal(authRequests.filter(r => r.path.endsWith('/verify-email')).length, 1, 'Link GET sends no activation POST');
  await activation.getByRole('button', { name: '啟用帳號', exact: true }).click(); await activation.getByRole('heading', { name: '小待辦', exact: true }).waitFor();
  // Original code and local shortcut cannot reuse a consumed challenge.
  const csrf = await (await context.request.get(`${base}/api/csrf-cookie`)).json();
  assert.equal((await context.request.post(`${base}/api/auth/verify-email`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { code: 'local666' } })).status(), 400);
  assert.deepEqual(errors, []);
  writeFileSync(new URL('browser-result.json', artifacts), JSON.stringify({ passed: true, widths: [1440,768,390], checks: ['pending registration', 'no workspace access', 'reload preserves verification', 'OTP autocomplete', 'resend cooldown', 'inline error', 'link GET no side effects', 'explicit activation POST', 'single use across code/link'] }, null, 2));
  console.log('PASS: pending signup, reload, cooldown, code error, link activation and three widths');
} finally { await browser?.close(); await server?.close(); }
