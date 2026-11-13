import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
if (process.env.E2E_ISOLATED !== '1' || !process.env.E2E_API_URL) throw Error('Explicit isolated backend required');
const port = Number(process.env.E2E_FRONTEND_PORT || 5291), base = `http://127.0.0.1:${port}`, phase = process.env.E2E_CAPTURE_PHASE || 'after', folder = new URL(`../../artifacts/tt289/${phase}/`, import.meta.url);
mkdirSync(folder, { recursive: true });
let vite, browser;
try {
  vite = await createServer({ root: new URL('../', import.meta.url).pathname, plugins: [{ name: 'tt289-before-source', load(id) { if (phase === 'before' && id.endsWith('/src/components/IssueDetail.vue')) return execFileSync('git', ['show', '038f9e4:frontend/src/components/IssueDetail.vue'], { encoding: 'utf8', cwd: new URL('../../', import.meta.url).pathname }); } }], server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await vite.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } }), page = await context.newPage(), errors = [], requests = [], external = []; page.setDefaultTimeout(15000);
  page.on('pageerror', e => errors.push(e.message)); page.on('request', r => { const url = r.url(); requests.push(url); if (url.includes('reading-test.invalid')) external.push(url); });
  async function call(operation, input) {
    const csrf = await (await context.request.get(`${base}/api/csrf-cookie`)).json();
    const res = await context.request.post(`${base}/api/${operation === 'register' ? 'auth' : 'rpc'}/${operation}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } }); assert.equal(res.status(), 200, await res.text()); return (await res.json()).data;
  }
  await call('register', { name: '閱讀測試', email: `reading-${crypto.randomUUID()}@example.test`, password: 'isolated-reading-password', workspaceName: '閱讀驗證' });
  const project = await call('createProject', { name: '會員系統', code: 'READ' });
  await page.setViewportSize({ width: 640, height: 280 }); await page.setContent('<body style="margin:0;padding:28px;font:18px system-ui;color:#242936;background:#f4f5f8"><h1 style="font-size:24px">登入畫面</h1><p>電子郵件與密碼欄位</p><p style="padding:12px;background:#6653bd;color:white">登入</p></body>'); const png = await page.screenshot();
  const csrf = await (await context.request.get(`${base}/api/csrf-cookie`)).json();
  const upload = await context.request.post(`${base}/api/v1/projects/${project.id}/attachments`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, multipart: { requestId: crypto.randomUUID(), file: { name: 'login.png', mimeType: 'image/png', buffer: png } } }); assert.equal(upload.status(), 200, await upload.text()); const attachment = (await upload.json()).data;
  const source = `調整會員登入流程, 保留電子郵件與密碼欄位\n\n![登入畫面](attachment:${attachment.id})\n\n- [x] 保留原本登入方式\n- [ ] 確認錯誤提示\n\n![外部圖片](https://reading-test.invalid/pixel.png)\n<script>window.unsafeReading=true</script>`;
  const issue = await call('createIssue', { projectId: project.id, title: '調整會員登入流程與錯誤提示', description: source, type: 'task', priority: 'high', estimateMinutes: 90 });
  const url = `${base}/app/#/projects/${project.id}/issues/${issue.id}?detail=1`;
  await page.setViewportSize({ width: 1440, height: 1000 }); await page.goto(url); const detail = page.getByRole('region', { name: '任務完整頁', exact: true });
  await detail.getByRole('heading', { name: '調整會員登入流程與錯誤提示', exact: true }).waitFor(); const preview = detail.getByRole('button', { name: '放大圖片：登入畫面', exact: true }); await preview.waitFor();
  await page.waitForFunction(() => [...document.querySelectorAll('.image-preview img')].every(el => el.complete && el.naturalWidth));
  assert.equal(requests.some(u => /\/issues\/[^/]+\/(history|time|children)\?/.test(u)), false, 'Only the active comments page is loaded on entry');
  assert.equal(await page.evaluate(() => window.unsafeReading === true), false); assert.deepEqual(external, []);
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1000 }); if (width <= 700) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1); await page.mouse.move(1,1); await page.evaluate(() => document.activeElement?.blur()); await page.waitForLoadState('networkidle');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    await detail.screenshot({ path: new URL(`reading-${width}.png`, folder).pathname });
    if (width === 1440) writeFileSync(new URL('inspect.html', folder), await detail.evaluate((el, data) => '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' + [...document.querySelectorAll('style')].map(s => s.outerHTML).join('') + '<div class="v-application v-theme--light">' + el.outerHTML.replace(/blob:http[^"]+/g, data) + '</div>', `data:image/png;base64,${png.toString('base64')}`));
    await preview.click(); const viewer = page.getByRole('dialog', { name: '圖片：登入畫面', exact: true }); await viewer.waitFor(); await page.keyboard.press('Tab'); assert.equal(await page.evaluate(() => !!document.activeElement?.closest('dialog')), true);
    await viewer.screenshot({ path: new URL(`image-${width}.png`, folder).pathname }); await page.keyboard.press('Escape'); assert.equal(await preview.evaluate(el => el === document.activeElement), true);
  }
  if (phase === 'before') { console.log('PASS: before capture, real private images and three widths'); }
  else {
    await page.setViewportSize({ width:1440,height:1000 });
    const propertiesTab = detail.getByRole('tab', { name:'屬性', exact:true }); await propertiesTab.focus(); await page.keyboard.press('ArrowRight'); assert.equal(await detail.getByRole('tab', { name:'關聯', exact:true }).getAttribute('aria-selected'), 'true'); await page.keyboard.press('ArrowLeft'); assert.equal(await propertiesTab.getAttribute('aria-selected'), 'true');
    await detail.getByLabel('新增留言', { exact:true }).fill('切換頁籤時保留的留言');
    await detail.getByRole('tab', { name:/^異動紀錄/ }).click(); await detail.locator('.history-entry').first().waitFor();
    await detail.getByRole('tab', { name:/^留言/ }).click(); assert.equal(await detail.getByLabel('新增留言', { exact:true }).inputValue(), '切換頁籤時保留的留言');
    page.once('dialog', d=>d.dismiss()); await detail.getByRole('button', { name:'返回任務列表', exact:true }).click(); assert.ok(page.url().includes(issue.id));
    await detail.getByLabel('新增留言', { exact:true }).fill('');
    const timeUrl = `**/api/v1/projects/${project.id}/issues/${issue.id}/time?*`; let fail = true;
    await page.route(timeUrl, route => fail ? route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({ok:false,error:{code:'SERVICE_UNAVAILABLE',message:'工時暫時無法載入'}})}) : route.continue());
    await detail.getByRole('tab', { name:/^工時/ }).click();
    await page.getByText('工時暫時無法載入', { exact:false }).first().waitFor();
    await detail.getByRole('tab', { name:/^留言/ }).click(); assert.equal(await detail.getByRole('alert').count(), 0, 'Time errors must not leak into the cached comments tab');
    await detail.getByRole('tab', { name:/^工時/ }).click(); await detail.getByText('工時暫時無法載入', { exact:false }).waitFor();
    const retry = detail.getByRole('button', { name:'重試載入紀錄', exact:true }); fail=false; await retry.click(); await detail.getByText('尚無工時紀錄', { exact:true }).waitFor(); await page.unroute(timeUrl);
    await detail.getByRole('tab', { name:/^留言/ }).click();
    // Reauthorization on each image opening, including a revoked/deleted image.
    const bound = (await (await context.request.get(`${base}/api/v1/projects/${project.id}/issues/${issue.id}/attachments`)).json()).data.items[0];
    const latest = (await (await context.request.get(`${base}/api/v1/projects/${project.id}/issues/${issue.id}`)).json()).data;
    const csrf2 = await (await context.request.get(`${base}/api/csrf-cookie`)).json();
    const deleted = await context.request.delete(`${base}/api/v1/projects/${project.id}/attachments/${attachment.id}`, { headers:{'X-CSRF-TOKEN':csrf2.csrfToken},data:{expectedRevision:bound.revision,issueId:issue.id,issueRevision:latest.revision,requestId:crypto.randomUUID()} }); assert.equal(deleted.status(),200,await deleted.text());
    await preview.click(); await detail.getByText('圖片無法載入或已無存取權限',{exact:true}).waitFor();
    assert.equal(await detail.locator('.image-preview img').count(),0);
    assert.deepEqual(errors,[]);assert.deepEqual(external,[]);
    writeFileSync(new URL('result.json',folder),JSON.stringify({passed:true,widths:[1440,768,390],screenshots:6,checks:['real deep link/private image','active activity only','keyboard viewer/focus','comment draft across tabs and back cancellation','failed time read/retry','deleted image rejects fresh action','XSS and external image refusal']},null,2));
    console.log('PASS: detail reading, real images, three widths, activity scope/retry, draft protection and image reauthorization');
  }
} finally { await browser?.close(); await vite?.close(); }
