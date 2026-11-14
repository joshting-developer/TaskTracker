import { verifyFixtureInBrowser } from './fixtures/verification.js';
// TT-206～208、297、298：首頁產品介紹、/login、/app/入口、舊hash相容、訪客TopBar與SEO metadata；只接受隔離API。E2E_PREVIEW=1驗正式build。
import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createServer, preview } from 'vite';
import { chromium } from 'playwright';
const api = process.env.E2E_API_URL || '';
if (!/^http:\/\/127\.0\.0\.1:13\d{3}$/.test(api)) throw new Error('請設定隔離E2E_API_URL（127.0.0.1:13xxx），不可使用業務資料庫');
const built = process.env.E2E_PREVIEW === '1', folder = `artifacts/guest-pages${built ? '-build' : ''}`, port = Number(process.env.E2E_FRONTEND_PORT || 5175), origin = `http://127.0.0.1:${port}`;
mkdirSync(folder, { recursive: true });
// TT-336：開發模式沒設VITE_SITE_ORIGIN時用目前測試埠, 分享metadata才會產生；E2E_PREVIEW驗正式build不補, 照build結果判斷
if (!built && !process.env.VITE_SITE_ORIGIN) process.env.VITE_SITE_ORIGIN = origin;
let server, browser;
const errors = [], measurements = [];
const viewports = [[1440, 900], [768, 1024], [390, 844]];
const pathOf = page => { const url = new URL(page.url()); return url.pathname + url.search + url.hash; };
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
  const config = { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': api } };
  server = built ? await preview({ root: new URL('..', import.meta.url).pathname, preview: config }) : await createServer({ root: new URL('..', import.meta.url).pathname, server: config });
  if (!built) await server.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, locale: 'zh-TW', reducedMotion: 'reduce' });
  page.setDefaultTimeout(15000);
  page.on('pageerror', error => errors.push(error.message));

  // 舊分享連結/#/projects：首頁先轉/app/，未登入再落到/login並保留hash。
  await page.goto(`${origin}/#/projects`);
  const nav = page.getByRole('navigation', { name: '訪客導覽' });
  await nav.waitFor();
  await page.waitForURL(url => url.pathname === '/login');
  assert.equal(pathOf(page), '/login#/projects', '舊hash連結轉到/login並保留深層連結');
  assert.equal(await nav.getByRole('link', { name: '登入' }).getAttribute('aria-current'), 'page');
  assert.equal(await nav.getByRole('link', { name: '產品介紹' }).getAttribute('href'), '/', '登入頁有明確回介紹頁的連結');
  assert.equal(await nav.getByRole('link', { name: /建立工作空間/ }).getAttribute('href'), '/login?mode=register');
  await page.keyboard.press('Tab');
  assert.equal(await page.evaluate(() => document.activeElement.getAttribute('aria-label')), 'TaskTracker 首頁');
  await measure(page, 'login');
  assert.ok(measurements.filter(row => row.page === 'login').every(row => row.bar && row.links.length === 3 && row.links.every(height => height >= 44)), '登入頁TopBar三個連結可見且≥44px');

  // 註冊後同一頁換到/app/並進入原深層連結，不顯示訪客TopBar。
  const email = `guest-${crypto.randomUUID()}@example.test`;
  await page.getByRole('button', { name: '第一次使用？建立工作空間' }).click();
  await page.getByLabel('你的名字').fill('訪客驗收');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('密碼').fill('guest-pages-password');
  await page.getByLabel('工作空間名稱').fill('訪客驗收');
  await page.getByRole('button', { name: '建立工作空間' }).click();
  await verifyFixtureInBrowser(page);
  await page.locator('.app-shell').waitFor();
  assert.equal(await page.locator('.guest-topbar').count(), 0, '登入後App不顯示訪客TopBar');
  assert.equal(pathOf(page), '/app/#/projects', '登入後在/app/保留原深層連結');

  // 已登入：首頁與/login都直接進工作空間。
  await page.goto(`${origin}/`);
  await page.locator('.app-shell').waitFor();
  assert.equal(new URL(page.url()).pathname, '/app/', '已登入開首頁進工作空間');
  // TT-304：帳號選單有「產品介紹」；從那裡進來（?intro=1）已登入也留在介紹頁, 上方選單只剩「回到工作區」。
  await page.goto(`${origin}/app/#/`);
  await page.locator('.app-shell').waitFor();
  await page.getByRole('button', { name: '帳號選單', exact: true }).click();
  const introItem = page.getByRole('menuitem', { name: '產品介紹', exact: true });
  assert.equal(await introItem.getAttribute('href'), '/?intro=1');
  await introItem.click();
  await page.waitForURL(url => url.pathname === '/' && url.search === '?intro=1');
  await page.waitForFunction(() => !document.documentElement.classList.contains('auth-pending'));
  await page.waitForTimeout(300);
  assert.equal(new URL(page.url()).pathname, '/', '從帳號選單進來不會被轉走');
  const memberNav = page.getByRole('navigation', { name: '訪客導覽' });
  assert.ok(await page.locator('.guest-topbar').isVisible(), '已登入看介紹頁也有上方選單');
  assert.equal(await memberNav.getByRole('link', { name: '回到工作區' }).getAttribute('href'), '/app/');
  assert.equal(await memberNav.getByRole('link', { name: '登入', exact: true }).count(), 0, '已登入不顯示登入');
  assert.equal(await page.getByRole('link', { name: '登入 TaskTracker' }).count(), 0, '已登入不顯示建立工作空間');
  await memberNav.getByRole('link', { name: '回到工作區' }).click();
  await page.locator('.app-shell').waitFor();
  await page.goto(`${origin}/`);
  await page.locator('.app-shell').waitFor();
  assert.equal(new URL(page.url()).pathname, '/app/', '直接開首頁仍然自動轉進工作空間');

  // 已登入點舊分享連結：首頁兩段轉址不能互搶, #後的深層連結要保留（重複幾次抓競態）。
  for (let round = 0; round < 3; round++) {
    await page.goto(`${origin}/#/projects`);
    await page.locator('.app-shell').waitFor();
    await page.waitForTimeout(300);
    assert.equal(pathOf(page), '/app/#/projects', '已登入開舊hash連結保留深層連結');
  }
  await page.goto(`${origin}/login`);
  await page.locator('.app-shell').waitFor();
  assert.equal(new URL(page.url()).pathname, '/app/', '已登入開/login進工作空間');

  // 登出後瀏覽器仍留登入提示：首頁→/app/→/login，提示清除後首頁正常顯示，不會循環。
  await page.evaluate(async () => { const { csrfToken } = await (await fetch('/api/csrf-cookie')).json(); await fetch('/api/auth/logout', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: '{}' }); });
  await page.goto(`${origin}/`);
  await page.waitForURL(url => url.pathname === '/login');
  await page.getByRole('heading', { name: '登入工作空間' }).waitFor();
  assert.equal(await page.evaluate(() => localStorage.getItem('tasktracker:signed-in')), null, '未登入時清除提示');
  await page.goto(`${origin}/`);
  await page.getByRole('heading', { level: 1 }).waitFor();
  await page.waitForFunction(() => !document.documentElement.classList.contains('auth-pending'));
  assert.equal(new URL(page.url()).pathname, '/', '未登入首頁停留在產品介紹');
  assert.equal(await nav.getByRole('link', { name: '登入' }).getAttribute('aria-current'), null, '首頁沒有目前頁標示');
  assert.equal(await nav.getByRole('link', { name: '產品介紹' }).count(), 0, '首頁就是介紹, TopBar不放產品介紹');
  await page.getByRole('link', { name: '登入', exact: true }).first().click();
  await page.waitForURL(url => url.pathname === '/login');
  await page.getByRole('heading', { name: '登入工作空間' }).waitFor();

  // 首頁進入登入, 只有伺服器開放公開註冊時才可從登入頁建立工作空間。
  await page.goto(`${origin}/`);
  await page.waitForFunction(() => !document.documentElement.classList.contains('auth-pending'));
  await nav.getByRole('link', { name: '登入', exact: true }).click();
  await page.waitForURL(url => url.pathname === '/login');
  await page.getByRole('button', { name: '第一次使用？建立工作空間', exact: true }).click();
  await page.getByRole('heading', { name: '建立工作空間' }).waitFor();
  await page.getByLabel('工作空間名稱').waitFor();

  // 舊邀請信連結/#/invite?token=…：落到/login的邀請表單。
  await page.goto(`${origin}/#/invite?token=not-a-real-token`);
  await page.getByRole('heading', { name: '工作空間邀請' }).waitFor();
  assert.equal(pathOf(page), '/login#/invite?token=not-a-real-token', '邀請連結保留token');

  // 未登入／不執行JS：首頁原始HTML即有title、description、H1與正文；舊/about轉首頁。
  const raw = await (await fetch(`${origin}/`)).text();
  for (const pattern of [/<title>[^<]*TaskTracker[^<]*<\/title>/, /<meta name="description" content="[^"]{40,}"/, /<h1[^>]*>[^<]+<\/h1>/, /<meta property="og:title"/, /<meta property="og:image"/, /<html lang="zh-Hant"/, /<meta name="robots" content="index, follow"/]) assert.match(raw, pattern);
  assert.doesNotMatch(raw, /@example\.test|workspaceId|api\/v1/, '公開HTML不含私人資料或API');
  for (const path of ['/about', '/about/']) {
    const moved = await fetch(`${origin}${path}`, { redirect: 'manual' });
    assert.deepEqual([moved.status, moved.headers.get('location')], [301, '/'], `${path}轉首頁`);
  }
  for (const path of ['/login', '/app/']) assert.match(await (await fetch(`${origin}${path}`)).text(), /<meta name="robots" content="noindex, follow"/, `${path} noindex`);
  if (built) {
    const robots = await (await fetch(`${origin}/robots.txt`)).text();
    assert.match(robots, /Allow: \/\n/); assert.match(robots, /Disallow: \/api\//); assert.match(robots, /Disallow: \/app\//);
    const sitemap = await (await fetch(`${origin}/sitemap.xml`)).text();
    assert.deepEqual([...sitemap.matchAll(/<loc>([^<]+)<\/loc>/g)].map(match => match[1]), ['/', '/features/tasks/', '/features/collaboration/', '/features/time/', '/features/integrations/'].map(path => origin + path), 'sitemap列首頁與四個公開功能頁');
    assert.match(raw, new RegExp(`<link rel="canonical" href="${origin}/" />`));
    const image = await fetch(raw.match(/og:image" content="([^"]+)"/)[1]);
    assert.equal(image.headers.get('content-type'), 'image/png');
  }
  const noJs = await browser.newPage({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
  await noJs.goto(`${origin}/`);
  assert.ok(await noJs.locator('.guest-topbar').isVisible(), '無JS仍可看到訪客導覽');
  assert.ok((await noJs.locator('main').innerText()).length > 200, '無JS仍可讀正文');
  await noJs.close();
  const guest = await browser.newPage({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
  guest.on('pageerror', error => errors.push(error.message));
  await guest.goto(`${origin}/`);
  await guest.waitForFunction(() => !document.documentElement.classList.contains('auth-pending'));
  await measure(guest, 'landing');
  await guest.close();
  assert.deepEqual(errors, []);
  writeFileSync(`${folder}/measurements.json`, JSON.stringify(measurements, null, 2));
  console.log(`PASS ${built ? 'build' : 'dev'}: landing at /, /login, /app/, legacy hash + invite redirect, stale hint, metadata`);
} finally {
  await browser?.close();
  await server?.close();
}
