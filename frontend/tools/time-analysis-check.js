import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
import { dateInTaipei } from '../src/domain/model.js';
if (!process.env.E2E_API_URL) throw new Error('請設定獨立隔離 E2E_API_URL');
let server, browser;
async function request(page, operation, input = {}, group = 'rpc') {
  return page.evaluate(async ({ operation, input, group }) => {
    const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
    const response = await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) });
    return { status: response.status, ...await response.json() };
  }, { operation, input, group });
}
async function call(page, operation, input = {}, group = 'rpc') {
  const result = await request(page, operation, input, group); assert.equal(result.ok, true, JSON.stringify(result)); return result.data;
}
try {
  server = await preview({ preview: { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const owner = await (await browser.newContext({ viewport: { width: 1440, height: 1000 } })).newPage();
  const member = await (await browser.newContext({ viewport: { width: 1440, height: 1000 } })).newPage();
  const errors = [];
  for (const page of [owner, member]) { page.setDefaultTimeout(10000); page.on('pageerror', error => errors.push(error.message)); await page.goto('http://127.0.0.1:5175'); }
  const today = dateInTaipei(), token = crypto.randomUUID(), password = 'analysis-test-password';
  const identity = await call(owner, 'register', { name: '分析負責人', email: `analysis-owner-${token}@example.test`, password, workspaceName: '團隊工時' }, 'auth');
  const first = await call(owner, 'createProject', { name: '會員服務', code: 'MEMBER' }), second = await call(owner, 'createProject', { name: '營運維護', code: 'OPS' });
  const epic = await call(owner, 'createIssue', { projectId: first.id, title: '會員功能', type: 'epic' });
  const task = await call(owner, 'createIssue', { projectId: first.id, title: '實作登入', epicId: epic.id });
  const bug = await call(owner, 'createIssue', { projectId: second.id, title: '修正通知', type: 'bug' });
  for (const [issue, minutes] of [[epic, 30], [task, 60], [bug, 90]]) await call(owner, 'logTime', { issueId: issue.id, minutes, workDate: today });
  const voided = await call(owner, 'logTime', { issueId: task.id, minutes: 10, workDate: today }); await call(owner, 'voidTime', { id: voided.id, expectedRevision: voided.revision });
  const invite = await call(owner, 'inviteMember', { email: `analysis-member-${token}@example.test` });
  const memberIdentity = await call(member, 'register', { name: '協作成員', email: invite.email, password, inviteToken: invite.token }, 'auth');
  const acl = await call(owner, 'getProjectMembers', { projectId: first.id });
  await call(owner, 'setProjectMember', { projectId: first.id, userId: memberIdentity.user.id, roleId: 'developer', expectedRevision: acl.revision });
  await call(member, 'logTime', { issueId: task.id, minutes: 30, workDate: today });
  await owner.reload(); await owner.getByRole('button', { name: '工時分析', exact: true }).click();
  const region = owner.getByRole('region', { name: '工時分析', exact: true });
  await region.getByRole('heading', { name: '工時分析', exact: true }).waitFor();
  assert.equal(await region.locator('.analysis-choice').count(), 3);
  mkdirSync('artifacts/time-analysis', { recursive: true });
  async function screenshots(label) {
    for (const width of [1440, 768, 390]) {
      await owner.setViewportSize({ width, height: 1000 });
      if (width === 390) await owner.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
      assert.equal(await owner.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${label} ${width}不能整頁橫向溢位`);
      await owner.screenshot({ path: `artifacts/time-analysis/${label}-${width}.png`, fullPage: true });
    }
    await owner.setViewportSize({ width: 1440, height: 1000 });
  }
  await screenshots('index');
  for (const [mode, title] of [['members', '成員投入'], ['projects', '專案投入'], ['trend', '時間趨勢']]) {
    await region.getByRole('button', { name: new RegExp(`^${title}`) }).click();
    await region.locator('.analysis-totals').getByText('3.5 h', { exact: true }).waitFor();
    assert.ok(owner.url().endsWith(`/time-analysis/${mode}`));
    if (mode === 'members') { await region.locator('tbody tr').filter({ hasText: '分析負責人' }).getByText('3 h', { exact: true }).waitFor(); await region.locator('tbody tr').filter({ hasText: '協作成員' }).getByText('0.5 h', { exact: true }).waitFor(); }
    if (mode === 'projects') { await region.locator('tbody tr').filter({ hasText: '會員服務' }).getByText('2 h', { exact: true }).waitFor(); await region.locator('tbody tr').filter({ hasText: '營運維護' }).getByText('1.5 h', { exact: true }).waitFor(); }
    if (mode === 'trend') { await region.getByRole('img', { name: /每日工時趨勢/ }).waitFor(); await region.locator('tbody tr').filter({ hasText: today }).getByText('3.5 h', { exact: true }).waitFor(); }
    await screenshots(mode);
    await owner.reload(); await region.locator('.analysis-totals').getByText('3.5 h', { exact: true }).waitFor();
    await region.getByRole('button', { name: '返回工時分析', exact: true }).click();
  }
  console.log('PASS: 三個分析入口／深連結／重新整理，Epic及子卡各計一次，成員／專案／日期加總一致，三尺寸');
  await region.getByRole('button', { name: /^成員投入/ }).click(); await region.locator('.analysis-totals').getByText('3.5 h', { exact: true }).waitFor();
  await region.getByLabel('分析專案').selectOption(first.id);
  await region.getByText('篩選已調整，請按「套用範圍」更新報表。', { exact: true }).waitFor();
  await Promise.all([owner.waitForResponse(response => response.url().endsWith('/api/rpc/getWorkspace')), owner.getByRole('button', { name: '重新整理工作空間', exact: true }).click()]);
  await region.locator('.analysis-totals').getByText('3.5 h', { exact: true }).waitFor();
  await region.getByRole('button', { name: '套用範圍', exact: true }).click(); await region.locator('.analysis-totals').getByText('2 h', { exact: true }).waitFor();
  await region.getByLabel('分析成員').selectOption(memberIdentity.user.id); await region.getByRole('button', { name: '套用範圍', exact: true }).click(); await region.locator('.analysis-totals').getByText('0.5 h', { exact: true }).waitFor();
  await region.getByLabel('分析專案').selectOption(second.id); await region.getByRole('button', { name: '套用範圍', exact: true }).click(); await region.getByText(/這段期間沒有符合範圍的工時紀錄/).waitFor(); assert.equal(await region.locator('table').count(), 0);
  await region.getByLabel('分析專案').selectOption(''); await region.getByLabel('分析成員').selectOption(''); await region.getByLabel('分析開始日期').fill('2020-01-01'); await region.getByRole('button', { name: '套用範圍', exact: true }).click(); await region.getByRole('alert').filter({ hasText: '最多366天' }).waitFor();
  await region.getByLabel('分析開始日期').fill(`${today.slice(0, 7)}-01`);
  await owner.route('**/api/rpc/getTimeReport', route => route.abort('failed'), { times: 1 });
  await region.getByRole('button', { name: '套用範圍', exact: true }).click(); await region.getByRole('alert').filter({ hasText: '暫時無法連線' }).waitFor();
  assert.equal(await region.locator('table').count(), 0, '錯誤不留舊報表冒充新結果');
  await region.getByRole('button', { name: '重試', exact: true }).click(); await region.locator('.analysis-totals').getByText('3.5 h', { exact: true }).waitFor();
  console.log('PASS: 日期／專案／成員交集、待套用不被背景更新偷用、空資料、日期上限、錯誤及重試');
  await member.reload(); assert.equal(await member.getByRole('button', { name: '工時分析', exact: true }).count(), 0);
  const query = { startDate: today, endDate: today };
  assert.equal((await request(member, 'getTimeReport', query)).status, 403);
  await member.goto('http://127.0.0.1:5175/#/time-analysis/projects'); await member.waitForURL('**/#/');
  await call(owner, 'setWorkspaceMemberRole', { userId: memberIdentity.user.id, roleId: 'manager', expectedRevision: 0 });
  await member.reload(); await member.getByRole('button', { name: '工時分析', exact: true }).click(); await member.getByRole('button', { name: /^專案投入/ }).click();
  const managerRegion = member.getByRole('region', { name: '工時分析', exact: true }); await managerRegion.locator('.analysis-totals').getByText('3.5 h', { exact: true }).waitFor();
  await call(owner, 'setWorkspaceMemberRole', { userId: memberIdentity.user.id, roleId: 'user', expectedRevision: 1 });
  await member.getByRole('button', { name: '重新整理工作空間', exact: true }).click(); await member.waitForURL('**/#/');
  assert.equal(await managerRegion.count(), 0); assert.equal((await request(member, 'getTimeReport', query)).status, 403);
  await owner.getByRole('button', { name: '工時分析', exact: true }).click(); await owner.goBack(); await region.getByRole('heading', { name: '成員投入', exact: true }).waitFor();
  assert.deepEqual(errors, []);
  assert.ok(identity.user.id);
  console.log('PASS: Owner／Manager入口、一般成員後端403與深連結拒絕、撤權清除、返回歷史及無瀏覽器錯誤');
} finally { await browser?.close(); await new Promise(resolve => server?.httpServer.close(resolve)); }
