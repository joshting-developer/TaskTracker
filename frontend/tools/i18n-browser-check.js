import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
import { fixtureResponse } from './fixtures/verification.js';
import en from '../src/i18n/en.json' with { type: 'json' };
const port = Number(process.env.E2E_FRONTEND_PORT || 5175);
assert.equal(process.env.E2E_ISOLATED, '1');
assert.ok(process.env.E2E_API_URL);
const out = 'artifacts/i18n'; mkdirSync(out, { recursive: true });
let server, browser;
const tr = key => en[key];
try {
  server = await preview({ preview: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const context = await browser.newContext({ locale: 'en-US', viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage(), errors = [];
  page.on('pageerror', error => errors.push(error.message)); page.setDefaultTimeout(15000);
  const base = `http://127.0.0.1:${port}`;
  await page.goto(base + '/login');
  await page.getByRole('button', { name: tr('登入'), exact: true }).waitFor().catch(async error => { await page.screenshot({ path: `${out}/login-failure.png` }); console.error(await page.locator('body').innerText(), errors); throw error; });
  assert.equal(await page.locator('html').getAttribute('lang'), 'en');
  await page.getByLabel(tr('選擇語言'), { exact: true }).selectOption('zh-TW');
  await page.getByRole('button', { name: '登入', exact: true }).waitFor();
  await page.getByLabel('選擇語言', { exact: true }).selectOption('en');
  await page.reload(); await page.getByRole('button', { name: tr('登入'), exact: true }).waitFor();
  async function call(operation, input = {}, group = 'rpc') {
    const csrf = await (await context.request.get(base + '/api/csrf-cookie')).json();
    const response = await context.request.post(`${base}/api/${group}/${operation}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.ok(response.ok(), await response.text());
    return (await fixtureResponse(context.request, response, input)).data;
  }
  const stamp = crypto.randomUUID();
  await call('register', { name: '中文使用者', email: `i18n-${stamp}@example.test`, password: 'isolated-i18n-password', workspaceName: '中文工作區' }, 'auth');
  const project = await call('createProject', { name: '使用者自己的中文專案', code: 'LANG' });
  const issue = await call('createIssue', { projectId: project.id, title: '保留中文卡片標題', description: '使用者的說明不翻譯', type: 'task' });
  await page.goto(base + '/app/#/'); await page.reload(); await page.locator('.app-shell').waitFor();
  const account = () => page.getByRole('button', { name: tr('帳號選單'), exact: true });
  async function language(id) {
    await page.locator('.topbar .locale-picker select').selectOption(id);
    assert.equal(await page.locator('html').getAttribute('lang'), id);
  }
  await account().waitFor();
  await page.getByRole('link', { name: tr('紙條'), exact: true }).click();
  await page.getByRole('tab', { name: tr('建立'), exact: true }).click();
  const draft = '尚未儲存的中文 **Markdown**';
  await page.getByLabel(tr('紙條內容'), { exact: true }).fill(draft);
  await page.getByLabel(tr('紙條內容'), { exact: true }).evaluate(el => { window.__i18nEditor = el; window.__i18nDocument = document; });
  const calls = []; const track = request => { if (new URL(request.url()).pathname.startsWith('/api/')) calls.push(request.url()); };
  page.on('request', track);
  await language('zh-TW');
  assert.equal(await page.getByLabel('紙條內容', { exact: true }).inputValue(), draft);
  assert.ok(await page.getByLabel('紙條內容', { exact: true }).evaluate(el => el === window.__i18nEditor && document === window.__i18nDocument));
  await language('en');
  assert.equal(await page.getByLabel(tr('紙條內容'), { exact: true }).inputValue(), draft);
  page.off('request', track); assert.deepEqual(calls, [], 'Changing language does not fetch workspace data');
  await page.getByLabel(tr('紙條內容'), { exact: true }).fill('');
  await page.goto(`${base}/app/#/projects/${project.id}/issues/${issue.id}?detail=1`);
  await page.getByRole('heading', { name: '保留中文卡片標題', exact: true }).waitFor();
  await page.getByText('使用者的說明不翻譯', { exact: true }).waitFor();
  await page.getByRole('tab', { name: new RegExp('^' + tr('工時')) }).click();
  await page.getByRole('button', { name: tr('填報工時'), exact: true }).click();
  await page.getByLabel(tr('投入小時'), { exact: true }).fill('0.5');
  await page.getByLabel(tr('工時說明'), { exact: true }).fill('中文工時內容');
  await page.locator('.time-entry-drawer').getByRole('button', { name: tr('登記工時'), exact: true }).click();
  await page.locator('.time-entry-drawer').waitFor({ state: 'detached' });
  await page.getByText('中文工時內容', { exact: true }).waitFor();
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1000 });
    if (width === 390) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    const tabs = page.getByRole('tablist', { name: tr('任務側欄'), exact: true });
    if (await tabs.isVisible()) {
      await tabs.getByRole('tab').first().focus(); await page.keyboard.press('End');
      const last = tabs.getByRole('tab').last(); assert.ok(await last.evaluate(el => el === document.activeElement));
      // Compare rendered CSS pixels; scroll offsets round fractional glyph widths.
      await page.waitForFunction(() => { const item = document.activeElement, list = item.closest('[role=tablist]'); const a = item.getBoundingClientRect(), b = list.getBoundingClientRect(); return Math.round(a.x) >= Math.round(b.x) - 1 && Math.round(a.right) <= Math.round(b.right) + 1; });
      await tabs.getByRole('tab').first().click();
    }
    await page.screenshot({ path: `${out}/detail-en-${width}.png`, fullPage: true });
    await account().click();
    const menu = page.getByRole('menu', { name: tr('帳號選項'), exact: true });
    const box = await menu.boundingBox(); assert.ok(box.x >= 0 && box.x + box.width <= width + 1);
    await menu.screenshot({ path: `${out}/menu-en-${width}.png` });
    await page.keyboard.press('Escape');
  }
  await page.setViewportSize({ width: 1440, height: 1000 });
  await language('zh-TW'); await page.reload();
  await page.getByRole('button', { name: '帳號選單', exact: true }).waitFor();
  assert.equal(await page.locator('html').getAttribute('lang'), 'zh-TW');
  await page.getByRole('heading', { name: '保留中文卡片標題', exact: true }).waitFor();
  await language('en'); await page.reload(); await account().waitFor();
  assert.equal(await page.locator('html').getAttribute('lang'), 'en');
  // Explicit browser language stays consistent across public pages and accounts; business data stays isolated.
  await call('logout', {}, 'auth'); await page.reload();
  await page.getByRole('button', { name: tr('登入'), exact: true }).waitFor();
  await page.getByLabel(tr('選擇語言'), { exact: true }).selectOption('zh-TW');
  await call('register', { name: '第二使用者', email: `i18n-other-${stamp}@example.test`, password: 'isolated-i18n-password', workspaceName: '第二工作區' }, 'auth');
  await page.goto(base + '/app/#/'); await page.reload(); await page.locator('.app-shell').waitFor();
  assert.equal(await page.locator('html').getAttribute('lang'), 'zh-TW');
  await call('logout', {}, 'auth');
  await call('login', { email: `i18n-${stamp}@example.test`, password: 'isolated-i18n-password' }, 'auth');
  await page.reload(); await page.getByRole('button', { name: '帳號選單', exact: true }).waitFor();
  assert.equal(await page.locator('html').getAttribute('lang'), 'zh-TW');
  const fallback = await browser.newPage({ locale: 'ja-JP' }); await fallback.goto(base + '/login');
  await fallback.getByRole('button', { name: '登入', exact: true }).waitFor();
  assert.equal(await fallback.locator('html').getAttribute('lang'), 'zh-TW');
  await fallback.close();
  assert.deepEqual(errors, []);
  console.log('PASS: browser defaults/fallback, bilingual auth, reload and shared explicit browser preference, no-remount/no-fetch draft switch, unchanged Chinese user content, English detail/time entry and three viewport menus');
} finally { await browser?.close(); await new Promise(resolve => server ? server.httpServer.close(resolve) : resolve()); }
