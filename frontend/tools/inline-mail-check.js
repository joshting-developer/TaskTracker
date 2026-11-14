import assert from 'node:assert/strict';
import { preview } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
if (process.env.E2E_ISOLATED !== '1' || process.env.E2E_API_URL !== 'http://127.0.0.1:13199') throw new Error('Owned Codex isolated inline backend required');
const port = Number(process.env.E2E_FRONTEND_PORT || 5199);
let server, browser;
try {
  server = await preview({ preview: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage(), errors = [];
  page.setDefaultTimeout(12000); page.on('pageerror', error => errors.push(error.message));
  await page.goto(`http://127.0.0.1:${port}/login`);
  await page.getByRole('button', { name: '第一次使用？建立工作空間', exact: true }).click();
  await page.getByLabel('你的名字', { exact: true }).fill('Inline mail fixture');
  await page.getByLabel('Email', { exact: true }).fill(`inline-${crypto.randomUUID()}@example.test`);
  await page.getByLabel('密碼', { exact: true }).fill('synthetic-inline-password');
  await page.getByLabel('工作空間名稱', { exact: true }).fill('Inline mail fixture');
  const [response] = await Promise.all([page.waitForResponse(value => value.url().endsWith('/api/auth/register')), page.getByRole('button', { name: '建立工作空間', exact: true }).click()]);
  assert.equal(response.status(), 200);
  assert.equal((await response.json()).data.deliveryStatus, 'failed', 'MAIL_MAILER=log must never be reported as actual SMTP sent');
  const failure = page.getByRole('alert').filter({ hasText: '驗證信未寄出' });
  await failure.waitFor();
  assert.equal(await page.getByRole('button', { name: /^重寄驗證碼/ }).isDisabled(), true);
  await page.reload(); await failure.waitFor();
  assert.equal(await page.getByLabel('驗證碼', { exact: true }).isVisible(), true, 'Committed account remains pending after reload');
  mkdirSync('../artifacts/tt162', { recursive: true });
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({ path: `../artifacts/tt162/inline-failed-${width}.png` });
  }
  await page.getByLabel('驗證碼', { exact: true }).fill('local666');
  await page.getByRole('button', { name: '驗證並登入', exact: true }).click();
  await page.locator('.app-shell').waitFor();
  assert.deepEqual(errors, []);
  console.log('PASS: real inline HTTP failure preserves pending session, exposes safe status, allows local verification, cooldown and three widths; no SMTP sent');
} finally {
  await browser?.close(); await new Promise(resolve => server ? server.httpServer.close(resolve) : resolve());
}
