import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
if (process.env.E2E_ISOLATED !== '1' || process.env.E2E_API_URL !== 'http://127.0.0.1:13089') throw Error('Owned isolated backend required');
const base = 'http://127.0.0.1:5291', artifacts = new URL('../../artifacts/tt293/', import.meta.url);
mkdirSync(artifacts, { recursive: true });
let server, browser;
try {
  server = await createServer({ root: new URL('../', import.meta.url).pathname, server: { host: '127.0.0.1', port: 5291, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const context = await browser.newContext({ permissions: ['clipboard-read', 'clipboard-write'], viewport: { width: 1440, height: 1050 } });
  const page = await context.newPage(), errors = []; page.setDefaultTimeout(15000); page.on('pageerror', e => errors.push(e.message));
  async function call(operation, input) {
    const csrf = await (await context.request.get(`${base}/api/csrf-cookie`)).json();
    const response = await context.request.post(`${base}/api/${operation === 'register' ? 'auth' : 'rpc'}/${operation}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), 200, await response.text()); return (await response.json()).data;
  }
  await call('register', { name: '留言貼圖測試', email: `comment-${crypto.randomUUID()}@example.test`, password: 'isolated-comment-browser', workspaceName: '留言測試' });
  const project = await call('createProject', { name: '留言測試', code: 'COMMENT' });
  const issue = await call('createIssue', { projectId: project.id, title: '留言圖片驗收' });
  await page.setContent('<body style="background:#fff;padding:24px;font:24px system-ui"><h1>測試圖片</h1><p>留言排版</p></body>');
  await page.setViewportSize({ width: 600, height: 220 }); const png = await page.screenshot();
  await page.setViewportSize({ width: 1440, height: 1050 });
  await page.goto(`${base}/app/#/projects/${project.id}`); await page.getByRole('button', { name: '留言圖片驗收', exact: true }).click();
  let form = page.locator('.comment-form'), input = form.getByLabel('新增留言', { exact: true });
  const uploadUrl = `**/api/v1/projects/${project.id}/attachments`;
  await page.waitForFunction(() => document.querySelector('button[aria-label="選擇新增留言圖片"]:not(:disabled)'));
  await input.fill('保留文字');
  await page.route(uploadUrl, route => route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { code: 'TEMPORARY', message: '上傳失敗測試' } }) }));
  await form.getByLabel('上傳新增留言圖片', { exact: true }).setInputFiles({ name: 'comment.png', mimeType: 'image/png', buffer: png });
  await form.getByRole('button', { name: '重試', exact: true }).waitFor();
  assert.equal(await input.inputValue(), '保留文字'); assert.ok(await form.getByRole('button', { name: '送出留言', exact: true }).isDisabled());
  await form.getByRole('button', { name: '取消', exact: true }).click(); assert.equal(await input.inputValue(), '保留文字');
  await page.unroute(uploadUrl);
  await input.focus(); await page.evaluate(async data => { await navigator.clipboard.write([new ClipboardItem({ 'image/png': new Blob([Uint8Array.from(atob(data), c => c.charCodeAt(0))], { type: 'image/png' }) })]); }, png.toString('base64'));
  await input.press('ControlOrMeta+v'); await form.getByText('已加入內容', { exact: true }).waitFor();
  const source = await input.inputValue(); assert.match(source, /attachment:/); assert.equal(await input.getAttribute('maxlength'), '2000');
  await form.getByRole('tab', { name: '預覽', exact: true }).click(); await form.locator('.image-preview img').waitFor();
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1050 }); await page.waitForFunction(() => innerWidth > 700 || document.querySelector('.sidebar').getBoundingClientRect().right <= 1); await form.scrollIntoViewIfNeeded(); await page.screenshot({ path: new URL(`comment-preview-${width}.png`, artifacts).pathname });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  }
  await form.getByRole('button', { name: '送出留言', exact: true }).click(); await page.locator('.comment .image-preview img').waitFor();
  await page.reload(); await page.locator('.comment .image-preview img').waitFor();
  const comments = await (await context.request.get(`${base}/api/v1/projects/${project.id}/issues/${issue.id}/comments?perPage=20&page=1`)).json();
  assert.equal(comments.data.items[0].content, source);
  await page.setViewportSize({ width: 1440, height: 1050 });
  await page.getByRole('button', { name: '返回任務列表', exact: true }).click();
  await page.getByRole('button', { name: '快速編輯 COMMENT-1', exact: true }).click();
  const drawer = page.getByRole('dialog').first(); await drawer.locator('.comment .image-preview img').waitFor();
  form = drawer.locator('.comment-form'); input = form.getByLabel('新增留言', { exact: true });
  await input.fill('抽屜留言'); await page.waitForFunction(() => document.querySelector('button[aria-label="選擇新增留言圖片"]:not(:disabled)'));
  const transfer = await page.evaluateHandle(data => { const t = new DataTransfer(); t.items.add(new File([Uint8Array.from(atob(data), c => c.charCodeAt(0))], 'drawer.png', { type: 'image/png' })); return t; }, png.toString('base64'));
  await input.dispatchEvent('drop', { dataTransfer: transfer }); await form.getByText('已加入內容', { exact: true }).waitFor();
  await form.getByRole('button', { name: /送出留言/ }).click(); await drawer.locator('.comment .image-preview img').nth(1).waitFor();
  assert.equal(await form.getByRole('list', { name: '圖片上傳狀態' }).count(), 0, 'Successful submission resets draft upload rows');
  assert.deepEqual(errors, []);
  writeFileSync(new URL('browser-result.json', artifacts), JSON.stringify({ passed: true, widths: [1440, 768, 390], checks: ['clipboard paste', 'failure/cancel preserve draft', 'unsettled blocks send', 'private preview', 'raw 2000 limit', 'send/reload', 'drawer drop/send', 'reset uploads'] }, null, 2));
  console.log('PASS: comment private images, detail/drawer, clipboard/drop, failure/cancel, raw limit, reset and three widths');
} finally { await browser?.close(); await server?.close(); }
