import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';
if (!process.env.E2E_API_URL) throw new Error('必須指定隔離 E2E_API_URL');
let server, browser;
try {
  server = await createServer({ root: new URL('..', import.meta.url).pathname, server: { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const owner = await browser.newPage({ viewport: { width: 1440, height: 1000 } }), guest = await (await browser.newContext()).newPage();
  const requests = [], errors = [];
  owner.on('request', request => { const path = new URL(request.url()).pathname; if (path.startsWith('/api/')) requests.push(path); });
  owner.on('pageerror', failure => errors.push(failure.message));
  await owner.goto('http://127.0.0.1:5175/login'); await guest.goto('http://127.0.0.1:5175/login');
  async function call(page, operation, input = {}, group = 'rpc') {
    for (let attempt = 0; attempt < 5; attempt++) {
      const result = await page.evaluate(async ({ operation, input, group }) => {
        const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
        const response = await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) });
        if (response.status === 429) return { throttled: true };
        const result = await response.json();
        if (!result.ok) throw new Error(result.error.message);
        return { data: result.data };
      }, { operation, input, group });
      if (!result.throttled) return result.data;
      console.log('Fixture auth throttle: wait 20');
      await page.waitForTimeout(20000);
    }
    throw new Error('Fixture auth throttle did not recover');
  }
  const email = `ws-owner-${crypto.randomUUID()}@example.test`, users = [];
  await call(owner, 'register', { name: 'Owner', email, password: 'test-local-password', workspaceName: 'Workspace Read' }, 'auth');
  for (let index = 0; index < 21; index++) {
    const invite = await call(owner, 'inviteMember', { email: `ws-${crypto.randomUUID()}@example.test` });
    const identity = await call(guest, 'register', { name: `Person ${String(index).padStart(3, '0')}`, email: invite.email, password: 'test-local-password', inviteToken: invite.token }, 'auth');
    users.push(identity.user);
    await call(guest, 'logout', {}, 'auth');
    if (index % 5 === 4) console.log('Fixture members', index + 1);
  }
  requests.length = 0;
  await owner.goto('http://127.0.0.1:5175/app/#/workspace/settings'); await owner.reload();
  const settings = owner.getByRole('region', { name: '工作空間設定', exact: true });
  await settings.getByLabel('工作空間名稱', { exact: true }).waitFor();
  assert.equal(requests.filter(path => /\/workspace\/(members|member-roles)$/.test(path)).length, 0, '一般設定未開名單');
  await settings.getByLabel('工作空間名稱', { exact: true }).fill('Renamed Workspace');
  await settings.getByRole('button', { name: '儲存工作空間名稱', exact: true }).click();
  await settings.getByRole('status').filter({ hasText: '工作空間名稱已儲存' }).waitFor();
  assert.equal(requests.filter(path => path.endsWith('/workspace/members')).length, 0, '改名不刷新成員');
  await settings.getByRole('tab', { name: '成員與權限', exact: true }).click();
  await settings.locator('.member-list li').first().waitFor();
  assert.equal(await settings.locator('.member-list li').count(), 20);
  assert.equal(await settings.getByLabel('工作空間身份 Owner', { exact: true }).count(), 0, 'ownership不提供角色調整');
  const pager = settings.getByLabel('工作空間成員分頁', { exact: true });
  await pager.getByRole('button', { name: '下一頁', exact: true }).click();
  await owner.waitForFunction(() => document.querySelectorAll('.member-list li').length === 2);
  const role = settings.getByLabel('工作空間身份 Person 020', { exact: true });
  owner.once('dialog', dialog => dialog.accept());
  await role.selectOption('manager');
  await owner.waitForFunction(() => document.querySelector('[aria-label="工作空間身份 Person 020"]')?.value === 'manager');
  await call(owner, 'setWorkspaceMemberRole', { userId: users[20].id, roleId: 'user', expectedRevision: 1 });
  owner.once('dialog', dialog => dialog.accept()); await role.selectOption('user');
  await settings.getByRole('button', { name: '載入最新設定', exact: true }).waitFor();
  assert.equal(await role.inputValue(), 'manager', '409不偷換畫面舊資格基準');
  await settings.getByRole('button', { name: '載入最新設定', exact: true }).click();
  await owner.waitForFunction(() => document.querySelector('[aria-label="工作空間身份 Person 020"]')?.value === 'user');
  await settings.getByLabel('搜尋工作空間成員', { exact: true }).fill('Person 020');
  await owner.waitForFunction(() => document.querySelectorAll('.member-list li').length === 1);
  await settings.getByLabel('受邀者 Email', { exact: true }).fill(`new-${crypto.randomUUID()}@example.test`);
  await settings.getByRole('button', { name: '建立邀請連結', exact: true }).click();
  await settings.getByLabel('邀請連結', { exact: true }).waitFor();
  assert.equal(requests.filter(path => /\/(getWorkspace|getWorkspaceSettings|getTodos)$/.test(path)).length, 0);
  mkdirSync(new URL('../artifacts/workspace-query', import.meta.url), { recursive: true });
  for (const width of [1440, 768, 390]) {
    await owner.setViewportSize({ width, height: 1000 });
    if (width === 390) await owner.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    await owner.evaluate(() => window.scrollTo(0, 0));
    assert.equal(await owner.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await owner.screenshot({ path: new URL(`../artifacts/workspace-query/${width}.png`, import.meta.url).pathname });
  }
  await call(guest, 'register', { name: 'Other Owner', email: `other-${crypto.randomUUID()}@example.test`, password: 'test-local-password', workspaceName: 'Other Workspace' }, 'auth');
  const otherInvite = await call(guest, 'inviteMember', { email });
  await call(owner, 'acceptWorkspaceInvite', { inviteToken: otherInvite.token }, 'auth');
  await settings.getByLabel('搜尋工作空間成員', { exact: true }).fill('Owner');
  await owner.waitForFunction(() => !document.querySelector('.workspace-settings-page'));
  await owner.getByRole('heading', { name: '小待辦', exact: true }).waitFor();
  assert.deepEqual(errors, []);
  console.log('PASS TT-241: general no directory/roles, 22 members20+2, rename/role/invite scoped refresh, role409, search, ownership, cross-workspace clear, 3 widths, bulkReads=0');
} finally { await browser?.close(); await server?.close(); }
