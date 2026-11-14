import assert from 'node:assert/strict';
import { preview } from 'vite';
import { chromium } from 'playwright';
import { verifyFixtureInBrowser } from './fixtures/verification.js';
import { openProjectList } from './navigation.js';
const port = Number(process.env.E2E_FRONTEND_PORT || 5175), apiUrl = process.env.E2E_API_URL;
if (process.env.E2E_ISOLATED !== '1' || !apiUrl) throw Error('Isolated home test required');
let server, browser;
try {
  server = await preview({ preview: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': apiUrl } } });
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } }), errors = [], reads = [];
  page.on('pageerror', e => errors.push(e.message));
  await page.goto(`http://127.0.0.1:${port}/login?mode=register`);
  await page.getByLabel('你的名字').fill('首頁測試'); await page.getByLabel('Email', { exact: true }).fill(`home-${crypto.randomUUID()}@example.test`);
  await page.getByLabel('密碼', { exact: true }).fill('home-isolated-password'); await page.getByLabel('工作空間名稱', { exact: true }).fill('首頁測試');
  await page.getByRole('button', { name: '建立工作空間', exact: true }).click(); await verifyFixtureInBrowser(page);
  page.on('request', r => { if (r.url().includes('/api/')) reads.push(new URL(r.url()).pathname); });
  await page.reload(); await page.locator('.app-shell').waitFor();
  assert.equal(await page.locator('.notes-page,.todo-home,.project-home,.page-heading').count(), 0);
  assert.equal(reads.some(p => p.startsWith('/api/v1/todos')), false);
  await page.getByRole('link', { name: '紙條', exact: true }).click(); await page.getByRole('heading', { name: '紙條', exact: true }).waitFor();
  assert.equal(new URL(page.url()).hash, '#/notes'); await page.reload(); await page.getByRole('heading', { name: '紙條', exact: true }).waitFor();
  await openProjectList(page); await page.getByRole('button', { name: '建立專案', exact: true }).click();
  const dialog = page.getByRole('dialog', { name: '建立專案', exact: true });
  await dialog.getByLabel(/專案名稱/).fill('獨立專案入口'); await dialog.getByLabel(/專案代碼/).fill('HOME'); await dialog.getByRole('button', { name: '建立專案', exact: true }).click();
  await page.getByRole('button', { name: '進入專案：獨立專案入口', exact: true }).click(); await page.getByRole('link', { name: '任務列表', exact: true }).waitFor();
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 }); await page.getByRole('link', { name: 'TaskTracker 返回首頁', exact: true }).evaluate(el => el.click());
    await page.getByRole('heading', { name: '首頁', exact: true }).waitFor();
    assert.equal(await page.locator('.notes-page,.todo-home,.project-home').count(), 0); assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await openProjectList(page); await page.getByRole('button', { name: '進入專案：獨立專案入口', exact: true }).click();
  }
  assert.deepEqual(errors, []); console.log('PASS empty home without note reads, independent notes deep link, project creation and three-width navigation');
} finally { await browser?.close(); await new Promise(resolve => server ? server.httpServer.close(resolve) : resolve()); }
