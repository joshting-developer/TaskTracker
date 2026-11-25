import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
import { weekOf } from '../src/domain/schedule.js';
// TT-446：時程三視圖的未排程入口；以隔離後端驗證空狀態說明、清單條件、補日期、revision 衝突、詳情與三種寬度。
const port = Number(process.env.E2E_FRONTEND_PORT || 5199), apiUrl = process.env.E2E_API_URL;
assert.equal(process.env.E2E_ISOLATED, '1'); assert.ok(apiUrl);
const before = process.env.SCHEDULE_BEFORE === '1';
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
const ready = (page, label) => page.waitForFunction(name => document.querySelector(`[aria-label="${name}"]`)?.getAttribute('aria-busy') === 'false', label);
try {
  server = await preview({ preview: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': apiUrl } } });
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage({ locale: 'zh-TW', viewport: { width: 1440, height: 900 } });
  // 固定台灣週一, 和 TT-450 相同, 補上的日期不會因執行日變成逾期。
  const now = new Date('2026-11-23T12:00:00+08:00');
  await page.clock.setFixedTime(now);
  page.setDefaultTimeout(15000);
  const errors = [], reads = [], writes = []; page.on('pageerror', e => errors.push(e.message));
  await page.goto(`http://127.0.0.1:${port}/login`);
  const owner = await call(page, 'register', { name: '補日期驗證', email: `unscheduled-${crypto.randomUUID()}@example.test`, password: 'unscheduled-isolated-password', workspaceName: '補日期' }, 'auth');
  const project = await call(page, 'createProject', { name: '補日期', code: 'UNS' });
  const other = await call(page, 'createProject', { name: '別的專案', code: 'OTH' });
  const week = weekOf(new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Taipei' }).format(now));
  const mine = await call(page, 'createIssue', { projectId: project.id, title: '我的未排程任務', assignee: owner.user.id, dueDate: week.days[4] });
  const loose = await call(page, 'createIssue', { projectId: project.id, title: '還沒指派的未排程任務' });
  await call(page, 'createIssue', { projectId: project.id, title: '已經做完的未排程任務', state: 'closed' });
  await call(page, 'createIssue', { projectId: other.id, title: '別的專案的任務' });
  mkdirSync('artifacts/schedule-unscheduled', { recursive: true });
  page.on('request', r => { if (r.url().includes('/api/')) (r.method() === 'GET' ? reads : writes).push(r.url()); });
  await page.goto(`http://127.0.0.1:${port}/app/#/projects/${project.id}`);
  await page.getByRole('link', { name: '週曆' }).click();
  await page.waitForURL(/\/schedule$/); await ready(page, '週曆');
  await (before ? page.getByText('2 張任務尚未排程') : page.getByRole('button', { name: '2 張任務尚未排程' })).waitFor();
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await page.screenshot({ path: `artifacts/schedule-unscheduled/${before ? 'before' : 'after'}-week-${width}.png`, fullPage: true });
  }
  await page.setViewportSize({ width: 1440, height: 900 });
  if (before) { console.log('SAVED before screenshots'); process.exit(0); }

  // 空狀態說明為什麼是空的、怎麼補；入口是按鈕, 已關閉預設不算
  await page.getByText('任務要有計畫開始或結束日才會出現在這裡, 可以按下方「2 張任務尚未排程」補上日期').waitFor();
  reads.length = 0;
  await page.getByRole('button', { name: '2 張任務尚未排程' }).click();
  const dialog = page.getByRole('dialog', { name: '尚未排程的任務' });
  await dialog.getByRole('button', { name: /我的未排程任務/ }).waitFor();
  assert.equal(await dialog.getByRole('listitem').count(), 2);
  assert.equal(await dialog.getByText('已經做完的未排程任務').count(), 0, '預設不列已關閉');
  assert.equal(await dialog.getByText('別的專案的任務').count(), 0, '只列目前 Project');
  await dialog.getByText(`到期日 ${week.days[4]}`).waitFor();
  const listed = reads.filter(url => url.includes('/schedule/unscheduled')).map(url => new URL(url).searchParams);
  assert.equal(listed.length, 1); assert.equal(listed[0].get('perPage'), '20'); assert.equal(listed[0].get('includeClosed'), '0');
  assert.equal(reads.some(url => /\/projects\/[^/]+\/issues\?/.test(url)), false, '不讀整個任務列表');
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    assert.equal(await dialog.evaluate(el => el.scrollWidth > el.clientWidth + 1), false, `${width} dialog overflow`);
    await page.screenshot({ path: `artifacts/schedule-unscheduled/after-dialog-${width}.png` });
  }
  await page.setViewportSize({ width: 1440, height: 900 });

  // 本地檢查：沒填日期、結束早於開始都不送出
  const row = title => dialog.getByRole('listitem').filter({ hasText: title });
  writes.length = 0;
  await row('還沒指派').getByRole('button', { name: /儲存/ }).click();
  await row('還沒指派').getByRole('alert').getByText('請填開始日或結束日').waitFor();
  await row('還沒指派').getByLabel('計畫開始日').fill(week.days[3]);
  await row('還沒指派').getByLabel('計畫結束日').fill(week.days[1]);
  await row('還沒指派').getByRole('button', { name: /儲存/ }).click();
  await row('還沒指派').getByRole('alert').getByText('計畫結束日不可早於開始日').waitFor();
  assert.equal(writes.filter(url => url.endsWith('/updateIssue')).length, 0, '本地驗證不送出');

  // 補日期：存完離開清單, 視圖重讀後出現條狀與新的計數
  await row('還沒指派').getByLabel('計畫結束日').fill(week.days[4]);
  await row('還沒指派').getByRole('button', { name: /儲存/ }).click();
  await dialog.getByRole('status').getByText(`${loose.identifier} 已排進時程`).waitFor();
  await page.waitForFunction(() => document.querySelectorAll('dialog.schedule-unscheduled li').length === 1);
  assert.equal(writes.filter(url => url.endsWith('/updateIssue')).length, 1);

  // revision 衝突：別人先改了同一張, 不覆蓋, 重讀後再存
  await row('我的未排程任務').getByLabel('計畫開始日').fill(week.days[0]);
  await call(page, 'updateIssue', { id: mine.id, title: '我的未排程任務（別人改了標題）', expectedRevision: mine.revision });
  await row('我的未排程任務').getByRole('button', { name: /儲存/ }).click();
  await dialog.getByRole('status').getByText(`${mine.identifier} 剛被其他人修改, 已重新讀取最新內容, 請確認後再存`).waitFor();
  await row('別人改了標題').waitFor();
  assert.equal(await row('別人改了標題').getByLabel('計畫開始日').inputValue(), '', '衝突後以伺服器最新內容為準');
  await row('別人改了標題').getByLabel('計畫開始日').fill(week.days[0]);
  await row('別人改了標題').getByRole('button', { name: /儲存/ }).click();
  await dialog.getByText('所有任務都已排程').waitFor();
  await dialog.getByRole('button', { name: '關閉' }).click();
  await ready(page, '週曆');
  const grid = page.locator('.schedule-grid');
  await grid.getByRole('button', { name: /還沒指派的未排程任務/ }).waitFor();
  await grid.getByRole('button', { name: /別人改了標題/ }).waitFor();
  assert.equal(await page.getByRole('button', { name: /張任務尚未排程/ }).count(), 0, '全部排完不再顯示入口');

  // 顯示已完成時, 計數與清單一起包含已關閉；從清單點標題打開詳情
  await page.getByRole('checkbox', { name: '顯示已完成' }).check(); await ready(page, '週曆');
  await page.getByRole('button', { name: '1 張任務尚未排程' }).click();
  await dialog.getByRole('button', { name: /已經做完的未排程任務/ }).click();
  assert.equal(await dialog.count(), 0, '開詳情時關閉對話框');
  await page.getByText('已經做完的未排程任務').first().waitFor();

  // 人員排程與甘特共用同一入口；「顯示已完成」各視圖分開保存, 預設不算已關閉
  for (const [path, label] of [['people', '人員排程'], ['gantt', '專案甘特']]) {
    await page.goto(`http://127.0.0.1:${port}/app/#/projects/${project.id}/${path}`); await ready(page, label);
    assert.equal(await page.getByRole('button', { name: /張任務尚未排程/ }).count(), 0, `${label} 預設不列已關閉`);
    await page.getByRole('checkbox', { name: '顯示已完成' }).check(); await ready(page, label);
    await page.getByRole('button', { name: '1 張任務尚未排程' }).click(); await dialog.getByRole('button', { name: /已經做完的未排程任務/ }).waitFor();
    await page.keyboard.press('Escape'); assert.equal(await dialog.count(), 0, `${label} Esc 關閉`);
  }
  assert.deepEqual(errors, []);
  console.log('PASS Unscheduled entry: empty-state hint, bounded list with closed/project scope, local date validation, save moves card into view, revision conflict reload, detail, three views and three widths');
} finally { await browser?.close(); await server?.httpServer.close(); }
