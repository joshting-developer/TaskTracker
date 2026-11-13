import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
if (process.env.E2E_ISOLATED !== '1' || !process.env.E2E_API_URL) throw Error('Explicit isolated backend required');
const port = Number(process.env.E2E_FRONTEND_PORT || 5291), base = `http://127.0.0.1:${port}`;
const artifacts = new URL('../../artifacts/tt282/', import.meta.url); mkdirSync(artifacts, { recursive: true });
let vite, browser;
try {
  vite = await createServer({ root: new URL('../', import.meta.url).pathname, server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await vite.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1050 }, permissions: ['clipboard-read', 'clipboard-write'] });
  const page = await context.newPage(), errors = []; page.on('pageerror', error => errors.push(error.message)); page.setDefaultTimeout(15000);
  async function call(path, data) {
    const csrf = await (await context.request.get(`${base}/api/csrf-cookie`)).json();
    const response = await context.request.post(`${base}${path}`, { data: { requestId: crypto.randomUUID(), ...data }, headers: { 'X-CSRF-TOKEN': csrf.csrfToken } });
    assert.equal(response.status(), 200, await response.text()); return (await response.json()).data;
  }
  const identity = await call('/api/auth/register', { name: '描述圖片測試', email: `draft-${crypto.randomUUID()}@example.test`, password: 'isolated-draft-password', workspaceName: '圖片草稿驗證' });
  const project = await call('/api/rpc/createProject', { name: '會員介面', code: 'DRAFT' });
  // A deterministic private image from synthetic content, no external or user data.
  await page.setContent('<body style="margin:0;background:#f5f6f8;color:#242936;font:20px system-ui;padding:32px"><h1>登入畫面</h1><p>請確認輸入欄位與按鈕間距</p><div style="height:80px;background:white;border:1px solid #bfc6d2"></div></body>');
  await page.setViewportSize({ width: 700, height: 300 }); const png = await page.screenshot();
  const formats = await page.evaluate(async b64 => { const bitmap = await createImageBitmap(new Blob([Uint8Array.from(atob(b64), c => c.charCodeAt(0))], {type:'image/png'})); const canvas = document.createElement('canvas'); canvas.width = bitmap.width; canvas.height = bitmap.height; canvas.getContext('2d').drawImage(bitmap,0,0); return {webp:canvas.toDataURL('image/webp',.9).split(',')[1], jpeg:canvas.toDataURL('image/jpeg',.9).split(',')[1]}; }, png.toString('base64'));
  await page.setViewportSize({ width: 1440, height: 1050 }); const root = `${base}/app/#/projects/${project.id}`; await page.goto(root);
  await page.getByRole('button', { name: '新增任務', exact: false }).first().click();
  const editor = page.getByRole('dialog').first(); await editor.getByLabel(/任務標題/).fill('描述貼圖驗證');
  const input = editor.getByLabel('描述', { exact: true }), file = editor.getByLabel('上傳描述圖片', { exact: true });
  await editor.getByRole('button', { name: '選擇描述圖片' }).waitFor();
  await page.waitForFunction(() => document.querySelector('button[aria-label="選擇描述圖片"]:not(:disabled)'));
  await input.fill('前段\n後段'); await input.evaluate(element => element.setSelectionRange(3, 3));
  let failed = false; const retryIds = [];
  await page.route(`**/api/v1/projects/${project.id}/attachments`, async route => {
    const request = route.request();
    if (request.method() !== 'POST') return route.continue();
    retryIds.push(request.postDataBuffer()?.toString().match(/name="requestId"\r\n\r\n([^\r]+)/)?.[1]);
    if (!failed) { failed = true; return route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { code: 'TEMPORARY', message: '測試暫時失敗' } }) }); }
    await new Promise(resolve => setTimeout(resolve, 300)); await route.continue();
  });
  await file.setInputFiles({ name: 'login.webp', mimeType: 'image/webp', buffer: Buffer.from(formats.webp, 'base64') });
  const uploads = editor.getByRole('list', { name: '圖片上傳狀態' }); await uploads.getByRole('button', { name: '重試' }).waitFor();
  assert.equal(await input.inputValue(), '前段\n後段'); assert.equal(await editor.getByRole('button', { name: '建立任務', exact: true }).isDisabled(), true);
  await uploads.getByRole('button', { name: '重試' }).click(); await input.evaluate(el => { el.focus(); el.setSelectionRange(el.value.length, el.value.length); }); await input.type('保留新文字');
  await uploads.getByText('已加入內容', { exact: true }).waitFor(); assert.equal(retryIds.length, 2); assert.equal(retryIds[0], retryIds[1]);
  assert.match(await input.inputValue(), /前段\n\n!\[login.webp\]\(attachment:[\da-f-]+\)\n後段保留新文字/);
  await page.unroute(`**/api/v1/projects/${project.id}/attachments`);
  // Actual browser clipboard API followed by the platform paste shortcut.
  await input.focus(); await input.press('End');
  await page.evaluate(async b64 => { const bytes = Uint8Array.from(atob(b64), c => c.charCodeAt(0)); await navigator.clipboard.write([new ClipboardItem({ 'image/png': new Blob([bytes], { type: 'image/png' }) })]); }, png.toString('base64'));
  await input.press('ControlOrMeta+v'); await page.waitForFunction(() => document.querySelector('textarea[name=description]').value.match(/attachment:/g)?.length === 2);
  // Drag a real browser File into the editor.
  const transfer = await page.evaluateHandle(b64 => { const data = new DataTransfer(); data.items.add(new File([Uint8Array.from(atob(b64), c => c.charCodeAt(0))], 'dropped.jpg', { type: 'image/jpeg' })); return data; }, formats.jpeg);
  await input.dispatchEvent('drop', { dataTransfer: transfer }); await page.waitForFunction(() => document.querySelector('textarea[name=description]').value.match(/attachment:/g)?.length === 3);
  const source = await input.inputValue(); assert.ok(source.length < 6000); assert.ok(!source.includes('data:image'));
  await editor.getByRole('tab', { name: '預覽', exact: true }).first().click(); await editor.locator('.editor-preview .image-preview').first().waitFor();
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1050 }); await editor.locator('.panel-content').evaluate(el => el.scrollTop = 0); await page.mouse.move(1, 1);
    await page.waitForLoadState('networkidle');
    await editor.locator('.editor-preview .image-caption').first().getByText(/700 × 300/).waitFor();
    assert.equal(await editor.evaluate(el => el.scrollWidth <= el.clientWidth), true);
    await page.screenshot({ path: new URL(`draft-${width}.png`, artifacts).pathname, fullPage: true });
  }
  writeFileSync(new URL('inspect.html', artifacts), (await page.content()).replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '').replace(/blob:http[^\"]+/g, `data:image/png;base64,${png.toString('base64')}`));
  await editor.getByRole('button', { name: '建立任務', exact: true }).click(); await editor.waitFor({ state: 'hidden' });
  const list = await (await context.request.get(`${base}/api/v1/projects/${project.id}/issues?perPage=20&page=1`)).json(); const issue = list.data.items.find(item => item.title === '描述貼圖驗證'); assert.ok(issue);
  const detail = await (await context.request.get(`${base}/api/v1/projects/${project.id}/issues/${issue.id}`)).json(); assert.equal(detail.data.description, source);
  const attachments = await (await context.request.get(`${base}/api/v1/projects/${project.id}/issues/${issue.id}/attachments`)).json(); assert.equal(attachments.data.items.length, 3); assert.ok(attachments.data.items.every(item => item.status === 'active'));
  await page.reload(); await page.getByRole('button', { name: '描述貼圖驗證', exact: true }).click();
  await page.locator('.issue-detail-description .image-preview').first().waitFor();
  await page.goto(root); await page.getByRole('button', { name: '新增任務', exact: false }).first().click();
  await editor.getByLabel(/任務標題/).fill('取消與輸入法'); await input.fill('保留取消草稿');
  await page.waitForFunction(() => document.querySelector('button[aria-label="選擇描述圖片"]:not(:disabled)'));
  await page.route(`**/api/v1/projects/${project.id}/attachments`, async route => { await new Promise(resolve => setTimeout(resolve, 500)); await route.continue().catch(() => {}); });
  await file.setInputFiles({ name: 'cancel.png', mimeType: 'image/png', buffer: png });
  await uploads.getByRole('button', { name: '取消', exact: true }).click();
  assert.equal(await input.inputValue(), '保留取消草稿'); await uploads.waitFor({ state: 'hidden' });
  await page.unroute(`**/api/v1/projects/${project.id}/attachments`);
  await file.setInputFiles({ name: 'unsafe.svg', mimeType: 'image/svg+xml', buffer: Buffer.from('<svg onload=alert(1)>') });
  await editor.getByRole('alert').getByText(/僅接受/).waitFor(); assert.equal(await input.inputValue(), '保留取消草稿');
  await input.fill('輸入法測試'); await input.dispatchEvent('compositionstart');
  await file.setInputFiles({ name: 'ime.png', mimeType: 'image/png', buffer: png });
  await uploads.getByText('100%', { exact: true }).waitFor(); assert.equal(await input.inputValue(), '輸入法測試');
  await input.dispatchEvent('compositionend'); await uploads.getByText('已加入內容', { exact: true }).waitFor();
  assert.match(await input.inputValue(), /attachment:/);
  await input.press('ControlOrMeta+z'); assert.equal(await input.inputValue(), '輸入法測試');
  assert.deepEqual(errors, []);
  writeFileSync(new URL('result.json', artifacts), JSON.stringify({ passed: true, project: project.id, issue: issue.id, widths: [1440, 768, 390], checks: ['real browser clipboard shortcut', 'real JPEG drop and WebP file input', 'stable retry requestId', 'failure preserves text', 'pending save guard', 'typing bookmark preservation', 'atomic create binds three images', 'reload protected images', 'cancel preserves source', 'SVG refusal', 'IME deferred insert', 'native undo'] }, null, 2));
  console.log('PASS: real IssuePanel clipboard/drop/file, retry, progress/save guard, text preservation, three widths, atomic bind and reload');
} finally { await browser?.close(); await vite?.close(); }
