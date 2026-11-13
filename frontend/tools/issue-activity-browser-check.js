import { fixtureResponse } from './fixtures/verification.js';
import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
if (process.env.E2E_ISOLATED !== '1' || process.env.E2E_API_URL !== 'http://127.0.0.1:13089') throw Error('Owned isolated backend required');
const port = Number(process.env.E2E_FRONTEND_PORT || 5291), base = `http://127.0.0.1:${port}`;
const artifacts = new URL('../../artifacts/tt317/', import.meta.url); mkdirSync(artifacts, { recursive: true });
let server, browser;
try {
  server = await createServer({ root: new URL('../', import.meta.url).pathname, server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await server.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const context = await browser.newContext(), page = await context.newPage(); page.setDefaultTimeout(30000);
  async function call(operation, input) {
    const csrf = await (await context.request.get(`${base}/api/csrf-cookie`)).json();
    const response = await context.request.post(`${base}/api/${operation === 'register' ? 'auth' : 'rpc'}/${operation}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), 200, `Fixture ${operation}`); return (await fixtureResponse(context.request, response)).data;
  }
  await call('register', { name: '活動測試', email: `activity-${crypto.randomUUID()}@example.test`, password: 'isolated-activity-browser', workspaceName: '活動總數' });
  const project = await call('createProject', { name: '活動測試專案', code: 'ACT' });
  let issue = await call('createIssue', { projectId: project.id, title: '活動筆數測試' });
  for (let n = 0; n < 21; n++) {
    issue = await call('addComment', { id: issue.id, expectedRevision: issue.revision, content: `合成留言 ${n}` });
    issue = await call('updateIssue', { id: issue.id, expectedRevision: issue.revision, title: `活動筆數測試 ${n}` });
    await call('logTime', { issueId: issue.id, workDate: '2026-11-13', minutes: 1, note: `合成工時 ${n}` });
  }
  const root = `/api/v1/projects/${project.id}/issues/${issue.id}`, requested = [];
  page.on('request', request => { if (request.url().includes(root + '/')) requested.push(new URL(request.url()).pathname.split('/').at(-1)); });
  await page.goto(`${base}/app/#/projects/${project.id}`);
  await page.getByRole('button', { name: '活動筆數測試 20', exact: true }).click();
  await page.getByRole('tab', { name: '留言 21', exact: true }).waitFor();
  await page.getByRole('tab', { name: '異動紀錄 22', exact: true }).waitFor();
  await page.getByRole('tab', { name: '工時 21', exact: true }).waitFor();
  assert.ok(!requested.includes('history') && !requested.includes('time'), 'Counts do not eagerly request other activity pages');
  await page.waitForFunction(() => document.querySelectorAll('.issue-detail-activity .comment').length === 20);
  await page.getByRole('tab', { name: '異動紀錄 22', exact: true }).click();
  await page.waitForFunction(() => document.querySelectorAll('.history-entry').length === 20);
  await page.getByRole('button', { name: '載入更多', exact: true }).click();
  await page.waitForFunction(() => document.querySelectorAll('.history-entry').length === 22);
  await page.getByRole('tab', { name: '異動紀錄 22', exact: true }).waitFor();
  await page.getByRole('tab', { name: '留言 21', exact: true }).click();
  await page.getByLabel('新增留言', { exact: true }).fill('新增後校正筆數');
  await page.getByRole('button', { name: '送出留言', exact: true }).click();
  await page.getByRole('tab', { name: '留言 22', exact: true }).waitFor();
  await page.getByRole('tab', { name: '工時 21', exact: true }).click();
  await page.getByRole('button', { name: '填報工時', exact: true }).click();
  const time = page.getByRole('dialog', { name: '填報你的工時', exact: true });
  await time.getByLabel('投入小時', { exact: true }).fill('0.25');
  await time.getByLabel('工時說明', { exact: true }).fill('新增工時後校正筆數');
  await time.getByRole('button', { name: '登記工時', exact: true }).click();
  await time.waitFor({ state: 'hidden' });
  await page.getByRole('tab', { name: '工時 22', exact: true }).waitFor();
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1100 });
    await page.waitForFunction(() => innerWidth > 700 || document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    await page.screenshot({ path: new URL(`activity-${width}.png`, artifacts).pathname, animations: 'disabled' });
  }
  await page.setViewportSize({ width: 1440, height: 1100 });
  await page.getByRole('button', { name: '返回任務列表', exact: true }).click();
  await page.getByRole('button', { name: '快速編輯 ACT-1', exact: true }).click();
  const drawer = page.getByRole('dialog').first();
  await drawer.getByRole('tab', { name: '留言 22', exact: true }).waitFor();
  await drawer.getByRole('tab', { name: '異動紀錄 22', exact: true }).waitFor();
  await drawer.getByRole('tab', { name: '異動紀錄 22', exact: true }).click();
  await drawer.getByRole('button', { name: '載入更多', exact: true }).click();
  await drawer.locator('.history-entry').nth(21).waitFor();
  assert.equal(await drawer.locator('.history-entry').count(), 22);
  console.log('PASS: real MySQL detail/drawer initial totals >20, lazy other activities, stable pagination totals, comment/time mutation counts, three widths');
} finally { await browser?.close(); await server?.close(); }
