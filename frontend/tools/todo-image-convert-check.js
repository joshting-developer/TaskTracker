import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';
// TT-294：含圖紙條轉卡時圖片複製成 Issue 附件並改寫引用；紙條原檔維持私有, 卡片詳情能顯示複本。
const port = Number(process.env.E2E_FRONTEND_PORT || 5175), baseURL = `http://127.0.0.1:${port}`;
if (process.env.E2E_ISOLATED !== '1' || !process.env.E2E_API_URL) throw new Error('請設定隔離 E2E_API_URL');
let vite, browser;
mkdirSync('artifacts/todo-image-convert', { recursive: true });
try {
  vite = await createServer({ server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await vite.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const page = await browser.newPage({ locale: 'zh-TW', viewport: { width: 1440, height: 1000 } });
  page.setDefaultTimeout(15000);
  const failures = []; page.on('pageerror', error => failures.push(error.message));
  await page.goto(`${baseURL}/login`);
  await page.evaluate(async () => (await import('/tools/fixtures/verified-api.js')).api.register({ name: '圖片轉卡', email: `todo-image-${crypto.randomUUID()}@example.test`, password: 'todo-image-password', workspaceName: '圖片轉卡驗證' }));
  const project = await page.evaluate(async () => (await import('/tools/fixtures/verified-api.js')).api.createProject({ name: '圖片卡片', code: 'PIC', requestId: crypto.randomUUID() }));
  await page.reload();
  // 從紙條建立頁貼上圖片, 走紙條附件端點。
  await page.getByRole('link', { name: '紙條', exact: true }).click();
  await page.getByRole('tab', { name: '建立', exact: true }).click();
  const home = page.getByRole('region', { name: '紙條', exact: true }), input = home.getByLabel('紙條內容', { exact: true });
  await input.fill('現場截圖\n\n');
  await input.evaluate(el => { el.setSelectionRange(el.value.length, el.value.length); const bytes = Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1cAAAAASUVORK5CYII='), c => c.charCodeAt(0)); const data = new DataTransfer(); data.items.add(new File([bytes], 'site.png', { type: 'image/png' })); el.dispatchEvent(new ClipboardEvent('paste', { bubbles: true, cancelable: true, clipboardData: data })); });
  await page.waitForFunction(() => /\]\(attachment:[0-9a-f-]{36}\)/.test(document.querySelector('[aria-label="紙條內容"]').value));
  const original = (await input.inputValue()).match(/attachment:([0-9a-f-]{36})/)[1];
  await home.getByRole('button', { name: '建立紙條', exact: true }).click();
  await home.getByRole('button', { name: /^更多：現場截圖/ }).first().click();
  const note = page.getByRole('dialog', { name: '紙條', exact: true });
  await note.getByRole('button', { name: '放大圖片：site.png', exact: true }).waitFor();
  await page.screenshot({ path: 'artifacts/todo-image-convert/todo-1440.png' });
  // 轉卡：可以選專案（紙條原有圖片不阻擋）, 建立後描述引用新的 Issue 附件。
  await note.getByRole('button', { name: '轉為卡片', exact: true }).click();
  const panel = page.getByRole('dialog', { name: '轉成正式卡片', exact: true });
  await panel.getByLabel('專案', { exact: true }).selectOption(project.id);
  assert.equal(await panel.getByLabel('專案', { exact: true }).inputValue(), project.id, '紙條原有圖片不阻擋選專案');
  await panel.getByRole('button', { name: '建立卡片並完成轉換', exact: true }).click();
  await panel.waitFor({ state: 'hidden' });
  const issue = await page.evaluate(async () => (await (await import('/tools/fixtures/verified-api.js')).api.getWorkspace()).issues[0]);
  const copied = issue.description.match(/attachment:([0-9a-f-]{36})/)?.[1];
  assert.ok(copied && copied !== original, '描述引用新的 Issue 附件');
  await page.goto(`${baseURL}/app/#/projects/${project.id}/issues/${issue.id}?detail=1`);
  const detail = page.getByRole('region', { name: '任務完整頁', exact: true });
  await detail.getByRole('button', { name: '放大圖片：site.png', exact: true }).waitFor();
  await detail.locator('.image-caption').getByText('1 × 1').first().waitFor();
  assert.equal(await page.evaluate(async id => (await fetch(`/api/v1/todo-attachments/${id}/content`)).status, original), 200, '紙條原檔仍給經手者');
  for (const width of [1440, 390]) { await page.setViewportSize({ width, height: 900 }); assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false); await page.screenshot({ path: `artifacts/todo-image-convert/issue-${width}.png`, fullPage: true }); }
  assert.deepEqual(failures, []);
  console.log('PASS todo image convert: paste to todo scope, drawer image, project pick allowed, copied issue attachment rendered, original kept private');
} finally { await browser?.close(); await vite?.close(); }
