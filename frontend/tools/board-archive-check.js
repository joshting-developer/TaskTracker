// TT-283：看板封存→已封存清單→唯讀查看→還原（看板頁與清單兩個入口）；三尺寸版面。只接受隔離API。
import assert from 'node:assert/strict';
import { app as inspectApp } from '../../.agents/skills/design-taste-frontend/scripts/lib/probe.mjs';
import { judgeApp } from '../../.agents/skills/design-taste-frontend/scripts/lib/rules.mjs';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';
const e2ePort = Number(process.env.E2E_FRONTEND_PORT || 5175); // TT-335：Claude 5198、Codex 5199, 見.agents/skills/tasktracker-test-environments
const api = process.env.E2E_API_URL || '';
if (!/^http:\/\/127\.0\.0\.1:13\d{3}$/.test(api)) throw new Error('請設定隔離E2E_API_URL（127.0.0.1:13xxx），不可使用業務資料庫');
const folder = 'artifacts/tt333';
mkdirSync(folder, { recursive: true });
let server, browser;
const errors = [], measurements = [];
async function call(page, operation, input = {}, group = 'rpc') {
  return page.evaluate(async ({ operation, input, group }) => {
    const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
    const result = await (await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
    if (!result.ok) throw new Error(result.error?.message || operation);
    if (result.data?.verificationRequired) { const csrf = await (await fetch('/api/csrf-cookie')).json(); const verified = await fetch('/api/auth/verify-email', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: JSON.stringify({ code: 'local666' }) }); if (!verified.ok) throw new Error('Fixture email verification failed'); result.data = (await verified.json()).data; } return result.data;
  }, { operation, input, group });
}
try {
  server = await createServer({ server: { host: '127.0.0.1', port: e2ePort, strictPort: true, proxy: { '/api': api } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, locale: 'zh-TW', reducedMotion: 'reduce' });
  page.setDefaultTimeout(15000);
  page.on('pageerror', error => errors.push(error.message));
  await page.goto(`http://127.0.0.1:${e2ePort}/login`);
  await call(page, 'register', { name: '封存驗收', email: `tt283-${crypto.randomUUID()}@example.test`, password: 'tt283-test-password', workspaceName: '封存驗收' }, 'auth');
  const project = await call(page, 'createProject', { name: '封存流程', code: 'TTARC' });
  const issue = await call(page, 'createIssue', { projectId: project.id, title: '留在看板裡的卡片' });
  const board = await call(page, 'createBoard', { projectId: project.id, name: '第一階段', selectedIssueIds: [issue.id] });
  const root = `http://127.0.0.1:${e2ePort}/app/#/projects/${project.id}`;

  // 從看板頁封存：文案是「封存」, 確認後回清單。
  await page.goto(`${root}/board/${board.id}`);
  await page.getByRole('heading', { name: '第一階段' }).waitFor();
  await page.evaluate(() => { window.confirm = () => { throw new Error('Native confirm must not run'); }; });
  await page.getByRole('button', { name: '封存看板', exact: true }).click();
  await page.getByRole('dialog', { name: '封存看板「第一階段」？' }).getByRole('button', { name: '封存看板', exact: true }).click();
  const list = page.getByRole('region', { name: '看板列表' });
  await list.waitFor();
  assert.equal(await list.getByRole('button', { name: '開啟看板 第一階段' }).count(), 0, '使用中清單不再顯示');

  // 已封存清單：顯示封存日期, 沒有新增看板與編輯收錄。
  await list.getByRole('button', { name: '已封存', exact: true }).click();
  await list.getByRole('button', { name: '開啟看板 第一階段' }).waitFor();
  assert.equal(await list.getByRole('button', { name: '已封存' }).getAttribute('aria-pressed'), 'true');
  assert.match(await list.locator('.board-summary-archived').innerText(), /封存於/);
  assert.equal(await list.getByRole('button', { name: '新增看板' }).count(), 0);
  assert.equal(await list.getByRole('button', { name: /^編輯看板/ }).count(), 0);
  for (const [width, height] of [[1440, 900], [768, 1024], [390, 844]]) {
    await page.setViewportSize({ width, height }); await page.waitForTimeout(100);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
    measurements.push({ view: 'archived-list', width, overflow }); assert.equal(overflow, false, `${width} 清單無橫向溢出`);
    await page.screenshot({ path: `${folder}/${width}-archived-list.png`, animations: 'disabled', caret: 'hide' });
  }
  await page.setViewportSize({ width: 1440, height: 900 });

  // 唯讀查看：有提示、卡片照常顯示、不能新增或拖曳、沒有編輯收錄／封存。
  await list.getByRole('button', { name: '開啟看板 第一階段' }).click();
  const note = page.getByRole('status').filter({ hasText: '這個看板已封存' });
  await note.waitFor();
  await page.locator(`.issue-card[data-issue="${issue.identifier}"]`).waitFor();
  assert.equal(await page.locator(`.issue-card[data-issue="${issue.identifier}"]`).getAttribute('draggable'), 'false');
  assert.equal(await page.locator('.lane-add').count(), 0, '封存看板不能新增卡片');
  assert.equal(await page.getByRole('button', { name: '封存看板' }).count(), 0);
  for (const [width, height] of [[1440, 900], [390, 844]]) {
    await page.setViewportSize({ width, height }); await page.waitForTimeout(100);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
    measurements.push({ view: 'archived-board', width, overflow }); assert.equal(overflow, false, `${width} 看板頁無橫向溢出`);
    await page.screenshot({ path: `${folder}/${width}-archived-board.png`, animations: 'disabled', caret: 'hide' });
  }
  await page.setViewportSize({ width: 1440, height: 900 });

  // 從看板頁還原：提示消失, 恢復編輯收錄與封存。
  await note.getByRole('button', { name: '還原看板' }).click();
  await page.getByRole('dialog', { name: '還原看板「第一階段」？' }).getByRole('button', { name: '還原看板', exact: true }).click();
  await page.getByRole('button', { name: '封存看板', exact: true }).waitFor();
  assert.equal(await note.count(), 0);

  // 再封存一次, 改從清單還原, 回到使用中。
  await page.getByRole('button', { name: '封存看板', exact: true }).click();
  await page.getByRole('dialog', { name: '封存看板「第一階段」？' }).getByRole('button', { name: '封存看板', exact: true }).click();
  await list.waitFor();
  await list.getByRole('button', { name: '已封存', exact: true }).click();
  await list.getByRole('button', { name: '看板操作 第一階段' }).click();
  await page.getByRole('menuitem', { name: '還原看板 第一階段' }).click();
  await page.getByRole('dialog', { name: '還原看板「第一階段」？' }).getByRole('button', { name: '還原看板', exact: true }).click();
  await list.getByText('目前沒有封存的看板').waitFor();
  await list.getByRole('button', { name: '使用中', exact: true }).click();
  await list.getByRole('button', { name: '開啟看板 第一階段' }).waitFor();
  assert.equal(await list.locator('[data-board-actions="default"]').count(), 0);
  for (const [width, height] of [[1440, 900], [768, 1024], [390, 844]]) {
    await page.setViewportSize({ width, height });
    await list.getByRole('button', { name: '看板操作 第一階段' }).click();
    const menu = page.getByRole('menu', { name: '看板操作選項' });
    await menu.waitFor();
    await page.waitForFunction(() => { const box = document.querySelector('[aria-label="看板操作選項"]')?.getBoundingClientRect(); return box && box.x >= 0 && box.right <= innerWidth; });
    const menuBox = await menu.boundingBox();
    assert.ok(menuBox.x >= 0 && menuBox.x + menuBox.width <= width, '操作選單不超出畫面');
    await page.screenshot({ path: `${folder}/${width}-board-menu.png`, animations: 'disabled' });
    await page.getByRole('menuitem', { name: '封存看板 第一階段' }).click();
    await page.getByRole('dialog', { name: '封存看板「第一階段」？' }).waitFor();
    await page.getByRole('menu', { name: '看板操作選項' }).waitFor({ state: 'hidden' });
    assert.equal(await page.getByRole('menu', { name: '看板操作選項' }).count(), 0, '確認視窗出現後選單已關閉');
    const design = await page.evaluate(inspectApp);
    measurements.push({ view: 'design-review', width, findings: judgeApp(design) });
    const confirm = page.getByRole('dialog', { name: '封存看板「第一階段」？' });
    await confirm.waitFor();
    await page.screenshot({ path: `${folder}/${width}-archive-confirm.png`, animations: 'disabled' });
    assert.match(await confirm.innerText(), /卡片、留言和工時都會保留/);
    assert.equal(await confirm.getByRole('button', { name: '取消', exact: true }).evaluate(element => element === document.activeElement), true, '預設焦點放取消');
    await page.keyboard.press('Shift+Tab');
    assert.equal(await confirm.evaluate(element => element.contains(document.activeElement)), true, '模態限制鍵盤焦點');
    for (const button of await confirm.getByRole('button').all()) assert.ok((await button.boundingBox()).height >= 44);
    await page.keyboard.press('Escape');
    await confirm.waitFor({ state: 'detached' });
    assert.equal(await list.getByRole('button', { name: '開啟看板 第一階段' }).count(), 1, '取消不封存');
  }
  await list.getByRole('button', { name: '看板操作 第一階段' }).click();
  await page.getByRole('menuitem', { name: '封存看板 第一階段' }).click();
  const staleDialog = page.getByRole('dialog', { name: '封存看板「第一階段」？' });
  const latest = await page.evaluate(async ({ project, board }) => (await (await fetch(`/api/v1/projects/${project}/boards/${board}`)).json()).data, { project: project.id, board: board.id });
  await call(page, 'updateBoard', { id: latest.id, name: latest.name, description: '另一個呼叫端更新', selectedIssueIds: latest.selectedIssueIds, expectedRevision: latest.revision });
  await staleDialog.getByRole('button', { name: '封存看板', exact: true }).click();
  await staleDialog.getByRole('alert').waitFor();
  assert.equal(await staleDialog.getByRole('button', { name: '封存看板', exact: true }).isDisabled(), true, '版本衝突不能盲目換版本');
  await staleDialog.getByRole('button', { name: '重新載入', exact: true }).click();
  await staleDialog.getByRole('button', { name: '封存看板', exact: true }).click();
  await list.getByRole('button', { name: '已封存', exact: true }).click();
  await list.getByRole('button', { name: '開啟看板 第一階段' }).waitFor();
  const otherContext = await browser.newContext();
  try {
    const reader = await otherContext.newPage();
    await reader.goto(`http://127.0.0.1:${e2ePort}/login`);
    const email = `tt333-reader-${crypto.randomUUID()}@example.test`;
    const invite = await call(page, 'inviteMember', { email });
    const member = await call(reader, 'register', { name: 'Reader', email, password: 'tt333-reader-password', inviteToken: invite.token }, 'auth');
    await call(page, 'setProjectMember', { projectId: project.id, userId: member.user.id, roleId: 'reader', expectedRevision: 0 });
    await reader.goto(`${root}/boards`);
    const readerList = reader.getByRole('region', { name: '看板列表' });
    await readerList.getByRole('button', { name: '開啟看板 Default' }).waitFor();
    assert.equal(await readerList.locator('[data-board-actions]').count(), 0, '沒有board.manage不顯示操作');
  } finally { await otherContext.close(); }
  assert.deepEqual(errors, []);
  writeFileSync(`${folder}/measurements.json`, JSON.stringify(measurements, null, 2));
  console.log('PASS: archive copy, archived list with date, read-only archived board, restore from board and from list, 3 viewports');
} finally {
  await browser?.close();
  await server?.close();
}
