import { fixtureResponse } from '../fixtures/verification.js';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { mkdirSync, writeFileSync } from 'node:fs';
import screenshotPolicy from './policy.json' with { type: 'json' };
import { createRequire } from 'node:module';
import { test, expect } from '@playwright/test';
import { openProjectList } from '../navigation.js';
import { moveBoardCard } from '../board-actions.js';

const require = createRequire(import.meta.url);
const playwrightVersion = require('playwright/package.json').version;
const origin = process.env.TASKTRACKER_VISUAL_ORIGIN;
const fixedTime = new Date('2026-11-13T12:00:00+08:00');
const password = 'synthetic-visual-test-only';
async function call(request, operation, input = {}, group = 'rpc', verifiedFixture = true) {
  const csrf = await (await request.get('/api/csrf-cookie')).json();
  const response = await request.post(`/api/${group}/${operation}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken, Origin: origin }, data: { requestId: crypto.randomUUID(), ...input } });
  const result = verifiedFixture ? await fixtureResponse(request, response) : await response.json();
  expect(result.ok, `${operation}: ${JSON.stringify(result.error || '')}`).toBe(true);
  return result.data;
}
async function navigation(page) {
  const toggle = page.getByRole('button', { name: '開啟導覽', exact: true });
  if (await toggle.isVisible() && await toggle.getAttribute('aria-expanded') !== 'true') await toggle.click();
}
async function shot(page, name) {
  // Detail/editor load attachment limits and relations independently; capture the settled screen.
  if (['07-detail', '08-editor'].includes(name)) await page.waitForLoadState('networkidle');
  if (name === '07-detail') {
    const activity = page.getByRole('region', { name: '任務完整頁', exact: true }).locator('.issue-detail-activity');
    await activity.getByText('尚無留言', { exact: true }).waitFor();
    await activity.getByRole('button', { name: '選擇新增留言圖片', exact: true }).waitFor();
  }
  await page.evaluate(() => document.fonts.ready);
  await page.mouse.move(0, 0);
  let injected;
  if (name === '05-issues' && process.env.VISUAL_INJECT) {
    const css = {
      spacing: '.page-heading { padding-top: 60px !important; }',
      overflow: 'main { min-width: calc(100vw + 160px) !important; }',
      'hide-action': '.page-heading .primary { visibility: hidden !important; }',
    }[process.env.VISUAL_INJECT];
    if (!css) throw new Error('Unknown visual fault');
    injected = await page.addStyleTag({ content: css });
  }
  try {
    await expect(page).toHaveScreenshot(`${name}.png`, { fullPage: false });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  } finally { await injected?.evaluate(element => element.remove()); }
}
async function fixture(page, browser) {
  const email = `visual-${crypto.randomUUID()}@example.test`;
  const identity = await call(page.request, 'register', { name: '視覺驗收者', email, password, workspaceName: '產品工作區' }, 'auth');
  const project = await call(page.request, 'createProject', { name: '產品開發', code: 'VRT', description: '協作任務與發佈追蹤', leadId: identity.user.id });
  const epic = await call(page.request, 'createIssue', { projectId: project.id, title: '會員功能', type: 'epic', state: 'open', assignee: identity.user.id });
  const tasks = [];
  for (let index = 0; index < 8; index++) tasks.push(await call(page.request, 'createIssue', { projectId: project.id, title: ['建立登入畫面', '驗證錯誤提示', '調整手機版導覽', '整理操作說明', '完成帳號設定', '測試資料隔離', '準備上線檢查', '確認郵件通知'][index], type: index === 1 ? 'bug' : 'task', state: index % 3 === 1 ? 'inprogress' : index % 3 === 2 ? 'closed' : 'open', description: '## 交付條件\n\n- 保留既有操作流程\n- 驗證桌面與手機版', assignee: identity.user.id, epicId: epic.id, priority: index === 1 ? 'high' : 'normal', estimateMinutes: 90, dueDate: '2026-11-30' }));
  const board = await call(page.request, 'createBoard', { projectId: project.id, name: 'API整合', description: '公開介面與外部工具', selectedIssueIds: [epic.id] });
  const empty = await call(page.request, 'createProject', { name: '空白專案', code: 'EMPTY', description: '尚未建立卡片' });
  await call(page.request, 'createTodo', { title: '確認下一次測試範圍', content: '確認下一次測試範圍\n\n- [ ] 確認測試資料\n- [ ] 補齊操作紀錄' });
  const otherContext = await browser.newContext({ baseURL: origin });
  try {
    const other = await call(otherContext.request, 'register', { name: '另一位管理者', email: `visual-other-${crypto.randomUUID()}@example.test`, password, workspaceName: '受邀工作區' }, 'auth');
    const privateProject = await call(otherContext.request, 'createProject', { name: '未授權專案', code: 'SECRET' });
    const invite = await call(otherContext.request, 'inviteMember', { email });
    await call(page.request, 'acceptWorkspaceInvite', { inviteToken: invite.token }, 'auth');
    await call(page.request, 'switchWorkspace', { workspaceId: identity.workspace.id }, 'auth');
    execFileSync('docker', ['compose', '-p', 'tasktracker-codex-visual', '-f', resolve(import.meta.dirname, '../../../docker-compose.mysql-test.yml'), 'exec', '-T', 'backend', 'php', 'tools/visual-fixture-clock.php'], { cwd: resolve(import.meta.dirname, '../../..'), input: JSON.stringify({ userId: identity.user.id, workspaceId: identity.workspace.id, projectId: project.id, emptyId: empty.id }), stdio: ['pipe', 'pipe', 'pipe'] });
    return { identity, project, empty, tasks, board, other, privateProject };
  } finally { await otherContext.close(); }
}

test('representative workspace flows', async ({ page, browser }) => {
  mkdirSync(new URL('../../../artifacts/visual/', import.meta.url), { recursive: true });
  writeFileSync(new URL('../../../artifacts/visual/environment.json', import.meta.url), JSON.stringify({ origin, platform: process.platform, arch: process.arch, browser: browser.version(), playwright: playwrightVersion, dpr: 1, locale: 'zh-TW', timezone: 'Asia/Taipei', clock: fixedTime.toISOString(), colorScheme: 'light', reducedMotion: 'reduce', chromiumArgs: ['--disable-gpu'], comparison: { maxDiffPixels: screenshotPolicy.maxDiffPixels, threshold: screenshotPolicy.threshold } }, null, 2));
  page.setDefaultTimeout(15000);
  await page.clock.setFixedTime(fixedTime);
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto('/');
  await page.getByRole('heading', { level: 1 }).waitFor();
  await page.waitForFunction(() => !document.documentElement.classList.contains('auth-pending'));
  await shot(page, '21-public-entry');
  await page.goto('/login');
  await page.getByRole('button', { name: '登入', exact: true }).waitFor();
  await shot(page, '01-login');
  // Native validation is a real browser state, not a mocked product response.
  await page.getByLabel('Email', { exact: true }).fill('invalid');
  await page.getByRole('button', { name: '登入', exact: true }).click();
  expect(await page.getByLabel('Email', { exact: true }).evaluate(element => element.validity.valid)).toBe(false);
  await shot(page, '02-validation');
  // Change only our ephemeral visual topology's compiled config; no product test endpoint.
  const compose = ['compose', '-p', 'tasktracker-codex-visual', '-f', resolve(import.meta.dirname, '../../../docker-compose.mysql-test.yml'), 'exec', '-T'];
  try {
    execFileSync('docker', [...compose, '-e', 'TASKTRACKER_PUBLIC_REGISTRATION=false', 'backend', 'php', 'artisan', 'config:cache'], { stdio: 'pipe' });
    await page.goto('/login?mode=register');
    await page.getByText('目前僅接受邀請註冊', { exact: true }).waitFor();
    await shot(page, '22-invitation-only');
  } finally { execFileSync('docker', [...compose, 'backend', 'php', 'artisan', 'config:clear'], { stdio: 'pipe' }); }
  await page.goto('/login');

  await call(page.request, 'register', { name: '驗證畫面測試', email: `visual-pending-${test.info().project.name}@example.test`, password, workspaceName: '驗證畫面' }, 'auth', false);
  // Visual fixture metadata uses the same fixed clock as the browser; real cooldown is covered by HTTP tests.
  let deliveryStatus = 'pending';
  await page.route('**/api/auth/current', async route => {
    const response = await route.fetch();
    const body = await response.json();
    body.data.deliveryStatus = deliveryStatus;
    body.data.resendAt = new Date(fixedTime.getTime() + 60000).toISOString();
    body.data.expiresAt = new Date(fixedTime.getTime() + 1800000).toISOString();
    body.data.resendAfterSeconds = 60;
    await route.fulfill({ response, json: body });
  });
  await page.reload();
  await page.getByRole('heading', { name: '驗證信箱', exact: true }).waitFor();
  await shot(page, '17-verification');
  deliveryStatus = 'failed';
  await page.reload();
  await page.getByRole('alert').filter({ hasText: '驗證信未寄出' }).waitFor();
  await shot(page, '23-verification-mail-failed');
  await page.unroute('**/api/auth/current');
  await page.reload();
  await page.getByText('請點擊信件中的啟用連結', { exact: true }).waitFor();

  await call(page.request, 'logout', {}, 'auth');
  await page.goto('/login#/activate/' + 'a'.repeat(64));
  await page.getByRole('button', { name: '啟用帳號', exact: true }).waitFor();
  await shot(page, '18-activation');
  await page.goto('/login');
  const data = await fixture(page, browser);
  await page.reload();
  await page.locator('.app-shell').waitFor();
  await shot(page, '24-home-empty');
  await navigation(page); await page.getByRole('link', { name: '紙條', exact: true }).click();
  await page.getByText('確認下一次測試範圍', { exact: true }).first().waitFor();
  await shot(page, '03-notes-list');
  const todo = (await (await page.request.get('/api/v1/todos?scope=mine&state=open')).json()).data.items[0];
  await call(page.request, 'commentTodo', { id: todo.id, expectedRevision: todo.revision, content: '已確認測試資料, 接著補上手機版驗證\n\n請確認VRT-2與#VRT-2' });
  execFileSync('docker', ['compose', '-p', 'tasktracker-codex-visual', '-f', resolve(import.meta.dirname, '../../../docker-compose.mysql-test.yml'), 'exec', '-T', 'backend', 'php', 'tools/visual-fixture-clock.php'], { cwd: resolve(import.meta.dirname, '../../..'), input: JSON.stringify({ userId: data.identity.user.id, workspaceId: data.identity.workspace.id, projectId: data.project.id, emptyId: data.empty.id }), stdio: ['pipe', 'pipe', 'pipe'] });
  await page.reload();
  await page.locator(`[data-todo-id="${todo.id}"]`).getByRole('button', { name: /^更多：/ }).click();
  const conversation = page.getByRole('dialog', { name: '紙條', exact: true });
  await conversation.getByText('已確認測試資料, 接著補上手機版驗證', { exact: true }).waitFor();
  await shot(page, '19-todo-conversation');
  await conversation.getByLabel('回覆內容', { exact: true }).fill('確認後交給下一位處理');
  await conversation.getByLabel('要傳給誰', { exact: true }).waitFor();
  await shot(page, '20-todo-forward');
  await conversation.getByLabel('回覆內容', { exact: true }).fill('');
  await conversation.getByRole('button', { name: '關閉紙條', exact: true }).click();

  await openProjectList(page);
  await page.getByRole('button', { name: '進入專案：產品開發', exact: true }).waitFor();
  await shot(page, '04-projects');
  await page.getByRole('button', { name: '進入專案：產品開發', exact: true }).click();
  await page.getByRole('button', { name: '建立登入畫面', exact: true }).waitFor();
  await shot(page, '05-issues');
  await page.getByRole('button', { name: '任務篩選', exact: true }).click();
  await page.getByLabel('篩選類型', { exact: true }).waitFor();
  await shot(page, '06-filters');
  await page.getByRole('button', { name: '任務篩選', exact: true }).click();
  await page.getByRole('button', { name: '建立登入畫面', exact: true }).click();
  const detail = page.getByRole('region', { name: '任務完整頁', exact: true });
  await detail.getByRole('heading', { name: '建立登入畫面', exact: true }).waitFor();
  await shot(page, '07-detail');
  await detail.getByRole('button', { name: '返回任務列表', exact: true }).click();
  await page.locator('.issue-table tbody tr').filter({ has: page.getByRole('button', { name: '建立登入畫面', exact: true }) }).getByRole('button', { name: /^快速編輯 / }).click();
  const editor = page.getByRole('dialog', { name: '任務詳細內容', exact: true });
  await editor.getByRole('textbox', { name: /任務標題/ }).waitFor();
  await expect(editor.getByRole('textbox', { name: /任務標題/ })).toBeFocused();
  await shot(page, '08-editor');
  // Focus must stay inside the actual modal even while tabbing backwards.
  await page.keyboard.press('Shift+Tab');
  expect(await editor.evaluate(element => element.contains(document.activeElement))).toBe(true);
  await page.keyboard.press('Escape');
  await expect(editor).not.toBeVisible();
  await navigation(page);
  await page.getByRole('link', { name: '任務看板', exact: true }).click();
  await page.getByRole('button', { name: '看板操作 API整合', exact: true }).waitFor();
  await shot(page, '25-board-list');
  await page.getByRole('button', { name: '看板操作 API整合', exact: true }).click();
  await page.getByRole('menuitem', { name: '封存看板 API整合', exact: true }).waitFor();
  await shot(page, '26-board-menu');
  await page.getByRole('menuitem', { name: '封存看板 API整合', exact: true }).click();
  const archiveConfirm = page.getByRole('dialog', { name: '封存看板「API整合」？', exact: true });
  await archiveConfirm.waitFor();
  await shot(page, '27-board-archive-confirm');
  await archiveConfirm.getByRole('button', { name: '取消', exact: true }).click();
  await page.getByRole('button', { name: '開啟看板 Default', exact: true }).click();
  await page.locator('.issue-card').first().waitFor();
  await shot(page, '09-board');
  await moveBoardCard(page, 'VRT-2', 'inprogress');
  await page.locator('[data-column="inprogress"] [data-issue="VRT-2"]').waitFor();
  await page.locator('.board-scroll').evaluate(element => { element.scrollLeft = 0; });
  await shot(page, '10-board-dragged');
  await page.goto(`/app/#/projects/${data.empty.id}/board`);
  await page.getByLabel('看板泳道').waitFor();
  await expect(page.locator('.issue-card')).toHaveCount(0);
  await shot(page, '11-board-empty');
  // Delay a real read to capture loading without fake data or replacing the API result.
  let release;
  const barrier = new Promise(resolve => { release = resolve; });
  await page.route('**/api/v1/projects?*', async route => { await barrier; await route.continue(); }, { times: 1 });
  await page.goto('/app/#/projects');
  await page.getByRole('heading', { name: '正在載入專案…', exact: true }).waitFor();
  await shot(page, '12-loading');
  release();
  await page.getByRole('button', { name: '進入專案：產品開發', exact: true }).waitFor();
  // Network loss exercises the real product error handler, not a synthetic response DTO.
  await page.route('**/api/v1/projects?*', route => route.abort('failed'));
  await page.reload();
  await page.getByRole('region', { name: '專案列表', exact: true }).getByRole('alert').waitFor();
  await shot(page, '13-network-error');
  await page.unroute('**/api/v1/projects?*');
  await page.reload();
  await page.getByRole('button', { name: '進入專案：產品開發', exact: true }).waitFor();
  await navigation(page);
  await page.getByRole('button', { name: '切換工作空間', exact: true }).click();
  const picker = page.getByRole('dialog', { name: '選擇工作空間', exact: true });
  await picker.getByRole('button', { name: '選擇工作空間 受邀工作區', exact: true }).waitFor();
  await shot(page, '14-workspace-picker');
  await picker.getByRole('button', { name: '選擇工作空間 受邀工作區', exact: true }).click();
  await expect(picker).not.toBeVisible();
  await page.locator('.app-shell').waitFor();

  await expect(page.getByRole('link', { name: '工作空間設定', exact: true })).toHaveCount(0);
  await openProjectList(page);
  await page.getByRole('heading', { name: '尚未加入任何專案', exact: true }).waitFor();
  await shot(page, '15-member-empty');
  await page.goto(`/app/#/projects/${data.privateProject.id}`);
  await page.getByRole('status').filter({ hasText: '專案存取權限已變更' }).waitFor();
  await expect(page.getByRole('button', { name: '新增任務', exact: true })).toHaveCount(0);
  await shot(page, '16-denied');
  await call(page.request, 'switchWorkspace', { workspaceId: data.identity.workspace.id }, 'auth');
  await page.goto('/login'); await page.locator('.app-shell').waitFor();
  const replyContext = await browser.newContext({ baseURL: origin });
  try {
    const recipientEmail = `reply-${crypto.randomUUID()}@example.test`;
    const invitation = await call(page.request, 'inviteMember', { email: recipientEmail });
    await call(replyContext.request, 'register', { name: '協作成員', email: recipientEmail, password, inviteToken: invitation.token }, 'auth');
    const replyNote = await call(replyContext.request, 'createTodo', { content: '回覆上一手驗收\n\n請確認VRT-2與#VRT-2', assigneeId: data.identity.user.id });
    execFileSync('docker', ['compose', '-p', 'tasktracker-codex-visual', '-f', resolve(import.meta.dirname, '../../../docker-compose.mysql-test.yml'), 'exec', '-T', 'backend', 'php', 'tools/visual-fixture-clock.php'], { cwd: resolve(import.meta.dirname, '../../..'), input: JSON.stringify({ userId: data.identity.user.id, workspaceId: data.identity.workspace.id, projectId: data.project.id, emptyId: data.empty.id }), stdio: ['pipe', 'pipe', 'pipe'] });
    await page.goto('/app/#/notes');
    await page.locator(`[data-todo-id="${replyNote.id}"]`).getByRole('button', { name: /^更多：/ }).click();
    const replyDrawer = page.getByRole('dialog', { name: '紙條', exact: true });
    await replyDrawer.getByLabel('要傳給誰', { exact: true }).selectOption('__previous__');
    await replyDrawer.getByLabel('回覆內容', { exact: true }).fill('已確認, 請接著處理');
    await expect(replyDrawer.getByRole('button', { name: '回覆上一手', exact: true })).toBeEnabled();
    await shot(page, '28-note-reply-previous');
  } finally { await replyContext.close(); }
  expect(errors).toEqual([]);
});
