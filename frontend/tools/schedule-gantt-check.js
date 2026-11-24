import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
import { addDays, weekOf } from '../src/domain/schedule.js';
// TT-386：專案甘特以隔離後端和合成資料驗證 Epic 分組、推算區間、到期菱形、收合、期間切換與窄螢幕清單。
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
const ready = page => page.waitForFunction(() => document.querySelector('[aria-label="專案甘特"]')?.getAttribute('aria-busy') === 'false');
try {
  server = await preview({ preview: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': apiUrl } } });
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const base = `http://127.0.0.1:${port}`, page = await browser.newPage({ locale: 'zh-TW', viewport: { width: 1440, height: 900 } });
  page.setDefaultTimeout(15000);
  const errors = [], reads = []; page.on('pageerror', e => errors.push(e.message));
  await page.goto(`${base}/login`);
  await call(page, 'register', { name: '甘特驗證', email: `gantt-${crypto.randomUUID()}@example.test`, password: 'gantt-isolated-password', workspaceName: '甘特' }, 'auth');
  const project = await call(page, 'createProject', { name: '產品路線', code: 'GNT' });
  const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Taipei' }).format(new Date()), week = weekOf(today);
  // 有日期的 Epic 用自己的條狀；沒日期的 Epic 依子卡推算；沒有 Epic 的卡片也要出現。
  const dated = await call(page, 'createIssue', { projectId: project.id, title: '會員改版', type: 'epic', startDate: week.days[0], endDate: addDays(week.from, 20) });
  const undated = await call(page, 'createIssue', { projectId: project.id, title: '通知中心', type: 'epic' });
  await call(page, 'createIssue', { projectId: project.id, title: '登入頁面', epicId: dated.id, startDate: week.days[1], endDate: week.days[3], dueDate: week.days[5] });
  await call(page, 'createIssue', { projectId: project.id, title: '註冊流程延誤', epicId: dated.id, startDate: week.days[2], endDate: addDays(week.from, 9), dueDate: week.days[4] });
  await call(page, 'createIssue', { projectId: project.id, title: '推播設定', epicId: undated.id, startDate: addDays(week.from, 7), endDate: addDays(week.from, 10) });
  await call(page, 'createIssue', { projectId: project.id, title: '信件範本', epicId: undated.id, startDate: addDays(week.from, 12) });
  await call(page, 'createIssue', { projectId: project.id, title: '零散修正', startDate: week.days[4] });
  await call(page, 'createIssue', { projectId: project.id, title: '遠期研究', startDate: addDays(week.from, 50) });
  await call(page, 'createIssue', { projectId: project.id, title: '沒有日期' });
  mkdirSync('artifacts/schedule-gantt', { recursive: true });
  page.on('request', r => { if (r.url().includes('/api/')) reads.push(r.url()); });
  await page.goto(`${base}/app/#/projects/${project.id}`);
  await page.getByRole('link', { name: '專案甘特' }).click();
  await page.waitForURL(/\/gantt$/); await ready(page);
  reads.length = 0; await page.reload(); await ready(page);
  const body = page.getByRole('list', { name: '甘特項目' }), toggle = name => body.getByRole('button', { name: new RegExp(`${name}：`) });
  await toggle('會員改版').waitFor();
  assert.equal(await toggle('會員改版').getAttribute('aria-label'), `${dated.identifier} 會員改版：2張卡片`);
  assert.equal(await toggle('通知中心').getAttribute('aria-label'), `${undated.identifier} 通知中心：2張卡片`);
  assert.equal(await toggle('未歸入 Epic').getAttribute('aria-label'), '未歸入 Epic：1張卡片');
  assert.equal(await page.locator('.gantt-epic-bar').count(), 1, '有日期的 Epic 用自己的條狀');
  assert.equal(await page.locator('.gantt-derived').count(), 1, '沒有日期的 Epic 依子卡推算');
  assert.equal(await page.getByText('遠期研究').count(), 0, '4 週期間外不顯示');
  await page.getByText('2 張任務尚未排程').waitFor(); // 沒日期的 Epic 本身也算未排程
  // 到期菱形：計畫結束晚於到期日改警示, 準時的不警示。
  const late = body.getByRole('img', { name: /計畫晚於到期日/ });
  assert.equal(await late.count(), 1);
  assert.equal(await body.getByRole('img', { name: `到期日 ${week.days[5]}` }).count(), 1);
  const schedule = reads.filter(url => url.includes('/schedule?')).map(url => new URL(url).searchParams);
  assert.equal(schedule.length, 1); assert.equal(schedule[0].get('from'), week.from); assert.equal(schedule[0].get('to'), addDays(week.from, 27));
  assert.equal(reads.some(url => /\/projects\/[^/]+\/issues\?/.test(url)), false, '甘特不讀任務列表');
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    if (width === 390) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${width} overflow`);
    await page.screenshot({ path: `artifacts/schedule-gantt/after-${width}.png`, fullPage: true });
  }
  const agenda = page.getByRole('list', { name: '甘特清單' });
  assert.equal(await agenda.getByRole('button').count(), 5); await agenda.getByText('到期 ').first().waitFor(); assert.equal(await agenda.locator('.late').count(), 1, '清單也標出計畫晚於到期');
  await page.setViewportSize({ width: 1440, height: 900 });
  // 收合後子卡隱藏, 重新整理保留。
  await toggle('會員改版').click();
  assert.equal(await toggle('會員改版').getAttribute('aria-expanded'), 'false');
  assert.equal(await body.getByRole('button', { name: /登入頁面/ }).count(), 0);
  await page.reload(); await ready(page);
  assert.equal(await toggle('會員改版').getAttribute('aria-expanded'), 'false', '收合在分頁重新整理後保留');
  await toggle('會員改版').click();
  await page.getByRole('button', { name: '12 週' }).click(); await ready(page);
  await body.getByRole('button', { name: /遠期研究/ }).first().waitFor();
  assert.equal(new URL(reads.findLast(url => url.includes('/schedule?'))).searchParams.get('to'), addDays(week.from, 83));
  await page.screenshot({ path: 'artifacts/schedule-gantt/after-12weeks-1440.png' });
  await page.getByRole('button', { name: '往後' }).click(); await ready(page);
  assert.equal(new URL(reads.findLast(url => url.includes('/schedule?'))).searchParams.get('from'), addDays(week.from, 28));
  await page.getByRole('button', { name: '本週' }).click(); await ready(page);
  await page.route('**/api/v1/projects/*/schedule?**', route => route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { code: 'INTERNAL_ERROR', message: '暫時無法讀取時程' } }) }));
  await page.getByRole('button', { name: '往後' }).click(); await page.getByRole('alert').waitFor();
  await page.unroute('**/api/v1/projects/*/schedule?**'); await page.getByRole('button', { name: '重試' }).click(); await ready(page);
  assert.equal(await page.getByRole('alert').count(), 0);
  await page.getByRole('button', { name: '本週' }).click(); await ready(page);
  await body.getByRole('button', { name: /登入頁面/ }).first().click(); await page.getByRole('dialog').waitFor();
  assert.deepEqual(errors, []); console.log('PASS Project gantt: epic groups, own vs derived epic span, loose cards, due diamonds with late warning, collapse persistence, 4/12-week range, navigation, retry, detail, mobile list and three widths');
} finally { await browser?.close(); await new Promise(resolve => server ? server.httpServer.close(resolve) : resolve()); }
