// TT-329：側邊欄改成連結。一般點擊在目前分頁切換且不整頁重載；Ctrl／Cmd點擊交給瀏覽器開新分頁, 新分頁直接到該頁（我的任務靠?scope=mine）；未儲存草稿時一般點擊照樣先確認。
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
import { openProjectList } from './navigation.js';
if (!process.env.E2E_API_URL) throw new Error('請設定隔離E2E_API_URL，不可使用業務資料庫');
let server, browser;
try {
  server = await preview({ preview: { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage(), errors = [];
  page.on('pageerror', error => errors.push(error.message)); page.setDefaultTimeout(10000);
  await page.goto('http://127.0.0.1:5175/login');
  await page.getByRole('button', { name: '第一次使用？建立工作空間', exact: true }).click();
  await page.getByLabel('你的名字', { exact: true }).fill('側邊欄檢查者');
  await page.getByLabel('Email', { exact: true }).fill(`sidebar-${crypto.randomUUID()}@example.test`);
  await page.getByLabel('密碼', { exact: true }).fill('sidebar-test-only-password');
  await page.getByLabel('工作空間名稱', { exact: true }).fill('側邊欄檢查');
  await page.getByRole('button', { name: '建立工作空間', exact: true }).click();
  // TT-319註冊驗證：local／testing可用local666
  const code = page.getByLabel('驗證碼', { exact: true });
  await Promise.race([code.waitFor(), page.getByRole('link', { name: '專案列表', exact: true }).waitFor()]);
  if (await code.isVisible()) { await code.fill('local666'); await page.getByRole('button', { name: '驗證並登入', exact: true }).click(); }
  await openProjectList(page);
  await page.getByRole('button', { name: '建立專案', exact: true }).click();
  const dialog = page.getByRole('dialog', { name: '建立專案', exact: true });
  await dialog.getByLabel(/專案名稱/).fill('側邊欄專案'); await dialog.getByLabel(/專案代碼/).fill('SIDE');
  await dialog.getByRole('button', { name: '建立專案', exact: true }).click();
  await page.getByRole('button', { name: '進入專案：側邊欄專案', exact: true }).click();
  await page.waitForURL(/#\/projects\/[^/?]+$/);
  const project = page.url().match(/#\/projects\/([^/?]+)/)[1];
  const nav = name => page.getByRole('link', { name, exact: true });

  // 每個項目都是真的連結, 網址就是該頁的hash
  const expected = { 任務列表: `#/projects/${project}`, 任務看板: `#/projects/${project}/boards`, 我的任務: `#/projects/${project}?scope=mine`, 工時月曆: `#/projects/${project}/time`, 狀態設定: `#/projects/${project}/settings`, 專案成員與權限: `#/projects/${project}/members` };
  for (const [name, href] of Object.entries(expected)) assert.equal(await nav(name).getAttribute('href'), href, `${name}的網址`);
  assert.equal(await nav('通知預覽').getAttribute('href'), `#/projects/${project}/notifications`);

  // 外觀不變：同內容的原本<button class="nav-item">放在同一位置, 尺寸與樣式要一樣
  const sameLook = await page.evaluate(() => {
    const link = [...document.querySelectorAll('a.nav-item')].find(item => item.textContent.trim() === '任務看板');
    const button = document.createElement('button'); button.className = link.className; button.innerHTML = link.innerHTML;
    link.after(button);
    const keys = ['display', 'fontSize', 'fontWeight', 'lineHeight', 'color', 'backgroundColor', 'paddingTop', 'paddingLeft', 'gap', 'borderRadius', 'textAlign', 'textDecorationLine', 'cursor'];
    const pick = element => { const style = getComputedStyle(element), box = element.getBoundingClientRect(); return { ...Object.fromEntries(keys.map(key => [key, style[key]])), width: box.width, height: box.height, svg: element.querySelector('svg').getBoundingClientRect().width }; };
    const result = { link: pick(link), button: pick(button) };
    button.remove(); return result;
  });
  assert.deepEqual(sameLook.link, sameLook.button, '連結的外觀要跟原本的按鈕一樣');

  // 一般點擊：目前分頁切換, 不整頁重新載入
  await page.evaluate(() => { window.__sameDocument = true; });
  await nav('任務看板').click();
  await page.waitForURL(/\/boards$/);
  assert.equal(await page.evaluate(() => window.__sameDocument), true, '一般點擊不能整頁重新載入');
  assert.equal(await nav('任務看板').getAttribute('aria-current'), 'page');

  // Ctrl／Cmd點擊「我的任務」開新分頁, 新分頁直接是我的任務
  const [tab] = await Promise.all([context.waitForEvent('page'), nav('我的任務').click({ modifiers: ['ControlOrMeta'] })]);
  await tab.waitForLoadState();
  await tab.locator('.nav-item.active', { hasText: '我的任務' }).waitFor();
  assert.equal(await tab.locator('.nav-item.active').count(), 1, '新分頁只有我的任務是選中狀態');
  assert.ok(page.url().endsWith('/boards'), '原分頁不跟著跳');
  await tab.close();

  // 未儲存草稿（狀態設定頁新增一個狀態但不儲存）：一般點擊側邊欄要先確認, 取消後留在原頁
  await nav('狀態設定').click();
  await page.waitForURL(/\/settings$/);
  await page.getByRole('button', { name: '新增狀態', exact: true }).click();
  let asked = false;
  page.once('dialog', dialogEvent => { asked = true; dialogEvent.dismiss(); });
  await nav('工時月曆').click();
  await page.waitForTimeout(300);
  assert.equal(asked, true, '有未儲存內容時要先確認');
  assert.ok(page.url().endsWith('/settings'), '取消後不能切換頁面');
  assert.equal(await nav('狀態設定').getAttribute('aria-current'), 'page');

  mkdirSync('artifacts/tt329', { recursive: true });
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${width}寬不能出現橫向捲動`);
    await page.screenshot({ path: `artifacts/tt329/sidebar-${width}.png` });
  }
  assert.deepEqual(errors, []);
  console.log('PASS: sidebar items are links with page hashes; plain click stays in-document; Ctrl/Cmd click opens a new tab on the same page (scope=mine); unsaved drafts still ask before leaving');
} finally {
  await browser?.close();
  await new Promise(resolve => server ? server.httpServer.close(resolve) : resolve());
}
