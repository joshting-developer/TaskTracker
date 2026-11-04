import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';

const apiTarget = process.env.E2E_API_URL;
if (!apiTarget) throw new Error('請設定 E2E_API_URL 指向隔離 Laravel 測試服務');
const artifacts = 'artifacts/usability-fixes-2026-11-04';
mkdirSync(artifacts, { recursive: true });
let vite, browser;
const failures = [];
async function register(page, email, inviteToken = '') {
  await page.goto(`http://127.0.0.1:5175/${inviteToken ? `#/invite?token=${inviteToken}` : ''}`);
  if (!inviteToken) await page.getByRole('button', { name: '第一次使用？建立工作空間' }).click();
  await page.getByLabel('你的名字').fill(inviteToken ? 'Flow Member' : 'Flow Owner');
  await page.getByLabel('Email', { exact: true }).fill(email);
  await page.getByLabel('密碼', { exact: true }).fill('flow-test-password');
  if (!inviteToken) await page.getByLabel('工作空間名稱', { exact: true }).fill('流程回驗空間');
  await page.getByRole('button', { name: inviteToken ? '加入工作空間' : '建立工作空間', exact: true }).click();
  await page.getByRole('heading', { name: '我的專案.', exact: true }).waitFor();
}
try {
  vite = await createServer({ server: { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': apiTarget } } });
  await vite.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const member = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  for (const target of [page, member]) { target.setDefaultTimeout(12000); target.on('pageerror', error => failures.push(error.message)); }
  const token = crypto.randomUUID();
  await register(page, `flow-owner-${token}@example.test`);
  const email = `flow-member-${token}@example.test`;
  const invite = await page.evaluate(async email => (await import('/src/api/index.js')).api.inviteMember({ email }), email);
  await register(member, email, invite.token);
  const fixture = await page.evaluate(async email => {
    const { api } = await import('/src/api/index.js');
    const ws = await api.getWorkspace(), person = ws.members.find(item => item.email === email);
    for (let i = 1; i <= 4; i++) await api.createTodo({ title: `個人摘要 ${i}`, requestId: crypto.randomUUID() });
    for (let i = 1; i <= 5; i++) await api.createTodo({ title: `交辦事項 ${i}`, assigneeId: person.id, requestId: crypto.randomUUID() });
    return { memberId: person.id, ownerId: ws.meta.userId };
  }, email);
  await page.reload();
  const todos = page.getByRole('region', { name: '首頁小待辦', exact: true });
  await todos.locator('.todo-row').first().waitFor();
  const first = todos.locator('.todo-row').first();
  const editedId = await first.getAttribute('data-todo-id');
  await first.getByLabel('待辦操作').click();
  await first.getByRole('button', { name: '編輯', exact: true }).click();
  await todos.getByLabel('編輯待辦內容').fill('切換後保留的草稿');
  await todos.getByRole('tab', { name: /我交辦的/ }).click();
  await todos.getByRole('status').filter({ hasText: '切換清單會保留草稿' }).waitFor();
  assert.equal(await todos.locator('.todo-actions').count(), 3, '其他 tab 操作仍可用');
  await todos.getByRole('button', { name: '顯示全部待辦（5）', exact: true }).click();
  assert.equal(await todos.locator('.todo-row').count(), 5);
  // 即使另一筆交辦被取消, 原本編輯的草稿也保留。
  const other = todos.locator('.todo-row').first();
  await other.getByLabel('待辦操作').click();
  page.once('dialog', dialog => dialog.accept());
  await other.getByRole('button', { name: '取消待辦', exact: true }).click();
  await todos.getByRole('status').filter({ hasText: '待辦已取消' }).waitFor();
  await page.screenshot({ path: `${artifacts}/draft-other-tab.png` });
  await todos.getByRole('button', { name: '返回草稿', exact: true }).click();
  const draft = todos.getByLabel('編輯待辦內容');
  assert.equal(await draft.inputValue(), '切換後保留的草稿');
  assert.equal(await draft.evaluate(el => el === document.activeElement), true);
  assert.equal(await todos.locator(`[data-todo-id="${editedId}"]`).getByRole('checkbox').isDisabled(), true);
  // 開始編輯另一筆需要明確放棄；拒絕時原草稿不變。
  const next = todos.locator('.todo-row:has(.todo-actions)').first();
  await next.getByLabel('待辦操作').click();
  page.once('dialog', dialog => dialog.dismiss());
  await next.getByRole('button', { name: '編輯', exact: true }).click();
  assert.equal(await draft.inputValue(), '切換後保留的草稿');
  await todos.getByRole('button', { name: '儲存待辦修改', exact: true }).click();
  await draft.waitFor({ state: 'hidden' });
  const saved = await page.evaluate(async id => (await (await import('/src/api/index.js')).api.getTodos()).todos.find(item => item.id === id), editedId);
  assert.equal(saved.title, '切換後保留的草稿');
  assert.deepEqual(failures, []);
  console.log('PASS: F03 跨 tab 草稿提示、其他操作與展開可用、取消別筆仍保留草稿、返回焦點、拒絕取代及保存');
} finally {
  await browser?.close();
  await vite?.close();
}
