import { requireIsolatedApi } from './fixtures/isolated-api.js';
import { fixtureResponse } from './fixtures/verification.js';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
requireIsolatedApi({ requireIsolatedFlag: true }); // TT-425：上傳浮窗彙總進度, 成功自動收起, 失敗留著可重試
const port = Number(process.env.E2E_FRONTEND_PORT || 5291), base = `http://127.0.0.1:${port}`,
  artifacts = new URL('../../artifacts/tt425/', import.meta.url); mkdirSync(artifacts, { recursive: true });
let server, browser;
try {
  server = await createServer({ root: new URL('../', import.meta.url).pathname, server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const context = await browser.newContext({ locale: 'zh-TW', viewport: { width: 1440, height: 1050 }, reducedMotion: 'reduce' }), page = await context.newPage(); page.setDefaultTimeout(15000);
  const errors = [], confirms = []; page.on('pageerror', e => errors.push(e.message)); page.on('dialog', d => { confirms.push(d.message()); d.accept(); });
  const binds = []; page.on('request', r => { if (r.method() === 'POST' && /\/attachments\/[^/]+\/bind$/.test(r.url())) binds.push(JSON.parse(r.postData())); });
  async function call(operation, input) {
    const csrf = await (await context.request.get(`${base}/api/csrf-cookie`)).json();
    const response = await context.request.post(`${base}/api/${operation === 'register' ? 'auth' : 'rpc'}/${operation}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), 200, await response.text()); return (await fixtureResponse(context.request, response)).data;
  }
  async function attachments(issueId) {
    const result = await (await context.request.get(`${base}/api/v1/projects/${project.id}/issues/${issueId}/attachments?page=1&perPage=20`)).json();
    return result.data.items.map(item => item.name).sort();
  }
  async function drop(target, files) {
    const transfer = await page.evaluateHandle(list => { const data = new DataTransfer(); for (const f of list) data.items.add(new File([Uint8Array.from(atob(f.b64), c => c.charCodeAt(0))], f.name, { type: f.type })); return data; }, files.map(f => ({ ...f, b64: f.buffer.toString('base64') })));
    await target.dispatchEvent('dragenter', { dataTransfer: transfer }); await target.dispatchEvent('drop', { dataTransfer: transfer });
  }
  // Hold uploads until released, so the in-flight state can be observed and edited around.
  let held = [], failNext = 0;
  const uploadUrl = '**/api/v1/projects/*/attachments';
  async function holdUploads() {
    await page.route(uploadUrl, route => {
      if (route.request().method() !== 'POST') return route.continue();
      if (failNext > 0) { failNext--; return route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { code: 'UNAVAILABLE', message: '服務暫時無法使用' } }) }); }
      return new Promise(resolve => held.push(() => resolve(route.continue())));
    });
  }
  async function release() { while (!held.length) await page.waitForTimeout(50); const list = held; held = []; for (const go of list) go(); }
  await call('register', { name: '上傳浮窗', email: `tray-${crypto.randomUUID()}@example.test`, password: 'isolated-upload-tray', workspaceName: '上傳浮窗' });
  const project = await call('createProject', { name: '上傳浮窗', code: 'TRAY' });
  const zip = execFileSync('python3', ['-c', "import io,zipfile,sys\nb=io.BytesIO()\nwith zipfile.ZipFile(b,'w') as z:z.writestr('a.txt','synthetic')\nsys.stdout.buffer.write(b.getvalue())"]);
  const pdf = Buffer.from('%PDF-1.7\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n');
  await page.setContent('<body style="margin:0;padding:24px;font:20px system-ui">tray</body>'); await page.setViewportSize({ width: 320, height: 160 });
  const png = await page.screenshot(); await page.setViewportSize({ width: 1440, height: 1050 });
  await holdUploads();

  // 1. New issue: three files at once; the tray sits inside the modal, typing continues while uploads wait.
  await page.goto(`${base}/app/#/projects/${project.id}`);
  await page.getByRole('button', { name: '新增任務', exact: false }).first().click();
  const editor = page.getByRole('dialog').first(); await editor.getByLabel(/任務標題/).fill('浮窗驗收');
  const input = editor.getByLabel('描述', { exact: true });
  await page.waitForFunction(() => document.querySelector('button[aria-label="選擇描述附件"]:not(:disabled)'));
  await drop(input, [{ name: 'shot.png', type: 'image/png', buffer: png }, { name: 'bundle.zip', type: 'application/zip', buffer: zip }, { name: 'brief.pdf', type: 'application/pdf', buffer: pdf }]);
  const tray = editor.getByRole('complementary', { name: '上傳進度', exact: true });
  await tray.getByText('已加入 3 個檔案', { exact: true }).waitFor();
  assert.equal(await page.getByRole('complementary', { name: '上傳進度' }).count(), 1, 'Only the topmost host renders the tray');
  await input.click(); await input.press('End'); await page.keyboard.type('上傳時繼續打字');
  assert.match(await input.inputValue(), /上傳時繼續打字/, 'Tray does not block editing');
  await tray.getByRole('button', { name: '收合上傳進度', exact: true }).click();
  assert.equal(await tray.getByRole('list').isVisible(), false); await release(); await release();
  await tray.getByText('2 個檔案待附加', { exact: true }).waitFor();
  await tray.getByRole('button', { name: '展開上傳進度', exact: true }).click();
  assert.equal(await tray.getByText('儲存後附到任務', { exact: true }).count(), 2, 'Uploaded files are not shown as attached yet');
  await tray.getByText('已加入內容', { exact: true }).waitFor();
  for (const width of [1440, 390]) {
    await page.setViewportSize({ width, height: width === 390 ? 844 : 1050 }); await page.waitForFunction(() => document.documentElement.scrollWidth <= innerWidth, null, { timeout: 3000 }).catch(() => {});
    const box = await tray.boundingBox(), save = await editor.getByRole('button', { name: '建立任務', exact: true }).boundingBox();
    assert.ok(box.y + box.height <= save.y || box.x >= save.x + save.width || box.x + box.width <= save.x, `Tray leaves the save button reachable at ${width}`);
    const wide = await page.evaluate(() => [document.documentElement.scrollWidth, innerWidth, [...document.querySelectorAll('body *')].filter(e => e.getBoundingClientRect().right > innerWidth + 1).slice(0, 5).map(e => `${e.tagName}.${e.className}:${Math.round(e.getBoundingClientRect().right)}`)]);
    assert.ok(wide[0] <= wide[1], `No horizontal overflow at ${width}: ${JSON.stringify(wide)}`);
    await page.screenshot({ path: new URL(`waiting-${width}.png`, artifacts).pathname });
  }
  await page.setViewportSize({ width: 1440, height: 1050 });
  // Waiting files fold the tray away by themselves, without stopping anything.
  await tray.getByRole('button', { name: '展開上傳進度', exact: true }).waitFor({ timeout: 6000 });
  await editor.getByRole('button', { name: '建立任務', exact: true }).click(); await editor.waitFor({ state: 'hidden' });
  const appTray = page.getByRole('complementary', { name: '上傳進度', exact: true });
  await appTray.getByText('3 個檔案已完成', { exact: true }).waitFor();
  await appTray.waitFor({ state: 'detached', timeout: 7000 });
  const created = binds[0]?.issueId; assert.ok(created, 'Files bound to the created issue');
  assert.deepEqual(await attachments(created), ['brief.pdf', 'bundle.zip', 'shot.png']);

  // 2. Detail comment: one of two uploads fails; the failure stays put until retried, then success folds away.
  await page.getByRole('button', { name: '浮窗驗收', exact: true }).click();
  const form = page.locator('.comment-form'), comment = form.getByLabel('新增留言', { exact: true });
  await page.waitForFunction(() => document.querySelector('button[aria-label="選擇新增留言附件"]:not(:disabled)'));
  failNext = 1;
  await drop(comment, [{ name: 'broken.pdf', type: 'application/pdf', buffer: pdf }, { name: 'later.zip', type: 'application/zip', buffer: zip }]);
  await release();
  await appTray.getByText('1 個成功、1 個失敗', { exact: true }).waitFor();
  await page.waitForTimeout(5000);
  assert.ok(await appTray.getByText('1 個成功、1 個失敗', { exact: true }).isVisible(), 'Failures never vanish on their own');
  assert.equal(await appTray.getByRole('button', { name: '關閉上傳進度' }).count(), 1);
  await page.screenshot({ path: new URL('partial-failure-1440.png', artifacts).pathname });
  await appTray.getByRole('button', { name: '重試', exact: true }).click(); await release();
  await appTray.getByText('2 個檔案待附加', { exact: true }).waitFor();
  await form.getByRole('button', { name: '送出留言', exact: true }).click();
  await appTray.getByText('2 個檔案已完成', { exact: true }).waitFor();
  assert.deepEqual(await attachments(created), ['brief.pdf', 'broken.pdf', 'bundle.zip', 'later.zip', 'shot.png']);
  await appTray.waitFor({ state: 'detached', timeout: 7000 });

  // 3. Cancel from the tray stops the upload and reports it as canceled, not done.
  await drop(comment, [{ name: 'drop-me.zip', type: 'application/zip', buffer: zip }]);
  await appTray.getByRole('button', { name: '取消', exact: true }).click();
  await appTray.getByText('已取消上傳', { exact: true }).waitFor(); held = [];
  await appTray.waitFor({ state: 'detached', timeout: 7000 });

  // 4. Attachments tab uses the same tray.
  await page.unroute(uploadUrl); await holdUploads();
  await page.getByRole('tab', { name: /附件/ }).first().click();
  await page.waitForFunction(() => [...document.querySelectorAll('.attachment-files button')].some(b => b.textContent.trim() === '加入檔案' && !b.disabled));
  await page.getByLabel('上傳任務附件', { exact: true }).setInputFiles({ name: 'tab.pdf', mimeType: 'application/pdf', buffer: pdf });
  await appTray.getByText('已加入 1 個檔案', { exact: true }).waitFor(); await release();
  await appTray.getByText('1 個檔案已完成', { exact: true }).waitFor();
  assert.ok((await attachments(created)).includes('tab.pdf'));

  // 5. Leaving with an upload in flight reports it as canceled instead of finishing silently.
  await page.getByRole('button', { name: '快速編輯 TRAY-1', exact: true }).click().catch(async () => { await page.goto(`${base}/app/#/projects/${project.id}`); await page.getByRole('button', { name: '快速編輯 TRAY-1', exact: true }).click(); });
  const drawer = page.getByRole('dialog').first();
  await page.waitForFunction(() => document.querySelector('button[aria-label="選擇描述附件"]:not(:disabled)'));
  await drop(drawer.getByLabel('描述', { exact: true }), [{ name: 'abandoned.zip', type: 'application/zip', buffer: zip }]);
  await drawer.getByRole('complementary', { name: '上傳進度' }).getByText('已加入 1 個檔案', { exact: true }).waitFor();
  await drawer.getByRole('button', { name: '關閉任務', exact: true }).click(); await drawer.waitFor({ state: 'hidden' });
  await appTray.getByText('已取消上傳', { exact: true }).waitFor(); held = [];
  assert.ok(!(await attachments(created)).includes('abandoned.zip'));
  assert.ok(confirms.every(message => /未儲存/.test(message)), 'No extra confirmation dialogs');
  assert.deepEqual(errors, []);
  writeFileSync(new URL('browser-result.json', artifacts), JSON.stringify({ passed: true, widths: [1440, 390], checks: ['tray inside modal', 'single host', 'editing while uploading', 'collapse keeps uploading', 'uploaded vs attached', 'auto collapse waiting', 'auto dismiss success', 'save button reachable', 'partial failure persists', 'tray retry', 'tray cancel', 'attachments tab', 'leaving cancels honestly', 'bind readback'] }, null, 2));
  console.log('PASS: upload tray aggregates editor/tab uploads, stays non-modal, folds success away, keeps failures with retry/cancel, reports abandoned uploads, two widths');
} catch (error) { if (browser) { const p = browser.contexts()[0]?.pages()[0]; if (p) { await p.screenshot({ path: new URL('failure.png', artifacts).pathname }); writeFileSync(new URL('failure.txt', artifacts), await p.locator('body').innerText()); } } throw error; } finally { await browser?.close(); await server?.close(); }
