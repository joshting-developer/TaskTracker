import { ownedDefaultTopology } from './fixtures/owned-topology.js';
import { expandIssueProperties } from './issue-form-actions.js';
import { fixtureResponse } from './fixtures/verification.js';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import assert from 'node:assert/strict';

ownedDefaultTopology();
const port = Number(process.env.E2E_FRONTEND_PORT || 5199), base = `http://127.0.0.1:${port}`;
let server, browser;
try {
  server = await createServer({ server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const owner = await browser.newContext({ locale: 'zh-TW' }), member = await browser.newContext({ locale: 'zh-TW' });
  const admin = await owner.newPage(), recipient = await member.newPage(), errors = [];
  for (const page of [admin, recipient]) { page.setDefaultTimeout(12000); page.on('pageerror', e => errors.push(e.message)); }
  async function call(context, operation, input = {}, group = 'rpc') {
    const csrf = await (await context.request.get(base + '/api/csrf-cookie')).json();
    const response = await context.request.post(`${base}/api/${group}/${operation}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), 200, await response.text());
    return (await fixtureResponse(context.request, response, input)).data;
  }
  async function settings() {
    await admin.goto(base + '/app/#/workspace/settings/members');
    await admin.getByLabel('成員狀態', { exact: true }).waitFor();
  }
  const password = 'isolated-member-status-password';
  const host = await call(owner, 'register', { name: '工作區擁有者', email: `host-${crypto.randomUUID()}@example.test`, password, workspaceName: '協作空間' }, 'auth');
  const invite = await call(owner, 'inviteMember', { email: `member-${crypto.randomUUID()}@example.test` });
  const actor = await call(member, 'register', { name: '待停用成員', email: invite.email, password, inviteToken: invite.token }, 'auth');
  const project = await call(owner, 'createProject', { name: '資格測試專案', code: 'STATUS' });
  const acl = await call(owner, 'getProjectMembers', { projectId: project.id });
  await call(owner, 'setProjectMember', { projectId: project.id, userId: actor.user.id, roleId: 'developer', expectedRevision: acl.revision });
  await call(owner, 'createIssue', { projectId: project.id, title: '保留原指派的卡片', assignee: actor.user.id });
  await recipient.goto(`${base}/app/#/projects/${project.id}`);
  await recipient.getByRole('button', { name: '保留原指派的卡片', exact: true }).waitFor();
  await settings();
  const row = admin.locator(`[data-member-id="${actor.user.id}"]`);
  await row.getByRole('button', { name: '停用成員 待停用成員', exact: true }).waitFor();
  assert.equal(await admin.locator(`[data-member-id="${host.user.id}"]`).getByRole('button', { name: /^停用成員/ }).count(), 0);
  admin.once('dialog', dialog => dialog.dismiss());
  await row.getByRole('button', { name: '停用成員 待停用成員', exact: true }).click();
  assert.equal(await row.count(), 1);
  admin.once('dialog', dialog => dialog.accept());
  await row.getByRole('button', { name: '停用成員 待停用成員', exact: true }).click();
  await row.waitFor({ state: 'hidden' });
  await admin.getByLabel('成員狀態', { exact: true }).selectOption('disabled');
  await row.getByRole('button', { name: '恢復成員 待停用成員', exact: true }).waitFor();
  mkdirSync('../artifacts/tt359', { recursive: true });
  for (const width of [1440, 768, 390]) {
    await admin.setViewportSize({ width, height: 900 });
    assert.equal(await admin.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await admin.screenshot({ path: `../artifacts/tt359/disabled-${width}.png`, fullPage: true });
  }
  await admin.setViewportSize({ width: 1440, height: 900 });
  assert.equal((await member.request.get(`${base}/api/v1/projects/${project.id}`)).status(), 401);
  await recipient.getByRole('button', { name: '重新整理工作空間', exact: true }).click();
  const home = recipient.getByRole('region', { name: '無隸屬工作區首頁', exact: true });
  await home.getByRole('heading', { name: '無隸屬工作區', exact: true }).waitFor();
  await home.getByRole('button', { name: '建立工作區', exact: true }).click();
  const picker = recipient.getByRole('dialog', { name: '選擇工作空間', exact: true });
  await picker.getByRole('button', { name: '建立工作空間', exact: true }).click();
  await picker.getByLabel('工作空間名稱', { exact: true }).fill('自己的工作區');
  await picker.getByRole('button', { name: '建立工作空間', exact: true }).click();
  await picker.waitFor({ state: 'hidden' });
  const ownSpace = (await call(member, 'current', {}, 'auth')).workspace;
  await admin.goto(`${base}/app/#/projects/${project.id}`);
  await admin.locator('.assignee').filter({ hasText: '待停用成員（已停用）' }).waitFor();
  await admin.getByRole('button', { name: '新增任務', exact: true }).click();
  const editor = admin.getByRole('dialog', { name: '新增一個任務', exact: true });
  await expandIssueProperties(editor);
  await editor.getByLabel('負責人', { exact: true }).waitFor();
  assert.equal(await editor.getByLabel('負責人', { exact: true }).locator(`option[value="${actor.user.id}"]`).count(), 0);
  await editor.getByRole('button', { name: '關閉任務', exact: true }).click();
  await settings();
  await admin.getByLabel('成員狀態', { exact: true }).selectOption('disabled');
  admin.once('dialog', dialog => dialog.accept());
  await row.getByRole('button', { name: '恢復成員 待停用成員', exact: true }).click();
  await row.waitFor({ state: 'hidden' });
  await call(member, 'switchWorkspace', { workspaceId: host.workspace.id }, 'auth');
  await recipient.reload();
  await recipient.goto(`${base}/app/#/projects/${project.id}`);
  await recipient.getByRole('button', { name: '保留原指派的卡片', exact: true }).waitFor();
  await admin.getByLabel('成員狀態', { exact: true }).selectOption('active');
  admin.once('dialog', dialog => dialog.accept());
  await row.getByRole('button', { name: '停用成員 待停用成員', exact: true }).click();
  await row.waitFor({ state: 'hidden' });
  await recipient.getByRole('button', { name: '重新整理工作空間', exact: true }).click();
  await home.getByRole('heading', { name: '選擇工作區', exact: true }).waitFor();
  await home.getByRole('button', { name: '選擇工作區', exact: true }).click();
  await picker.getByRole('button', { name: '選擇工作空間 自己的工作區', exact: true }).click();
  await picker.waitFor({ state: 'hidden' });
  assert.equal((await call(member, 'current', {}, 'auth')).workspace.id, ownSpace.id);
  assert.deepEqual(errors, []);
  writeFileSync('../artifacts/tt359/browser-result.json', JSON.stringify({ passed: true, widths: [1440, 768, 390], ownerProtected: true, revokedSessionDenied: true, zeroWorkspaceCanCreate: true, historyPreserved: true, noDisabledAssignmentOption: true, restoreKeepsAcl: true, otherWorkspacePreserved: true, errors }, null, 2));
  console.log('PASS member disable/restore, zero-workspace creation, retained assignment and other workspace choice');
} catch (failure) {
  mkdirSync('../artifacts/tt359', { recursive: true });
  for (const [index, page] of (browser?.contexts().flatMap(context => context.pages()) || []).entries()) await page.screenshot({ path: `../artifacts/tt359/failure-${index}.png`, fullPage: true }).catch(() => {});
  throw failure;
} finally { await browser?.close(); await server?.close(); }
