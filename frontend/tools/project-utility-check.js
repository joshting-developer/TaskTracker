import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
if (!process.env.E2E_API_URL) throw new Error('必須指定隔離E2E_API_URL');
let server, browser;
try {
  server = await createServer({ root: new URL('..', import.meta.url).pathname, server: { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } }), errors = [], requests = [];
  page.setDefaultTimeout(10000);
  page.on('pageerror', error => errors.push(error.message));
  page.on('response', async response => { const url = new URL(response.url()); if (!url.pathname.startsWith('/api/')) return; const item = { path: url.pathname, bytes: 0 }; requests.push(item); try { item.bytes = (await response.body()).length; } catch { /* 導覽後body不可取 */ } });
  await page.goto('http://127.0.0.1:5175/login');
  async function call(target, op, input = {}, group = 'rpc') {
    return target.evaluate(async ({ op, input, group }) => {
      const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
      const result = await (await fetch(`/api/${group}/${op}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
      if (!result.ok) throw new Error(result.error.message);
      if (result.data?.verificationRequired) { const csrf = await (await fetch('/api/csrf-cookie')).json(); const verified = await fetch('/api/auth/verify-email', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: JSON.stringify({ code: 'local666' }) }); if (!verified.ok) throw new Error('Fixture email verification failed'); result.data = (await verified.json()).data; } return result.data;
    }, { op, input, group });
  }
  await call(page, 'register', { name: 'Utility Owner', email: `utility-${crypto.randomUUID()}@example.test`, password: 'isolated-test-password', workspaceName: 'Utility Test' }, 'auth');
  const project = await call(page, 'createProject', { name: 'Utility', code: 'UTIL' });
  const other = await call(page, 'createProject', { name: 'Other', code: 'OTHR' });
  const invite = await call(page, 'inviteMember', { email: `receiver-${crypto.randomUUID()}@example.test` });
  const receiver = await (await browser.newContext()).newPage();
  await receiver.goto('http://127.0.0.1:5175/login');
  const identity = await call(receiver, 'register', { name: 'Receiver', email: invite.email, password: 'isolated-test-password', inviteToken: invite.token }, 'auth');
  await call(page, 'setProjectMember', { projectId: project.id, userId: identity.user.id, roleId: 'developer', expectedRevision: 0 });
  await call(page, 'setProjectMember', { projectId: other.id, userId: identity.user.id, roleId: 'developer', expectedRevision: 0 });
  for (let i = 0; i < 25; i++) await call(page, 'createIssue', { projectId: project.id, title: `Notice ${i}`, description: `private description ${i}`, assignee: identity.user.id });
  await call(page, 'createIssue', { projectId: other.id, title: 'Other project notice', assignee: identity.user.id });

  await page.reload();
  await page.getByRole('link', { name: '專案列表', exact: true }).click();
  await page.getByRole('button', { name: '進入專案：Utility', exact: true }).click();
  await page.getByRole('link', { name: '通知預覽', exact: true }).waitFor();
  requests.length = 0;
  await page.getByRole('link', { name: '通知預覽', exact: true }).click();
  await page.locator('.notification-card').nth(19).waitFor();
  assert.equal(await page.locator('.notification-card').count(), 20, '第一頁只顯示20筆');
  assert.equal(await page.getByText('Other project notice').count(), 0, '不含其他專案通知');
  assert.equal(await page.locator('.notification-card').getByText('將任務指派給你').count(), 0, '列表不帶正文');
  const listRequest = requests.find(item => item.path.endsWith(`/projects/${project.id}/notifications`));
  assert.ok(listRequest, '使用專案通知分頁端點');
  const first = page.locator('.notification-card').first();
  await first.getByRole('button', { name: '查看內容', exact: true }).click();
  await first.getByText(/將任務指派給你/).waitFor();
  assert.equal(requests.filter(item => /\/notifications\/[^/]+$/.test(item.path)).length, 1, '正文按需讀取一筆');
  const pager = page.getByLabel('通知預覽分頁', { exact: true });
  await pager.getByRole('button', { name: '下一頁', exact: true }).click();
  await page.waitForFunction(() => document.querySelectorAll('.notification-card').length === 5);
  await pager.getByRole('button', { name: '上一頁', exact: true }).click();
  await page.waitForFunction(() => document.querySelectorAll('.notification-card').length === 20);
  await page.locator('.notification-card').first().getByRole('button', { name: /^UTIL-\d+ →$/ }).click();
  await page.getByRole('region', { name: '任務完整頁', exact: true }).waitFor();
  await page.getByText(/private description \d+/).first().waitFor();

  requests.length = 0;
  await page.getByRole('link', { name: '狀態設定', exact: true }).click();
  await page.getByLabel('狀態 2 Type', { exact: true }).waitFor();
  await page.waitForFunction(() => !document.querySelector('[role="status"]')?.textContent?.includes('載入狀態用量'));
  assert.equal(await page.getByLabel('狀態 2 Type', { exact: true }).inputValue(), 'inprogress');
  assert.equal(await page.getByLabel('移除狀態 1', { exact: true }).isDisabled(), true, 'open必要且使用中');
  assert.equal(await page.getByLabel('移除狀態 2', { exact: true }).isDisabled(), false, '未使用狀態可移除');
  assert.ok(requests.some(item => item.path.endsWith(`/projects/${project.id}/status-usage`)), '使用狀態用量端點');
  const issueId = (await call(page, 'createIssue', { projectId: project.id, title: 'Moving', state: 'inprogress' })).id;
  assert.ok(issueId);
  await page.getByRole('button', { name: '重新整理工作空間' }).click();
  await page.waitForFunction(() => document.querySelector('[aria-label="移除狀態 2"]')?.disabled === true);
  await page.getByRole('button', { name: '新增狀態', exact: true }).click();
  await page.getByLabel('狀態 4 Type', { exact: true }).fill('testing');
  await page.getByLabel('狀態 4 名稱', { exact: true }).fill('待測試');
  await page.getByRole('button', { name: '儲存狀態設定' }).click();
  await page.getByRole('status').filter({ hasText: '狀態設定已儲存' }).waitFor();
  assert.equal(await page.getByLabel('移除狀態 4', { exact: true }).isDisabled(), false, '新狀態未使用');

  const receiverRequests = [];
  receiver.on('response', response => { const url = new URL(response.url()); if (url.pathname.startsWith('/api/')) receiverRequests.push(url.pathname); });
  await receiver.reload();
  await receiver.getByRole('link', { name: '專案列表', exact: true }).click();
  await receiver.getByRole('button', { name: '進入專案：Utility', exact: true }).click();
  assert.equal(await receiver.getByRole('link', { name: '狀態設定', exact: true }).count(), 0);
  await receiver.getByRole('link', { name: '通知預覽', exact: true }).click();
  await receiver.locator('.notification-card').nth(19).waitFor();
  assert.equal(await receiver.getByRole('button', { name: '檢查 SMTP 連線' }).count(), 0);

  for (const width of [1440, 768, 390]) {
    await receiver.setViewportSize({ width, height: 900 });
    assert.equal(await receiver.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${width}無橫向溢位`);
  }
  const bulk = [...requests.map(item => item.path), ...receiverRequests].filter(path => /\/(getWorkspace|getTodos)$/.test(path));
  assert.deepEqual(bulk, [], '不讀整包Workspace');
  assert.deepEqual(errors, []);
  console.log('project utility PASS', JSON.stringify({ notificationListBytes: listRequest.bytes, bulkReads: bulk.length }));
} finally {
  await browser?.close();
  await server?.close();
}
