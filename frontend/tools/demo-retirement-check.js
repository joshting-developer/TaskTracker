import { verifyFixtureInBrowser } from './fixtures/verification.js';
import { openProjectList } from './navigation.js';
import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';
if (process.env.E2E_ISOLATED !== '1' || process.env.E2E_API_URL !== 'http://127.0.0.1:13199') throw new Error('Use the Codex isolated topology');
const port = Number(process.env.E2E_FRONTEND_PORT || 5199), base = `http://127.0.0.1:${port}`;
let vite, browser;
try {
  vite = await createServer({ server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await vite.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage({ reducedMotion: 'reduce' });
  const demoCalls = [], errors = [];
  page.on('request', r => { if (r.url().includes('/loadDemo')) demoCalls.push(r.url()); });
  page.on('pageerror', e => errors.push(e.message));
  await page.goto(base + '/login?mode=register');
  await page.getByLabel('你的名字').fill('空白專案驗收'); await page.getByLabel('Email', { exact: true }).fill(`empty-${crypto.randomUUID()}@example.test`);
  await page.getByLabel('密碼', { exact: true }).fill('isolated-demo-retirement'); await page.getByLabel('工作空間名稱').fill('空白工作空間');
  await page.getByRole('button', { name: '建立工作空間', exact: true }).click(); await verifyFixtureInBrowser(page); await page.locator('.app-shell').waitFor();
  await openProjectList(page); await page.getByRole('heading', { name: '尚無專案', exact: true }).waitFor();
  assert.equal(await page.getByRole('button', { name: /示範專案/ }).count(), 0); assert.deepEqual(demoCalls, []);
  mkdirSync('../artifacts/tt356', { recursive: true });
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 }); await page.waitForTimeout(250);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({ path: `../artifacts/tt356/empty-${width}.png`, fullPage: true });
  }
  await page.route('**/api/auth/current', route => route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { code: 'UNAVAILABLE', message: '工作空間暫時無法載入' } }) }));
  await page.reload(); await page.getByRole('heading', { name: '無法載入工作空間', exact: true }).waitFor();
  assert.equal(await page.locator('.loader').count(), 0);
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 }); await page.waitForTimeout(250);
    await page.screenshot({ path: `../artifacts/tt356/connection-error-${width}.png`, fullPage: true });
  }
  await page.unroute('**/api/auth/current'); await page.getByRole('button', { name: '重試', exact: true }).click(); await page.locator('.app-shell').waitFor();
  await openProjectList(page); await page.getByRole('heading', { name: '尚無專案', exact: true }).waitFor();
  assert.deepEqual(errors, []); assert.deepEqual(demoCalls, []);
  writeFileSync('../artifacts/tt356/browser-result.json', JSON.stringify({ passed: true, demoCalls: 0, viewports: [1440, 768, 390], recovery: true, errors }, null, 2));
  console.log('PASS: no demo button or calls, connection error stops spinner, retry restores authenticated workspace, three widths');
} finally { await browser?.close(); await vite?.close(); }
