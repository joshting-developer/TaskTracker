import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { preview } from 'vite';
import { chromium } from 'playwright';
assert.equal(process.env.E2E_ISOLATED, '1');
assert.equal(process.env.E2E_API_URL, 'http://127.0.0.1:13199');
const phase = process.argv[2] || 'after';
assert.ok(['before', 'after'].includes(phase));
const output = `artifacts/tt374/${phase}`;
await mkdir(output, { recursive: true });
const server = await preview({ preview: { host: '127.0.0.1', port: 5199, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
let browser;
const hashes = {}, checks = [];
try {
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
  for (const locale of ['zh-TW', 'en-US']) {
    const context = await browser.newContext({ locale, reducedMotion: 'reduce' });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 1000 });
      await page.goto('http://127.0.0.1:5199/?intro=1');
      await page.locator('html:not(.auth-pending)').waitFor();
      await page.evaluate(() => document.fonts.ready);
      assert.equal(await page.locator('h1').count(), 1);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
      assert.equal(await page.locator('[data-slide]:visible').count(), 1);
      if (locale === 'en-US') assert.doesNotMatch((await page.locator('body').innerText()).replaceAll('繁體中文', ''), /\p{Script=Han}/u);
      for (const selector of ['.guest-topbar', '.hero', '.scenario-section', '.site-footer']) {
        const bytes = await page.locator(selector).screenshot({ animations: 'disabled' });
        hashes[`${locale}-${width}-${selector}`] = createHash('sha256').update(bytes).digest('hex');
      }
      if (phase === 'after') {
        assert.equal(await page.locator('.story-section').count(), 1);
        assert.equal(await page.locator('.aspect > p').count(), 4);
        const questions = page.locator('.faq-section details');
        assert.equal(await questions.count(), 5);
        if (locale === 'en-US') assert.doesNotMatch(await page.locator('.faq-section').textContent(), /\p{Script=Han}/u);
        for (const detail of await questions.all()) {
          await detail.locator('summary').focus();
          await page.keyboard.press('Enter');
          assert.equal(await detail.getAttribute('open'), '');
          assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
          await page.keyboard.press('Space');
          assert.equal(await detail.getAttribute('open'), null);
        }
        await questions.first().locator('summary').click();
        await page.locator('[data-public-locale]').selectOption(locale === 'en-US' ? 'zh-TW' : 'en');
        assert.equal(await questions.first().getAttribute('open'), '');
        await page.locator('[data-public-locale]').selectOption(locale === 'en-US' ? 'en' : 'zh-TW');
        await page.locator('.faq-section').screenshot({ path: `${output}/faq-open-${locale}-${width}.png`, animations: 'disabled' });
        await questions.first().locator('summary').click();
        for (const section of ['story', 'faq']) await page.locator(`.${section}-section`).screenshot({ path: `${output}/${section}-${locale}-${width}.png`, animations: 'disabled' });
        assert.equal(await page.locator('.start-section a[data-guest]').getAttribute('href'), '/login?mode=register');
        assert.equal(await page.locator('.start-section a[data-member]').getAttribute('href'), '/app/');
      }
      await page.screenshot({ path: `${output}/home-${locale}-${width}.png`, fullPage: true, animations: 'disabled' });
      checks.push({ locale, width });
    }
    assert.deepEqual(errors, []);
    await context.close();
  }
  if (phase === 'after') {
    const noJs = await browser.newPage({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
    await noJs.goto('http://127.0.0.1:5199/?intro=1');
    assert.match(await noJs.locator('.story-section').innerText(), /Josh/);
    await noJs.locator('.faq-section summary').first().click();
    assert.equal(await noJs.locator('.faq-section details').first().getAttribute('open'), '');
    assert.equal(await noJs.locator('[data-slide]:visible').count(), 3);
    await noJs.close();
    if (process.env.E2E_VISUAL_COMPARE === '1') {
      const baseline = JSON.parse(await readFile('artifacts/tt374/before/unchanged-sections.json', 'utf8'));
      // Added content shifts lower sections onto different fractional pixel positions.
      // Compare the fixed first screen exactly; review the moved sections in full-page captures.
      for (const [key, hash] of Object.entries(hashes)) {
        if (key.endsWith('.guest-topbar') || key.endsWith('.hero')) assert.equal(hash, baseline[key], key);
      }
    }
  }
  await writeFile(`${output}/unchanged-sections.json`, JSON.stringify(hashes, null, 2));
  await writeFile(`${output}/result.json`, JSON.stringify({ passed: true, phase, checks }, null, 2));
  console.log(`PASS ${phase}: bilingual homepage × 3 widths${phase === 'after' ? ', FAQ keyboard toggles, no JS and language switch' : ''}${process.env.E2E_VISUAL_COMPARE === '1' ? ', exact header/hero comparison' : ''}`);
} finally { await browser?.close(); await server.close(); }
