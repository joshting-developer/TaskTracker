import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
const e2ePort = Number(process.env.E2E_FRONTEND_PORT || 5175); // TT-335：Claude 5198、Codex 5199, 見.agents/skills/tasktracker-test-environments

if (!process.env.E2E_API_URL) throw new Error('請設定隔離 E2E_API_URL');
const before = process.env.E2E_BEFORE === '1';
const root = new URL('..', import.meta.url).pathname;
const artifacts = new URL('../artifacts/bundle/', import.meta.url);
mkdirSync(artifacts, { recursive: true });
let server, browser;
try {
  server = await preview({ root, preview: { host: '127.0.0.1', port: e2ePort, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const failures = [];
  page.on('pageerror', error => failures.push(error.message));
  const base = `http://127.0.0.1:${e2ePort}`;
  await page.goto(`${base}/login`);
  await page.getByRole('heading', { name: '登入工作空間' }).waitFor();
  async function measure() {
    return page.evaluate(() => performance.getEntriesByType('resource').filter(entry => /\/assets\//.test(entry.name)).map(entry => ({ name: entry.name.split('/').pop(), bytes: entry.decodedBodySize, transferBytes: entry.transferSize })));
  }
  const login = await measure();
  if (!before) {
    assert.equal(login.filter(entry => entry.name.endsWith('.js')).length, 1);
    assert(!login.some(entry => /TimeAnalysis|ProjectBoard|MarkdownEditor|pusher|woff|ttf/.test(entry.name)));
    await page.getByLabel('Email', { exact: true }).fill(`unknown-${crypto.randomUUID()}@example.test`);
    await page.getByLabel('密碼', { exact: true }).fill('bundle-test-password');
    await page.getByRole('button', { name: '登入', exact: true }).click();
    await page.locator('.v-alert').waitFor();
    assert(await page.locator('.v-alert svg path').count() > 0, 'Vuetify錯誤圖示使用SVG');
  }
  async function call(operation, input = {}, group = 'rpc') {
    return page.evaluate(async ({ operation, input, group }) => {
      const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
      const result = await (await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
      if (!result.ok) throw new Error(result.error?.message);
      if (result.data?.verificationRequired) { const csrf = await (await fetch('/api/csrf-cookie')).json(); const verified = await fetch('/api/auth/verify-email', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: JSON.stringify({ code: 'local666' }) }); if (!verified.ok) throw new Error('Fixture email verification failed'); result.data = (await verified.json()).data; } return result.data;
    }, { operation, input, group });
  }
  await call('register', { name: 'Bundle', email: `bundle-${crypto.randomUUID()}@example.test`, password: 'bundle-test-password', workspaceName: 'Bundle' }, 'auth');
  const project = await call('createProject', { name: 'Bundle Project', code: 'BUNDLE' });
  await page.reload();
  await page.getByRole('region', { name: '首頁小待辦', exact: true }).waitFor();
  const home = await measure();
  if (!before) {
    assert(home.some(entry => /MarkdownEditor-.*js$/.test(entry.name)), '進入首頁才載入Markdown編輯器');
    const homeRegion = page.getByRole('region', { name: '首頁小待辦', exact: true });
    const quick = homeRegion.getByRole('group', { name: '快速新增待辦編輯器', exact: true });
    await quick.getByLabel('快速新增待辦', { exact: true }).fill('**延遲載入驗證**\n\n- [ ] 保留 Markdown');
    await quick.getByRole('tab', { name: '預覽', exact: true }).click();
    await quick.locator('strong').getByText('延遲載入驗證', { exact: true }).waitFor();
    assert.equal(await quick.locator('input[type=checkbox]:disabled').count(), 1);
    await quick.getByRole('tab', { name: '編輯', exact: true }).click();
    await homeRegion.getByRole('button', { name: '加入待辦', exact: true }).click();
    await homeRegion.locator('.todo-row').filter({ hasText: '延遲載入驗證' }).waitFor();
  }
  await page.goto(`${base}/#/projects/${project.id}/boards`);
  await page.getByRole('heading', { name: '看板列表', exact: true }).waitFor();
  await page.getByRole('heading', { name: 'Default', exact: true }).waitFor();
  const boards = await measure();
  let recovery = 'not-tested';
  if (!before) {
    assert(!boards.some(entry => /TimeAnalysis|ProjectBoard|pusher|woff|ttf/.test(entry.name)), '看板列表不下載看板畫面與WebSocket SDK');
    await page.getByRole('button', { name: '專案', exact: true }).click();
    await page.getByRole('heading', { name: '專案列表', exact: true }).waitFor();
    await page.getByRole('button', { name: '建立專案', exact: true }).click();
    await page.locator('dialog[open] .v-btn svg path').waitFor();
    await page.screenshot({ path: new URL('project-svg.png', artifacts).pathname });
    await page.getByRole('button', { name: '關閉專案表單', exact: true }).click();
    let attempts = 0;
    const pattern = '**/assets/TimeAnalysis-*.js';
    await page.route(pattern, route => { attempts++; return route.abort('failed'); });
    await page.context().setOffline(true);
    await page.locator('.sidebar').getByRole('link', { name: '工時分析', exact: true }).click();
    await page.getByText('無法載入此功能', { exact: true }).waitFor();
    await page.screenshot({ path: new URL('chunk-error.png', artifacts).pathname });
    await page.setViewportSize({ width: 390, height: 900 });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({ path: new URL('chunk-error-mobile.png', artifacts).pathname });
    await page.setViewportSize({ width: 1440, height: 1000 });
    const failedAttempts = attempts;
    await page.waitForTimeout(400);
    assert.equal(attempts, failedAttempts, '顯示失敗後不背景重試');
    assert(failedAttempts <= 2, 'modulepreload與import最多各嘗試一次');
    page.once('dialog', dialog => dialog.dismiss());
    await page.getByRole('button', { name: '重新載入頁面', exact: true }).click();
    await page.getByText('無法載入此功能', { exact: true }).waitFor();
    await page.context().setOffline(false);
    await page.unroute(pattern);
    await page.getByRole('button', { name: '重試載入', exact: true }).click();
    const loaded = await page.getByRole('heading', { name: '工時分析', exact: true }).waitFor({ timeout: 3000 }).then(() => true).catch(() => false);
    if (loaded) recovery = 'retry';
    else {
      page.once('dialog', dialog => dialog.dismiss());
      await page.getByRole('button', { name: '重新載入頁面', exact: true }).click();
      await page.getByText('無法載入此功能', { exact: true }).waitFor();
      page.once('dialog', dialog => dialog.accept());
      await page.getByRole('button', { name: '重新載入頁面', exact: true }).click();
      await page.getByRole('heading', { name: '工時分析', exact: true }).waitFor();
      recovery = 'confirmed-reload';
    }
    await page.screenshot({ path: new URL('analysis-loaded.png', artifacts).pathname });
    assert(!(await measure()).some(entry => /woff|ttf|eot/.test(entry.name)));
  }
  assert.deepEqual(failures, []);
  const result = { before, login, home, boards, recovery };
  writeFileSync(new URL(before ? 'before.json' : 'after.json', artifacts), JSON.stringify(result, null, 2));
  console.log(JSON.stringify(result, null, 2));
} finally {
  await browser?.close();
  await server?.httpServer.close();
}
