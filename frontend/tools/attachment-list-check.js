import { requireIsolatedApi } from './fixtures/isolated-api.js';
import { fixtureResponse } from './fixtures/verification.js';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
requireIsolatedApi({ requireIsolatedFlag: true }); // TT-426：附件數量、配額、下載回饋與刪除
const port = Number(process.env.E2E_FRONTEND_PORT || 5291), base = `http://127.0.0.1:${port}`,
  artifacts = new URL('../../artifacts/tt426/', import.meta.url); mkdirSync(artifacts, { recursive: true });
let server, browser;
try {
  server = await createServer({ root: new URL('../', import.meta.url).pathname, server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const context = await browser.newContext({ locale: 'zh-TW', viewport: { width: 1440, height: 1050 }, acceptDownloads: true }), page = await context.newPage(); page.setDefaultTimeout(15000);
  const errors = []; page.on('pageerror', e => errors.push(e.message)); page.on('dialog', d => d.accept());
  async function csrf() { return (await (await context.request.get(`${base}/api/csrf-cookie`)).json()).csrfToken; }
  async function call(operation, input) {
    const response = await context.request.post(`${base}/api/${operation === 'register' ? 'auth' : 'rpc'}/${operation}`, { headers: { 'X-CSRF-TOKEN': await csrf() }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), 200, await response.text()); return (await fixtureResponse(context.request, response)).data;
  }
  async function issueRevision(id) { return (await (await context.request.get(`${base}/api/v1/projects/${project.id}/issues/${id}`)).json()).data.revision; }
  // Seed through the real upload + bind API, one file at a time like the editor does.
  async function attach(issueId, name, mimeType, buffer) {
    const upload = await context.request.post(`${base}/api/v1/projects/${project.id}/attachments`, { headers: { 'X-CSRF-TOKEN': await csrf() }, multipart: { requestId: crypto.randomUUID(), file: { name, mimeType, buffer } } });
    assert.equal(upload.status(), 200, await upload.text()); const file = (await upload.json()).data;
    const bind = await context.request.post(`${base}/api/v1/projects/${project.id}/attachments/${file.id}/bind`, { headers: { 'X-CSRF-TOKEN': await csrf() }, data: { requestId: crypto.randomUUID(), expectedRevision: file.revision, issueId, issueRevision: await issueRevision(issueId) } });
    assert.equal(bind.status(), 200, await bind.text());
  }
  async function drop(target, files) {
    const transfer = await page.evaluateHandle(list => { const data = new DataTransfer(); for (const f of list) data.items.add(new File([Uint8Array.from(atob(f.b64), c => c.charCodeAt(0))], f.name, { type: f.type })); return data; }, files.map(f => ({ ...f, b64: f.buffer.toString('base64') })));
    await target.dispatchEvent('dragenter', { dataTransfer: transfer }); await target.dispatchEvent('drop', { dataTransfer: transfer });
  }
  await call('register', { name: '附件清單', email: `files-${crypto.randomUUID()}@example.test`, password: 'isolated-attachment-list', workspaceName: '附件清單' });
  const project = await call('createProject', { name: '附件清單', code: 'FILE' });
  const issue = await call('createIssue', { projectId: project.id, title: '附件配額' });
  const zip = execFileSync('python3', ['-c', "import io,zipfile,sys\nb=io.BytesIO()\nwith zipfile.ZipFile(b,'w') as z:z.writestr('a.txt','synthetic')\nsys.stdout.buffer.write(b.getvalue())"]);
  const pdf = Buffer.from('%PDF-1.7\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n');
  await page.setContent('<body style="margin:0;padding:24px;font:20px system-ui">files</body>'); await page.setViewportSize({ width: 320, height: 160 });
  const png = await page.screenshot(); await page.setViewportSize({ width: 1440, height: 1050 });

  // 1. The tab shows its count before it is opened; the list stays lazy until then.
  await attach(issue.id, 'spec.pdf', 'application/pdf', pdf);
  const listRequests = []; page.on('request', r => { if (/\/issues\/[^/]+\/attachments\?/.test(r.url())) listRequests.push(r.url()); });
  await page.goto(`${base}/app/#/projects/${project.id}/issues/${issue.id}?detail=1`);
  const tab = page.getByRole('tab', { name: /^附件/ });
  await page.waitForFunction(() => /附件\s*1$/.test(document.querySelector('#detail-attachments-tab')?.textContent.trim() || ''));
  assert.equal(listRequests.length, 0, 'Attachment metadata remains lazy');
  await tab.click();
  const panel = page.getByRole('region', { name: '任務附件', exact: true });
  await panel.getByText('1/20 個附件, 其中 PDF/ZIP 1/10', { exact: true }).waitFor();
  await panel.getByText('PDF · 0.1 KB', { exact: true }).waitFor();

  // 2. Download feedback stays on the row; a failure keeps a retry until it works.
  const contentUrl = `**/api/v1/projects/${project.id}/attachments/*/content*`;
  await page.route(contentUrl, route => route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { code: 'UNAVAILABLE', message: '服務暫時無法使用' } }) }));
  await panel.getByRole('button', { name: '下載spec.pdf', exact: true }).click();
  await panel.getByRole('alert').getByText(/^下載失敗：/).waitFor();
  await page.unroute(contentUrl);
  const downloaded = page.waitForEvent('download');
  await panel.getByRole('button', { name: '重試下載spec.pdf', exact: true }).click();
  assert.equal((await downloaded).suggestedFilename(), 'spec.pdf');
  await panel.getByText('已開始下載', { exact: true }).waitFor();

  // 3. Near the limit: 19 attachments, the editor takes one more and refuses the next with a reason.
  for (let n = 1; n <= 18; n++) await attach(issue.id, `shot-${n}.png`, 'image/png', png);
  await page.reload(); await page.waitForFunction(() => /附件\s*19$/.test(document.querySelector('#detail-attachments-tab')?.textContent.trim() || ''));
  await tab.click(); await panel.getByText('還能再加 1 個', { exact: true }).waitFor();
  assert.equal(await panel.getByRole('button', { name: /^刪除shot-/ }).count(), 0, 'Images may be referenced in text, so they are not deletable here');
  const form = page.locator('.comment-form'), comment = form.getByLabel('新增留言', { exact: true });
  await page.waitForFunction(() => document.querySelector('button[aria-label="選擇新增留言附件"]:not(:disabled)'));
  await comment.fill('保留這段留言');
  await drop(comment, [{ name: 'last.zip', type: 'application/zip', buffer: zip }, { name: 'extra.zip', type: 'application/zip', buffer: zip }]);
  const rows = form.getByRole('list', { name: '附件上傳狀態' });
  await rows.getByText('送出後附到任務', { exact: true }).waitFor();
  await rows.getByText('任務附件數量已達上限', { exact: true }).waitFor();
  assert.equal(await comment.inputValue(), '保留這段留言', 'Refusal keeps the draft');
  await rows.getByRole('button', { name: '移除', exact: true }).click();
  await form.getByRole('button', { name: '送出留言', exact: true }).click();
  await page.waitForFunction(() => /附件\s*20$/.test(document.querySelector('#detail-attachments-tab')?.textContent.trim() || ''));

  // 4. Full: adding is disabled with the reason; deleting a PDF frees a slot again.
  await panel.getByText('任務附件已達 20 個上限', { exact: true }).waitFor();
  assert.ok(await panel.getByRole('button', { name: '加入檔案', exact: true }).isDisabled());
  for (const width of [1440, 390]) {
    await page.setViewportSize({ width, height: width === 390 ? 844 : 1050 });
    await page.waitForFunction(() => document.documentElement.scrollWidth <= innerWidth, null, { timeout: 3000 }).catch(() => {});
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `No horizontal overflow at ${width}`);
    const toggle = page.getByRole('button', { name: '任務資訊', exact: true });
    if (await toggle.isVisible() && await toggle.getAttribute('aria-expanded') !== 'true') await toggle.click();
    if (!(await panel.isVisible())) await tab.click();
    await panel.getByRole('button', { name: '下載last.zip', exact: true }).scrollIntoViewIfNeeded();
    assert.ok(await panel.getByRole('button', { name: '下載last.zip', exact: true }).isVisible(), `Download reachable at ${width}`);
    await page.waitForTimeout(300); // let the sidebar's 0.2s collapse finish before capturing
    await page.screenshot({ path: new URL(`full-${width}.png`, artifacts).pathname });
  }
  await page.setViewportSize({ width: 1440, height: 1050 });
  await panel.getByRole('button', { name: '刪除spec.pdf', exact: true }).click();
  await page.waitForFunction(() => /附件\s*19$/.test(document.querySelector('#detail-attachments-tab')?.textContent.trim() || ''));
  await panel.getByText('還能再加 1 個', { exact: true }).waitFor();
  assert.equal(await panel.getByText('spec.pdf', { exact: true }).count(), 0);
  assert.ok(!(await panel.getByRole('button', { name: '加入檔案', exact: true }).isDisabled()));
  assert.deepEqual(errors, []);
  writeFileSync(new URL('browser-result.json', artifacts), JSON.stringify({ passed: true, widths: [1440, 390], checks: ['count before opening', 'lazy list', 'quota line', 'download failure retry', 'download success', 'near-limit warning', 'editor refuses over limit and keeps draft', 'full disables add', 'images not deletable', 'PDF delete frees slot', 'no overflow'] }, null, 2));
  console.log('PASS: attachment tab count, lazy list, quota/near-limit/full reasons, row download failure/retry/success, editor limit with existing files, PDF delete and two widths');
} catch (error) { if (browser) { const p = browser.contexts()[0]?.pages()[0]; if (p) { await p.screenshot({ path: new URL('failure.png', artifacts).pathname }); writeFileSync(new URL('failure.txt', artifacts), await p.locator('body').innerText()); } } throw error; } finally { await browser?.close(); await server?.close(); }
