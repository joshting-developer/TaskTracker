import { requireIsolatedApi } from './fixtures/isolated-api.js';
import { fixtureResponse } from './fixtures/verification.js';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
requireIsolatedApi({ requireIsolatedFlag: true }); // TT-424：拖入編輯器的 PDF/ZIP 走一般附件, 圖片照舊進內文
const port = Number(process.env.E2E_FRONTEND_PORT || 5291), base = `http://127.0.0.1:${port}`,
  artifacts = new URL('../../artifacts/tt424/', import.meta.url); mkdirSync(artifacts, { recursive: true });
let server, browser;
try {
  server = await createServer({ root: new URL('../', import.meta.url).pathname, server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const context = await browser.newContext({ locale: 'zh-TW', viewport: { width: 1440, height: 1050 } }), page = await context.newPage(); page.setDefaultTimeout(15000);
  const errors = [], binds = []; page.on('pageerror', e => errors.push(e.message));
  page.on('request', r => { if (r.method() === 'POST' && /\/attachments\/[^/]+\/bind$/.test(r.url())) binds.push(JSON.parse(r.postData())); });
  async function call(operation, input) {
    const csrf = await (await context.request.get(`${base}/api/csrf-cookie`)).json();
    const response = await context.request.post(`${base}/api/${operation === 'register' ? 'auth' : 'rpc'}/${operation}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), 200, await response.text()); return (await fixtureResponse(context.request, response)).data;
  }
  async function attachments(issueId) {
    const result = await (await context.request.get(`${base}/api/v1/projects/${project.id}/issues/${issueId}/attachments?page=1&perPage=20`)).json();
    return result.data.items.map(item => `${item.name}:${item.mime}`).sort();
  }
  // Drop real browser File objects, the way a desktop drag delivers them.
  async function drop(target, files) {
    const transfer = await page.evaluateHandle(list => { const data = new DataTransfer(); for (const f of list) data.items.add(new File([Uint8Array.from(atob(f.b64), c => c.charCodeAt(0))], f.name, { type: f.type })); return data; }, files.map(f => ({ ...f, b64: f.buffer.toString('base64') })));
    await target.dispatchEvent('dragenter', { dataTransfer: transfer }); await target.dispatchEvent('drop', { dataTransfer: transfer });
  }
  await call('register', { name: '拖放測試', email: `drop-${crypto.randomUUID()}@example.test`, password: 'isolated-drop-files', workspaceName: '拖放附件' });
  const project = await call('createProject', { name: '拖放附件', code: 'DROP' });
  const zip = execFileSync('python3', ['-c', "import io,zipfile,sys\nb=io.BytesIO()\nwith zipfile.ZipFile(b,'w') as z:z.writestr('spec/readme.txt','synthetic data')\nsys.stdout.buffer.write(b.getvalue())"]);
  const pdf = Buffer.from('%PDF-1.7\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n');
  await page.setContent('<body style="margin:0;background:#fff;font:20px system-ui;padding:24px">拖放圖片</body>'); await page.setViewportSize({ width: 320, height: 160 });
  const png = await page.screenshot(); await page.setViewportSize({ width: 1440, height: 1050 });

  // 1. New issue: one drop with an image, a ZIP and unsupported files; nothing binds before the issue exists.
  await page.goto(`${base}/app/#/projects/${project.id}`);
  await page.getByRole('button', { name: '新增任務', exact: false }).first().click();
  const editor = page.getByRole('dialog').first(); await editor.getByLabel(/任務標題/).fill('拖放附件驗收');
  const input = editor.getByLabel('描述', { exact: true }), group = editor.getByRole('group', { name: '描述編輯器', exact: true });
  await page.waitForFunction(() => document.querySelector('button[aria-label="選擇描述附件"]:not(:disabled)'));
  await input.fill('說明文字');
  const writing = group.locator('.editor-writing');
  const hover = await page.evaluateHandle(() => { const data = new DataTransfer(); data.items.add(new File(['x'], 'hover.zip', { type: 'application/zip' })); return data; });
  await writing.dispatchEvent('dragenter', { dataTransfer: hover }); await group.getByText('放開即可加入圖片或 PDF、ZIP 附件').waitFor();
  await page.screenshot({ path: new URL('drop-zone-1440.png', artifacts).pathname });
  await writing.dispatchEvent('dragleave', { dataTransfer: hover }); assert.equal(await group.locator('.drop-hint').count(), 0, 'Drop zone hides after leaving');
  await drop(input, [{ name: 'screen.png', type: 'image/png', buffer: png }, { name: 'spec.zip', type: 'application/zip', buffer: zip }, { name: 'notes.txt', type: 'text/plain', buffer: Buffer.from('plain') }, { name: 'logo.svg', type: 'image/svg+xml', buffer: Buffer.from('<svg/>') }]);
  const rows = editor.getByRole('list', { name: '附件上傳狀態' });
  await rows.getByText('已加入內容', { exact: true }).waitFor(); await rows.getByText('儲存後附到任務', { exact: true }).waitFor();
  assert.equal(await rows.getByText('僅接受 PNG、JPEG、WebP、PDF、ZIP', { exact: true }).count(), 2, 'Each unsupported file shows its reason');
  const source = await input.inputValue(); assert.match(source, /^說明文字/); assert.match(source, /!\[screen\.png\]\(attachment:[\da-f-]+\)/); assert.ok(!/spec\.zip|notes\.txt|logo\.svg/.test(source), 'Only the image enters the text');
  // A ZIP renamed to .png still reaches the server's content check and is never embedded.
  await drop(input, [{ name: 'fake.png', type: 'image/png', buffer: zip }]);
  await rows.locator('li.failed').getByText(/不符合限制/).waitFor(); assert.ok(!(await input.inputValue()).includes('fake.png'));
  assert.ok(await editor.getByRole('button', { name: '建立任務', exact: true }).isDisabled(), 'A failed upload still blocks saving');
  await rows.locator('li.failed').getByRole('button', { name: '取消', exact: true }).click();
  await rows.getByRole('button', { name: '移除', exact: true }).first().click(); assert.equal(await rows.getByRole('button', { name: '移除', exact: true }).count(), 1);
  assert.equal(binds.length, 0, 'Files wait for the issue to exist');
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1050 }); await rows.scrollIntoViewIfNeeded(); await page.screenshot({ path: new URL(`new-issue-rows-${width}.png`, artifacts).pathname });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `No horizontal overflow at ${width}`);
  }
  await page.setViewportSize({ width: 1440, height: 1050 });
  await editor.getByRole('button', { name: '建立任務', exact: true }).click(); await editor.waitFor({ state: 'hidden' });
  assert.equal(binds.length, 1); const issueId = binds[0].issueId;
  assert.deepEqual(await attachments(issueId), ['screen.png:image/png', 'spec.zip:application/zip']);

  // 2. Existing issue: attaching only a file saves without a field change and without a revision conflict.
  await page.getByRole('button', { name: '快速編輯 DROP-1', exact: true }).click();
  const drawer = page.getByRole('dialog').first(), drawerInput = drawer.getByLabel('描述', { exact: true });
  await page.waitForFunction(() => document.querySelector('button[aria-label="選擇描述附件"]:not(:disabled)'));
  const before = await drawerInput.inputValue();
  await drop(drawerInput, [{ name: 'brief.pdf', type: 'application/pdf', buffer: pdf }]);
  await drawer.getByText('儲存後附到任務', { exact: true }).waitFor(); assert.equal(await drawerInput.inputValue(), before);
  await drawer.getByRole('button', { name: '儲存修改', exact: true }).click(); await drawer.getByText('已附到任務', { exact: true }).waitFor();
  assert.deepEqual(await attachments(issueId), ['brief.pdf:application/pdf', 'screen.png:image/png', 'spec.zip:application/zip']);
  await page.keyboard.press('Escape'); await drawer.waitFor({ state: 'hidden' }).catch(() => {});

  // 3. Detail comment: a lone PDF can be sent; a stale-revision bind keeps the file for a retry.
  await page.goto(`${base}/app/#/projects/${project.id}`); await page.getByRole('button', { name: '拖放附件驗收', exact: true }).click();
  const form = page.locator('.comment-form'), comment = form.getByLabel('新增留言', { exact: true });
  await page.waitForFunction(() => document.querySelector('button[aria-label="選擇新增留言附件"]:not(:disabled)'));
  await drop(comment, [{ name: 'minutes.pdf', type: 'application/pdf', buffer: pdf }]);
  await form.getByText('送出後附到任務', { exact: true }).waitFor();
  let conflicted = false; const bindUrl = `**/api/v1/projects/${project.id}/attachments/*/bind`;
  await page.route(bindUrl, route => { if (conflicted) return route.continue(); conflicted = true; return route.fulfill({ status: 409, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { code: 'REVISION_CONFLICT', message: '任務已更新, 請重新載入' } }) }); });
  await form.getByRole('button', { name: '送出留言', exact: true }).click();
  await form.getByText(/尚未附到任務：/).waitFor(); await form.getByText('有 1 個檔案尚未附到任務, 可再按送出重試').waitFor();
  await page.unroute(bindUrl);
  await form.getByRole('button', { name: '送出留言', exact: true }).click(); await form.getByRole('list', { name: '附件上傳狀態' }).waitFor({ state: 'detached' });
  assert.deepEqual(await attachments(issueId), ['brief.pdf:application/pdf', 'minutes.pdf:application/pdf', 'screen.png:image/png', 'spec.zip:application/zip']);

  // 4. A file dropped beside the editor must not open in the tab and drop the draft.
  await comment.fill('保留中的留言');
  const guarded = await page.evaluate(() => { const data = new DataTransfer(); data.items.add(new File(['x'], 'stray.zip', { type: 'application/zip' })); const event = new DragEvent('drop', { dataTransfer: data, bubbles: true, cancelable: true }); document.body.dispatchEvent(event); return event.defaultPrevented; });
  assert.equal(guarded, true); assert.equal(await comment.inputValue(), '保留中的留言');
  assert.deepEqual(errors, []);
  writeFileSync(new URL('browser-result.json', artifacts), JSON.stringify({ passed: true, widths: [1440, 768, 390], checks: ['drop zone', 'mixed image/ZIP/unsupported drop', 'image only into text', 'disguised ZIP rejected by server', 'bind after create', 'edit attach without field change', 'comment file-only send', 'bind conflict retry', 'outside drop guarded'] }, null, 2));
  console.log('PASS: editor drop routes PNG to text and PDF/ZIP to issue attachments, create/edit/comment bind, rejection reasons, conflict retry, outside-drop guard and three widths');
} catch (error) { if (browser) { const p = browser.contexts()[0]?.pages()[0]; if (p) { await p.screenshot({ path: new URL('failure.png', artifacts).pathname }); writeFileSync(new URL('failure.txt', artifacts), await p.locator('body').innerText()); } } throw error; } finally { await browser?.close(); await server?.close(); }
