// TT-206～208：訪客TopBar、靜態/about/與SEO metadata；只接受隔離API。E2E_PREVIEW=1驗正式build。
import assert from 'node:assert/strict';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createServer, preview } from 'vite';
import { chromium } from 'playwright';
const api = process.env.E2E_API_URL || '';
if (!/^http:\/\/127\.0\.0\.1:13\d{3}$/.test(api)) throw new Error('請設定隔離E2E_API_URL（127.0.0.1:13xxx），不可使用業務資料庫');
const built = process.env.E2E_PREVIEW === '1', folder = `artifacts/guest-pages${built ? '-build' : ''}`, origin = 'http://127.0.0.1:5175';
mkdirSync(folder, { recursive: true });
let server, browser;
const errors = [], measurements = [];
const viewports = [[1440, 900], [768, 1024], [390, 844]];
async function measure(page, name) {
  for (const [width, height] of viewports) {
    await page.setViewportSize({ width, height });
    await page.waitForTimeout(100);
    const row = await page.evaluate(() => {
      const bar = document.querySelector('.guest-topbar');
      const links = [...document.querySelectorAll('.guest-nav a')].map(link => link.getBoundingClientRect().height);
      return { width: innerWidth, overflow: document.documentElement.scrollWidth > innerWidth, bar: bar ? getComputedStyle(bar).display !== 'none' : false, links };
    });
    measurements.push({ page: name, ...row });
    assert.equal(row.overflow, false, `${name} ${width} 無橫向溢出`);
    await page.screenshot({ path: `${folder}/${name}-${width}.png`, fullPage: true, animations: 'disabled', caret: 'hide' });
  }
  await page.setViewportSize({ width: 1440, height: 900 });
}
try {
  const config = { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': api } };
  server = built ? await preview({ preview: config }) : await createServer({ server: config });
  if (!built) await server.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, locale: 'zh-TW', reducedMotion: 'reduce' });
  page.setDefaultTimeout(15000);
  page.on('pageerror', error => errors.push(error.message));

  // 未登入登入頁：訪客TopBar、目前頁、鍵盤順序與觸控高度。
  await page.goto(`${origin}/#/projects`);
  const nav = page.getByRole('navigation', { name: '訪客導覽' });
  await nav.waitFor();
  assert.equal(await nav.getByRole('link', { name: '登入' }).getAttribute('aria-current'), 'page');
  assert.equal(await nav.getByRole('link', { name: '產品介紹' }).getAttribute('aria-current'), null);
  await page.keyboard.press('Tab');
  assert.equal(await page.evaluate(() => document.activeElement.getAttribute('aria-label')), 'TaskTracker 登入');
  await measure(page, 'login');
  assert.ok(measurements.filter(row => row.page === 'login').every(row => row.bar && row.links.every(height => height >= 44)), '登入頁TopBar可見且連結≥44px');

  // 登入後不顯示訪客TopBar，hash深層連結保留。
  const email = `guest-${crypto.randomUUID()}@example.test`;
  await page.getByRole('button', { name: '第一次使用？建立工作空間' }).click();
  await page.getByLabel('你的名字').fill('訪客驗收');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('密碼').fill('guest-pages-password');
  await page.getByLabel('工作空間名稱').fill('訪客驗收');
  await page.getByRole('button', { name: '建立工作空間' }).click();
  await page.locator('.app-shell').waitFor();
  assert.equal(await page.locator('.guest-topbar').count(), 0, '登入後App不顯示訪客TopBar');
  assert.match(page.url(), /#\/projects/, '登入後保留原深層連結');

  if (existsSync('about/index.html')) {
    // 已登入開/about/：正文可讀、TopBar隱藏、CTA改回工作空間。
    await page.goto(`${origin}/about/`);
    await page.getByRole('heading', { level: 1 }).waitFor();
    await page.waitForFunction(() => !document.documentElement.classList.contains('auth-pending'));
    assert.equal(await page.locator('.guest-topbar').isVisible(), false, '已登入隱藏訪客TopBar');
    await page.getByRole('link', { name: '回到工作空間' }).first().waitFor();
    await page.context().clearCookies();

    // 未登入／不執行JS：原始HTML即有title、description、H1與正文。
    const raw = await (await fetch(`${origin}/about/`)).text();
    for (const pattern of [/<title>[^<]*TaskTracker[^<]*<\/title>/, /<meta name="description" content="[^"]{40,}"/, /<h1[^>]*>[^<]+<\/h1>/, /<meta property="og:title"/, /<meta property="og:image"/, /<html lang="zh-Hant"/]) assert.match(raw, pattern);
    assert.doesNotMatch(raw, /@example\.test|workspaceId|api\/v1/, '公開HTML不含私人資料或API');
    if (built) {
      const robots = await (await fetch(`${origin}/robots.txt`)).text();
      assert.match(robots, /Allow: \/about\//); assert.match(robots, /Disallow: \/api\//);
      const sitemap = await (await fetch(`${origin}/sitemap.xml`)).text();
      assert.deepEqual([...sitemap.matchAll(/<loc>([^<]+)<\/loc>/g)].map(match => match[1]), [`${origin}/about/`], 'sitemap只列公開頁');
      const image = await fetch(raw.match(/og:image" content="([^"]+)"/)[1]);
      assert.equal(image.headers.get('content-type'), 'image/png');
      assert.match(await (await fetch(`${origin}/`)).text(), /<meta name="robots" content="noindex, follow"/);
    }
    const noJs = await browser.newPage({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
    await noJs.goto(`${origin}/about/`);
    assert.ok(await noJs.locator('.guest-topbar').isVisible(), '無JS仍可看到訪客導覽');
    assert.ok((await noJs.locator('main').innerText()).length > 200, '無JS仍可讀正文');
    await noJs.close();
    const guest = await browser.newPage({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
    guest.on('pageerror', error => errors.push(error.message));
    await guest.goto(`${origin}/about/`);
    await guest.waitForFunction(() => !document.documentElement.classList.contains('auth-pending'));
    assert.equal(await guest.getByRole('navigation', { name: '訪客導覽' }).getByRole('link', { name: '產品介紹' }).getAttribute('aria-current'), 'page');
    await measure(guest, 'about');
    await guest.close();
  }
  assert.deepEqual(errors, []);
  writeFileSync(`${folder}/measurements.json`, JSON.stringify(measurements, null, 2));
  console.log(`PASS ${built ? 'build' : 'dev'}: guest topbar, login deep link, about page and metadata`);
} finally {
  await browser?.close();
  await server?.close();
}
