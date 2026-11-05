import { openProjectList } from './navigation.js';
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { createServer, preview } from 'vite';
import { chromium } from 'playwright';
if (!process.env.E2E_API_URL) throw new Error('請設定獨立隔離 E2E_API_URL');
const artifacts = 'artifacts/account-settings';
mkdirSync(artifacts, { recursive: true });
const built = process.env.E2E_PREVIEW === '1';
let vite, browser;
async function call(page, operation, input = {}, group = 'rpc') {
  return page.evaluate(async ({ operation, input, group }) => {
    const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
    const response = await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify(input) });
    const result = await response.json(); if (!result.ok) throw new Error(result.error?.message); return result.data;
  }, { operation, input, group });
}
try {
  const server = { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } };
  vite = built ? await preview({ preview: server }) : await createServer({ server });
  if (!built) await vite.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const owner = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const member = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const errors = [];
  for (const page of [owner, member]) { page.setDefaultTimeout(15000); page.on('pageerror', error => errors.push(error.message)); }
  const token = crypto.randomUUID();
  await owner.goto('http://127.0.0.1:5175');
  await call(owner, 'register', { name: 'Owner', email: `settings-owner-${token}@example.test`, password: 'settings-ui-test-password', workspaceName: '協作工作區' }, 'auth');
  await owner.reload();
  const avatar = owner.getByRole('button', { name: '帳號選單', exact: true });
  const menu = owner.getByRole('menu', { name: '帳號選項', exact: true });
  const profile = owner.getByRole('dialog', { name: '個人資料', exact: true });
  const settings = owner.locator('.team-dialog');
  async function choose(name) { await avatar.click(); await menu.getByRole('menuitem', { name, exact: true }).click(); }
  async function screenshots(label, target) {
    for (const width of [1440, 768, 390]) {
      await owner.setViewportSize({ width, height: width === 390 ? 844 : 1000 });
      await target.screenshot({ path: `${artifacts}/${label}-${width}.png` });
      assert.equal(await owner.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    }
    if (label === 'menu') {
      await owner.setViewportSize({ width: 844, height: 320 });
      const box = await target.boundingBox(); assert.ok(box.y + box.height <= 320);
      await target.screenshot({ path: `${artifacts}/menu-landscape.png` });
    }
    await owner.setViewportSize({ width: 1440, height: 1000 });
  }
  await avatar.waitFor();
  assert.equal(await owner.locator('.sidebar').getByRole('button', { name: '登出', exact: true }).count(), 0);
  const touch = await avatar.boundingBox(); assert.ok(touch.width >= 44 && touch.height >= 44);
  await avatar.focus(); await owner.keyboard.press('ArrowDown'); await menu.waitFor();
  assert.equal(await menu.getByRole('menuitem', { name: '個人資料', exact: true }).evaluate(el => el === document.activeElement), true);
  await owner.keyboard.press('ArrowDown'); assert.equal(await menu.getByRole('menuitem', { name: '工作空間設定', exact: true }).evaluate(el => el === document.activeElement), true);
  await screenshots('menu', menu);
  await owner.keyboard.press('Escape'); await menu.waitFor({ state: 'hidden' }); assert.equal(await avatar.evaluate(el => el === document.activeElement), true);
  await avatar.click(); await owner.getByRole('heading', { name: '小待辦', exact: true }).click(); await menu.waitFor({ state: 'hidden' });
  await choose('個人資料'); await profile.getByLabel('顯示名稱', { exact: true }).fill('修改後名稱');
  assert.equal(await profile.getByLabel('Email', { exact: true }).getAttribute('readonly'), '');
  owner.once('dialog', dialog => dialog.dismiss()); await owner.keyboard.press('Escape'); assert.equal(await profile.isVisible(), true);
  await screenshots('profile', profile);
  await profile.getByRole('button', { name: '儲存個人資料', exact: true }).click(); await profile.waitFor({ state: 'hidden' });
  assert.equal((await call(owner, 'getWorkspace')).meta.actor, '修改後名稱');
  await choose('個人資料'); await profile.getByLabel('顯示名稱', { exact: true }).fill('衝突保留草稿');
  const profileBase = await call(owner, 'getProfile');
  await call(owner, 'updateProfile', { name: '另一個視窗姓名', expectedRevision: profileBase.revision, requestId: crypto.randomUUID() });
  await profile.getByRole('button', { name: '儲存個人資料', exact: true }).click(); await profile.getByRole('alert').waitFor();
  assert.equal(await profile.getByLabel('顯示名稱', { exact: true }).inputValue(), '衝突保留草稿');
  owner.once('dialog', dialog => dialog.accept()); await profile.getByRole('button', { name: '重新載入個人資料', exact: true }).click();
  await owner.waitForFunction(() => document.querySelector('.profile-dialog input[autocomplete="name"]')?.value === '另一個視窗姓名');
  await profile.getByRole('button', { name: '關閉個人資料', exact: true }).click();
  await choose('工作空間設定'); await settings.getByLabel('工作空間名稱', { exact: true }).fill('工作區草稿');
  owner.once('dialog', dialog => dialog.dismiss()); await settings.getByRole('tab', { name: '成員與權限', exact: true }).click(); assert.equal(await settings.getByLabel('工作空間名稱', { exact: true }).isVisible(), true);
  const workspaceBase = await call(owner, 'getWorkspaceSettings');
  await call(owner, 'updateWorkspace', { name: '另一個視窗空間', expectedRevision: workspaceBase.workspace.revision, requestId: crypto.randomUUID() });
  await settings.getByRole('button', { name: '儲存工作空間名稱', exact: true }).click(); await settings.getByRole('alert').waitFor(); assert.equal(await settings.getByLabel('工作空間名稱', { exact: true }).inputValue(), '工作區草稿');
  owner.once('dialog', dialog => dialog.accept()); await settings.getByRole('button', { name: '載入最新設定', exact: true }).click();
  await owner.waitForFunction(() => document.querySelector('.team-dialog input[maxlength="60"]')?.value === '另一個視窗空間');
  await settings.getByLabel('工作空間名稱', { exact: true }).fill('正式工作區'); await settings.getByRole('button', { name: '儲存工作空間名稱', exact: true }).click(); await settings.getByRole('status').filter({ hasText: '工作空間名稱已儲存' }).waitFor();
  await screenshots('general', settings);
  await settings.getByRole('tab', { name: '一般設定', exact: true }).focus(); await owner.keyboard.press('ArrowRight');
  assert.equal(await settings.getByRole('tab', { name: '成員與權限', exact: true }).getAttribute('aria-selected'), 'true');
  assert.equal(await settings.getByRole('combobox', { name: /^工作空間身份 / }).count(), 0, '擁有者資格不可由一般表單更動');
  await settings.getByLabel('受邀者 Email', { exact: true }).fill(`settings-member-${token}@example.test`); await settings.getByRole('button', { name: '建立邀請連結', exact: true }).click();
  const invite = await settings.getByLabel('邀請連結', { exact: true }).inputValue(); await settings.getByRole('button', { name: '關閉工作空間設定', exact: true }).click();
  await member.goto(invite); await member.getByLabel('你的名字').fill('Member'); await member.getByLabel('Email', { exact: true }).fill(`settings-member-${token}@example.test`); await member.getByLabel('密碼', { exact: true }).fill('settings-ui-test-password'); await member.getByRole('button', { name: '加入工作空間', exact: true }).click();
  await call(owner, 'createProject', { name: '權限測試專案', code: 'ACCOUNT', requestId: crypto.randomUUID() });
  await choose('工作空間設定'); await settings.getByRole('tab', { name: '成員與權限', exact: true }).click();
  const memberRole = settings.getByLabel('工作空間身份 Member', { exact: true });
  owner.once('dialog', dialog => dialog.dismiss());
  await memberRole.selectOption('manager');
  assert.equal(await memberRole.inputValue(), 'user', '取消確認恢復原身份');
  const roleRequests = [];
  const failRoleSave = async route => { roleRequests.push(route.request().postDataJSON()); await route.abort('failed'); };
  await owner.route('**/api/rpc/setWorkspaceMemberRole', failRoleSave);
  owner.once('dialog', dialog => dialog.accept());
  await memberRole.selectOption('manager');
  await settings.getByRole('alert').waitFor();
  assert.equal(await memberRole.inputValue(), 'user', '儲存失敗仍顯示伺服器原身份');
  await owner.unroute('**/api/rpc/setWorkspaceMemberRole', failRoleSave);
  owner.on('request', request => { if (request.url().endsWith('/api/rpc/setWorkspaceMemberRole')) roleRequests.push(request.postDataJSON()); });
  for (const role of ['manager', 'user']) {
    owner.once('dialog', dialog => dialog.accept());
    await memberRole.selectOption(role);
    await settings.getByRole('status').filter({ hasText: '工作空間角色已儲存' }).waitFor();
    assert.equal(await memberRole.inputValue(), role);
    if (role === 'manager') assert.equal(roleRequests[0].requestId, roleRequests[1].requestId, '重選相同身份沿用requestId');
    if (role === 'user') await screenshots('members', settings);
    await member.getByRole('button', { name: '重新整理工作空間', exact: true }).click(); await openProjectList(member); await member.getByRole('button', { name: '進入專案：權限測試專案', exact: true }).waitFor({ state: role === 'manager' ? 'visible' : 'hidden' });
  }
  await settings.getByRole('button', { name: '關閉工作空間設定', exact: true }).click();
  await member.getByRole('button', { name: '帳號選單', exact: true }).click(); await member.getByRole('menuitem', { name: '工作空間設定', exact: true }).click();
  const readonly = member.locator('.team-dialog'); await readonly.getByLabel('工作空間名稱', { exact: true }).waitFor(); assert.equal(await readonly.getByLabel('工作空間名稱', { exact: true }).getAttribute('readonly'), ''); assert.equal(await readonly.getByRole('button', { name: '儲存工作空間名稱', exact: true }).count(), 0);
  await readonly.getByRole('tab', { name: '成員與權限', exact: true }).click(); assert.equal(await readonly.getByRole('button', { name: /調整工作空間角色|建立邀請連結/ }).count(), 0); assert.equal(await readonly.getByRole('combobox', { name: /^工作空間身份 / }).count(), 0); await readonly.getByRole('button', { name: '關閉工作空間設定', exact: true }).click();
  await owner.setViewportSize({ width: 390, height: 844 });
  await owner.getByRole('button', { name: '開啟導覽', exact: true }).click();
  await owner.getByRole('button', { name: '工作空間成員', exact: true }).click();
  await settings.getByRole('button', { name: '關閉工作空間設定', exact: true }).click();
  await owner.waitForFunction(() => document.activeElement?.getAttribute('aria-label') === '帳號選單');
  await owner.setViewportSize({ width: 1440, height: 1000 });
  await openProjectList(owner);
  await owner.getByRole('button', { name: '進入專案：權限測試專案', exact: true }).click(); const hash = await owner.evaluate(() => location.hash);
  await choose('工作空間設定'); await settings.getByRole('button', { name: '關閉工作空間設定', exact: true }).click(); assert.equal(await owner.evaluate(() => location.hash), hash);
  await owner.getByRole('button', { name: '專案成員與權限', exact: true }).click(); await owner.getByRole('region', { name: '專案成員與權限', exact: true }).waitFor(); await owner.getByRole('link', { name: 'TaskTracker 返回首頁', exact: true }).click();
  await owner.getByLabel('快速新增待辦', { exact: true }).fill('未保存的速記'); owner.once('dialog', dialog => dialog.dismiss()); await choose('登出'); assert.equal(await owner.getByLabel('快速新增待辦', { exact: true }).inputValue(), '未保存的速記');
  await owner.getByLabel('快速新增待辦', { exact: true }).fill(''); await choose('登出'); await owner.getByRole('button', { name: '登入', exact: true }).waitFor(); assert.deepEqual(errors, []);
  console.log('PASS: 帳號選單鍵盤／外點／焦點、個人與空間改名衝突草稿、固定角色升降與專案權限入口、一般成員唯讀、登出保護、三尺寸');
} finally { await browser?.close(); if (built) await new Promise(resolve => vite?.httpServer.close(resolve)); else await vite?.close(); }
