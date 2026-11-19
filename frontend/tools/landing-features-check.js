// TT-355：靜態功能分頁、無JS閱讀與正式build入口；TT-435 首頁功能區改為鍵盤可操作的分頁。
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
  const page = await browser.newPage({ locale: 'zh-TW', reducedMotion: 'reduce' });
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
      // TT-432：首頁 header 改深色, 功能頁維持淺色；兩種都要符合 4.5 對比。
      const contrast = await page.locator('.product-nav a').first().evaluate(el => {
        const rgb = value => value.match(/\d+(\.\d+)?/g).slice(0, 3).map(Number);
        const lum = ([r, g, b]) => [r, g, b].map(v => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }).reduce((sum, v, i) => sum + v * [0.2126, 0.7152, 0.0722][i], 0);
        const [a, b] = [lum(rgb(getComputedStyle(el).color)), lum(rgb(getComputedStyle(el.closest('.guest-topbar')).backgroundColor))];
        return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
      });
      assert.ok(contrast >= 4.5, `header navigation contrast ${contrast.toFixed(2)}`);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${path} ${width} overflow`);
      const name = path === '/' ? 'home' : path.split('/')[2];
      await page.screenshot({ path: `${folder}/${name}-${width}.png`, fullPage: true, animations: 'disabled' });
      results.push({ path, width, title });
    }
    if (path !== '/') assert.equal(await page.locator('.product-nav [aria-current="page"]').count(), 1);
  }
  // TT-435：功能區改成分頁＋3D 截圖堆疊, 方向鍵切換、Home／End 跳頭尾, 首屏功能列會切到對應分頁。
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(origin + '/');
  const tabs = page.getByRole('tab');
  const selected = () => page.locator('[role="tab"][aria-selected="true"]').innerText();
  assert.equal(await tabs.count(), 4);
  assert.equal(await page.locator('[role="tabpanel"]:visible').count(), 1);
  assert.match(await selected(), /任務與看板/);
  await page.getByRole('tab', { name: /工時分析/ }).click();
  assert.match(await selected(), /工時分析/);
  assert.equal(await page.locator('#time').isVisible(), true);
  await page.keyboard.press('ArrowDown');
  assert.match(await selected(), /API 與權限/);
  assert.equal(await page.evaluate(() => document.activeElement.getAttribute('aria-selected')), 'true');
  await page.keyboard.press('ArrowDown');
  assert.match(await selected(), /任務與看板/);
  await page.keyboard.press('End');
  assert.match(await selected(), /API 與權限/);
  await page.keyboard.press('Home');
  assert.match(await selected(), /任務與看板/);
  assert.equal(await page.locator('.showcase-rig > .shot[aria-hidden="false"]').count(), 1);
  await page.locator('.feature-rail a[href="#collaboration"]').click();
  await page.locator('#collaboration-tab[aria-selected="true"]').waitFor();
  assert.equal(await page.locator('#collaboration').isVisible(), true);
  // 選中的截圖不可以歪, 文字才看得清楚
  assert.equal(await page.locator('.showcase-rig > .shot.is-front').evaluate(el => getComputedStyle(el).transform), 'none');
  for (const link of await page.locator('[data-showcase] a[href^="/"]').all()) assert.equal((await fetch(origin + await link.getAttribute('href'))).status, 200);
  const noJs = await browser.newPage({ locale: 'zh-TW', javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
  await noJs.goto(origin + '/');
  assert.equal(await noJs.locator('[data-aspect]:visible').count(), 4);
  assert.equal(await noJs.locator('[data-aspect] img:visible').count(), 4);
  assert.equal(await noJs.locator('[role="tab"]').count(), 0);
  assert.ok((await noJs.locator('main').innerText()).length > 500);
  await noJs.close();
  // TT-435：大螢幕且沒有減少動態時改成首屏故事（參考 JT）：首屏固定, 往下捲換功能說明與截圖。
  const motion = await browser.newPage({ locale: 'zh-TW', reducedMotion: 'no-preference', viewport: { width: 1440, height: 900 } });
  await motion.route('**/api/**', route => route.fulfill({ status: 401, contentType: 'application/json', body: '{"ok":false}' }));
  await motion.goto(origin + '/');
  await motion.waitForFunction(() => !document.documentElement.classList.contains('auth-pending'));
  assert.equal(await motion.locator('.hero.is-story').count(), 1);
  assert.equal(await motion.locator('#features').isVisible(), false, '功能區內容搬進首屏, 不重複顯示');
  assert.equal(await motion.locator('.hero-stage .guest-topbar').count(), 1);
  const storyAt = async fraction => {
    await motion.evaluate(fraction => { const h = document.querySelector('.hero'), s = document.querySelector('.hero-stage'); scrollTo({ top: h.getBoundingClientRect().top + scrollY + (h.offsetHeight - s.offsetHeight) * fraction, behavior: 'instant' }); }, fraction);
    await motion.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    assert.equal(await motion.locator('.hero-stage').evaluate(el => el.getBoundingClientRect().top), 0);
    return motion.evaluate(() => document.querySelector('.feature-rail a.is-active')?.getAttribute('href') || 'intro');
  };
  assert.deepEqual([await storyAt(0.05), await storyAt(0.26), await storyAt(0.43), await storyAt(0.61), await storyAt(0.78), await storyAt(0.26)], ['intro', '#tasks', '#collaboration', '#time', '#integrations', '#tasks']);
  assert.equal(await motion.locator('.hero-chapters #tasks').isVisible(), true);
  assert.equal(await motion.locator('.hero-rig > .shot.is-current').count(), 1);
  await motion.locator('.feature-rail a[href="#time"]').click();
  await motion.waitForFunction(() => document.querySelector('.feature-rail a.is-active')?.getAttribute('href') === '#time');
  await motion.locator('.hero-chapters #time').waitFor();
  assert.equal(await motion.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
  // 視窗縮小就回到一般捲動：選單、功能區與分頁都放回原位
  await motion.setViewportSize({ width: 1024, height: 900 });
  await motion.locator('.hero:not(.is-story)').waitFor({ state: 'attached' });
  assert.equal(await motion.locator('body > .guest-topbar').count(), 1);
  assert.equal(await motion.locator('#features').isVisible(), true);
  assert.equal(await motion.locator('[data-showcase] [role="tabpanel"]').count(), 4);
  assert.equal(await motion.locator('[data-showcase] [role="tabpanel"]:visible').count(), 1);
  await motion.close();
  // Already signed in: feature deep links stay readable, primary links return to the app.
  await page.route('**/api/**', route => route.fulfill({ contentType: 'application/json', body: route.request().url().endsWith('csrf-cookie') ? '{"csrfToken":"synthetic"}' : '{"ok":true}' }));
  await page.goto(origin + '/features/tasks/');
  await page.getByRole('link', { name: '回到工作區', exact: true }).waitFor();
  assert.equal(new URL(page.url()).pathname, '/features/tasks/');
  assert.deepEqual(errors, []);
  writeFileSync(`${folder}/result.json`, JSON.stringify({ passed: true, results, keyboard: true, noJs: true, syntheticAuth: true }, null, 2));
  console.log('PASS: 5 static pages × 3 widths, unique SEO, links, keyboard feature tabs, hero scroll story, no JS, signed-in feature navigation');
} finally { await browser?.close(); await server?.close(); }
