import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';
if (process.env.E2E_API_URL !== 'http://127.0.0.1:13079') throw new Error('Use isolated visual API on 13079');
const before = process.env.E2E_BEFORE === '1', folder = `../artifacts/tt264/${before ? 'before' : 'after'}`;
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
async function measure(page, state) {
  const row = await page.locator('.todo-quick').evaluate(element => {
    const bounds = selector => { const b = element.querySelector(selector).getBoundingClientRect(); return { x: b.x, y: b.y, width: b.width, height: b.height, bottom: b.bottom }; };
    return { width: innerWidth, select: bounds('select'), search: bounds('.icon-button'), submit: bounds('button.primary'), overflow: document.documentElement.scrollWidth > innerWidth };
  });
  row.state = state; measurements.push(row);
  if (!before) {
    for (const name of ['select', 'search', 'submit']) assert.ok(row[name].height >= 44 && row[name].height <= 46, `${state} ${name}: ${JSON.stringify(row)}`);
    assert.ok(Math.abs(row.select.y - row.search.y) <= 1);
    if (row.width >= 480) assert.ok(Math.abs(row.select.y - row.submit.y) <= 1, 'submit aligns independent of expanded content');
    else assert.ok(row.submit.y >= row.select.bottom, 'mobile action gets own row');
    assert.equal(row.overflow, false);
  }
  await page.screenshot({ path: `${folder}/${row.width}-${state}.png`, animations: 'disabled', caret: 'hide' });
}
async function measureViewports(page, state) {
  for (const [width, height] of [[1440, 900], [768, 1024], [390, 844]]) {
    await page.setViewportSize({ width, height });
    if (width === 390) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    await measure(page, state);
  }
  await page.setViewportSize({ width: 1440, height: 900 });
}
try {
  server = await createServer({ server: { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await server.listen();
  browser = await chromium.launch({ headless: true, args: ['--disable-gpu'], ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'zh-TW', timezoneId: 'Asia/Taipei' }), page = await context.newPage();
  page.setDefaultTimeout(15000); page.on('pageerror', error => errors.push(error.message));
  await page.goto('http://127.0.0.1:5175');
  const key = crypto.randomUUID();
  const owner = await call(page, 'register', { name: '待辦管理者', email: `quick-owner-${key}@example.test`, password: 'synthetic-quick-test-only', workspaceName: '待辦排版測試' }, 'auth');
  const email = `quick-member-${key}@example.test`, invite = await call(page, 'inviteMember', { email });
  const member = await (await browser.newContext()).newPage(); await member.goto('http://127.0.0.1:5175');
  const recipient = await call(member, 'register', { name: '測試承辦者', email, password: 'synthetic-quick-test-only', inviteToken: invite.token }, 'auth');
  await page.reload();
  const todos = page.getByRole('region', { name: '首頁小待辦', exact: true }), input = todos.getByLabel('快速新增待辦', { exact: true });
  const choice = todos.getByRole('combobox', { name: '待辦交給誰', exact: true }), search = todos.getByRole('button', { name: '搜尋待辦交給誰選項', exact: true }), submit = todos.locator('.todo-quick > button.primary');
  await choice.locator(`option[value="${recipient.user.id}"]`).waitFor({ state: 'attached' });
  for (const [width, height] of [[1440, 900], [768, 1024], [390, 844]]) {
    await page.setViewportSize({ width, height });
    if (width === 390) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    await input.fill(''); await choice.selectOption(owner.user.id); assert.equal(await submit.isDisabled(), true); await measure(page, 'empty');
    await input.fill('## 速記\n\n- [ ] 確認工作範圍'); await measure(page, 'content');
    await search.click(); await todos.getByRole('textbox', { name: '搜尋待辦交給誰', exact: true }).waitFor(); await measure(page, 'search');
    await choice.selectOption(recipient.user.id); assert.equal(await submit.textContent(), '交辦'); await measure(page, 'assigned');
    await search.click(); await choice.selectOption(owner.user.id);
  }
  await page.setViewportSize({ width: 1440, height: 900 });
  let release; const waiting = new Promise(resolve => { release = resolve; });
  await page.route('**/api/v1/todo-assignees?*', async route => { await waiting; await route.continue(); }, { times: 1 });
  await search.click(); await todos.getByRole('textbox', { name: '搜尋待辦交給誰', exact: true }).fill('管理');
  await todos.getByRole('status').filter({ hasText: '載入選項中' }).waitFor(); await measureViewports(page, 'loading'); release();
  await todos.getByRole('status').filter({ hasText: '載入選項中' }).waitFor({ state: 'hidden' });
  await page.route('**/api/v1/todo-assignees?*', route => route.abort('failed'), { times: 1 });
  await todos.getByRole('textbox', { name: '搜尋待辦交給誰', exact: true }).fill('錯誤');
  await todos.getByRole('alert').waitFor(); await measureViewports(page, 'error');
  await todos.getByRole('button', { name: '重試', exact: true }).click(); await todos.getByRole('alert').waitFor({ state: 'hidden' });
  await todos.getByRole('textbox', { name: '搜尋待辦交給誰', exact: true }).fill('');
  await choice.locator(`option[value="${recipient.user.id}"]`).waitFor({ state: 'attached' });
  await search.click(); await choice.selectOption(owner.user.id); await input.fill('自己的Markdown速記\n\n- [ ] 準備測試');
  let writes = 0; page.on('request', request => { if (request.url().endsWith('/api/rpc/createTodo')) writes++; });
  let save; const saving = new Promise(resolve => { save = resolve; });
  await page.route('**/api/rpc/createTodo', async route => { await saving; await route.continue(); }, { times: 1 });
  await submit.click(); assert.equal(await submit.isDisabled(), true); assert.equal(await choice.isDisabled(), true); assert.equal(await search.isDisabled(), true); await measureViewports(page, 'saving');
  await submit.evaluate(element => element.click()); assert.equal(writes, 1); save();
  await todos.getByRole('status').filter({ hasText: '已加入待辦' }).waitFor();
  await input.fill('交辦給同事的速記'); await choice.selectOption(recipient.user.id); await submit.focus(); await submit.press('Enter');
  await todos.getByRole('status').filter({ hasText: '已加入待辦' }).waitFor();
  assert.equal(writes, 2);
  const received = await member.evaluate(async () => (await import('/src/api/index.js')).api.getTodos());
  assert.ok(received.todos.some(todo => todo.title === '交辦給同事的速記')); assert.equal(received.todos.some(todo => todo.title === '自己的Markdown速記'), false); assert.deepEqual(errors, []);
  console.log(`PASS: ${before ? 'before reproduction' : '44px responsive quick entry'}; loading/error/saving, keyboard, own/assigned, no duplicate writes`);
} finally { writeFileSync(`${folder}/measurements.json`, JSON.stringify({ measurements, errors }, null, 2)); await browser?.close(); await server?.close(); }
