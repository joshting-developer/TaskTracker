import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';
const e2ePort = Number(process.env.E2E_FRONTEND_PORT || 5175); // TT-335：Claude 5198、Codex 5199, 見.agents/skills/tasktracker-test-environments
if (!process.env.E2E_API_URL) throw new Error('必須指定隔離 E2E_API_URL');
let server, browser;
try {
  server = await createServer({ root: new URL('..', import.meta.url).pathname, server: { host: '127.0.0.1', port: e2ePort, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const errors = [], requests = [];
  page.on('pageerror', failure => errors.push(failure.message));
  page.on('request', request => { const path = new URL(request.url()).pathname; if (path.startsWith('/api/')) requests.push(path); });
  await page.goto(`http://127.0.0.1:${e2ePort}/login`);
  async function call(target, operation, input = {}, group = 'rpc') {
    for (let attempt = 0; attempt < 5; attempt++) {
      const result = await target.evaluate(async ({ operation, input, group }) => {
        const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
        const response = await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) });
        if (response.status === 429) return { throttled: true, retry: Number(response.headers.get('Retry-After') || 20) };
        const result = await response.json();
        if (!result.ok) throw new Error(result.error.message);
        return { data: result.data };
      }, { operation, input, group });
      if (!result.throttled) if (result.data?.verificationRequired) { const csrf = await (await fetch('/api/csrf-cookie')).json(); const verified = await fetch('/api/auth/verify-email', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: JSON.stringify({ code: 'local666' }) }); if (!verified.ok) throw new Error('Fixture email verification failed'); result.data = (await verified.json()).data; } return result.data;
      console.log('Fixture auth throttle: wait', result.retry);
      await target.waitForTimeout(Math.min(59000, result.retry * 1000 + 100));
    }
    throw new Error('Fixture auth throttle did not recover');
  }
  const ownerEmail = `acl-owner-${crypto.randomUUID()}@example.test`;
  await call(page, 'register', { name: 'Owner', email: ownerEmail, password: 'test-local-password', workspaceName: 'ACL Read' }, 'auth');
  const project = await call(page, 'createProject', { name: 'ACL Read', code: 'ACL' });
  const guest = await (await browser.newContext()).newPage();
  await guest.goto(`http://127.0.0.1:${e2ePort}/login`);
  const users = [];
  let revision = 0;
  for (let i = 0; i < 25; i++) {
    const invite = await call(page, 'inviteMember', { email: `acl-${crypto.randomUUID()}@example.test` });
    const identity = await call(guest, 'register', { name: `Person ${String(i).padStart(3, '0')}`, email: invite.email, password: 'test-local-password', inviteToken: invite.token }, 'auth');
    users.push(identity.user);
    await call(guest, 'logout', {}, 'auth');
    const changed = await call(page, 'setProjectMember', { projectId: project.id, userId: identity.user.id, roleId: i === 0 ? 'maintainer' : 'developer', expectedRevision: revision });
    revision = changed.revision;
    if (i % 5 === 4) console.log('Fixture members', i + 1);
  }
  requests.length = 0;
  await page.goto(`http://127.0.0.1:${e2ePort}/app/#/projects/${project.id}/members`);
  await page.reload();
  const region = page.getByRole('region', { name: '專案成員與權限', exact: true });
  await region.locator('.project-member-row').first().waitFor();
  assert.equal(await region.locator('.project-member-row').count(), 20);
  assert.equal(requests.filter(path => /\/acl\/(history|candidates)$/.test(path)).length, 0, '初始頁不下載候選與歷史');
  const pager = region.getByLabel('專案成員分頁', { exact: true });
  await pager.getByRole('button', { name: '下一頁', exact: true }).click();
  await page.waitForFunction(() => document.querySelectorAll('.project-member-row').length === 6);
  await region.getByRole('button', { name: '編輯成員 Person 024', exact: true }).click();
  assert.equal(await region.getByLabel('加入專案的成員', { exact: true }).inputValue(), users[24].id);
  await region.getByLabel('專案角色', { exact: true }).selectOption('reader');
  await call(page, 'setProjectMember', { projectId: project.id, userId: users[23].id, roleId: 'reader', expectedRevision: revision });
  revision++;
  await region.getByRole('button', { name: '儲存專案成員', exact: true }).click();
  await region.getByRole('button', { name: '載入最新成員版本', exact: true }).waitFor();
  assert.equal(await region.getByLabel('專案角色', { exact: true }).inputValue(), 'reader', '409保留草稿');
  page.once('dialog', dialog => dialog.accept());
  await region.getByRole('button', { name: '載入最新成員版本', exact: true }).click();
  await region.getByRole('button', { name: '編輯成員 Person 024', exact: true }).click();
  await region.getByLabel('專案角色', { exact: true }).selectOption('reader');
  await region.getByRole('button', { name: '儲存專案成員', exact: true }).click();
  await region.locator('.project-member-row').filter({ hasText: 'Person 024' }).getByText('讀者', { exact: true }).waitFor();
  revision++;
  await region.locator('.membership-history > summary').click();
  await region.locator('.membership-history article').first().waitFor();
  assert.equal(await region.locator('.membership-history article').count(), 20);
  await region.getByLabel('授權歷史分頁', { exact: true }).getByRole('button', { name: '下一頁', exact: true }).click();
  await page.waitForFunction(() => document.querySelectorAll('.membership-history article').length === 7);
  await region.getByLabel('搜尋專案成員', { exact: true }).fill('Person 000');
  await page.waitForFunction(() => document.querySelectorAll('.project-member-row').length === 1);
  await region.getByLabel('加入專案的成員', { exact: true }).focus();
  await region.getByRole('button', { name: '搜尋加入專案的成員選項', exact: true }).click();
  await region.getByRole('textbox', { name: '搜尋加入專案的成員', exact: true }).fill('Person 024');
  await region.locator(`select option[value="${users[24].id}"]`).waitFor({ state: 'attached' });
  assert.ok(requests.some(path => path.endsWith('/acl/candidates')));
  assert.equal(requests.filter(path => /\/(getWorkspace|getProjectMembers|getTodos)$/.test(path)).length, 0);
  mkdirSync(new URL('../artifacts/acl-query', import.meta.url), { recursive: true });
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1000 });
    if (width === 390) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    await page.evaluate(() => window.scrollTo(0, 0));
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({ path: new URL(`../artifacts/acl-query/${width}.png`, import.meta.url).pathname });
  }
  await call(guest, 'login', { email: users[0].email, password: 'test-local-password' }, 'auth');
  await guest.goto(`http://127.0.0.1:${e2ePort}/app/#/projects/${project.id}/members`);
  await guest.reload();
  await guest.getByRole('region', { name: '專案成員與權限', exact: true }).waitFor();
  await call(page, 'removeProjectMember', { projectId: project.id, userId: users[0].id, expectedRevision: revision });
  await guest.getByRole('button', { name: '重新載入成員', exact: true }).click();
  await guest.waitForFunction(() => !document.querySelector('.project-members'));
  assert.deepEqual(errors, []);
  console.log('PASS TT-242: 26 members /20+6, candidates/history lazy, off-page edit, revision409 draft, history20+7, search, revoke, 3 widths, bulkReads=0');
} finally { await browser?.close(); await server?.close(); }
