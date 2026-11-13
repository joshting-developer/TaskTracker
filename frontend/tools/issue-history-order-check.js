import { verifyFixtureInBrowser } from './fixtures/verification.js';
// TT-302：詳細頁直接看到建立者／建立時間與最後異動；異動紀錄新的在上, 「載入更多」在下面接更舊的, 詳細頁和快速編輯一致。
import assert from 'node:assert/strict';
import { preview } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
if (!process.env.E2E_API_URL) throw new Error('請設定隔離E2E_API_URL，不可使用業務資料庫');
const port = Number(process.env.E2E_FRONTEND_PORT || 5175), base = `http://127.0.0.1:${port}`;
const artifacts = new URL('../../artifacts/tt302/', import.meta.url); mkdirSync(artifacts, { recursive: true });
let server, browser;
try {
  // 用正式build（先npm run build）＋preview, 跟上線的入口一致。
  server = await preview({ preview: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const context = await browser.newContext(), page = await context.newPage(), errors = [];
  page.setDefaultTimeout(30000); page.on('pageerror', error => errors.push(error.message));
  // 先從畫面建立工作空間登入, 測試資料再從同一個頁面送RPC, 跟真的使用者同一個Session。
  async function call(operation, input) {
    const result = await page.evaluate(async ([operation, input]) => {
      const csrf = await (await fetch('/api/csrf-cookie', { credentials: 'same-origin' })).json();
      const response = await fetch(`/api/rpc/${operation}`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) });
      return { status: response.status, body: await response.json() };
    }, [operation, input]);
    assert.equal(result.status, 200, `Fixture ${operation}`); return result.body.data;
  }
  await page.goto(`${base}/login`);
  await page.getByRole('button', { name: '第一次使用？建立工作空間', exact: true }).click();
  await page.getByLabel('你的名字', { exact: true }).fill('歷程測試者');
  await page.getByLabel('Email', { exact: true }).fill(`history-${crypto.randomUUID()}@example.test`);
  await page.getByLabel('密碼', { exact: true }).fill('isolated-history-browser');
  await page.getByLabel('工作空間名稱', { exact: true }).fill('異動排序');
  await page.getByRole('button', { name: '建立工作空間', exact: true }).click();
  await verifyFixtureInBrowser(page);
  await page.locator('.app-shell').waitFor();
  const project = await call('createProject', { name: '異動排序專案', code: 'HIS' });
  let issue = await call('createIssue', { projectId: project.id, title: '排序測試 起點' });
  for (let n = 0; n < 21; n++) issue = await call('updateIssue', { id: issue.id, expectedRevision: issue.revision, title: `排序測試 ${n}` });

  await page.goto(`${base}/app/#/projects/${project.id}`); await page.reload();
  await page.getByRole('button', { name: '排序測試 20', exact: true }).click();
  const properties = page.locator('#detail-properties');
  await properties.locator('dt', { hasText: '建立' }).waitFor();
  assert.match(await properties.locator('div', { has: page.locator('dt', { hasText: /^建立$/ }) }).locator('dd').innerText(), /^歷程測試者\n?\d{4}\//);
  assert.match(await properties.locator('div', { has: page.locator('dt', { hasText: '最後異動' }) }).locator('dd').innerText(), /^歷程測試者\n?\d{4}\//);

  async function checkOrder(scope) {
    await scope.getByRole('tab', { name: '異動紀錄 22', exact: true }).click();
    const entries = scope.locator('.history-entry');
    await entries.nth(19).waitFor();
    assert.match(await entries.first().innerText(), /排序測試 19 → 排序測試 20/, '最新的在最上面');
    await scope.getByRole('button', { name: '載入更多', exact: true }).click();
    await entries.nth(21).waitFor();
    assert.match(await entries.nth(21).innerText(), /建立了任務/, '載入更多把更舊的接在下面, 建立紀錄在最後');
    assert.match(await entries.nth(19).innerText(), /排序測試 0 → 排序測試 1/);
    const list = await entries.nth(21).boundingBox(), first = await entries.first().boundingBox();
    assert.ok(list.y > first.y, '舊的紀錄在畫面下方');
  }
  await checkOrder(page.getByRole('region', { name: '任務完整頁', exact: true }));
  mkdirSync(new URL('./', artifacts).pathname, { recursive: true });
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1100 });
    await page.waitForFunction(() => innerWidth > 700 || document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${width}寬不能橫向捲動`);
    await page.screenshot({ path: new URL(`history-${width}.png`, artifacts).pathname, animations: 'disabled' });
    await page.evaluate(() => { scrollTo(0, 0); document.querySelectorAll('*').forEach(element => { if (element.scrollTop) element.scrollTop = 0; }); });
    await page.screenshot({ path: new URL(`properties-${width}.png`, artifacts).pathname, animations: 'disabled' });
  }
  await page.setViewportSize({ width: 1440, height: 1100 });
  await page.getByRole('button', { name: '返回任務列表', exact: true }).click();
  await page.getByRole('button', { name: '快速編輯 HIS-1', exact: true }).click();
  await checkOrder(page.getByRole('dialog').first());
  assert.deepEqual(errors, []);
  console.log('PASS: creator/created time and last change shown in detail; history newest-first with load-more appending older below in detail and drawer; three widths without horizontal scroll');
} finally {
  await browser?.close();
  await new Promise(resolve => server ? server.httpServer.close(resolve) : resolve());
}
