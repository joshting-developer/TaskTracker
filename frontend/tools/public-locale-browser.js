import { loadDesignAudit } from './fixtures/optional-design-audit.js';
const designAudit = await loadDesignAudit();
import { createHash } from 'node:crypto';
import assert from 'node:assert/strict';
import { mkdir, writeFile, readFile } from 'node:fs/promises';
import { preview } from 'vite';
import { chromium } from 'playwright';
assert.equal(process.env.E2E_ISOLATED, '1');
// 各 Agent 用自己的隔離後端與前端埠
assert.ok(['http://127.0.0.1:13198', 'http://127.0.0.1:13199'].includes(process.env.E2E_API_URL));
const frontendPort = Number(process.env.E2E_FRONTEND_PORT || 5199);
const out = '../artifacts/tt365'; await mkdir(out, { recursive: true });
// TT-437：功能子頁已移除, 公開頁只剩首頁
const paths = ['/'];
const titles = ['Project management'];
// TT-432：介紹頁圖片延後載入, 整頁截圖前先載入並解碼目前語系看得到的圖, 基準才穩定。
const settleImages = page => page.evaluate(async () => { for (const image of document.images) image.loading = 'eager'; await Promise.all([...document.images].filter(image => image.checkVisibility()).map(image => image.decode().catch(() => {}))); });
let server, browser;
try {
  server = await preview({ preview: { host: '127.0.0.1', port: frontendPort, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
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
      // TT-377：英文是獨立網址 /en/, 換語言會換網址並保留查詢參數
      await page.goto(`http://127.0.0.1:${frontendPort}/en${path}?intro=1`);
      await page.locator('html:not(.auth-pending)').waitFor();
      assert.equal(await page.locator('html').getAttribute('lang'), 'en');
      assert.equal(await page.locator('h1').innerText(), titles[index]);
      assert.equal((await page.locator('body').innerText()).replaceAll('繁體中文','').match(/\p{Script=Han}/gu), null, 'All public copy is translated');
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${path} ${width}`);
      await page.evaluate(() => document.fonts.ready);
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
      await settleImages(page);
      await page.screenshot({ path: `${out}/public-en-${index}-${width}.png`, fullPage: true });
      const metrics = designAudit ? await page.evaluate(designAudit.probe) : null, options = { width, allow: ['palette-purple'] };
      findings.push({ path, width, designAudit: !!designAudit, rows: designAudit ? [...designAudit.judge(metrics,options),...designAudit.judgeLayout(await page.evaluate(designAudit.layout),options)] : [] });
      await page.locator('[data-public-locale]').selectOption('zh-TW');
      await page.waitForURL(url => url.pathname === '/' && url.search === '?intro=1');
      await page.locator('html:not(.auth-pending)').waitFor();
      assert.equal(await page.locator('html').getAttribute('lang'), 'zh-Hant');
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
      await settleImages(page);
      await page.screenshot({ path: `${out}/public-zh-${index}-${width}.png`, fullPage: true });
      await page.locator('[data-public-locale]').selectOption('en');
      await page.waitForURL(url => url.pathname === '/en/' && url.search === '?intro=1');
    }
  }
  // 明確選擇會被記住：中文首頁只在選過英文時轉到 /en/
  await page.locator('[data-public-locale]').selectOption('zh-TW'); await page.waitForURL(url => url.pathname === '/');
  await page.reload();
  assert.equal(await page.locator('[data-public-locale]').inputValue(), 'zh-TW');
  assert.equal(new URL(page.url()).pathname, '/');
  await page.goto(`http://127.0.0.1:${frontendPort}` + '/login');
  await page.getByRole('button',{name:'登入',exact:true}).waitFor();
  await page.getByLabel('選擇語言',{exact:true}).selectOption('en');
  await page.goto(`http://127.0.0.1:${frontendPort}` + '/?intro=1');
  await page.waitForURL(url => url.pathname === '/en/' && url.search === '?intro=1');
  await page.getByRole('heading',{name:'Project management',exact:true}).waitFor();
  assert.equal(await page.locator('[data-public-locale]').inputValue(), 'en');
  // 沒有明確選擇時不依瀏覽器語言轉址, 英文瀏覽器開 / 仍是中文頁
  const english = await browser.newPage({ locale: 'en-US' });
  await english.goto(`http://127.0.0.1:${frontendPort}` + '/?intro=1');
  assert.equal(new URL(english.url()).pathname, '/');
  assert.equal(await english.locator('h1').innerText(), '專案管理平台');
  await english.close();
  const fallback = await browser.newPage({ locale: 'ja-JP' });
  await fallback.goto(`http://127.0.0.1:${frontendPort}` + '/?intro=1');
  assert.equal(await fallback.locator('h1').innerText(), '專案管理平台');
  await fallback.close();
  const denied = await browser.newPage({ locale: 'en-US' });
  await denied.addInitScript(() => Object.defineProperty(window, 'localStorage', { get() { throw new DOMException('Denied', 'SecurityError'); } }));
  await denied.goto(`http://127.0.0.1:${frontendPort}` + '/en/?intro=1');
  await denied.locator('[data-public-locale]').selectOption('zh-TW');
  await denied.waitForURL(url => url.pathname === '/');
  assert.equal(await denied.locator('h1').innerText(), '專案管理平台'); await denied.close();
  assert.deepEqual(errors, []);
  await writeFile(`${out}/design-inspection.json`,JSON.stringify(findings,null,2));
  await writeFile(`${out}/browser-summary.json`,JSON.stringify({passed:true, pages:paths.length, widths:[1440,768,390], checks:['bilingual-urls','bilingual-complete-copy','no-overflow','explicit-choice-redirect','no-browser-language-redirect','reload','cross-public-login','storage-denied'], findings},null,2));
  if (process.env.PUBLIC_VISUAL_COMPARE === '1') {
    const baseline = JSON.parse(await readFile('../docs/visual-baselines/public-language-darwin-arm64.json', 'utf8'));
    assert.equal(baseline.approved, true);
    for (const row of baseline.files) assert.equal(createHash('sha256').update(await readFile(`${out}/${row.path}`)).digest('hex'), row.sha256, row.path);
    console.log(`PASS: ${baseline.files.length} approved public screenshots match exactly`);
  }
  console.log('PASS: / and /en/, three widths, URL language switch, explicit choice redirect, no browser-language redirect and no-storage fallback');
} finally { await browser?.close(); await new Promise(resolve => server ? server.httpServer.close(resolve) : resolve()); }
