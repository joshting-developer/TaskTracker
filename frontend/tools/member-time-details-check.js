import assert from 'node:assert/strict';
import { mkdirSync, readFileSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
import { dateInTaipei } from '../src/domain/model.js';
if (!process.env.E2E_API_URL) throw new Error('請指定隔離的 E2E_API_URL，不使用業務資料庫');
const port = Number(process.env.E2E_FRONTEND_PORT || 5175);
const base = `http://127.0.0.1:${port}`;
let server, browser;
async function call(page, operation, input = {}, group = 'rpc') {
  return page.evaluate(async ({ operation, input, group }) => {
    const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
    const response = await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) });
    const result = await response.json();
    if (!result.ok) throw new Error(JSON.stringify(result));
    if (result.data?.verificationRequired) { const csrf = await (await fetch('/api/csrf-cookie')).json(); const verified = await fetch('/api/auth/verify-email', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: JSON.stringify({ code: 'local666' }) }); if (!verified.ok) throw new Error('Fixture email verification failed'); result.data = (await verified.json()).data; } return result.data;
  }, { operation, input, group });
}
try {
  server = await preview({ preview: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 1000 } })).newPage();
  page.setDefaultTimeout(10000);
  const errors = []; page.on('pageerror', error => errors.push(error.message));
  if (process.env.E2E_VISUAL_FIXTURE === '1') await page.clock.setFixedTime(new Date('2026-11-13T01:00:00Z'));
  await page.goto(`${base}/login`);
  const today = process.env.E2E_VISUAL_FIXTURE === '1' ? '2026-11-13' : dateInTaipei();
  const token = crypto.randomUUID();
  let first, second, task, epic;
  if (process.env.E2E_REVIEW_FIXTURE) {
    if (process.env.E2E_VISUAL_FIXTURE !== '1') throw new Error('Reviewed fixture requires fixed visual test date');
    const fixture = JSON.parse(readFileSync(process.env.E2E_REVIEW_FIXTURE, 'utf8'));
    const identity = await call(page, 'login', { email: fixture.email, password: 'detail-test-password' }, 'auth');
    assert.equal(identity.user.id, fixture.userId); assert.equal(identity.workspace.id, fixture.workspaceId);
    ({ first, second, task, epic } = fixture);
  } else {
  await call(page, 'register', { name: '工時負責人', email: `member-detail-${token}@example.test`, password: 'detail-test-password', workspaceName: '成員明細測試' }, 'auth');
  first = await call(page, 'createProject', { name: '會員服務', code: 'MEMBERS' });
  second = await call(page, 'createProject', { name: '通知服務', code: 'NOTIFY' });
  task = { ...{ projectId: first.id, title: '會員登入與錯誤處理', type: 'task' }, ...(await call(page, 'createIssue', { projectId: first.id, title: '會員登入與錯誤處理', type: 'task' })) }; // 寫入只回有界結果（TT-178）
  epic = { ...{ projectId: second.id, title: '通知整合', type: 'epic' }, ...(await call(page, 'createIssue', { projectId: second.id, title: '通知整合', type: 'epic' })) }; // 寫入只回有界結果（TT-178）
  for (let day = 0; day < 23; day++) {
    const date = new Date(`${today}T12:00:00Z`); date.setUTCDate(date.getUTCDate() - day);
    await call(page, 'logTime', { issueId: task.id, workDate: date.toISOString().slice(0, 10), minutes: 30, note: `處理會員登入流程，第 ${day + 1} 次測試` });
  }
  for (let index = 0; index < 21; index++) await call(page, 'logTime', { issueId: epic.id, workDate: today, minutes: 1, note: index === 20 ? '<img src=x onerror=alert(1)> 文字安全測試' : `確認通知整合 ${index + 1}` });
  }
  await page.reload();
  await page.getByRole('link', { name: '工時分析', exact: true }).click();
  const region = page.getByRole('region', { name: '工時分析', exact: true });
  await region.getByRole('button', { name: /^成員投入/ }).click();
  await region.getByRole('button', { name: '近 28 天', exact: true }).click();
  await region.locator('.analysis-totals').getByText('11.85 h', { exact: true }).waitFor();
  // 未套用的表單不改變點入成員時的資料範圍。
  await region.getByLabel('分析專案', { exact: true }).selectOption(second.id);
  await region.getByRole('button', { name: '工時負責人', exact: true }).first().click();
  const details = region.getByRole('region', { name: '成員工時明細', exact: true });
  const idle = () => region.locator('.member-details[aria-busy="false"]').waitFor();
  await details.locator('.member-summary').filter({ hasText: '11.85 h' }).waitFor();
  assert.equal(await details.locator('.member-row').count(), 20);
  const pagination = details.getByRole('navigation', { name: '成員明細分頁', exact: true });
  await pagination.getByRole('button', { name: '下一頁', exact: true }).click(); await idle();
  assert.equal(await details.locator('.member-row').count(), 4);
  assert.match(await details.locator('.member-summary').textContent(), /11.85 h/);
  await details.getByRole('button', { name: '依卡片', exact: true }).click(); await idle();
  assert.equal(await details.locator('.member-row').count(), 2);
  await details.locator('.member-row').filter({ hasText: task.title }).getByText('11.5 h', { exact: true }).waitFor();
  await details.locator('.member-row').filter({ hasText: epic.title }).getByRole('button', { name: /展開/ }).click(); await idle();
  assert.equal(await details.locator('.member-entry').count(), 20);
  const entryPagination = details.getByRole('navigation', { name: '填報紀錄分頁', exact: true });
  await entryPagination.getByRole('button', { name: '下一頁', exact: true }).click(); await idle();
  assert.equal(await details.locator('.member-entry').count(), 1);
  assert.equal(await details.locator('.member-note img').count(), 0, '說明以文字呈現，不執行HTML');
  // 將所有說明頁都檢查一遍，確認惡意HTML只呈現文字。
  const notes = await details.locator('.member-note').allTextContents();
  await entryPagination.getByRole('button', { name: '上一頁', exact: true }).click(); await idle();
  notes.push(...await details.locator('.member-note').allTextContents());
  assert.ok(notes.some(note => note.includes('<img src=x onerror=alert(1)>')));
  assert.equal(await details.locator('img').count(), 0);
  await Promise.all([
    page.waitForResponse(response => new URL(response.url()).pathname === '/api/v1/time-report/details'),
    page.getByRole('button', { name: '重新整理工作空間', exact: true }).click(),
  ]);
  await idle();
  assert.equal(await details.locator('.member-entry').count(), 20, '背景更新保留展開群組與紀錄頁碼');
  mkdirSync('artifacts/member-time-details', { recursive: true });
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1000 });
    if (width === 390) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    await page.screenshot({ path: `artifacts/member-time-details/cards-${width}.png`, fullPage: false });
  }
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.route('**/api/v1/time-report/details*', route => route.abort('failed'), { times: 1 });
  await details.getByRole('button', { name: '依日期', exact: true }).click();
  await details.getByRole('alert').filter({ hasText: '暫時無法連線' }).waitFor();
  assert.equal(await details.locator('.member-note').count(), 0);
  await details.getByRole('button', { name: '重試', exact: true }).click(); await idle();
  assert.equal(await details.locator('.member-row').count(), 20);
  await page.screenshot({ path: 'artifacts/member-time-details/dates-1440.png', fullPage: false });
  await details.getByRole('button', { name: '返回成員分析', exact: true }).click();
  await region.locator('.analysis-totals').getByText('11.85 h', { exact: true }).waitFor();
  assert.equal(await region.getByLabel('分析專案', { exact: true }).inputValue(), second.id);
  await region.getByRole('button', { name: '套用範圍', exact: true }).click();
  await region.locator('.analysis-totals').getByText('0.35 h', { exact: true }).waitFor();
  await region.getByRole('button', { name: '工時負責人', exact: true }).first().click();
  await details.locator('.member-summary').filter({ hasText: '0.35 h' }).waitFor();
  assert.equal(await details.locator('.member-row').count(), 1, '專案篩選隨已套用範圍進入明細');
  await details.getByRole('button', { name: '返回成員分析', exact: true }).click();
  assert.deepEqual(errors, []);
  console.log('PASS: 成員日期／卡片、兩層20筆分頁、整段總計、直接Epic工時、說明XSS安全、範圍保留、錯誤重試及三尺寸');
} finally { await browser?.close(); if (server) await new Promise(resolve => server.httpServer.close(resolve)); }
