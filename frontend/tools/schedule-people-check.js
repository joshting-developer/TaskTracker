import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
import { addDays, weekOf } from '../src/domain/schedule.js';
// TT-384：人員排程以隔離後端和合成成員驗證列、負荷、期間切換、篩選與窄螢幕清單。
const port = Number(process.env.E2E_FRONTEND_PORT || 5199), apiUrl = process.env.E2E_API_URL;
assert.equal(process.env.E2E_ISOLATED, '1'); assert.ok(apiUrl);
let server, browser;
async function call(page, operation, input = {}, group = 'rpc') {
  const result = await page.evaluate(async ({ operation, input, group }) => {
    const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
    return (await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
  }, { operation, input, group });
  assert.equal(result.ok, true, JSON.stringify(result));
  if (result.data?.verificationRequired) return call(page, 'verify-email', { code: 'local666' }, 'auth');
  return result.data;
}
const ready = page => page.waitForFunction(() => document.querySelector('[aria-label="人員排程"]')?.getAttribute('aria-busy') === 'false');
try {
  server = await preview({ preview: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': apiUrl } } });
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const base = `http://127.0.0.1:${port}`, page = await browser.newPage({ locale: 'zh-TW', viewport: { width: 1440, height: 900 } });
  page.setDefaultTimeout(15000);
  const errors = [], reads = []; page.on('pageerror', e => errors.push(e.message));
  await page.goto(`${base}/login`);
  const owner = await call(page, 'register', { name: '排程負責人', email: `people-${crypto.randomUUID()}@example.test`, password: 'people-isolated-password', workspaceName: '人員' }, 'auth');
  const project = await call(page, 'createProject', { name: '人員排程', code: 'PPL' });
  // 第二位成員沒有任何排程, 也要出現在列上。
  const email = `people-idle-${crypto.randomUUID()}@example.test`, invite = await call(page, 'inviteMember', { email });
  const idlePage = await browser.newPage({ locale: 'zh-TW' }); await idlePage.goto(`${base}/login`);
  const idle = await call(idlePage, 'register', { name: '空閒成員', email, password: 'people-idle-password', inviteToken: invite.token }, 'auth');
  await call(idlePage, 'acceptWorkspaceInvite', { inviteToken: invite.token }, 'auth'); await idlePage.close();
  const acl = await call(page, 'getProjectMembers', { projectId: project.id });
  await call(page, 'setProjectMember', { projectId: project.id, userId: idle.user.id, roleId: 'developer', expectedRevision: acl.revision });
  const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Taipei' }).format(new Date()), week = weekOf(today);
  // 負責人週三同時三張 → 負荷高；另有一張第三週才開始, 只在 4 週期間出現。
  for (const [title, start, end] of [['整週任務', week.days[0], week.days[4]], ['週二到週四', week.days[1], week.days[3]], ['週三單日', week.days[2], week.days[2]]]) await call(page, 'createIssue', { projectId: project.id, title, assignee: owner.user.id, startDate: start, endDate: end });
  await call(page, 'createIssue', { projectId: project.id, title: '第三週才開始', assignee: owner.user.id, startDate: addDays(week.from, 15) });
  await call(page, 'createIssue', { projectId: project.id, title: '還沒指派', startDate: week.days[1] });
  await call(page, 'createIssue', { projectId: project.id, title: '沒有日期' });
  mkdirSync('artifacts/schedule-people', { recursive: true });
  page.on('request', r => { if (r.url().includes('/api/')) reads.push(r.url()); });
  await page.goto(`${base}/app/#/projects/${project.id}`);
  await page.getByRole('link', { name: '人員排程' }).click();
  await page.waitForURL(/\/people$/); await ready(page);
  reads.length = 0; await page.reload(); await ready(page);
  const grid = page.getByRole('table', { name: '人員排程' }), row = name => grid.locator('.people-row').filter({ hasText: name });
  await row('空閒成員').waitFor();
  assert.equal(await row('空閒成員').getByRole('button').count(), 0, '沒有排程的成員也列出');
  assert.equal(await row('排程負責人').getByRole('button').count(), 3);
  assert.equal(await row('未指派').getByRole('button', { name: /還沒指派/ }).count(), 1);
  assert.equal(await page.getByText('第三週才開始').count(), 0, '2 週期間不含第三週');
  await page.getByText('1 張任務尚未排程').waitFor();
  // 同一天三張：成員標負荷高且當天格子標記；同列重疊不遮蓋。
  assert.equal(await row('排程負責人').getByText('負荷高').count(), 1);
  assert.equal(await row('空閒成員').getByText('負荷高').count(), 0);
  assert.equal(await row('排程負責人').locator('.people-cell.busy').count(), 1);
  const boxes = await Promise.all(['整週任務', '週二到週四', '週三單日'].map(title => row('排程負責人').getByRole('button', { name: new RegExp(title) }).boundingBox()));
  for (let i = 0; i < boxes.length; i++) for (let j = i + 1; j < boxes.length; j++) assert.ok(boxes[i].y + boxes[i].height <= boxes[j].y || boxes[j].y + boxes[j].height <= boxes[i].y, JSON.stringify(boxes));
  const schedule = reads.filter(url => url.includes('/schedule?')).map(url => new URL(url).searchParams);
  assert.equal(schedule.at(-1).get('from'), week.from); assert.equal(schedule.at(-1).get('to'), addDays(week.from, 13));
  assert.equal(reads.some(url => /\/projects\/[^/]+\/issues\?/.test(url)), false, '人員排程不讀任務列表');
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    if (width === 390) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${width} overflow`);
    await page.screenshot({ path: `artifacts/schedule-people/after-${width}.png`, fullPage: true });
  }
  const agenda = page.getByRole('list', { name: '成員排程清單' });
  assert.equal(await agenda.getByRole('button').count(), 4); await agenda.getByText('這段期間沒有排程').waitFor();
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.getByRole('button', { name: '4 週' }).click(); await ready(page);
  await row('排程負責人').getByRole('button', { name: /第三週才開始/ }).waitFor();
  assert.equal(await page.getByRole('button', { name: '4 週' }).getAttribute('aria-pressed'), 'true');
  assert.equal(await row('排程負責人').getByRole('button', { name: /週三單日/ }).evaluate(el => el.classList.contains('compact')), true, '4 週的單日條狀改成色塊');
  assert.equal(await row('排程負責人').getByRole('button', { name: /整週任務/ }).evaluate(el => el.classList.contains('compact')), false);
  await page.screenshot({ path: 'artifacts/schedule-people/after-4weeks-1440.png' });
  await page.reload(); await ready(page);
  assert.equal(await page.getByRole('button', { name: '4 週' }).getAttribute('aria-pressed'), 'true', '期間在分頁重新整理後保留');
  await page.getByRole('button', { name: '下一週' }).click(); await ready(page);
  assert.equal(new URL(reads.findLast(url => url.includes('/schedule?'))).searchParams.get('from'), addDays(week.from, 7));
  await page.getByRole('button', { name: '本週' }).click(); await ready(page);
  const select = page.getByRole('combobox', { name: '承辦人', exact: true });
  await select.focus(); await select.locator(`option[value="${idle.user.id}"]`).waitFor({ state: 'attached' });
  await select.selectOption(idle.user.id); await ready(page);
  assert.equal(await grid.locator('.people-row').count(), 2, '篩選承辦人只留表頭與該成員');
  await row('空閒成員').waitFor();
  await select.selectOption(''); await ready(page);
  await page.route('**/api/v1/projects/*/schedule?**', route => route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { code: 'INTERNAL_ERROR', message: '暫時無法讀取時程' } }) }));
  await page.getByRole('button', { name: '下一週' }).click(); await page.getByRole('alert').waitFor();
  await page.unroute('**/api/v1/projects/*/schedule?**'); await page.getByRole('button', { name: '重試' }).click(); await ready(page);
  assert.equal(await page.getByRole('alert').count(), 0);
  await page.getByRole('button', { name: '本週' }).click(); await ready(page);
  await row('排程負責人').getByRole('button', { name: /週三單日/ }).click(); await page.getByRole('dialog').getByText(/PPL-3/).first().waitFor();
  const closedProject = await call(page, 'createProject', { name: '已完成負荷', code: 'DONE' });
  for (const title of ['已完成A', '已完成B', '已完成C']) await call(page, 'createIssue', { projectId: closedProject.id, title, assignee: owner.user.id, startDate: week.days[2], state: 'closed' });
  await page.goto(`${base}/app/#/projects/${closedProject.id}/people`); await page.reload(); await ready(page);
  const closedRow = page.getByRole('table', { name: '人員排程' }).locator('.people-row').filter({ hasText: '排程負責人' });
  assert.equal(await closedRow.getByRole('button').count(), 0, '預設隱藏已關閉');
  await page.getByRole('checkbox', { name: '顯示已完成' }).check(); await ready(page);
  await closedRow.getByRole('button', { name: /已完成A/ }).waitFor();
  await page.reload(); await ready(page);
  assert.equal(await page.getByRole('checkbox', { name: '顯示已完成' }).isChecked(), true, '顯示已完成設定保留');
  await page.screenshot({ path: `artifacts/schedule-people/closed-${process.env.SCHEDULE_BEFORE === '1' ? 'before' : 'after'}.png`, fullPage: true });
  assert.equal(await closedRow.getByRole('button').count(), 3, '已關閉條狀仍顯示');
  if (process.env.SCHEDULE_BEFORE !== '1') {
    assert.equal(await closedRow.getByText('負荷高').count(), 0, '同一天三張已關閉卡不算負荷高');
    assert.equal(await closedRow.locator('.people-cell.busy').count(), 0, '已關閉卡不計入每日負荷');
  }
  await page.route('**/api/v1/projects/*/schedule?**', async route => {
    const response = await route.fetch(), body = await response.json();
    body.data.total = 1001; await route.fulfill({ response, json: body });
  });
  await page.goto(`http://127.0.0.1:${port}/app/#/projects/${closedProject.id}/people`); await page.reload(); await ready(page);
  await page.getByRole('alert').getByText('任務超過 1000 張, 請縮短期間或加篩選').waitFor();
  assert.equal(await page.locator('.schedule-bar').count(), 0, '超限不呈現片段推算');
  assert.deepEqual(errors, []); console.log('PASS People schedule: idle members listed, unassigned row, heavy-load day, non-overlapping lanes, 2/4-week range, navigation, assignee filter, retry, detail, mobile agenda and three widths');
} finally { await browser?.close(); await new Promise(resolve => server ? server.httpServer.close(resolve) : resolve()); }
