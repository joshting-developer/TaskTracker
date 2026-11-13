import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
const e2ePort = Number(process.env.E2E_FRONTEND_PORT || 5175); // TT-335：Claude 5198、Codex 5199, 見.agents/skills/tasktracker-test-environments
if (!process.env.E2E_API_URL) throw new Error('必須指定隔離E2E_API_URL');
let server, browser;
try {
  server = await createServer({ root: new URL('..', import.meta.url).pathname, server: { host: '127.0.0.1', port: e2ePort, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } }), errors = [], requests = [];
  page.setDefaultTimeout(10000);
  page.on('pageerror', error => errors.push(error.message));
  page.on('response', async response => {
    const url = new URL(response.url());
    if (!url.pathname.startsWith('/api/')) return;
    const item = { path: url.pathname, params: url.searchParams, bytes: 0 };
    requests.push(item);
    try { item.bytes = (await response.body()).length; } catch { /* 導覽後body不可取 */ }
  });
  await page.goto(`http://127.0.0.1:${e2ePort}/login`);
  async function call(target, op, input = {}, group = 'rpc') {
    return target.evaluate(async ({ op, input, group }) => {
      const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
      const result = await (await fetch(`/api/${group}/${op}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
      if (!result.ok) throw new Error(`${op}: ${result.error.message}`);
      if (result.data?.verificationRequired) { const csrf = await (await fetch('/api/csrf-cookie')).json(); const verified = await fetch('/api/auth/verify-email', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: JSON.stringify({ code: 'local666' }) }); if (!verified.ok) throw new Error('Fixture email verification failed'); result.data = (await verified.json()).data; } return result.data;
    }, { op, input, group });
  }
  const owner = await call(page, 'register', { name: 'Calendar Owner', email: `calendar-${crypto.randomUUID()}@example.test`, password: 'isolated-test-password', workspaceName: 'Calendar Test' }, 'auth');
  const project = await call(page, 'createProject', { name: 'Calendar', code: 'CAL' });
  const other = await call(page, 'createProject', { name: 'Other', code: 'OTH' });
  const invite = await call(page, 'inviteMember', { email: `member-${crypto.randomUUID()}@example.test` });
  const memberPage = await (await browser.newContext()).newPage();
  await memberPage.goto(`http://127.0.0.1:${e2ePort}/login`);
  const member = await call(memberPage, 'register', { name: 'Calendar Member', email: invite.email, password: 'isolated-test-password', inviteToken: invite.token }, 'auth');
  await call(page, 'setProjectMember', { projectId: project.id, userId: member.user.id, roleId: 'developer', expectedRevision: 0 });
  const epic = await call(page, 'createIssue', { projectId: project.id, title: '月曆 Epic', type: 'epic' });
  const task = await call(page, 'createIssue', { projectId: project.id, title: '月曆子卡', epicId: epic.id, estimateMinutes: 240 });
  const loose = await call(page, 'createIssue', { projectId: project.id, title: '獨立卡', estimateMinutes: 60 });
  const outside = await call(page, 'createIssue', { projectId: other.id, title: '其他專案卡' });
  const log = (target, issue, workDate, minutes, note = '') => call(target, 'logTime', { issueId: issue.id, workDate, minutes, note });
  await log(page, task, '2026-10-05', 60, 'owner task');
  await log(page, epic, '2026-10-05', 30, 'owner epic');
  await log(page, outside, '2026-10-05', 300, 'outside');
  await log(page, task, '2026-09-30', 120, 'previous month');
  for (let index = 0; index < 25; index++) await log(page, loose, '2026-10-09', 6, `batch ${index}`);
  await log(memberPage, task, '2026-10-05', 90, 'member task');

  await page.reload();
  await page.getByRole('link', { name: '專案列表', exact: true }).click();
  await page.getByRole('button', { name: '進入專案：Calendar', exact: true }).click();
  await page.getByRole('link', { name: '工時月曆', exact: true }).waitFor();
  requests.length = 0;
  await page.getByRole('link', { name: '工時月曆', exact: true }).click();
  const calendar = page.getByRole('region', { name: '工時月曆', exact: true });
  await calendar.waitFor();
  const october = page.waitForResponse(response => response.url().includes('/time-calendar') && new URL(response.url()).searchParams.get('month') === '2026-10');
  await calendar.getByLabel('工時月份', { exact: true }).fill('2026-10');
  await october;
  // 未保存範圍時預設本人（沿用原行為），再切到全團隊。
  await calendar.getByText('本月合計 4 h', { exact: true }).waitFor();
  const team = page.waitForResponse(response => response.url().includes('/time-calendar') && !new URL(response.url()).searchParams.has('member'));
  await calendar.getByRole('combobox', { name: '工時成員', exact: true }).selectOption('');
  await team;
  await calendar.getByText('本月合計 5.5 h', { exact: true }).waitFor();
  await calendar.getByRole('button', { name: '2026-10-05 工時 3 h', exact: true }).waitFor();
  await calendar.getByRole('button', { name: '2026-10-09 工時 2.5 h', exact: true }).waitFor();
  assert.equal(await calendar.getByRole('button', { name: '2026-09-30 工時 0 h', exact: true }).count(), 1, '上月工時不進本月摘要');
  await calendar.locator('.report-epic').filter({ hasText: '月曆 Epic' }).getByText('3 h / 預估 4 h', { exact: true }).waitFor();
  assert.equal(await calendar.getByText('其他專案卡').count(), 0, '其他專案不出現');
  const summaryRequest = requests.findLast(item => item.path.endsWith('/time-calendar'));
  assert.ok(summaryRequest && summaryRequest.path === `/api/v1/projects/${project.id}/time-calendar`);

  // 點日期才讀當日明細；20＋5分頁。
  const dayResponse = page.waitForResponse(response => response.url().includes('/time-entries') && new URL(response.url()).searchParams.get('date') === '2026-10-09');
  await calendar.getByRole('button', { name: '2026-10-09 工時 2.5 h', exact: true }).click();
  await dayResponse;
  // TT-305：明細收進抽屜的「工時明細」分頁, 依成員分組；分頁按鈕也在抽屜裡。
  const dayDrawer = page.locator('.time-entry-drawer');
  const detailsTab = dayDrawer.getByRole('tab', { name: '2026-10-09 工時明細', exact: true });
  assert.equal(await dayDrawer.getByRole('tab', { name: '填報工時', exact: true }).getAttribute('aria-selected'), 'true', '點日期預設在填報分頁');
  await detailsTab.click();
  assert.equal(await detailsTab.getAttribute('aria-selected'), 'true');
  await page.waitForFunction(() => document.querySelectorAll('.time-entry-drawer .time-entry').length === 20);
  assert.ok(await dayDrawer.locator('.time-entry-group').count() >= 1, '依成員分組');
  assert.equal(await calendar.locator('.time-day-details').count(), 0, '月曆下方不再有明細');
  const pager = dayDrawer.getByLabel('工時明細分頁', { exact: true });
  await pager.getByRole('button', { name: '下一頁', exact: true }).click();
  await page.waitForFunction(() => document.querySelectorAll('.time-entry-drawer .time-entry').length === 5);
  await pager.getByRole('button', { name: '上一頁', exact: true }).click();
  await page.waitForFunction(() => document.querySelectorAll('.time-entry-drawer .time-entry').length === 20);
  // 從明細按修改：切回填報分頁並帶入那筆紀錄。
  const editButton = dayDrawer.getByRole('button', { name: /^修改工時 / }).first();
  if (await editButton.count()) {
    await editButton.click();
    await page.locator('.time-entry-drawer').getByRole('heading', { name: '修改你的工時', exact: true }).waitFor();
    assert.equal(await page.locator('.time-entry-drawer').getByRole('tab', { name: '修改工時', exact: true }).getAttribute('aria-selected'), 'true', '修改時在填報分頁');
    assert.ok(Number(await page.locator('.time-entry-drawer').getByLabel('投入小時', { exact: true }).inputValue()) > 0, '帶入原本的時數');
  }
  await page.locator('.time-entry-drawer').getByRole('button', { name: '關閉工時面板', exact: true }).click();
  // 月曆下方的「查看工時明細」直接開在明細分頁。
  await calendar.getByRole('button', { name: '查看 2026-10-09 工時明細', exact: true }).click();
  assert.equal(await page.locator('.time-entry-drawer').getByRole('tab', { name: '2026-10-09 工時明細', exact: true }).getAttribute('aria-selected'), 'true');
  await page.locator('.time-entry-drawer').getByRole('button', { name: '關閉工時面板', exact: true }).click();

  // 成員篩選由伺服器限定。
  await calendar.getByRole('button', { name: '搜尋工時成員選項', exact: true }).click();
  const memberOptions = page.waitForResponse(response => response.url().includes('/member-options') && new URL(response.url()).searchParams.get('search') === 'Calendar Member');
  await calendar.getByRole('textbox', { name: '搜尋工時成員', exact: true }).fill('Calendar Member');
  await memberOptions;
  const filtered = page.waitForResponse(response => response.url().includes('/time-calendar') && new URL(response.url()).searchParams.get('member') === member.user.id);
  await calendar.getByRole('combobox', { name: '工時成員', exact: true }).selectOption(member.user.id);
  await filtered;
  await calendar.getByText('本月合計 1.5 h', { exact: true }).waitFor();
  await calendar.getByRole('combobox', { name: '工時成員', exact: true }).selectOption('');
  await calendar.getByText('本月合計 5.5 h', { exact: true }).waitFor();

  // 快速換月：只採用最後一次範圍。
  await calendar.getByRole('button', { name: '上個月', exact: true }).click();
  await calendar.getByRole('button', { name: '下個月', exact: true }).click();
  await calendar.getByRole('button', { name: '下個月', exact: true }).click();
  await calendar.getByRole('button', { name: '上個月', exact: true }).click();
  await calendar.getByText('本月合計 5.5 h', { exact: true }).waitFor();
  assert.equal(await calendar.getByLabel('工時月份', { exact: true }).inputValue(), '2026-10');

  // 填報後只重讀該月摘要與當日明細。
  await calendar.getByRole('button', { name: '2026-10-07 工時 0 h', exact: true }).click();
  const drawer = page.locator('.time-entry-drawer');
  await drawer.getByRole('combobox', { name: '工時任務', exact: true }).selectOption({ label: 'CAL-3 · 獨立卡' });
  await drawer.getByLabel('投入小時', { exact: true }).fill('0.5');
  await drawer.getByLabel('工時說明').fill('月曆新增');
  requests.length = 0;
  await drawer.getByRole('button', { name: '登記工時', exact: true }).click();
  await drawer.waitFor({ state: 'detached' });
  await calendar.getByText('本月合計 6 h', { exact: true }).waitFor();
  await calendar.getByRole('button', { name: '查看 2026-10-07 工時明細', exact: true }).click();
  await page.locator('.time-entry-drawer .time-entry').filter({ hasText: '月曆新增' }).waitFor();
  await page.locator('.time-entry-drawer').getByRole('button', { name: '關閉工時面板', exact: true }).click();
  assert.ok(requests.some(item => item.path.endsWith('/time-calendar') && item.params.get('month') === '2026-10'));
  assert.ok(requests.some(item => item.path.endsWith('/time-entries') && item.params.get('date') === '2026-10-07'));

  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${width}無橫向溢位`);
  }
  const bulk = requests.concat().filter(item => /\/(getWorkspace|getTodos)$/.test(item.path));
  assert.deepEqual(bulk.map(item => item.path), [], '月曆不讀整包Workspace');
  assert.deepEqual(errors, []);
  console.log('time calendar PASS', JSON.stringify({ summaryBytes: summaryRequest.bytes, bulkReads: bulk.length }));
} finally {
  await browser?.close();
  await server?.close();
}
