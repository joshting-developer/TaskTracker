import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
assert.equal(process.env.E2E_ISOLATED, '1');
assert.match(process.env.E2E_API_URL || '', /^http:\/\/127\.0\.0\.1:13\d{3}$/);
const phase = process.env.E2E_CAPTURE_PHASE || 'after', root = new URL('../../', import.meta.url), folder = new URL(`artifacts/tt393/${phase}/`, root);
mkdirSync(folder, { recursive: true });
let server, browser;
try {
  const port = Number(process.env.E2E_FRONTEND_PORT || 5199);
  server = await createServer({ root: new URL('../', import.meta.url).pathname, plugins: [{ name: 'tt393-before', load(id) {
    if (phase === 'before' && ['/src/components/GuestTopBar.vue','/src/components/AuthGate.vue','/src/guest-topbar.css'].some(path => id.endsWith(path))) return execFileSync('git', ['show', `d98aa64:frontend${id.slice(id.indexOf('/src/'))}`], { cwd: root, encoding: 'utf8' });
  }}], server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await server.listen(); browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ locale: 'zh-TW', viewport: { width: 1440, height: 1000 } });
  const errors = []; page.on('pageerror', e => errors.push(e.message));
  for (const locale of ['zh-TW','en']) {
    await page.goto(`http://127.0.0.1:${port}/login`);
    await page.getByRole('heading', { name: /登入工作空間|Sign in/ }).waitFor();
    await page.locator('.locale-picker select').selectOption(locale);
    for (const width of [1440,768,390]) {
      await page.setViewportSize({ width, height: 1000 });
      await page.waitForLoadState('networkidle'); await page.evaluate(async () => { await document.fonts.ready; document.activeElement?.blur(); await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); });
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
      if (phase !== 'before') {
        assert.equal(await page.locator('.guest-nav a[href="/login"]').count(), 0);
        const geometry = await page.evaluate(() => { const picker = document.querySelector('.locale-picker select').getBoundingClientRect(); const links = [...document.querySelectorAll('.guest-nav a')].map(el => el.getBoundingClientRect()); return { picker: { top: picker.top, bottom: picker.bottom, height: picker.height }, links: links.map(el => ({ top: el.top, bottom: el.bottom, height: el.height })) }; });
        assert(geometry.picker.height >= 44); assert(geometry.links.every(link => link.height >= 44));
        assert(geometry.links.every(link => Math.abs((link.top + link.bottom) / 2 - (geometry.picker.top + geometry.picker.bottom) / 2) < 2), JSON.stringify({ locale, width, ...geometry }));
      }
      await page.screenshot({ path: new URL(`login-${locale}-${width}.png`, folder).pathname, fullPage: true, animations: 'disabled', caret: 'hide' });
    }
  }
  await page.goto(`http://127.0.0.1:${port}/login?mode=register`); await page.locator('input[autocomplete="name"]').waitFor();
  if (phase !== 'before') { assert.equal(await page.locator('.guest-nav a[href="/login?mode=register"]').count(), 0); await page.locator('.guest-nav a[href="/login"]').click(); await page.locator('input[autocomplete="current-password"]').waitFor(); }
  assert.deepEqual(errors, []); writeFileSync(new URL('result.json', folder), JSON.stringify({ phase, screenshots: 6, errors, registrationNavigation: true }));
  console.log('PASS auth navigation: languages, responsive overflow, touch sizes, aligned controls, and current-route navigation');
} finally { await browser?.close(); await server?.close(); }
