import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
import { addDays, weekOf } from '../src/domain/schedule.js';
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
const scheduleReady = page => page.waitForFunction(() => document.querySelector('[aria-label="週曆"]')?.getAttribute('aria-busy') === 'false');
try {
  server = await preview({ preview: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': apiUrl } } });
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage({ locale: 'zh-TW', viewport: { width: 1440, height: 900 } });
  // 固定台灣週一, 避免週二等 fixture 隨執行日變成逾期。
  const now = new Date('2026-11-23T12:00:00+08:00');
  await page.clock.setFixedTime(now);
  page.setDefaultTimeout(15000);
  const errors = [], reads = []; page.on('pageerror', e => errors.push(e.message));
  await page.goto(`http://127.0.0.1:${port}/login`);
  const identity = await call(page, 'register', { name: '週曆驗證', email: `schedule-${crypto.randomUUID()}@example.test`, password: 'schedule-isolated-password', workspaceName: '排程' }, 'auth');
  const project = await call(page, 'createProject', { name: '產品排程', code: 'WEEK' });
  const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Taipei' }).format(now), week = weekOf(today);
  const across = await call(page, 'createIssue', { projectId: project.id, title: '跨週的長任務名稱用來確認條狀標題會截斷而不撐破版面', assignee: identity.user.id, startDate: addDays(week.from, -3), endDate: addDays(week.to, 2) });
  await call(page, 'createIssue', { projectId: project.id, title: '週二單日', startDate: week.days[1] });
  await call(page, 'createIssue', { projectId: project.id, title: '週二到週四', startDate: week.days[1], endDate: week.days[3] });
  await call(page, 'createIssue', { projectId: project.id, title: '只填結束日', endDate: week.days[4] });
  await call(page, 'createIssue', { projectId: project.id, title: '下週才開始', startDate: addDays(week.to, 1) });
  await call(page, 'createIssue', { projectId: project.id, title: '只有到期日', dueDate: week.days[2] });
  // 逾期：計畫結束日在今天之前且未關閉；週一當天沒有本週的過去日, 改放上週結束（不在畫面上）。
  const overdueEnd = addDays(today, -1), overdueVisible = overdueEnd >= week.from;
  await call(page, 'createIssue', { projectId: project.id, title: '逾期未完成', startDate: addDays(week.from, -1), endDate: overdueEnd });
  mkdirSync('artifacts/schedule-week', { recursive: true });
  if (process.env.SCHEDULE_BEFORE === '1') {
    await page.goto(`http://127.0.0.1:${port}/app/#/projects/${project.id}`);
    await page.locator('.sidebar nav').first().waitFor();
    for (const width of [1440, 768]) { await page.setViewportSize({ width, height: 900 }); await page.screenshot({ path: `artifacts/schedule-week/before-sidebar-${width}.png` }); }
  } else {
    page.on('request', r => { if (r.url().includes('/api/')) reads.push(r.url()); });
    await page.goto(`http://127.0.0.1:${port}/app/#/projects/${project.id}`);
    await page.getByRole('link', { name: '週曆' }).click();
    await page.waitForURL(/\/schedule$/); await scheduleReady(page);
    reads.length = 0; await page.reload(); await scheduleReady(page);
    const grid = page.locator('.schedule-grid');
    await grid.getByRole('button', { name: /跨週的長任務/ }).waitFor();
    for (const title of ['週二單日', '週二到週四', '只填結束日']) assert.equal(await grid.getByRole('button', { name: new RegExp(title) }).count(), 1, title);
    for (const title of ['下週才開始', '只有到期日']) assert.equal(await page.getByText(title).count(), 0, title);
    await page.getByText('1 張任務尚未排程').waitFor();
    // 重疊卡片不互相遮蓋：同一天的兩個條狀上下分開。
    const boxes = await Promise.all(['週二單日', '週二到週四'].map(title => grid.getByRole('button', { name: new RegExp(title) }).boundingBox()));
    assert.ok(boxes[0].y + boxes[0].height <= boxes[1].y || boxes[1].y + boxes[1].height <= boxes[0].y, JSON.stringify(boxes));
    if (overdueVisible) {
      const late = grid.getByRole('button', { name: /逾期未完成/ });
      assert.equal(await late.evaluate(el => el.classList.contains('overdue')), true);
      assert.match(await late.getAttribute('aria-label'), /已逾期/);
    }
    assert.equal(await grid.locator('.schedule-bar.overdue').count(), overdueVisible ? 1 : 0, '只有過期未關閉的卡片標逾期');
    const longBar = grid.getByRole('button', { name: /跨週的長任務/ });
    // 狀態色取專案狀態設定；全部成員時顯示承辦人縮寫, 未指派不顯示。
    assert.equal(await longBar.evaluate(el => getComputedStyle(el).getPropertyValue('--status-color').trim()), '#9a9da9');
    assert.equal(await longBar.locator('.avatar').textContent(), '週');
    assert.equal(await grid.getByRole('button', { name: /週二單日/ }).locator('.avatar').count(), 0);
    assert.equal(await longBar.evaluate(el => el.classList.contains('before') && el.classList.contains('after')), true);
    const schedule = reads.filter(url => url.includes('/schedule?')).map(url => new URL(url).searchParams);
    assert.equal(schedule.length, 1); assert.equal(schedule[0].get('from'), week.from); assert.equal(schedule[0].get('to'), week.to);
    assert.equal(reads.some(url => /\/projects\/[^/]+\/issues\?/.test(url)), false, '週曆不讀任務列表');
    assert.equal(await page.getByRole('checkbox', { name: '顯示已完成' }).isChecked(), false);
    await page.getByRole('checkbox', { name: '顯示已完成' }).check(); await scheduleReady(page);
    await page.reload(); await scheduleReady(page);
    assert.equal(await page.getByRole('checkbox', { name: '顯示已完成' }).isChecked(), true);
    assert.equal(new URL(reads.findLast(url => url.includes('/schedule?'))).searchParams.get('perPage'), '1000');
    assert.equal(await page.locator('[aria-label="時程分頁"]').count(), 0);
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 900 });
      if (width === 390) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${width} overflow`);
      await page.screenshot({ path: `artifacts/schedule-week/after-${width}.png`, fullPage: true });
    }
    // 窄螢幕逐日清單：跨日卡片每天都列出。
    const agenda = page.getByRole('list', { name: '本週每日任務' });
    assert.equal(await agenda.getByRole('button', { name: /跨週的長任務/ }).count(), 7);
    assert.equal(await agenda.getByRole('button', { name: /週二到週四/ }).count(), 3);
    await page.setViewportSize({ width: 1440, height: 900 });
    for (const width of [1440, 768]) { await page.setViewportSize({ width, height: 900 }); await page.screenshot({ path: `artifacts/schedule-week/after-sidebar-${width}.png` }); }
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.getByRole('button', { name: '下一週' }).click(); await scheduleReady(page);
    await grid.getByRole('button', { name: /下週才開始/ }).waitFor();
    assert.equal(await grid.getByRole('button', { name: /跨週的長任務/ }).evaluate(el => el.classList.contains('before') && !el.classList.contains('after')), true);
    await page.getByRole('button', { name: '本週' }).click(); await scheduleReady(page);
    const assigneeSelect = page.getByRole('combobox', { name: '承辦人', exact: true });
    await assigneeSelect.focus(); await assigneeSelect.locator(`option[value="${identity.user.id}"]`).waitFor({ state: 'attached' });
    await assigneeSelect.selectOption(identity.user.id); await scheduleReady(page);
    assert.equal(await grid.getByRole('button').count(), 1);
    assert.equal(await grid.locator('.schedule-bar .avatar').count(), 0, '篩選單一承辦人時不重複顯示縮寫');
    assert.equal(new URL(reads.findLast(url => url.includes('/schedule?'))).searchParams.get('assignee'), identity.user.id);
    await page.reload(); await scheduleReady(page);
    assert.equal(await grid.getByRole('button').count(), 1, '承辦人篩選在分頁重新整理後保留');
    await page.route('**/api/v1/projects/*/schedule?**', route => route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { code: 'INTERNAL_ERROR', message: '暫時無法讀取時程' } }) }));
    await page.getByRole('button', { name: '下一週' }).click(); await page.getByRole('alert').waitFor();
    await page.unroute('**/api/v1/projects/*/schedule?**'); await page.getByRole('button', { name: '重試' }).click(); await scheduleReady(page);
    assert.equal(await page.getByRole('alert').count(), 0);
    await page.getByRole('button', { name: '本週' }).click(); await scheduleReady(page);
    await longBar.click(); await page.getByRole('dialog').getByText(across.identifier || across.title.slice(0, 6)).first().waitFor();
    await page.route('**/api/v1/projects/*/schedule?**', async route => {
      const response = await route.fetch(), body = await response.json();
      body.data.total = 1001; await route.fulfill({ response, json: body });
    });
    await page.goto(`http://127.0.0.1:${port}/app/#/projects/${project.id}/schedule`); await page.reload(); await scheduleReady(page);
    await page.getByRole('alert').getByText('任務超過 1000 張, 請縮短期間或加篩選').waitFor();
    assert.equal(await page.locator('.schedule-bar').count(), 0, '超限不呈現片段推算');
    assert.deepEqual(errors, []); console.log('PASS Schedule week: planned-date bars, status color, overdue, assignee initials, single-ended, unscheduled count, non-overlapping lanes, week navigation, assignee filter, retry, mobile agenda and three widths');
  }
} finally { await browser?.close(); await new Promise(resolve => server ? server.httpServer.close(resolve) : resolve()); }
