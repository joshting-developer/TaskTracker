import { loadDesignAudit } from './fixtures/optional-design-audit.js';
const designAudit = await loadDesignAudit();
import { createHash } from 'node:crypto';
import assert from 'node:assert/strict';
import { mkdir, writeFile, readFile } from 'node:fs/promises';
import { preview } from 'vite';
import { chromium } from 'playwright';
assert.equal(process.env.E2E_ISOLATED, '1');
assert.equal(process.env.E2E_API_URL, 'http://127.0.0.1:13199');
const out = '../artifacts/tt365'; await mkdir(out, { recursive: true });
const paths = ['/', '/features/tasks/', '/features/collaboration/', '/features/time/', '/features/integrations/'];
const titles = ['Project management', 'Break work into trackable issues', 'Keep assignments and replies together', 'See where time goes', 'Let external tools work with issues'];
let server, browser;
try {
  server = await preview({ preview: { host: '127.0.0.1', port: 5199, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  browser = await chromium.launch({ headless: true, executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
  const context = await browser.newContext({ locale: 'en-US', reducedMotion: 'reduce' });
  let page = await context.newPage();
  const errors = [], findings = [], requests = [];
  page.on('pageerror', error => errors.push(error.message));
  context.on('request', request => { if (new URL(request.url()).pathname.startsWith('/api/')) requests.push(request.url()); });
  for (const width of [1440,768,390]) {
    for (const [index,path] of paths.entries()) {
      await page.close(); page = await context.newPage();
      page.on('pageerror', error => errors.push(error.message));
      await page.setViewportSize({ width, height: 1000 });
      await page.goto(`http://127.0.0.1:5199${path}?intro=1`);
      await page.locator('html:not(.auth-pending)').waitFor();
      assert.equal(await page.locator('html').getAttribute('lang'), 'en');
      assert.equal(await page.locator('h1').innerText(), titles[index]);
      assert.equal((await page.locator('body').innerText()).replaceAll('繁體中文','').match(/\p{Script=Han}/gu), null, 'All public copy is translated');
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${path} ${width}`);
      await page.evaluate(() => document.fonts.ready);
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
      await page.screenshot({ path: `${out}/public-en-${index}-${width}.png`, fullPage: true });
      const metrics = designAudit ? await page.evaluate(designAudit.probe) : null, options = { width, allow: ['palette-purple'] };
      findings.push({ path, width, designAudit: !!designAudit, rows: designAudit ? [...designAudit.judge(metrics,options),...designAudit.judgeLayout(await page.evaluate(designAudit.layout),options)] : [] });
      const before = requests.length;
      await page.locator('[data-public-locale]').selectOption('zh-TW');
      assert.equal(await page.locator('html').getAttribute('lang'), 'zh-Hant');
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
      await page.screenshot({ path: `${out}/public-zh-${index}-${width}.png`, fullPage: true });
      await page.locator('[data-public-locale]').selectOption('en');
      assert.equal(requests.length, before, 'Language switch sends no API requests');
    }
  }
  await page.locator('[data-public-locale]').selectOption('zh-TW'); await page.reload();
  assert.equal(await page.locator('[data-public-locale]').inputValue(), 'zh-TW');
  await page.goto('http://127.0.0.1:5199/login');
  await page.getByRole('button',{name:'登入',exact:true}).waitFor();
  await page.getByLabel('選擇語言',{exact:true}).selectOption('en');
  await page.goto('http://127.0.0.1:5199/?intro=1');
  await page.getByRole('heading',{name:'Project management',exact:true}).waitFor();
  assert.equal(await page.locator('[data-public-locale]').inputValue(), 'en');
  const fallback = await browser.newPage({ locale: 'ja-JP' });
  await fallback.goto('http://127.0.0.1:5199/features/tasks/');
  assert.equal(await fallback.locator('h1').innerText(), '把工作拆成可追蹤的任務');
  await fallback.close();
  const denied = await browser.newPage({ locale: 'en-US' });
  await denied.addInitScript(() => Object.defineProperty(window, 'localStorage', { get() { throw new DOMException('Denied', 'SecurityError'); } }));
  await denied.goto('http://127.0.0.1:5199/?intro=1');
  await denied.locator('[data-public-locale]').selectOption('zh-TW');
  assert.equal(await denied.locator('h1').innerText(), '專案管理平台'); await denied.close();
  assert.deepEqual(errors, []);
  await writeFile(`${out}/design-inspection.json`,JSON.stringify(findings,null,2));
  await writeFile(`${out}/browser-summary.json`,JSON.stringify({passed:true, pages:5, widths:[1440,768,390], checks:['bilingual-complete-copy','no-overflow','browser-default','reload','cross-public-login','storage-denied','no-api-on-switch'], findings},null,2));
  if (process.env.PUBLIC_VISUAL_COMPARE === '1') {
    const baseline = JSON.parse(await readFile('../docs/visual-baselines/public-language-darwin-arm64.json', 'utf8'));
    assert.equal(baseline.approved, true);
    for (const row of baseline.files) assert.equal(createHash('sha256').update(await readFile(`${out}/${row.path}`)).digest('hex'), row.sha256, row.path);
    console.log('PASS: thirty approved public screenshots match exactly');
  }
  console.log('PASS: five public pages, two languages, three widths, shared choice, no-storage fallback and no API on switch');
} finally { await browser?.close(); await new Promise(resolve => server ? server.httpServer.close(resolve) : resolve()); }
