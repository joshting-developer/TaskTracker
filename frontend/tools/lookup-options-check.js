import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
const e2ePort = Number(process.env.E2E_FRONTEND_PORT || 5175); // TT-335：Claude 5198、Codex 5199, 見.agents/skills/tasktracker-test-environments

if (!process.env.E2E_API_URL) throw new Error('需要隔離 E2E_API_URL');
let server, browser;
try {
  server = await preview({ root: new URL('..', import.meta.url).pathname, preview: { host: '127.0.0.1', port: e2ePort, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  page.setDefaultTimeout(15000);
  const errors = [], requests = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('request', request => { if (request.url().includes('/api/')) requests.push(new URL(request.url()).pathname); });
  const base = `http://127.0.0.1:${e2ePort}`;
  await page.goto(`${base}/login`);
  async function call(operation, input = {}, group = 'rpc') {
    return page.evaluate(async ({ operation, input, group }) => {
      const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
      const response = await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) });
      const result = await response.json();
      if (!result.ok) throw new Error(result.error?.message);
      if (result.data?.verificationRequired) { const csrf = await (await fetch('/api/csrf-cookie')).json(); const verified = await fetch('/api/auth/verify-email', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: JSON.stringify({ code: 'local666' }) }); if (!verified.ok) throw new Error('Fixture email verification failed'); result.data = (await verified.json()).data; } return result.data;
    }, { operation, input, group });
  }
  await call('register', { name: 'Options', email: `options-${crypto.randomUUID()}@example.test`, password: 'test-local-password', workspaceName: 'Options' }, 'auth');
  const project = await call('createProject', { name: 'Options', code: 'OPT' });
  const make = fields => call('createIssue', { projectId: project.id, title: 'Candidate', ...fields });
  for (let n = 1; n <= 7; n++) await make({ title: `Initial ${n}` });
  const epic = await make({ title: 'Far Epic', type: 'epic' });
  const parent = await make({ title: 'Far Parent', epicId: epic.id });
  const child = await make({ title: 'Selected Child', parentIssueId: parent.id, epicId: epic.id });
  for (let n = 11; n <= 145; n++) await make({ title: `Candidate ${n}`, description: '不得夾帶的描述' });
  const url = `/api/v1/projects/${project.id}/issue-options`;
  const options = await page.evaluate(async url => (await (await fetch(url + '?perPage=30')).json()).data, url);
  assert.equal(options.items.length, 30);
  assert(!options.items.some(item => item.id === parent.id || item.id === epic.id));
  for (const item of options.items) {
    for (const field of ['description', 'history', 'comments', 'spentMinutes', 'childCount']) assert(!(field in item));
  }
  assert.equal(await page.evaluate(async url => (await fetch(url + '?perPage=101')).status, url), 400);
  await page.goto(`${base}/#/projects/${project.id}`);
  await page.reload();
  await page.getByRole('textbox', { name: '搜尋任務', exact: true }).fill('Selected Child');
  const row = page.locator('tr[data-issue]').filter({ hasText: 'Selected Child' });
  await row.getByRole('button', { name: /^快速編輯 / }).click();
  const editor = page.locator('.issue-dialog');
  await editor.waitFor();
  await editor.locator('[name="title"]').fill('Child Preserved');
  const updated = page.waitForResponse(response => response.url().endsWith('/updateIssue') && response.status() === 200);
  await editor.getByRole('button', { name: '儲存修改', exact: true }).click();
  await updated;
  const stored = await page.evaluate(async ({ project, child }) => (await (await fetch(`/api/v1/projects/${project}/issues/${child}`)).json()).data, { project: project.id, child: child.id });
  assert.equal(stored.epicId, epic.id);
  assert.equal(stored.parentIssueId, parent.id);
  await editor.getByRole('button', { name: '關閉任務', exact: true }).click();
  await page.goto(`${base}/#/projects/${project.id}/boards`);
  await page.getByRole('button', { name: '新增看板', exact: true }).click();
  const dialog = page.locator('.board-dialog');
  await dialog.locator('.board-picker-item').first().waitFor();
  requests.length = 0;
  const search = dialog.getByLabel('搜尋收錄項目', { exact: true });
  await search.fill('Far Parent');
  await dialog.getByLabel(`收錄 ${parent.identifier}`, { exact: true }).check();
  await search.fill('Far Epic');
  await dialog.getByLabel(`收錄 ${epic.identifier}`, { exact: true }).check();
  await search.fill('Candidate');
  await dialog.getByRole('button', { name: '下一頁', exact: true }).click();
  await dialog.getByRole('status').filter({ hasText: '已選 2 個項目' }).waitFor();
  assert(requests.some(path => path.endsWith('/issue-options')));
  assert(!requests.some(path => /\/(issues|getWorkspace|getTodos)$/.test(path)), '收錄候選不回一般列表或整包Workspace');
  await dialog.getByLabel('看板名稱', { exact: true }).fill('Selected Across Pages');
  const saved = page.waitForResponse(response => response.url().endsWith('/createBoard') && response.status() === 200);
  await dialog.getByRole('button', { name: '儲存看板', exact: true }).click();
  const board = (await (await saved).json()).data;
  assert.deepEqual([...board.selectedIssueIds].sort(), [parent.id, epic.id].sort());
  const artifacts = new URL('../artifacts/lookup-options/', import.meta.url); mkdirSync(artifacts, { recursive: true });
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1000 });
    if (width === 390) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({ path: new URL(`${width}.png`, artifacts).pathname });
  }
  assert.deepEqual(errors, []);
  console.log('PASS: 145卡30筆候選／最小投影／上限；父項與Epic不在當頁仍保存；看板跨搜尋與頁保留選取、僅issue-options、三尺寸');
} finally { await browser?.close(); await server?.httpServer.close(); }
