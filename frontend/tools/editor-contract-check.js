import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';
assert.equal(process.env.E2E_ISOLATED, '1'); assert.ok(process.env.E2E_API_URL);
let server, browser;
const port = Number(process.env.E2E_FRONTEND_PORT || 5199), base = `http://127.0.0.1:${port}`;
try {
  server = await createServer({ server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await server.listen();
  browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ locale: 'zh-TW', viewport: { width: 1440, height: 900 } }); page.setDefaultTimeout(15000);
  const errors = [], uploads = []; page.on('pageerror', e => errors.push(e.message)); page.on('request', r => { if (/\/api\/v1\/.*attachments/.test(r.url())) uploads.push(r.url()); });
  await page.goto(`${base}/login`);
  const todo = await page.evaluate(async () => {
    const { api } = await import('/tools/fixtures/verified-api.js');
    const identity = await api.register({ name: '編輯契約', email: `editor-${crypto.randomUUID()}@example.test`, password: 'editor-isolated-password', workspaceName: '共用編輯器' });
    return api.createTodo({ content: '確認紙條回覆', assigneeId: identity.user.id, requestId: crypto.randomUUID() });
  });
  await page.goto(`${base}/app/#/notes`); await page.getByRole('button', { name: '更多：確認紙條回覆', exact: true }).click();
  const drawer = page.getByRole('dialog', { name: '紙條', exact: true });
  const input = drawer.getByLabel('回覆內容', { exact: true }); await input.fill('回覆草稿');
  mkdirSync('artifacts/editor-contract', { recursive: true });
  const before = process.env.EDITOR_BEFORE === '1';
  if (!before) {
    const editor = drawer.getByRole('group', { name: '回覆內容編輯器', exact: true });
    await input.evaluate(el => el.setSelectionRange(0, el.value.length));
    await input.press('ControlOrMeta+b'); assert.equal(await input.inputValue(), '**回覆草稿**');
    await input.press('ControlOrMeta+z'); assert.equal(await input.inputValue(), '回覆草稿');
    await input.dispatchEvent('compositionstart'); await input.dispatchEvent('keydown', { key: 'b', ctrlKey: true, isComposing: true }); assert.equal(await input.inputValue(), '回覆草稿'); await input.dispatchEvent('compositionend');
    // TT-294：紙條只收圖片；ZIP 拖放不上傳且保留文字。
    const transfer = await page.evaluateHandle(() => { const d = new DataTransfer(); d.items.add(new File(['fake'], 'private.zip', { type: 'application/zip' })); return d; });
    await input.dispatchEvent('drop', { dataTransfer: transfer }); assert.equal(await input.inputValue(), '回覆草稿'); assert.deepEqual(uploads.filter(url => !url.endsWith('/limits')), []);
    await editor.getByRole('button', { name: '移除', exact: true }).click();
    await input.fill('**回覆草稿**'); await editor.getByRole('tab', { name: '預覽', exact: true }).click(); await editor.locator('.editor-preview strong').getByText('回覆草稿', { exact: true }).waitFor();
    await editor.getByRole('tab', { name: '編輯', exact: true }).click(); assert.equal(await input.inputValue(), '**回覆草稿**');
    // 貼上圖片走紙條附件端點, 不經任何 Project。
    await input.evaluate(el => { el.setSelectionRange(el.value.length, el.value.length); const bytes = Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1cAAAAASUVORK5CYII='), c => c.charCodeAt(0)); const data = new DataTransfer(); data.items.add(new File([bytes], 'private.png', { type: 'image/png' })); el.dispatchEvent(new ClipboardEvent('paste', { bubbles: true, cancelable: true, clipboardData: data })); });
    await page.waitForFunction(() => /\]\(attachment:[0-9a-f-]{36}\)/.test(document.querySelector('[aria-label="回覆內容"]').value));
    assert.ok(uploads.length && uploads.every(url => url.includes('/api/v1/todo-attachments')), JSON.stringify(uploads));
    let asked = false; page.once('dialog', async d => { asked = true; await d.dismiss(); }); await drawer.getByRole('button', { name: '關閉紙條' }).click(); assert.equal(asked, true); assert.ok(await drawer.isVisible());
  }
  for (const width of [1440, 768, 390]) { await page.setViewportSize({ width, height: 900 }); assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false); await page.screenshot({ path: `artifacts/editor-contract/${before ? 'before' : 'after'}-${width}.png`, fullPage: true }); }
  if (!before) {
    await drawer.getByRole('button', { name: '完成', exact: true }).click();
    await page.waitForFunction(() => !document.querySelector('.note-transfer'));
    const comments = await page.evaluate(async id => (await import('/tools/fixtures/verified-api.js')).api.todoComments(id, { page: 1, perPage: 20 }), todo.id);
    const saved = comments.items.find(row => row.content.startsWith('**回覆草稿**'));
    assert.ok(saved && /attachment:/.test(saved.content), 'Markdown reply with image persists through completion');
    const imageId = saved.content.match(/attachment:([0-9a-f-]{36})/)[1];
    assert.equal(await page.evaluate(async id => (await fetch(`/api/v1/todo-attachments/${id}/content`)).status, imageId), 200, '經手者可讀綁定後的紙條圖片');
    // 對話中的圖片透過紙條附件端點載入並解出尺寸。
    await drawer.getByRole('button', { name: '放大圖片：private.png', exact: true }).waitFor();
    await drawer.locator('.image-caption').getByText('1 × 1').waitFor();
    await page.screenshot({ path: 'artifacts/editor-contract/todo-image-1440.png' });
  }
  assert.deepEqual(errors, []); console.log(`PASS editor contract ${before ? 'baseline' : 'reply formatting, IME, undo, draft guard, todo image paste, zip refusal and three widths'}`);
} finally { await browser?.close(); await server?.close(); }
