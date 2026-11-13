import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
if (!process.env.E2E_API_URL) throw new Error('必須指定隔離E2E_API_URL');
let server, browser;
try {
  server = await createServer({ root: new URL('..', import.meta.url).pathname, server: { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage();
  const errors = [], requests = [];
  page.on('pageerror', error => { errors.push(error.message); console.log('PAGEERROR',error.stack); });
  page.on('response', response => { if (new URL(response.url()).pathname.startsWith('/api/')) { requests.push({ url: response.url(), status: response.status() }); if (response.status() >= 400) console.log('HTTP', response.status(), new URL(response.url()).pathname); } });
  await page.goto('http://127.0.0.1:5175/login');
  async function call(operation, input = {}, group = 'rpc') {
    return page.evaluate(async ({ operation, input, group }) => {
      const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
      const data = await (await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
      if (!data.ok) throw new Error(data.error.message); if (data.data?.verificationRequired) { const csrf = await (await fetch('/api/csrf-cookie')).json(); const verified = await fetch('/api/auth/verify-email', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: JSON.stringify({ code: 'local666' }) }); if (!verified.ok) throw new Error('Fixture email verification failed'); data.data = (await verified.json()).data; } return data.data;
    }, { operation, input, group });
  }
  const identity = await call('register', { name: 'Board query', email: `query-${crypto.randomUUID()}@example.test`, password: 'isolation-test-password', workspaceName: 'Board query' }, 'auth');
  const project = await call('createProject', { name: 'Query Project', code: 'QUERY' });
  const epic = await call('createIssue', { projectId: project.id, type: 'epic', title: 'Epic scope' });
  const other = await call('createIssue', { projectId: project.id, title: 'Not on board' });
  const issue = await call('createIssue', { projectId: project.id, title: 'Visible task', assignee: identity.user.id, description: 'Preserve description', epicId: epic.id, relatedIssueIds: [other.id] });
  let remoteCandidate;
  for (let index = 0; index < 101; index++) { const candidate = await call('createIssue', {projectId: project.id, title: `Paged task ${index}`, epicId: epic.id}); if (index === 80) remoteCandidate = candidate; }
  const board = await call('createBoard', { projectId: project.id, name: 'Delivery', selectedIssueIds: [epic.id] });
  requests.length = 0; errors.length = 0;
  await page.goto(`http://127.0.0.1:5175/app/#/projects/${project.id}`); await page.reload();
  await page.waitForFunction(() => document.querySelectorAll('.issue-table tbody tr').length === 20);
  const mineResponse=page.waitForResponse(r=>new URL(r.url()).pathname.endsWith('/issues')&&new URL(r.url()).searchParams.get('scope')==='mine'&&r.status()===200);
  await page.getByRole('button',{name:'我的任務',exact:true}).click(); await mineResponse;
  await page.waitForFunction(() => document.querySelectorAll('.issue-table tbody tr').length === 1);
  assert.equal(await page.getByRole('button',{name:'Visible task',exact:true}).count(),1);
  await page.getByRole('button',{name:'任務列表',exact:true}).click();
  await page.waitForFunction(() => document.querySelectorAll('.issue-table tbody tr').length === 20);
  const nextPage = page.waitForResponse(r => new URL(r.url()).pathname.endsWith('/issues') && new URL(r.url()).searchParams.get('page') === '2' && r.status() === 200);
  await page.getByRole('button', {name:'下一頁',exact:true}).click(); await nextPage;
  const searched = page.waitForResponse(r => new URL(r.url()).pathname.endsWith('/issues') && new URL(r.url()).searchParams.get('search') === 'Preserve description' && r.status() === 200);
  await page.getByRole('textbox',{name:'搜尋任務',exact:true}).fill('Preserve description'); await searched;
  await page.waitForFunction(() => document.querySelectorAll('.issue-table tbody tr').length === 1);
  assert.equal(await page.getByRole('button',{name:'Visible task',exact:true}).count(),1);
  assert.equal(requests.filter(r => /\/(getWorkspace|getTodos)$/.test(new URL(r.url).pathname)).length,0);
  await page.goto(`http://127.0.0.1:5175/app/#/projects/${project.id}/boards`); await page.reload();
  try { await page.getByRole('heading', { name: 'Delivery', exact: true }).waitFor({ timeout: 12000 }); }
  catch(error) { console.log((await page.locator('body').innerText()).slice(-3000)); throw error; }
  assert.equal(requests.filter(r => /\/(getWorkspace|getTodos)$/.test(new URL(r.url).pathname)).length,0);
  await page.getByRole('button', { name: '開啟看板 Delivery', exact: true }).click();
  try { await page.locator('.issue-card').first().waitFor({ timeout: 12000 }); }
  catch(error) { console.log((await page.locator('body').innerText()).slice(-3000)); throw error; }
  await page.waitForFunction(() => document.querySelectorAll('.issue-card').length === 103);
  assert.equal(await page.locator('.issue-card').count(),103);
  assert.ok(requests.some(r => new URL(r.url).pathname.endsWith(`/boards/${board.id}/issues`) && new URL(r.url).searchParams.get('page') === '2'));
  assert.equal(requests.filter(r => /\/(getWorkspace|getTodos)$/.test(new URL(r.url).pathname)).length,0);
  await page.getByRole('button', { name: 'Visible task', exact: true }).dblclick();
  const editor = page.getByRole('dialog', { name: '任務詳細內容', exact: true });
  await editor.waitFor();
  await editor.locator('[name="title"]').fill('Edited title');
  await editor.getByRole('button',{name:'新增關聯',exact:true}).click();
  await editor.getByRole('button',{name:'搜尋關聯任務選項',exact:true}).click();
  const candidateResponse = page.waitForResponse(r => new URL(r.url()).pathname.endsWith('/issue-options') && new URL(r.url()).searchParams.get('search') === 'Paged task 80' && r.status() === 200);
  await editor.getByRole('textbox',{name:'搜尋關聯任務',exact:true}).fill('Paged task 80'); await candidateResponse;
  await editor.getByRole('combobox',{name:'關聯任務',exact:true}).selectOption(remoteCandidate.id);
  await editor.getByRole('button',{name:`開啟關聯任務 ${remoteCandidate.identifier}`,exact:true}).waitFor();
  await editor.getByRole('button', { name: '儲存修改', exact: true }).click();
  await page.getByRole('button', { name: 'Edited title', exact: true }).waitFor();
  const saved = await page.evaluate(async ({project,issue}) => (await (await fetch(`/api/v1/projects/${project}/issues/${issue}`)).json()).data, {project:project.id, issue:issue.id});
  assert.equal(saved.description,'Preserve description');
  assert.deepEqual([...saved.relatedIssueIds].sort(),[other.id,remoteCandidate.id].sort());
  await editor.getByRole('button',{name:'關閉任務',exact:true}).click();
  await page.getByRole('button', { name: 'Edited title', exact: true }).click();
  await page.getByRole('region', {name:'任務完整頁',exact:true}).waitFor();
  await page.getByRole('tab', {name:/異動紀錄/}).click();
  await page.waitForResponse(r=>new URL(r.url()).pathname.endsWith('/history') && r.status()===200);
  assert.equal(requests.filter(r => /\/(getWorkspace|getTodos)$/.test(new URL(r.url).pathname)).length,0);
  const timeResponse = page.waitForResponse(r => new URL(r.url()).pathname.endsWith('/time') && r.status() === 200);
  await page.getByRole('tab', {name:/^工時/}).click();
  await timeResponse;
  const childrenResponse = page.waitForResponse(r => new URL(r.url()).pathname.endsWith('/children') && r.status() === 200);
  await page.getByRole('tab',{name:'關聯',exact:true}).click(); await childrenResponse;
  assert.deepEqual(errors,[]);
  console.log('board query flow PASS', JSON.stringify({requests: requests.length, bulkReads: requests.filter(r => /\/(getWorkspace|getTodos)$/.test(new URL(r.url).pathname)).length}));
} finally { await browser?.close(); await server?.close(); }
