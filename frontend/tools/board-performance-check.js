import assert from 'node:assert/strict';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
import { moveBoardCard } from './board-actions.js';

if (process.env.E2E_API_URL !== 'http://127.0.0.1:13029') throw new Error('500卡fixture只允許獨立MySQL測試服務13029，禁止指向業務服務');
const root = new URL('..', import.meta.url).pathname;
const artifacts = new URL('../artifacts/board-performance/', import.meta.url); mkdirSync(artifacts, { recursive: true });
const fixturePath = new URL('fixture.json', artifacts);
let server, browser;
try {
  server = await preview({ root, preview: { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  page.setDefaultTimeout(20000);
  const errors = [], calls = [], confirmations = [];
  page.on('dialog', async dialog => { confirmations.push(dialog.message()); await dialog.dismiss(); });
  page.on('pageerror', error => errors.push(error.message));
  page.on('request', request => { if (request.url().includes('/api/')) calls.push(new URL(request.url()).pathname); });
  const base = 'http://127.0.0.1:5175';
  await page.goto(base);
  async function call(operation, input = {}, group = 'rpc') {
    return page.evaluate(async ({ operation, input, group }) => {
      const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
      const result = await (await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
      if (!result.ok) throw new Error(result.error?.message);
      return result.data;
    }, { operation, input, group });
  }
  let fixture;
  if (process.env.E2E_REUSE_FIXTURE === '1') {
    fixture = JSON.parse(readFileSync(fixturePath));
    assert.equal(fixture.api, process.env.E2E_API_URL);
    await call('login', fixture.login, 'auth');
  } else {
    const login = { email: `large-${crypto.randomUUID()}@example.test`, password: 'isolated-test-password' };
    const identity = await call('register', { ...login, name: 'Large Board', workspaceName: 'TT181 isolated' }, 'auth');
    const project = await call('createProject', { name: 'TT181 500-card fixture', code: 'LARGE' });
    const epics = [];
    for (let n = 0; n < 5; n++) epics.push(await call('createIssue', { projectId: project.id, title: `Epic ${n + 1}`, type: 'epic' }));
    let last;
    for (let n = 0; n < 495; n++) {
      last = await call('createIssue', { projectId: project.id, title: `Work ${String(n + 1).padStart(3, '0')} ${n % 3 ? '工作卡片' : '較長的卡片標題與說明文字用來驗證換行與捲動行為'}`, epicId: epics[n % 5].id, state: ['open', 'inprogress', 'closed'][n % 3], assignee: n % 2 ? identity.user.id : '', estimateMinutes: n % 4 * 30 });
      if (n % 100 === 99) console.log('Fixture issues', n + 6);
    }
    fixture = { api: process.env.E2E_API_URL, login, project, epics, last };
    // 僅為忽略的隔離假帳號，不得用於業務服務或提交憑證。
    writeFileSync(fixturePath, JSON.stringify(fixture), { mode: 0o600 });
  }
  const url = `${base}/#/projects/${fixture.project.id}/board`;
  const session = await page.context().newCDPSession(page);
  await session.send('Performance.enable');
  const cpuRate = Number(process.env.E2E_CPU_RATE || 4);
  await session.send('Emulation.setCPUThrottlingRate', { rate: cpuRate });
  await page.addInitScript(() => {
    window.boardLongTasks = [];
    new PerformanceObserver(list => window.boardLongTasks.push(...list.getEntries().map(item => ({ start: item.startTime, duration: item.duration })))).observe({ type: 'longtask', buffered: true });
  });
  async function metrics() { return Object.fromEntries((await session.send('Performance.getMetrics')).metrics.map(item => [item.name, item.value])); }
  const samples = [];
  for (const variant of (process.env.E2E_INTERACTIONS_ONLY === '1' ? [] : ['baseline', 'containment', 'baseline', 'containment', 'baseline', 'containment'])) {
    await page.goto(url); await page.reload();
    await page.waitForFunction(() => document.querySelectorAll('.issue-card').length === 500);
    await page.evaluate(() => window.scrollTo(0, 0));
    if (variant === 'baseline') await page.addStyleTag({ content: '.visual-board .issue-card { content-visibility: visible !important; contain-intrinsic-size: none !important; }' });
    else await page.addStyleTag({ content: '.visual-board .issue-card { content-visibility: auto; contain-intrinsic-size: auto 190px; } .visual-board.compact-cards .issue-card { contain-intrinsic-size: auto 110px; }' });
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    const readyAtMs = await page.evaluate(() => performance.now());
    const before = await metrics();
    const start = performance.now();
    await page.evaluate(async () => {
      const frame = () => new Promise(resolve => requestAnimationFrame(resolve));
      for (let n = 0; n < 20; n++) { window.scrollTo(0, document.documentElement.scrollHeight * n / 19); await frame(); }
      window.scrollTo(0, 0); await frame();
    });
    for (let n = 0; n < 6; n++) await page.setViewportSize({ width: n % 2 ? 1440 : 1380, height: 1000 });
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    const after = await metrics();
    const scrollResizeElapsedMs = Math.round(performance.now() - start);
    await page.evaluate(async () => {
      const cells = [...document.querySelectorAll('.board-cell')];
      for (let n = 0; n < 20; n++) { cells[n % cells.length].dispatchEvent(new Event('dragover', { bubbles: true, cancelable: true })); await new Promise(resolve => requestAnimationFrame(resolve)); }
    });
    const afterDrag = await metrics();
    const data = await page.evaluate(() => ({ domNodes: document.querySelectorAll('*').length, cards: document.querySelectorAll('.issue-card').length, scrollHeight: document.documentElement.scrollHeight, longTasks: window.boardLongTasks }));
    samples.push({ variant, readyAtMs: Math.round(readyAtMs), initialLongTasks: data.longTasks.filter(task => task.start + task.duration <= readyAtMs), scenarioLongTasks: data.longTasks.filter(task => task.start + task.duration > readyAtMs), scrollResizeElapsedMs, dragFeedbackMs: Math.round(performance.now() - start) - scrollResizeElapsedMs, dragScriptMs: Math.round((afterDrag.ScriptDuration - after.ScriptDuration) * 100000) / 100, layoutMs: Math.round((after.LayoutDuration - before.LayoutDuration) * 100000) / 100, styleMs: Math.round((after.RecalcStyleDuration - before.RecalcStyleDuration) * 100000) / 100, scriptMs: Math.round((after.ScriptDuration - before.ScriptDuration) * 100000) / 100, ...data });
  }
  await page.goto(url); await page.reload(); await page.waitForFunction(() => document.querySelectorAll('.issue-card').length === 500);
  if (process.env.E2E_CANDIDATE_INTERACTIONS === '1') {
    await page.addStyleTag({ content: '.issue-card { content-visibility: auto; contain-intrinsic-size: auto 190px; } .compact-cards .issue-card { contain-intrinsic-size: auto 110px; } .issue-card:focus-within { content-visibility: visible; }' });
  }
  async function furthestCard() {
    const id = await page.locator('.issue-card').evaluateAll(elements => elements.map(element => ({ id: element.dataset.issue, bottom: element.getBoundingClientRect().bottom })).sort((a, b) => b.bottom - a.bottom)[0].id);
    return page.locator(`.issue-card[data-issue="${id}"]`);
  }
  const last = await furthestCard();
  await last.scrollIntoViewIfNeeded();
  await last.focus();
  assert.equal(await last.evaluate(element => getComputedStyle(element).contentVisibility), 'visible');
  await last.press('F2');
  const editor = page.locator('.issue-dialog'); await editor.waitFor();
  await editor.getByRole('button', { name: '關閉任務', exact: true }).click();
  await editor.waitFor({ state: 'detached' });
  assert.deepEqual(confirmations, [], '既有Epic開啟後未修改，關閉不應誤判為草稿');
  await page.getByLabel('看板泳道', { exact: true }).selectOption('epic');
  await page.waitForFunction(() => document.querySelectorAll('.board-lane').length >= 5);
  assert.equal(await page.locator('.issue-card').count(), 500);
  const laneToggle = page.locator('.lane-toggle').first();
  await laneToggle.click();
  await page.waitForFunction(() => document.querySelectorAll('.issue-card').length === 400);
  await laneToggle.click();
  await page.waitForFunction(() => document.querySelectorAll('.issue-card').length === 500);
  await page.getByLabel('看板泳道', { exact: true }).selectOption('none');
  const first = page.locator('.issue-card').first(), identifier = await first.getAttribute('data-issue');
  const state = await first.evaluate(element => element.closest('[data-column]').dataset.column);
  await moveBoardCard(page, identifier, state === 'open' ? 'inprogress' : 'open');
  assert.equal(await page.locator('.issue-card').count(), 500);
  const search = page.getByRole('textbox', { name: '搜尋任務', exact: true });
  await search.fill('Work 495');
  await page.waitForFunction(() => document.querySelectorAll('.issue-card').length === 1);
  assert.equal(await page.locator('.large-board').count(), 0);
  await search.fill('');
  await page.waitForFunction(() => document.querySelectorAll('.issue-card').length === 500);
  await page.getByLabel('看板卡片密度', { exact: true }).selectOption('compact');
  await page.locator('.compact-cards').waitFor();
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1000 });
    if (width === 390) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    const bottomCard = await furthestCard();
    await bottomCard.scrollIntoViewIfNeeded();
    const box = await bottomCard.boundingBox();
    assert(box.y >= 0 && box.y + box.height <= 1001, '最長欄的底卡必須完整可見');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({ path: new URL(`bottom-${width}.png`, artifacts).pathname });
  }
  assert.deepEqual(errors, []);
  assert(!calls.some(path => /\/(getWorkspace|getTodos)$/.test(path)));
  const report = { interactionVariant: process.env.E2E_CANDIDATE_INTERACTIONS === '1' ? 'containment-candidate' : 'unchanged-production', build: process.env.E2E_BUILD_LABEL || 'baseline', browser: await browser.version(), viewport: '1440x1000', samples, cpuRate, totalCards: 500, data: '5 Epic + 495 tasks, real isolated MySQL HTTP 100/page', cache: 'warm Chrome assets; API requests are real; CSS experiment added after load; drag feedback uses20 alternating dragover events plus real DnD below', note: 'CDP layout/style/script counters measure scroll+resize; initialLongTasks end before settled500-card readyAtMs; scenarioLongTasks are scroll/resize/drag feedback; not RUM/INP.' };
  writeFileSync(new URL(process.env.E2E_INTERACTIONS_ONLY === '1' ? 'interactions.json' : 'measurements.json', artifacts), JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  console.log('PASS: all500 / bottom / F2 / drag / lanes / density / mobile / bulkReads=0');
} finally { await browser?.close(); await server?.httpServer.close(); }
