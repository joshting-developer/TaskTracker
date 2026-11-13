// TT-248：看板列表的總數／已完成數、編輯收錄入口與三尺寸版面；只接受隔離API。
import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';
const api = process.env.E2E_API_URL || '';
if (!/^http:\/\/127\.0\.0\.1:13\d{3}$/.test(api)) throw new Error('請設定隔離E2E_API_URL（127.0.0.1:13xxx），不可使用業務資料庫');
const before = process.env.E2E_BEFORE === '1', folder = `artifacts/tt248/${before ? 'before' : 'after'}`;
mkdirSync(folder, { recursive: true });
let server, browser;
const measurements = [], errors = [];
async function call(page, operation, input = {}, group = 'rpc') {
  return page.evaluate(async ({ operation, input, group }) => {
    const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
    const result = await (await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
    if (!result.ok) throw new Error(result.error?.message || operation);
    return result.data;
  }, { operation, input, group });
}
try {
  server = await createServer({ server: { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': api } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, locale: 'zh-TW', reducedMotion: 'reduce' });
  page.setDefaultTimeout(15000);
  page.on('pageerror', error => errors.push(error.message));
  await page.goto('http://127.0.0.1:5175/login');
  await call(page, 'register', { name: '看板列表驗收', email: `tt248-${crypto.randomUUID()}@example.test`, password: 'tt248-test-password', workspaceName: '看板列表' }, 'auth');
  const project = await call(page, 'createProject', { name: '交付追蹤', code: 'TTBL' });
  const create = input => call(page, 'createIssue', { projectId: project.id, ...input });
  const epic = await create({ title: '會員功能', type: 'epic' });
  const done = await create({ title: '登入頁', epicId: epic.id, state: 'closed' });
  await create({ title: '註冊信', epicId: epic.id });
  await create({ title: '密碼重設', epicId: epic.id, state: 'inprogress' });
  await create({ title: '看板外已完成', state: 'closed' });
  await call(page, 'createBoard', { projectId: project.id, name: '會員功能看板', selectedIssueIds: [epic.id, done.id] });
  await call(page, 'createBoard', { projectId: project.id, name: '空看板', selectedIssueIds: [] });
  const requests = [];
  page.on('request', request => { if (request.url().includes('/api/')) requests.push(new URL(request.url()).pathname); });
  await page.goto(`http://127.0.0.1:5175/app/#/projects/${project.id}/boards`);
  await page.reload();
  const card = name => page.locator('.board-summary-card').filter({ has: page.getByRole('button', { name: `開啟看板 ${name}`, exact: true }) });
  await card('會員功能看板').waitFor();
  if (!before) {
    // Default：5張含2張closed；自訂：Epic＋3子卡去重為4張，其中1張closed；空看板0／0。
    for (const [name, total, closed] of [['Default', 5, 2], ['會員功能看板', 4, 1], ['空看板', 0, 0]]) {
      const counts = card(name).locator('.board-summary-counts');
      assert.equal((await counts.innerText()).replace(/\s+/g, ' ').trim(), `${total} 張卡片 已完成 ${closed}`, name);
      const width = await card(name).locator('.board-summary-progress > span').evaluate(element => element.style.width);
      assert.equal(width, `${total ? Math.round(closed / total * 100) : 0}%`, `${name} progress`);
      const described = await card(name).getByRole('button', { name: `開啟看板 ${name}`, exact: true }).getAttribute('aria-describedby');
      assert.equal(await page.locator(`#${described}`).count(), 1, 'counts described for assistive tech');
    }
    assert.equal(await card('Default').getByRole('button', { name: /^編輯看板/ }).count(), 0, 'Default沒有編輯收錄');
    assert.ok(!requests.some(path => path.endsWith('/getWorkspace')), `看板列表不讀整包Workspace: ${requests.join(',')}`);
    await page.keyboard.press('Tab');
    const edit = card('會員功能看板').getByRole('button', { name: '編輯看板 會員功能看板', exact: true });
    await edit.focus();
    assert.equal(await edit.evaluate(element => element === document.activeElement), true);
  }
  for (const [width, height] of [[1440, 900], [768, 1024], [390, 844]]) {
    await page.setViewportSize({ width, height });
    await page.waitForTimeout(100);
    const row = await page.evaluate(() => {
      const button = document.querySelector('.board-summary-card .board-summary-edit, .board-summary-card .text-button');
      const box = button?.getBoundingClientRect();
      return { width: innerWidth, overflow: document.documentElement.scrollWidth > innerWidth, edit: box && { height: box.height, width: box.width }, cards: document.querySelectorAll('.board-summary-card').length };
    });
    measurements.push(row);
    if (!before) {
      assert.equal(row.overflow, false, `${width} 無橫向溢出`);
      assert.ok(row.edit.height >= (width <= 760 ? 44 : 36), `${width} 編輯入口高度 ${row.edit.height}`);
    }
    await page.screenshot({ path: `${folder}/${width}-boards.png`, fullPage: true, animations: 'disabled', caret: 'hide' });
  }
  if (!before) {
    await page.setViewportSize({ width: 1440, height: 900 });
    await card('會員功能看板').getByRole('button', { name: '編輯看板 會員功能看板', exact: true }).click();
    await page.getByRole('dialog').waitFor();
  }
  assert.deepEqual(errors, []);
  writeFileSync(`${folder}/measurements.json`, JSON.stringify(measurements, null, 2));
  console.log(`PASS ${before ? 'before' : 'after'}: board list counts, closed type, Epic dedupe, edit entry, 3 viewports`);
} finally {
  await browser?.close();
  await server?.close();
}
