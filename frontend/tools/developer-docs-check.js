import { requireIsolatedApi } from './fixtures/isolated-api.js';
import assert from 'node:assert/strict';
import { preview } from 'vite';
import { chromium } from 'playwright';
requireIsolatedApi(); // TT-336
const docsPort = Number(process.env.E2E_DOCS_PORT || process.env.E2E_FRONTEND_PORT || 5175); // TT-336：沒另外指定時跟著自己的前端埠
if (![5175, 5185, 5198, 5199].includes(docsPort)) throw new Error('只允許隔離文件測試埠');
let server, browser;
try {
  server = await preview({ preview: { host: '127.0.0.1', port: docsPort, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const context = await browser.newContext();
  const page = await context.newPage();
  const errors = []; page.on('pageerror', error => errors.push(error.message));
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1000 });
    for (const slug of ['index', 'authentication', 'examples', 'agents', 'contract']) {
      await page.goto(`http://127.0.0.1:${docsPort}/api/public/docs/${slug}`);
      assert.equal(await page.getByRole('heading', { level: 1 }).count(), 1);
      assert.equal(await page.locator('nav [aria-current="page"]').count(), 1);
      assert.equal(await page.locator('input, form, script').count(), 0);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
      const raw = page.getByRole('link', { name: 'Markdown 原文', exact: true });
      assert((await (await page.request.get(new URL(await raw.getAttribute('href'), page.url()).href)).text()).startsWith('#'));
      if ((width === 1440 && slug === 'index') || (width === 390 && slug === 'examples')) await page.screenshot({ path: `/tmp/tt252-docs-${width}.png` });
    }
  }
  await page.goto(`http://127.0.0.1:${docsPort}/api/public/docs`);
  await page.keyboard.press('Tab');
  assert.equal(await page.evaluate(() => document.activeElement.textContent), '跳到文件內容');
  await page.keyboard.press('Enter');
  assert.equal(await page.evaluate(() => document.activeElement.id), 'content');
  assert.deepEqual(await context.cookies(), []);
  assert.deepEqual(errors, []);
  console.log('PASS: 五頁×三尺寸、無全頁水平捲軸、Markdown原文、無Token表單／Script／Cookie、鍵盤skip');
} finally {
  await browser?.close(); await server?.httpServer.close();
}
