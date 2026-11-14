import { verifyFixtureInBrowser } from './fixtures/verification.js';
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
const e2ePort = Number(process.env.E2E_FRONTEND_PORT || 5175); // TT-335：Claude 5198、Codex 5199, 見.agents/skills/tasktracker-test-environments
if (!process.env.E2E_API_URL) throw new Error('請設定隔離 E2E_API_URL');
let server, browser;
async function call(page, operation, input = {}, group = 'rpc') {
  return page.evaluate(async ({ operation, input, group }) => {
    const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
    const response = await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) });
    const result = await response.json(); if (!result.ok) throw new Error(JSON.stringify(result)); if (result.data?.verificationRequired) { const csrf = await (await fetch('/api/csrf-cookie')).json(); const verify = () => fetch('/api/auth/verify-email', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: JSON.stringify({ code: 'local666' }) }); let verified = await verify(); if (verified.status === 429) { const seconds = Number(verified.headers.get('Retry-After')); if (!Number.isFinite(seconds) || seconds < 0 || seconds > 60) throw new Error('Bounded Retry-After required'); await new Promise(resolve => setTimeout(resolve, seconds * 1000 + 250)); verified = await verify(); } if (!verified.ok) throw new Error('Fixture email verification failed'); result.data = (await verified.json()).data; } return result.data;
  }, { operation, input, group });
}
try {
  server = await preview({ preview: { host: '127.0.0.1', port: e2ePort, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } }), first = await context.newPage(), second = await (await browser.newContext({ viewport: { width: 1440, height: 1000 } })).newPage();
  const errors = []; for (const page of [first, second]) { page.setDefaultTimeout(10000); page.on('pageerror', e => errors.push(e.message)); await page.goto(`http://127.0.0.1:${e2ePort}/login`); }
  mkdirSync('artifacts/workspace-switch', { recursive: true });
  const token = crypto.randomUUID(), password = 'workspace-switch-test';
  const a = await call(first, 'register', { name: '協作者A', email: `ws-a-${token}@example.test`, password, workspaceName: '第一工作區' }, 'auth');
  const b = await call(second, 'register', { name: '協作者B', email: `ws-b-${token}@example.test`, password, workspaceName: '第二工作區' }, 'auth');
  const project = await call(first, 'createProject', { name: 'A私有專案', code: 'PRIVATE' });
  await call(first, 'createIssue', { projectId: project.id, title: 'A私有卡片' });
  await call(first, 'createTodo', { title: 'A工作區速記' });
  await call(second, 'createProject', { name: 'B私有專案', code: 'OTHER' });
  const invite = await call(second, 'inviteMember', { email: a.user.email });
  const inviteUrl = `http://127.0.0.1:${e2ePort}/app/#/invite?token=${invite.token}`;
  await first.reload(); await second.reload();
  await first.getByRole('link', { name: '工作空間設定', exact: true }).waitFor();
  assert.equal(await first.getByRole('link', { name: '工作空間設定', exact: true }).count(), 1);
  await first.getByRole('button', { name: '帳號選單', exact: true }).click(); assert.equal(await first.getByRole('menuitem', { name: '工作空間設定', exact: true }).count(), 0); await first.keyboard.press('Escape');
  await second.goto(inviteUrl); const wrong = second.getByRole('dialog', { name: '工作空間邀請', exact: true }); await wrong.getByText(/目前登入的是/).waitFor(); assert.equal(await wrong.getByRole('button', { name: '接受邀請並進入', exact: true }).count(), 0); await wrong.getByRole('button', { name: '關閉工作空間邀請', exact: true }).click();
  await first.goto(inviteUrl); const invitation = first.getByRole('dialog', { name: '工作空間邀請', exact: true }); await invitation.getByRole('heading', { name: '第二工作區', exact: true }).waitFor();
  for (const width of [1440, 768, 390]) { await first.setViewportSize({ width, height: 1000 }); await invitation.screenshot({ path: `artifacts/workspace-switch/invitation-${width}.png` }); }
  await first.setViewportSize({ width: 1440, height: 1000 });
  await invitation.getByRole('button', { name: '接受邀請並進入', exact: true }).click(); await invitation.waitFor({ state: 'hidden' }); await first.locator('.app-shell').waitFor();
  let workspace = await call(first, 'getWorkspace'); assert.equal(workspace.meta.workspace.id, b.workspace.id); assert.equal(workspace.meta.role, 'user'); assert.equal(workspace.projects.length, 0);
  assert.equal(await first.getByText('A工作區速記', { exact: true }).count(), 0); assert.equal(await first.getByRole('link', { name: '工作空間設定', exact: true }).count(), 0);
  const picker = first.getByRole('dialog', { name: '選擇工作空間', exact: true });
  async function openPicker() { const mobile = first.getByRole('button', { name: '開啟導覽', exact: true }); if (await mobile.isVisible() && await mobile.getAttribute('aria-expanded') === 'false') await mobile.click(); await first.getByRole('button', { name: '切換工作空間', exact: true }).click(); await picker.getByRole('button', { name: '選擇工作空間 第一工作區', exact: true }).waitFor(); }
  mkdirSync('artifacts/workspace-switch', { recursive: true });
  await openPicker();
  for (const width of [1440, 768, 390]) { await first.setViewportSize({ width, height: 1000 }); await picker.screenshot({ path: `artifacts/workspace-switch/picker-${width}.png` }); assert.equal(await first.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true); }
  await first.setViewportSize({ width: 1440, height: 1000 });
  await first.route('**/api/auth/switchWorkspace', route => route.abort('failed'), { times: 1 });
  await picker.getByRole('button', { name: '選擇工作空間 第一工作區', exact: true }).click();
  await picker.getByRole('alert').filter({ hasText: '暫時無法連線' }).waitFor();
  await picker.getByRole('button', { name: '重新載入', exact: true }).click();
  await picker.getByRole('button', { name: '選擇工作空間 第一工作區', exact: true }).click(); await picker.waitFor({ state: 'hidden' }); await first.goto(`http://127.0.0.1:${e2ePort}/app/#/notes`); await first.getByText('A工作區速記', { exact: true }).first().waitFor();
  assert.equal(await first.getByRole('link', { name: '工作空間設定', exact: true }).count(), 1);
  await first.getByRole('link', { name: '工作空間設定', exact: true }).click();
  const settings = first.getByRole('region', { name: '工作空間設定', exact: true });
  await settings.getByLabel('工作空間名稱', { exact: true }).fill('工作區未送出設定');
  await openPicker(); first.once('dialog', d => d.dismiss());
  await picker.getByRole('button', { name: '選擇工作空間 第二工作區', exact: true }).click();
  assert.equal(await settings.getByLabel('工作空間名稱', { exact: true }).inputValue(), '工作區未送出設定');
  assert.equal(await first.evaluate(() => location.hash), '#/workspace/settings');
  first.once('dialog', d => d.accept()); await picker.getByRole('button', { name: '選擇工作空間 第二工作區', exact: true }).click();
  await picker.waitFor({ state: 'hidden' }); await first.locator('.app-shell').waitFor();
  assert.equal(await settings.count(), 0, '切空間清除舊設定頁及草稿');
  await openPicker(); await picker.getByRole('button', { name: '選擇工作空間 第一工作區', exact: true }).click();
  await picker.waitFor({ state: 'hidden' }); await first.goto(`http://127.0.0.1:${e2ePort}/app/#/notes`); await first.getByText('A工作區速記', { exact: true }).first().waitFor();
  const stale = await context.newPage(); stale.setDefaultTimeout(10000); await stale.goto(`http://127.0.0.1:${e2ePort}/app/#/notes`); await stale.getByText('A工作區速記', { exact: true }).first().waitFor();
  await first.getByRole('tab', { name: '建立', exact: true }).click(); await first.getByLabel('紙條內容', { exact: true }).fill('A未送出草稿'); await openPicker();
  first.once('dialog', d => d.dismiss()); await picker.getByRole('button', { name: '選擇工作空間 第二工作區', exact: true }).click(); assert.equal(await picker.isVisible(), true); assert.equal(await first.getByLabel('紙條內容', { exact: true }).inputValue(), 'A未送出草稿');
  first.once('dialog', d => d.accept()); await picker.getByRole('button', { name: '選擇工作空間 第二工作區', exact: true }).click(); await picker.waitFor({ state: 'hidden' }); await first.goto(`http://127.0.0.1:${e2ePort}/app/#/notes`); await first.getByRole('tab', { name: '建立', exact: true }).click(); assert.equal(await first.getByLabel('紙條內容', { exact: true }).inputValue(), '');
  await stale.getByRole('tab', { name: '建立', exact: true }).click(); await stale.getByLabel('紙條內容', { exact: true }).fill('舊分頁不能錯寫');
  const [rejected] = await Promise.all([stale.waitForResponse(response => response.url().endsWith('/api/rpc/createTodo')), stale.getByRole('button', { name: '建立紙條', exact: true }).click()]);
  assert.equal(rejected.status(), 409); assert.equal((await rejected.json()).error.code, 'WORKSPACE_CHANGED');
  const todos = await call(first, 'getTodos'); assert.equal(todos.todos.some(todo => todo.title === '舊分頁不能錯寫'), false); await stale.getByText('A工作區速記', { exact: true }).first().waitFor({ state: 'hidden' });
  console.log('PASS: Owner側欄設定、身份隔離、既有登入邀請、兩個工作區切換、私有專案與待辦隔離、草稿取消／確認、另一分頁舊請求409與重讀、三尺寸');
  const c = await call(second, 'inviteMember', { email: `ws-new-${token}@example.test` });
  const fresh = await (await browser.newContext()).newPage(); fresh.setDefaultTimeout(10000); await fresh.goto(`http://127.0.0.1:${e2ePort}/app/#/invite?token=${c.token}`); await fresh.getByLabel('你的名字').fill('新同事'); await fresh.getByLabel('Email', { exact: true }).fill(c.email); await fresh.getByLabel('密碼', { exact: true }).fill(password); await fresh.getByRole('button', { name: '加入工作空間', exact: true }).click();
  await verifyFixtureInBrowser(fresh); await fresh.locator('.app-shell').waitFor();
  const existing = await (await browser.newContext()).newPage(); existing.setDefaultTimeout(10000); await existing.goto(inviteUrl); await existing.getByRole('button', { name: '已有帳號？登入', exact: true }).click(); await existing.getByLabel('Email', { exact: true }).fill(a.user.email); await existing.getByLabel('密碼', { exact: true }).fill(password); await existing.getByRole('button', { name: '登入並接受邀請', exact: true }).click(); await existing.locator('.app-shell').waitFor();
  assert.equal((await call(existing, 'getWorkspace')).meta.workspace.id, b.workspace.id);
  const mismatch = await (await browser.newContext()).newPage(); mismatch.setDefaultTimeout(10000); mismatch.on('pageerror', e => errors.push(e.message));
  await mismatch.goto(`http://127.0.0.1:${e2ePort}/login`); await call(mismatch, 'login', { email: b.user.email, password }, 'auth');
  await mismatch.goto(inviteUrl); await mismatch.reload();
  await mismatch.getByRole('button', { name: '改用受邀帳號登入', exact: true }).click();
  await mismatch.getByRole('button', { name: '已有帳號？登入', exact: true }).click();
  await mismatch.getByLabel('Email', { exact: true }).fill(a.user.email); await mismatch.getByLabel('密碼', { exact: true }).fill(password);
  await mismatch.getByRole('button', { name: '登入並接受邀請', exact: true }).click(); await mismatch.locator('.app-shell').waitFor();
  assert.equal((await call(mismatch, 'getWorkspace')).meta.workspace.id, b.workspace.id);
  assert.deepEqual(errors, []);
  console.log('PASS: 未登入既有帳號登入接受／已使用邀請重試不降權、新帳號仍可註冊加入、無瀏覽器錯誤');
} catch (error) {
  for (const [index, page] of (browser?.contexts().flatMap(context => context.pages()) || []).entries()) await page.screenshot({ path: `artifacts/workspace-switch/failure-${index}.png`, fullPage: true }).catch(() => {});
  throw error;
} finally { await browser?.close(); await new Promise(resolve => server?.httpServer.close(resolve)); }
