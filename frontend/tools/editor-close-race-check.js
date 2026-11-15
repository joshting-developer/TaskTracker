import { fixtureResponse } from './fixtures/verification.js';
import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
if (process.env.E2E_ISOLATED !== '1' || !process.env.E2E_API_URL) throw Error('Explicit isolated backend required');
const port = Number(process.env.E2E_FRONTEND_PORT || 5291), base = `http://127.0.0.1:${port}`, folder = new URL('../../artifacts/tt288/', import.meta.url); mkdirSync(folder, { recursive: true });
let vite, browser;
try {
  vite = await createServer({ root: new URL('../', import.meta.url).pathname, server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await vite.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const context = await browser.newContext({ locale: 'zh-TW', viewport: { width: 1440, height: 1000 } }), page = await context.newPage(), errors = []; page.setDefaultTimeout(15000); page.on('pageerror', e => errors.push(e.message));
  async function call(operation, input, group = 'rpc', client = context) {
    const csrf = await (await client.request.get(`${base}/api/csrf-cookie`)).json(); const response = await client.request.post(`${base}/api/${group}/${operation}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } }); assert.equal(response.status(), 200, await response.text()); return (await fixtureResponse(client.request, response, input)).data;
  }
  await call('register', { name: 'Race tester', email: `close-${crypto.randomUUID()}@example.test`, password: 'isolated-form-password', workspaceName: '關閉驗證' }, 'auth');
  const project = await call('createProject', { name: '延遲讀取', code: 'RACE' });
  const issue = await call('createIssue', { projectId: project.id, title: '關閉後不重新開啟', type: 'task' });
  await page.goto(`${base}/app/#/projects/${project.id}`);
  await page.getByRole('button', { name: `快速編輯 ${issue.identifier}`, exact: true }).click();
  const editor = page.getByRole('dialog', { name: '任務詳細內容', exact: true }); await editor.waitFor(); await page.waitForLoadState('networkidle');
  await editor.getByLabel(/任務標題/).fill('更新後仍留在列表');
  await editor.getByLabel('描述', { exact: true }).fill('已提交的內容');
  const url = `**/api/v1/projects/${project.id}/issues/${issue.id}`;
  let release; const held = new Promise(resolve => { release = resolve; }); let captured;
  const intercepted = new Promise(resolve => { captured = resolve; });
  await page.route(url, async route => { const response = await route.fetch(); captured(); await held; await route.fulfill({ response }); }, { times: 1 });
  await editor.getByRole('button', { name: '儲存修改', exact: true }).click();
  await intercepted; await editor.getByText(/版本 2 · 已儲存/).waitFor();
  await editor.getByRole('button', { name: '關閉任務', exact: true }).click(); await editor.waitFor({ state: 'hidden' });
  const resumed = page.waitForResponse(response => response.request().method() === 'GET' && new URL(response.url()).pathname === `/api/v1/projects/${project.id}/issues/${issue.id}`);
  release(); await resumed; await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
  assert.equal(await page.getByRole('dialog').count(), 0, 'A late saved-issue read cannot resurrect a closed editor');
  await page.getByRole('button', { name: '更新後仍留在列表', exact: true }).waitFor();
  assert.deepEqual(errors, []); console.log('PASS: delayed real read after save cannot reopen a closed editor');
} finally { await browser?.close(); await vite?.close(); }
