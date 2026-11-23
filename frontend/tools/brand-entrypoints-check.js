import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { createServer, preview } from 'vite';
import { chromium } from 'playwright';
import { fixtureResponse } from './fixtures/verification.js';
const port = Number(process.env.E2E_FRONTEND_PORT || 5199);
assert.equal(process.env.E2E_ISOLATED, '1'); assert.ok(['http://127.0.0.1:13198', 'http://127.0.0.1:13199'].includes(process.env.E2E_API_URL));
const base = `http://127.0.0.1:${port}`, out = '../artifacts/tt363'; await mkdir(out, { recursive: true });
let server, browser;
try {
  const settings = { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } };
  if (process.env.E2E_USE_DEV === '1') { server = await createServer({ server: settings }); await server.listen(); }
  else server = await preview({ preview: settings });
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
  const context = await browser.newContext({ locale: 'zh-TW' }), page = await context.newPage(), errors = [];
  page.on('pageerror', e => errors.push(e.message));
  const marks = new Set();
  for (const path of ['/', '/login']) {
    await page.goto(base + path); await page.locator('.guest-brand-mark').waitFor();
    const iconLinks = await page.locator('link[rel="icon"],link[rel="apple-touch-icon"]').evaluateAll(nodes => nodes.map(n => ({ type: n.type, href: n.href })));
    assert.equal(iconLinks.length, 6);
    for (const icon of iconLinks) {
      const response = await context.request.get(icon.href); assert.equal(response.status(), 200, icon.href);
      const mime = response.headers()['content-type'];
      assert.match(mime, icon.type === 'image/svg+xml' ? /image\/svg\+xml/ : icon.type === 'image/x-icon' ? /image\/(x-icon|vnd.microsoft.icon)/ : /image\/png/);
    }
    const mark = await page.locator('.guest-brand-mark').evaluate(async image => { await image.decode(); return image.src; }); marks.add(mark);
    const manifest = page.locator('link[rel="manifest"]'); assert.equal(await manifest.count(), 1);
    const manifestUrl = await manifest.evaluate(el => el.href); const response = await context.request.get(manifestUrl); assert.equal(response.status(), 200);
    const data = await response.json(); assert.equal(data.name, 'Pavenro'); assert.equal(data.start_url, '/app/'); assert.equal(data.icons.length, 3);
    for (const icon of data.icons) { assert.equal((await context.request.get(base + icon.src)).status(), 200); }
    assert.equal(data.icons.find(i => i.purpose === 'maskable').sizes, '512x512');
    const cdp = await context.newCDPSession(page); const appManifest = await cdp.send('Page.getAppManifest'); assert.deepEqual(appManifest.errors, []); await cdp.detach();
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 1000 }); await page.evaluate(() => document.fonts.ready);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, path + ' overflow ' + width);
      await page.locator('.guest-brand').focus(); assert.ok(await page.locator('.guest-brand').evaluate(el => el === document.activeElement));
      await page.screenshot({ path: `${out}/${path === '/' ? 'landing' : 'login'}-${width}.png`, fullPage: true });
    }
  }
  const rpc = async (operation, input = {}, group = 'rpc') => {
    const csrf = await (await context.request.get(base + '/api/csrf-cookie')).json();
    const response = await context.request.post(`${base}/api/${group}/${operation}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.ok(response.ok(), await response.text()); return (await fixtureResponse(context.request, response, input)).data;
  };
  await rpc('register', { name: '品牌檢查', email: `brand-${crypto.randomUUID()}@example.test`, password: 'isolated-brand-password', workspaceName: '品牌檢查' }, 'auth');
  await page.goto(base + '/app/#/projects'); await page.locator('.app-shell').waitFor();
  assert.equal(await page.locator('.page-footer b').textContent(), process.env.E2E_EXPECTED_VERSION || 'dev');
  marks.add(await page.locator('.brand-mark').evaluate(async image => { await image.decode(); return image.src; })); assert.equal(marks.size, 1, 'All entrypoints use the same SVG');
  await page.evaluate(() => { window.__brandDocument = document; });
  await page.getByRole('link', { name: 'Pavenro 返回首頁', exact: true }).focus(); await page.keyboard.press('Enter');
  await page.getByRole('heading', { name: '首頁', exact: true }).waitFor(); assert.equal(await page.evaluate(() => document === window.__brandDocument), true);
  await page.waitForTimeout(500);
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1000 });
    if (width === 390) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({ path: `${out}/app-${width}.png` });
  }
  assert.equal(await page.evaluate(async () => (await navigator.serviceWorker.getRegistrations()).length), 0, 'No Service Worker introduced');
  assert.deepEqual(errors, []);
  console.log('PASS: home and login guest entries, one master SVG, HTTP icons and manifest, keyboard, isolated login and three widths; no offline PWA claim');
} finally { await browser?.close(); if (process.env.E2E_USE_DEV === '1') await server?.close(); else await new Promise(resolve => server ? server.httpServer.close(resolve) : resolve()); }
