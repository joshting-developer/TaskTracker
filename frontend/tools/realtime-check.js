import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { preview } from 'vite';
import { chromium } from 'playwright';
const e2ePort = Number(process.env.E2E_FRONTEND_PORT || 5175); // TT-335：Claude 5198、Codex 5199, 見.agents/skills/tasktracker-test-environments

// 本測試只使用獨立Compose；斷線測試會暫停該專案的Reverb，絕不操作主服務。
const testProject = 'tasktracker-realtime-test';
if (process.env.E2E_API_URL !== 'http://127.0.0.1:13024' || process.env.E2E_REALTIME_PROJECT !== testProject) throw new Error('需要指定獨立即時測試環境');
const run = promisify(execFile);
const env = { ...process.env, TASKTRACKER_API_PORT: '13024', TASKTRACKER_WS_PORT: '18080', TASKTRACKER_PORT: '5175' };
const compose = action => run('docker', ['compose', '-p', testProject, action, 'websocket'], { cwd: '..', env, timeout: 60000 });
let server, browser, stopped = false;
async function request(page, operation, input = {}, group = 'rpc') {
  return page.evaluate(async ({ operation, input, group }) => {
    const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
    const response = await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify(input) });
    return { status: response.status, ...await response.json() };
  }, { operation, input, group });
}
async function call(page, operation, input = {}, group = 'rpc') {
  const result = await request(page, operation, { requestId: crypto.randomUUID(), ...input }, group);
  assert.equal(result.ok, true, JSON.stringify(result)); if (result.data?.verificationRequired) return call(page, 'verify-email', { code: 'local666' }, 'auth'); return result.data;
}
try {
  server = await preview({ preview: { host: '127.0.0.1', port: e2ePort, strictPort: true, proxy: { '/api': process.env.E2E_API_URL, '/reverb': { target: 'http://127.0.0.1:18080', ws: true, rewrite: path => path.replace(/^\/reverb/, '') } } } });
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const owner = await (await browser.newContext()).newPage(), reader = await (await browser.newContext()).newPage();
  const errors = [], signals = []; let closed = 0;
  reader.on('pageerror', error => errors.push(error.message));
  reader.on('websocket', socket => {
    socket.on('framereceived', ({ payload }) => { try { const message = JSON.parse(payload); if (message.event === 'project.changed') signals.push(typeof message.data === 'string' ? JSON.parse(message.data) : message.data); } catch {} });
    socket.on('close', () => closed++);
  });
  await owner.goto(`http://127.0.0.1:${e2ePort}/login`); await reader.goto(`http://127.0.0.1:${e2ePort}/login`);
  const token = crypto.randomUUID(), password = 'realtime-browser-password';
  const identity = await call(owner, 'register', { name: '即時負責人', email: `live-owner-${token}@example.test`, password, workspaceName: '即時協作' }, 'auth');
  const project = await call(owner, 'createProject', { name: '多人同步', code: 'SOCKET' });
  const issue = await call(owner, 'createIssue', { projectId: project.id, title: '原始卡片' });
  const invite = await call(owner, 'inviteMember', { email: `live-reader-${token}@example.test` });
  const member = await call(reader, 'register', { name: '即時開發者', email: `live-reader-${token}@example.test`, password, inviteToken: invite.token }, 'auth');
  const channel = `private-workspaces.${identity.workspace.id}.projects.${project.id}`;
  assert.equal((await request(reader, 'authorize', { channel_name: channel, socket_id: '123.456' }, 'realtime')).status, 404);
  let acl = await call(owner, 'getProjectMembers', { projectId: project.id });
  acl = await call(owner, 'setProjectMember', { projectId: project.id, userId: member.user.id, roleId: 'developer', expectedRevision: acl.revision });
  const boardUrl = `http://127.0.0.1:${e2ePort}/app/#/projects/${project.id}/board`;
  await owner.goto(boardUrl); await owner.reload(); await reader.goto(boardUrl); await reader.reload();
  const live = page => page.getByRole('status', { name: '看板同步狀態', exact: true });
  await live(owner).filter({ hasText: '即時同步' }).waitFor(); await live(reader).filter({ hasText: '即時同步' }).waitFor();
  const changed = await call(owner, 'updateIssue', { id: issue.id, expectedRevision: issue.revision, title: '跨使用者即時更新', state: 'inprogress' });
  const card = reader.locator(`[data-issue="${issue.identifier}"]`);
  await card.getByText('跨使用者即時更新', { exact: true }).waitFor({ timeout: 8000 });
  assert.equal(await card.evaluate(el => el.closest('[data-column]').dataset.column), 'inprogress');
  assert.equal(await card.locator('select').count(), 0);
  const signalDeadline = Date.now() + 8000;
  while (!signals.length && Date.now() < signalDeadline) await new Promise(resolve => setTimeout(resolve, 50));
  assert.ok(signals.length > 0); assert.deepEqual(signals[0], { changed: true });
  const epic = await call(owner, 'createIssue', { projectId: project.id, title: '即時Epic', type: 'epic' });
  await reader.locator(`[data-issue="${epic.identifier}"]`).waitFor({ timeout: 8000 });
  console.log('PASS: 真實Queue／Reverb雙使用者新增、狀態及標題同步，payload無業務資料');

  await card.dblclick();
  const editor = reader.locator('.issue-dialog'); await editor.waitFor();
  await editor.locator('[name="title"]').fill('尚未儲存的本機草稿');
  const beforeDraftSignal = signals.length;
  await call(owner, 'updateIssue', { id: issue.id, expectedRevision: changed.revision, title: '同事已修改' });
  const draftSignalDeadline = Date.now() + 8000;
  while (signals.length <= beforeDraftSignal && Date.now() < draftSignalDeadline) await new Promise(resolve => setTimeout(resolve, 50));
  assert(signals.length > beforeDraftSignal, '草稿期間仍收到同步訊號');
  assert.equal(await card.getByText('跨使用者即時更新', { exact: true }).count(), 1, '草稿期間僅重驗Project，不重讀列表或偷換基準版本');
  assert.equal(await editor.locator('[name="title"]').inputValue(), '尚未儲存的本機草稿');
  const conflict = reader.waitForResponse(response => response.url().endsWith('/api/rpc/updateIssue') && response.status() === 409);
  await editor.getByRole('button', { name: '儲存修改', exact: true }).click(); await conflict;
  assert.equal(await editor.locator('[name="title"]').inputValue(), '尚未儲存的本機草稿');
  reader.once('dialog', dialog => dialog.accept()); await editor.getByRole('button', { name: '關閉任務', exact: true }).click();
  console.log('PASS: 收到更新仍保留草稿與原revision，儲存回409');

  stopped = true; await compose('stop');
  await reader.waitForFunction(() => !document.querySelector('.board-sync')?.textContent.includes('即時同步'), { timeout: 15000 });
  const offline = await call(owner, 'createIssue', { projectId: project.id, title: '斷線期間新增' });
  await compose('start'); stopped = false;
  await live(reader).filter({ hasText: '即時同步' }).waitFor({ timeout: 30000 });
  await reader.locator(`[data-issue="${offline.identifier}"]`).waitFor({ timeout: 8000 });
  console.log('PASS: 實際停止／重啟Reverb後重連並補抓遺漏變更');
  mkdirSync('artifacts/realtime', { recursive: true });
  for (const width of [1440, 768, 390]) {
    await reader.setViewportSize({ width, height: 900 });
    if (width === 390) await reader.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    await reader.screenshot({ path: `artifacts/realtime/board-${width}.png` });
    assert.equal(await reader.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
  }
  acl = await call(owner, 'getProjectMembers', { projectId: project.id });
  await call(owner, 'removeProjectMember', { projectId: project.id, userId: member.user.id, expectedRevision: acl.revision });
  await reader.locator('.visual-board').waitFor({ state: 'detached', timeout: 8000 });
  assert.equal((await request(reader, 'authorize', { channel_name: channel, socket_id: '123.456' }, 'realtime')).status, 404);
  assert.ok(closed > 0, '失去Project資格時清除連線');
  const noCsrf = await reader.context().request.post(`http://127.0.0.1:${e2ePort}/api/realtime/authorize`, { headers: { 'Content-Type': 'application/json' }, data: {} });
  assert.equal(noCsrf.status(), 419);
  assert.deepEqual(errors, []);
  console.log('PASS: 撤權即時清空看板並斷線、拒絕重訂閱，HTTP CSRF保護、三尺寸與瀏覽器無錯誤');
} finally {
  try { if (stopped) await compose('start'); }
  finally { await browser?.close(); await new Promise(resolve => server ? server.httpServer.close(resolve) : resolve()); }
}
