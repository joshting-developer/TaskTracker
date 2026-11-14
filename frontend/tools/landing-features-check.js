// TT-355：靜態功能分頁、鍵盤輪播、無JS閱讀與正式build入口。
import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
const port = Number(process.env.E2E_FRONTEND_PORT || 5199);
const origin = `http://127.0.0.1:${port}`;
const pages = ['/', '/features/tasks/', '/features/collaboration/', '/features/time/', '/features/integrations/'];
const folder = 'artifacts/tt355';
mkdirSync(folder, { recursive: true });
let server, browser;
const results = [];
try {
  server = await preview({ root: new URL('..', import.meta.url).pathname, preview: { host: '127.0.0.1', port, strictPort: true } });
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage({ reducedMotion: 'reduce' });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  // Guest auth failure must never hide public content or interfere with navigation.
  await page.route('**/api/**', route => route.fulfill({ status: 401, contentType: 'application/json', body: '{"ok":false}' }));
  const titles = new Set();
  for (const path of pages) {
    const raw = await (await fetch(origin + path)).text();
    const title = raw.match(/<title>([^<]+)<\/title>/)[1];
    assert.ok(!titles.has(title)); titles.add(title);
    assert.match(raw, /<h1[^>]*>[^<]+<\/h1>/);
    assert.match(raw, /<meta name="description" content="[^"]{40,}"/);
    assert.doesNotMatch(raw, /__SITE_ORIGIN__|小團隊|小團體|接受工作空間邀請/);
    for (const [width, height] of [[1440, 900], [768, 1024], [390, 844]]) {
      await page.setViewportSize({ width, height });
      await page.goto(origin + path);
      await page.waitForFunction(() => !document.documentElement.classList.contains('auth-pending'));
      assert.equal(await page.locator('h1').count(), 1);
      assert.ok(['rgb(41, 38, 49)', 'rgb(99, 80, 189)'].includes(await page.locator('.product-nav a').first().evaluate(el => getComputedStyle(el).color)), 'light header navigation stays readable, including current-page accent');
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${path} ${width} overflow`);
      const name = path === '/' ? 'home' : path.split('/')[2];
      await page.screenshot({ path: `${folder}/${name}-${width}.png`, fullPage: true, animations: 'disabled' });
      results.push({ path, width, title });
    }
    if (path !== '/') assert.equal(await page.locator('.product-nav [aria-current="page"]').count(), 1);
  }
  await page.goto(origin + '/');
  const counter = page.locator('[data-counter]');
  assert.equal(await page.locator('[data-slide]:visible').count(), 1);
  await page.getByRole('button', { name: '下一個情境' }).click();
  assert.equal(await counter.innerText(), '2 / 3');
  await page.getByRole('button', { name: '下一個情境' }).focus();
  await page.keyboard.press('Enter');
  assert.equal(await counter.innerText(), '3 / 3');
  await page.keyboard.press('Space');
  assert.equal(await counter.innerText(), '1 / 3');
  await page.locator('.scenarios').focus();
  await page.keyboard.press('ArrowLeft');
  assert.equal(await counter.innerText(), '3 / 3');
  await page.keyboard.press('ArrowRight');
  assert.equal(await counter.innerText(), '1 / 3');
  for (const link of await page.locator('.aspects a').all()) assert.equal((await fetch(origin + await link.getAttribute('href'))).status, 200);
  const noJs = await browser.newPage({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
  await noJs.goto(origin + '/');
  assert.equal(await noJs.locator('[data-slide]:visible').count(), 3);
  assert.equal(await noJs.locator('.carousel-controls').isVisible(), false);
  assert.ok((await noJs.locator('main').innerText()).length > 500);
  await noJs.close();
  // Already signed in: feature deep links stay readable, primary links return to the app.
  await page.route('**/api/**', route => route.fulfill({ contentType: 'application/json', body: route.request().url().endsWith('csrf-cookie') ? '{"csrfToken":"synthetic"}' : '{"ok":true}' }));
  await page.goto(origin + '/features/tasks/');
  await page.getByRole('link', { name: '回到工作區', exact: true }).waitFor();
  assert.equal(new URL(page.url()).pathname, '/features/tasks/');
  assert.deepEqual(errors, []);
  writeFileSync(`${folder}/result.json`, JSON.stringify({ passed: true, results, keyboard: true, noJs: true, syntheticAuth: true }, null, 2));
  console.log('PASS: 5 static pages × 3 widths, unique SEO, links, keyboard carousel, no JS, signed-in feature navigation');
} finally { await browser?.close(); await server?.close(); }
