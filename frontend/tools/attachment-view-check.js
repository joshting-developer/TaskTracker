import { fixtureResponse } from './fixtures/verification.js';
import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
if (process.env.E2E_ISOLATED !== '1' || !process.env.E2E_API_URL) throw new Error('Explicit isolated backend required');
const port = Number(process.env.E2E_FRONTEND_PORT || 5291), base = `http://127.0.0.1:${port}`;
let server, browser;
const artifact = new URL('../../artifacts/tt287/', import.meta.url); mkdirSync(artifact, { recursive: true });
try {
  let fixture;
  const serveFixture = (_req, res) => {
    const sourceRoot = _req.url.includes('before=1') ? '/artifacts/tt287-before' : '/src';
    res.setHeader('Content-Type', 'text/html');
    res.end(`<!doctype html><html lang="zh-Hant"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TT-287 附件檢視</title><style>body{font:14px system-ui,sans-serif;color:#242936;margin:0;background:#fff}main{max-width:900px;padding:24px;margin:auto}h1{font-size:22px}h2{font-size:16px}button{font:inherit;background:none;border:0;cursor:pointer}*{box-sizing:border-box}</style><main id="app"></main><script type="module">
import {createApp, ref, h} from '/node_modules/.vite/deps/vue.js';
import MarkdownContent from '${sourceRoot}/components/MarkdownContent.vue';
import MarkdownEditor from '${sourceRoot}/components/MarkdownEditor.vue';
import {setWorkspaceContext} from '/src/api/http.js';
const fixture=${JSON.stringify(fixture).replaceAll('<', '\\u003c')};setWorkspaceContext(fixture.workspace);
window.setWorkspaceContext=setWorkspaceContext;
createApp({setup(){const source=ref(fixture.source);window.setSource=value=>source.value=value;return()=>[h('h1','登入頁排版討論'),h('h2','描述'),h(MarkdownContent,{source:source.value,projectId:fixture.project}),h('h2','編輯預覽'),h(MarkdownEditor,{modelValue:source.value,'onUpdate:modelValue':value=>source.value=value,label:'描述',projectId:fixture.project}),h('div',{hidden:true},[h(MarkdownContent,{source:source.value})])]}}).mount('#app');</script></html>`);
  };
  server = await createServer({ root: new URL('../', import.meta.url).pathname, optimizeDeps: { include: ['vue', 'marked', 'dompurify'] }, plugins: [{ name: 'isolated-attachment-review', configureServer(instance) { instance.middlewares.use('/__attachment_review', serveFixture); } }], server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage(); const errors = [], external = [];
  page.on('pageerror', e => { errors.push(e.message); console.error('browser:', e.message); }); page.on('response', r => { if(r.status() >= 400) console.error('response:', r.status(), new URL(r.url()).pathname); }); page.on('request', r => { if (r.url().includes('tracker.example')) external.push(r.url()); });
  async function call(path, data, method = 'POST', client = context) {
    const csrf = await (await client.request.get(`${base}/api/csrf-cookie`)).json();
    const response = await client.request.fetch(`${base}${path}`, { method, data: { requestId: crypto.randomUUID(), ...data }, headers: { 'X-CSRF-TOKEN': csrf.csrfToken } });
    assert.equal(response.status(), 200, await response.text()); return (await fixtureResponse(client.request, response)).data;
  }
  const identity = await call('/api/auth/register', { name: '附件檢視測試', email: `view-${crypto.randomUUID()}@example.test`, password: 'isolated-image-view', workspaceName: '附件測試' });
  const project = await call('/api/rpc/createProject', { name: '附件測試', code: 'VIEW' });
  const issue = await call('/api/rpc/createIssue', { projectId: project.id, title: '圖片顯示測試' });
  // A real PNG from a synthetic product mock, no external image assets or private content.
  await page.setContent('<body style="margin:0;background:#f4f5f8;font:20px system-ui;color:#242936;padding:32px"><h1 style="font-size:28px">登入頁排版</h1><div style="background:white;padding:28px;border:1px solid #ccd1db;border-radius:6px;max-width:540px"><h2>登入 TaskTracker</h2><p>電子郵件</p><div style="height:44px;border:1px solid #9da5b5;border-radius:4px"></div><p>密碼</p><div style="height:44px;border:1px solid #9da5b5;border-radius:4px"></div><p style="background:#6653bd;color:white;padding:12px;text-align:center;border-radius:4px">登入</p></div></body>');
  await page.setViewportSize({ width: 760, height: 520 }); const png = await page.screenshot();
  const csrf = await (await context.request.get(`${base}/api/csrf-cookie`)).json();
  const response = await context.request.post(`${base}/api/v1/projects/${project.id}/attachments`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, multipart: { requestId: crypto.randomUUID(), file: { name: 'login-layout.png', mimeType: 'image/png', buffer: png } } });
  assert.equal(response.status(), 200, await response.text()); const attachment = (await fixtureResponse(context.request, response)).data;
  const bound = await call(`/api/v1/projects/${project.id}/attachments/${attachment.id}/bind`, { expectedRevision: 1, issueId: issue.id, issueRevision: 1 });
  const source = `請確認欄位間距與按鈕寬度\n\n![登入頁排版](attachment:${attachment.id})\n\n![外部圖片](https://tracker.example/pixel.png)\n\n![不安全圖片](data:image/svg+xml,<svg onload=alert(1)>)\n\n<img src="https://tracker.example/raw.png" onerror="alert(1)">\n\n- [x] 保留 Markdown 清單`;
  fixture = { workspace: identity.workspace.id, project: project.id, source };
  await page.setViewportSize({ width: 1440, height: 1000 }); await page.goto(`${base}/__attachment_review`);
  const preview = page.getByRole('button', { name: '放大圖片：登入頁排版', exact: true }).first();
  await preview.waitFor({ timeout: 10000 }).catch(async error => { writeFileSync(new URL('failure.html', artifact), await page.content()); throw error; }); assert.equal(await page.locator('.image-preview img').count(), 1); assert.equal(external.length, 0);
  assert.equal(await page.locator('input[type=checkbox]').first().isChecked(), true);
  fixture.source = `請確認欄位間距與按鈕寬度\n\n![登入頁排版](attachment:${attachment.id})`;
  await page.evaluate(clean => window.setSource(clean), fixture.source);
  await preview.click(); const dialog = page.getByRole('dialog', { name: '圖片：登入頁排版' }).first(); await dialog.waitFor();
  await page.keyboard.press('Escape'); await dialog.waitFor({ state: 'hidden' }); await assert.doesNotReject(() => preview.evaluate(el => { if (el !== document.activeElement) throw Error('Focus not restored'); }));
  await page.getByRole('tab', { name: '預覽', exact: true }).click();
  await page.locator('.editor-preview .image-preview').waitFor();
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1000 }); await page.mouse.move(1, 1); await page.screenshot({ path: new URL(`description-${width}.png`, artifact).pathname, fullPage: true });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    await preview.click(); await dialog.waitFor();
    await page.screenshot({ path: new URL(`lightbox-${width}.png`, artifact).pathname, fullPage: true });
    await page.keyboard.press('Tab'); assert.equal(await page.evaluate(() => !!document.activeElement?.closest('dialog')), true);
    await page.keyboard.press('Escape');
  }
  await page.screenshot({ path: new URL('editor-preview-390.png', artifact).pathname, fullPage: true });
  writeFileSync(new URL('inspect.html', artifact), (await page.content()).replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '').replace(/blob:http[^\"]+/g, `data:image/png;base64,${png.toString('base64')}`));
  if (process.env.E2E_BEFORE_REF) {
    for (const path of ['components/MarkdownContent.vue', 'components/MarkdownEditor.vue', 'ui/markdown.js', 'ui/tabs.js']) {
      const target = new URL(`../artifacts/tt287-before/${path}`, import.meta.url); mkdirSync(new URL('.', target), { recursive: true });
      writeFileSync(target, execFileSync('git', ['show', `${process.env.E2E_BEFORE_REF}:frontend/src/${path}`], { cwd: new URL('../../', import.meta.url).pathname }));
    }
    const before = await context.newPage(); await before.goto(`${base}/__attachment_review?before=1`);
    await before.getByRole('tab', { name: '預覽', exact: true }).click();
    for (const width of [1440, 768, 390]) {
      await before.setViewportSize({ width, height: 1000 }); await before.screenshot({ path: new URL(`before-${width}.png`, artifact).pathname, fullPage: true });
    }
    await before.close();
  }
  const download = page.waitForEvent('download'); await preview.click(); await dialog.getByRole('button', { name: '下載圖片' }).click(); assert.equal((await download).suggestedFilename(), 'login-layout.png'); await page.keyboard.press('Escape');
  const other = await browser.newContext();
  const otherIdentity = await call('/api/auth/register', { name: 'Other tenant', email: `view-other-${crypto.randomUUID()}@example.test`, password: 'isolated-image-view', workspaceName: 'Other tenant' }, 'POST', other);
  fixture.workspace = otherIdentity.workspace.id;
  const otherPage = await other.newPage(); await otherPage.goto(`${base}/__attachment_review`);
  await otherPage.getByRole('status').getByText('圖片無法載入或已無存取權限', { exact: true }).waitFor();
  assert.equal(await otherPage.locator('.image-preview img').count(), 0);
  await other.close();
  // The server deletes metadata; a fresh viewing action must not use the old authorized blob.
  await call(`/api/v1/projects/${project.id}/attachments/${attachment.id}`, { expectedRevision: bound.revision, issueId: issue.id, issueRevision: bound.issueRevision }, 'DELETE');
  await preview.click(); await page.locator('.image-placeholder').getByText('圖片無法載入或已無存取權限', { exact: true }).first().waitFor();
  await page.evaluate(() => window.setWorkspaceContext('another-workspace'));
  assert.equal(await page.locator('.attachment-image img').count(), 0);
  const cap = await page.evaluate(async () => {
    const { renderMarkdownDocument } = await import('/src/ui/markdown.js');
    const id = '00000000-0000-0000-0000-000000000001';
    const capped = renderMarkdownDocument(Array.from({length: 25}, () => `![a](attachment:${id})`).join('\n\n'), true);
    const unsafe = renderMarkdownDocument('![a](attachment:../../secret) ![b](javascript:alert(1)) <span data-attachment-slot="0">evil</span>', true);
    return { count: capped.attachments.length, unsafe: unsafe.attachments.length };
  });
  assert.deepEqual(cap, { count: 20, unsafe: 0 });
  assert.deepEqual(external, []); assert.deepEqual(errors, []);
  writeFileSync(new URL('result.json', artifact), JSON.stringify({ passed: true, widths: [1440, 768, 390], screenshots: 7, checks: ['real HTTP private image', 'Markdown preview', 'no external image requests', 'escaped HTML/XSS', 'keyboard focus/Escape', 'fresh authorized download', 'deleted attachment rejected', 'workspace cleanup', 'cross-tenant refusal', '20-reference cap and traversal refusal'] }, null, 2));
  console.log('PASS: private Markdown images, three widths, keyboard/lightbox/download, external/XSS refusal, deletion and workspace cleanup');
} finally { await browser?.close(); await server?.close(); }
