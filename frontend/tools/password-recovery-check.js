import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { verifyFixtureInBrowser } from './fixtures/verification.js';
const api = process.env.E2E_API_URL;
if (process.env.E2E_ISOLATED !== '1' || api !== 'http://127.0.0.1:13199') throw new Error('Use the Codex isolated recovery topology');
const port = Number(process.env.E2E_FRONTEND_PORT || 5199), origin = `http://127.0.0.1:${port}`;
const folder = 'artifacts/tt328'; mkdirSync(folder, { recursive: true });
let server, browser;
try {
  server = await createServer({ server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': api } } }); await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage({ reducedMotion: 'reduce' });
  const errors = []; page.on('pageerror', error => errors.push(error.message));
  await page.goto(origin + '/login?mode=register');
  const email = `recover-${crypto.randomUUID()}@example.test`;
  await page.getByLabel('你的名字').fill('恢復驗收'); await page.getByLabel('Email', { exact: true }).fill(email);
  await page.getByLabel('密碼', { exact: true }).fill('old-synthetic-password'); await page.getByLabel('工作空間名稱').fill('恢復驗收');
  await page.getByRole('button', { name: '建立工作空間', exact: true }).click(); await verifyFixtureInBrowser(page); await page.locator('.app-shell').waitFor();
  await page.evaluate(async () => { const csrf = await (await fetch('/api/csrf-cookie')).json(); await fetch('/api/auth/logout', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: '{}' }); });
  await page.goto(origin + '/login');
  await page.getByRole('button', { name: '忘記密碼', exact: true }).click();
  for (const [width, height] of [[1440, 900], [768, 1024], [390, 844]]) {
    await page.setViewportSize({ width, height }); await page.waitForTimeout(350); assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, JSON.stringify(await page.evaluate(() => [...document.querySelectorAll('*')].filter(e => e.getBoundingClientRect().right > innerWidth + 2).slice(0, 8).map(e => [e.tagName, e.className, e.getBoundingClientRect().right]))));
    await page.screenshot({ path: `${folder}/request-${width}.png`, fullPage: true });
  }
  await page.getByLabel('Email', { exact: true }).fill(email); await page.getByRole('button', { name: '寄送重設連結' }).click();
  await page.getByRole('status').filter({ hasText: '如果這個信箱已註冊' }).waitFor();
  const token = execFileSync('docker', ['compose', '-p', 'tasktracker-codex-test', '-f', '../docker-compose.mysql-test.yml', 'exec', '-T', 'backend', 'php', 'tools/password-recovery-fixture.php', email], { encoding: 'utf8', env: { ...process.env, TASKTRACKER_TEST_PORT: '13199' } }).trim();
  assert.match(token, /^[a-f0-9]{64}$/);
  await page.goto(`${origin}/login#/reset-password/${token}?email=${encodeURIComponent(email)}`);
  await page.getByRole('heading', { name: '設定新密碼' }).waitFor();
  for (const [width, height] of [[1440, 900], [768, 1024], [390, 844]]) {
    await page.setViewportSize({ width, height }); await page.waitForTimeout(350); assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, JSON.stringify(await page.evaluate(() => [...document.querySelectorAll('*')].filter(e => e.getBoundingClientRect().right > innerWidth + 2).slice(0, 8).map(e => [e.tagName, e.className, e.getBoundingClientRect().right]))));
    await page.screenshot({ path: `${folder}/reset-${width}.png`, fullPage: true });
  }
  await page.getByLabel('新密碼', { exact: true }).fill('new-synthetic-password'); await page.getByLabel('確認新密碼').fill('new-synthetic-password');
  await page.getByRole('button', { name: '重設密碼', exact: true }).click(); await page.getByRole('heading', { name: '密碼已重設' }).waitFor();
  assert.equal(new URL(page.url()).hash, '');
  await page.getByRole('button', { name: '返回登入' }).click();
  await page.getByRole('button', { name: '忘記密碼', exact: true }).click();
  await page.getByRole('heading', { name: '忘記密碼', exact: true }).waitFor();
  await page.getByRole('button', { name: '返回登入' }).click(); await page.getByLabel('Email', { exact: true }).fill(email);
  await page.getByLabel('密碼', { exact: true }).fill('old-synthetic-password'); await page.getByRole('button', { name: '登入', exact: true }).click();
  await page.locator('.v-alert[role=alert]').waitFor();
  await page.getByLabel('密碼', { exact: true }).fill('new-synthetic-password'); await page.getByRole('button', { name: '登入', exact: true }).click(); await page.locator('.app-shell').waitFor();
  assert.deepEqual(errors, []);
  writeFileSync(`${folder}/result.json`, JSON.stringify({ passed: true, viewports: [1440, 768, 390], oldPasswordRejected: true, newPasswordAccepted: true, noAutoLogin: true, errors }, null, 2));
  console.log('PASS: isolated recovery request, single-use reset, old password denied, new login, 3 widths');
} finally { await browser?.close(); await server?.close(); }
