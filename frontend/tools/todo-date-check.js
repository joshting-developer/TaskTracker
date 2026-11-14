// TT-313：小待辦日期規則（完成／退回／交辦／建立、台北時區、跨年）與首頁三種情況的實際顯示。只接受隔離API。
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { todoDate } from '../src/domain/todoContent.js';
const e2ePort = Number(process.env.E2E_FRONTEND_PORT || 5175); // TT-335：Claude 5198、Codex 5199, 見.agents/skills/tasktracker-test-environments

const now = new Date('2026-11-13T12:00:00Z');
for (const [item, user, want] of [
  [{ createdAt: '2026-11-12T16:30:00Z', createdBy: 'a', initialAssigneeId: 'b', state: 'open' }, 'b', '11/13 交辦'],
  [{ createdAt: '2026-11-12T01:00:00Z', createdBy: 'b', initialAssigneeId: 'b', state: 'open' }, 'b', '11/12 建立'],
  [{ createdAt: '2026-11-01T01:00:00Z', returnedAt: '2026-11-05T01:00:00Z', createdBy: 'b', initialAssigneeId: 'a', state: 'open' }, 'b', '11/5 退回'],
  [{ createdAt: '2026-11-01T01:00:00Z', completedAt: '2026-11-06T01:00:00Z', createdBy: 'a', initialAssigneeId: 'b', state: 'done' }, 'b', '11/6 完成'],
  [{ createdAt: '2025-12-31T17:00:00Z', createdBy: 'a', initialAssigneeId: 'b', state: 'open' }, 'b', '1/1 交辦'],
  [{ createdAt: '2025-12-30T01:00:00Z', createdBy: 'a', initialAssigneeId: 'b', state: 'open' }, 'b', '2025/12/30 交辦'],
]) assert.equal(todoDate(item, user, now).label, want);

const api = process.env.E2E_API_URL || '';
if (!/^http:\/\/127\.0\.0\.1:13\d{3}$/.test(api)) throw new Error('請設定隔離E2E_API_URL（127.0.0.1:13xxx），不可使用業務資料庫');
const folder = 'artifacts/tt313';
mkdirSync(folder, { recursive: true });
let server, browser;
const errors = [];
async function call(page, operation, input = {}, group = 'rpc') {
  return page.evaluate(async ({ operation, input, group }) => {
    const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
    const result = await (await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
    if (!result.ok) throw new Error(result.error?.message || operation);
    if (result.data?.verificationRequired) { const csrf = await (await fetch('/api/csrf-cookie')).json(); const verified = await fetch('/api/auth/verify-email', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: JSON.stringify({ code: 'local666' }) }); if (!verified.ok) throw new Error('Fixture email verification failed'); result.data = (await verified.json()).data; } return result.data;
  }, { operation, input, group });
}
const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Taipei', month: 'numeric', day: 'numeric' }).formatToParts(new Date());
const md = `${today.find(p => p.type === 'month').value}/${today.find(p => p.type === 'day').value}`;
try {
  server = await createServer({ server: { host: '127.0.0.1', port: e2ePort, strictPort: true, proxy: { '/api': api } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const owner = await browser.newPage({ viewport: { width: 1440, height: 900 }, locale: 'zh-TW', reducedMotion: 'reduce' });
  const member = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'zh-TW' })).newPage();
  for (const page of [owner, member]) { page.setDefaultTimeout(15000); page.on('pageerror', error => errors.push(error.message)); await page.goto(`http://127.0.0.1:${e2ePort}/login`); }
  await call(owner, 'register', { name: '交辦人', email: `tt313-${crypto.randomUUID()}@example.test`, password: 'tt313-test-password', workspaceName: '日期驗收' }, 'auth');
  const invite = await call(owner, 'inviteMember', { email: `tt313m-${crypto.randomUUID()}@example.test` });
  await call(member, 'register', { name: '承辦人', email: invite.email, password: 'tt313-test-password', inviteToken: invite.token }, 'auth');
  const assignees = await owner.evaluate(async () => (await (await fetch('/api/v1/todo-assignees?perPage=100')).json()).data.items);
  await call(owner, 'createTodo', { title: '自己的待辦' });
  await call(owner, 'createTodo', { title: '交辦出去的待辦', assigneeId: assignees.find(m => m.name === '承辦人').id });

  await owner.goto(`http://127.0.0.1:${e2ePort}/app/#/notes`);
  const home = owner.getByRole('region', { name: '紙條', exact: true });
  await home.locator('.notes-list li').filter({ hasText: '自己的待辦' }).locator('time').filter({ hasText: `${md} 建立` }).waitFor();
  await home.getByRole('tab', { name: /我交辦的/ }).click();
  const sent = home.locator('.notes-list li').filter({ hasText: '交辦出去的待辦' });
  await sent.getByText('來自 交辦人', { exact: false }).waitFor();
  await sent.locator('time').filter({ hasText: `${md} 交辦` }).waitFor();
  assert.match(await sent.locator('time').getAttribute('title'), /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/, '滑鼠提示是完整日期時間');

  await member.goto(`http://127.0.0.1:${e2ePort}/app/#/notes`);
  const inbox = member.getByRole('region', { name: '紙條', exact: true }).locator('.notes-list li').filter({ hasText: '交辦出去的待辦' });
  await inbox.getByText('來自 交辦人', { exact: false }).waitFor();
  await inbox.locator('time').filter({ hasText: `${md} 交辦` }).waitFor();
  for (const [width, height] of [[1440, 900], [768, 1024], [390, 844]]) {
    await member.setViewportSize({ width, height }); await member.waitForTimeout(100);
    const row = await inbox.locator('.notes-row-content p').evaluate(el => ({ lines: Math.round(el.getBoundingClientRect().height / parseFloat(getComputedStyle(el).lineHeight)), overflow: document.documentElement.scrollWidth > innerWidth }));
    assert.equal(row.overflow, false, `${width} 無橫向溢出`);
    assert.ok(row.lines <= 2, `${width} 日期資訊最多兩行`);
    await member.screenshot({ path: `${folder}/${width}-home.png`, animations: 'disabled', caret: 'hide' });
  }
  assert.deepEqual(errors, []);
  console.log('PASS: todoDate rules (done/returned/assigned/own, Taipei, cross-year) and home shows 建立／交給／來自 dates, 3 viewports');
} finally {
  await browser?.close();
  await server?.close();
}
