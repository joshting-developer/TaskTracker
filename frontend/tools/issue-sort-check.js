// TT-306：任務列表排序（建立日期、方向按鈕、可及名稱、重新整理保留、換排序回預設、到期日反向）、三尺寸。只接受隔離API。
import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';
const e2ePort = Number(process.env.E2E_FRONTEND_PORT || 5175); // TT-335：Claude 5198、Codex 5199, 見.agents/skills/tasktracker-test-environments
const api = process.env.E2E_API_URL || '';
if (!/^http:\/\/127\.0\.0\.1:13\d{3}$/.test(api)) throw new Error('請設定隔離E2E_API_URL（127.0.0.1:13xxx），不可使用業務資料庫');
const folder = 'artifacts/tt306';
mkdirSync(folder, { recursive: true });
let server, browser;
const errors = [], measurements = [];
async function call(page, operation, input = {}, group = 'rpc') {
  return page.evaluate(async ({ operation, input, group }) => {
    const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
    const result = await (await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
    if (!result.ok) throw new Error(result.error?.message || operation);
    if (result.data?.verificationRequired) { const csrf = await (await fetch('/api/csrf-cookie')).json(); const verified = await fetch('/api/auth/verify-email', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: JSON.stringify({ code: 'local666' }) }); if (!verified.ok) throw new Error('Fixture email verification failed'); result.data = (await verified.json()).data; } return result.data;
  }, { operation, input, group });
}
try {
  server = await createServer({ server: { host: '127.0.0.1', port: e2ePort, strictPort: true, proxy: { '/api': api } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, locale: 'zh-TW', reducedMotion: 'reduce' });
  page.setDefaultTimeout(15000);
  page.on('pageerror', error => errors.push(error.message));
  await page.goto(`http://127.0.0.1:${e2ePort}/login`);
  await call(page, 'register', { name: '排序驗收', email: `tt306-${crypto.randomUUID()}@example.test`, password: 'tt306-test-password', workspaceName: '排序驗收' }, 'auth');
  const project = await call(page, 'createProject', { name: '排序', code: 'TTSRT' });
  // 依序建立：第一張最舊；只有第二、三張有到期日。
  for (const [title, dueDate] of [['第一張', ''], ['第二張', '2026-12-20'], ['第三張', '2026-12-10'], ['第四張', '']]) await call(page, 'createIssue', { projectId: project.id, title, dueDate });
  const url = `http://127.0.0.1:${e2ePort}/app/#/projects/${project.id}`;
  await page.goto(url);
  const titles = () => page.locator('.issue-table .task-title').allInnerTexts();
  // 換排序後列表會延遲重讀；等到順序符合預期再比對, 最多5秒。
  async function expectOrder(check, message) {
    const deadline = Date.now() + 5000; let last;
    while (Date.now() < deadline) { last = await titles(); if (check(last)) return last; await page.waitForTimeout(150); }
    assert.fail(`${message}：${last.join('、')}`);
  }
  const sort = page.getByLabel('排序方式');
  await page.locator('.issue-table').waitFor();

  await sort.selectOption('created');
  const direction = page.locator('.sort-direction');
  assert.equal(await direction.getAttribute('aria-label'), '目前新到舊，切換為舊到新');
  await expectOrder(t => t.join() === '第四張,第三張,第二張,第一張', '建立日期預設新到舊');
  await direction.click();
  assert.equal(await direction.getAttribute('aria-label'), '目前舊到新，切換為新到舊');
  await expectOrder(t => t.join() === '第一張,第二張,第三張,第四張', '建立日期舊到新');

  // 重新整理後排序和方向還在。
  await page.reload();
  await page.locator('.issue-table').waitFor();
  assert.equal(await page.getByLabel('排序方式').inputValue(), 'created');
  assert.equal(await page.locator('.sort-direction').getAttribute('aria-label'), '目前舊到新，切換為新到舊', '重新整理保留方向');
  await expectOrder(t => t.join() === '第一張,第二張,第三張,第四張', '重新整理後順序');

  // 換排序時方向回到預設；到期日反向時沒填的排最前。
  await page.getByLabel('排序方式').selectOption('due');
  assert.equal(await page.locator('.sort-direction').getAttribute('aria-label'), '目前近到遠，切換為遠到近');
  await expectOrder(t => t.slice(0, 2).join() === '第三張,第二張', '近到遠, 有日期的在前');
  await page.locator('.sort-direction').click();
  await expectOrder(t => t.slice(2).join() === '第二張,第三張', '遠到近, 沒填的排前面');
  await page.locator('.sort-direction').focus();
  await page.keyboard.press('Enter');
  assert.equal(await page.locator('.sort-direction').getAttribute('aria-label'), '目前近到遠，切換為遠到近', '鍵盤可以切換');

  for (const [width, height] of [[1440, 900], [768, 1024], [390, 844]]) {
    await page.setViewportSize({ width, height }); await page.waitForTimeout(100);
    const box = await page.locator('.sort-direction').boundingBox();
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
    measurements.push({ width, button: [box.width, box.height], overflow });
    assert.equal(overflow, false, `${width} 無橫向溢出`);
    assert.ok(box.height >= (width <= 700 ? 44 : 38), `${width} 按鈕大小`);
    await page.screenshot({ path: `${folder}/${width}-list.png`, animations: 'disabled', caret: 'hide' });
  }
  assert.deepEqual(errors, []);
  writeFileSync(`${folder}/measurements.json`, JSON.stringify(measurements, null, 2));
  console.log('PASS: created sort, direction toggle + labels + keyboard, persisted after reload, reset on sort change, due desc nulls first, 3 viewports');
} finally {
  await browser?.close();
  await server?.close();
}
