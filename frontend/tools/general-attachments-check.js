import { requireIsolatedApi } from './fixtures/isolated-api.js';
import { fixtureResponse } from './fixtures/verification.js';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
requireIsolatedApi({ requireIsolatedFlag: true }); // TT-336
const port = Number(process.env.E2E_FRONTEND_PORT || 5291), base = `http://127.0.0.1:${port}`, // TT-336：跟其他附件檢查一樣讀自己的前端埠
  artifacts = new URL('../../artifacts/tt295/', import.meta.url); mkdirSync(artifacts, { recursive: true });
let server, browser;
try {
  server = await createServer({ root: new URL('../', import.meta.url).pathname, server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1050 } }), page = await context.newPage(); page.setDefaultTimeout(15000);
  const errors = [], contentRequests = []; page.on('pageerror', e => errors.push(e.message)); page.on('request', r => { if (/attachments\/[^/]+\/content/.test(r.url())) contentRequests.push(r.url()); });
  async function call(operation, input) {
    const csrf = await (await context.request.get(`${base}/api/csrf-cookie`)).json();
    const response = await context.request.post(`${base}/api/${operation === 'register' ? 'auth' : 'rpc'}/${operation}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), 200, await response.text()); return (await fixtureResponse(context.request, response)).data;
  }
  await call('register', { name: '附件測試', email: `files-${crypto.randomUUID()}@example.test`, password: 'isolated-general-files', workspaceName: '一般附件' });
  const project = await call('createProject', { name: '一般附件', code: 'FILES' });
  const issue = await call('createIssue', { projectId: project.id, title: '附件驗收' });
  await page.goto(`${base}/app/#/projects/${project.id}`); await page.getByRole('button', { name: '附件驗收', exact: true }).click();
  assert.equal(await page.getByRole('region', { name: '任務附件', exact: true }).count(), 0, 'Attachment metadata remains lazy');
  await page.getByRole('tab', { name: '附件', exact: true }).click();
  const panel = page.getByRole('region', { name: '任務附件', exact: true }), file = panel.getByLabel('上傳任務附件', { exact: true });
  await panel.getByRole('button', { name: '加入檔案', exact: true }).waitFor(); await page.waitForFunction(() => [...document.querySelectorAll('button')].some(el => el.textContent === '加入檔案' && !el.disabled));
  const pdf = Buffer.from('%PDF-1.7\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n');
  let failed = false; const uploadIds = [], uploadUrl = `**/api/v1/projects/${project.id}/attachments`;
  await page.route(uploadUrl, route => {
    if (route.request().method() !== 'POST') return route.continue();
    uploadIds.push(route.request().postDataBuffer().toString().match(/name="requestId"\r\n\r\n([^\r]+)/)?.[1]);
    if (!failed) { failed = true; return route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { code: 'TEMPORARY', message: '測試失敗' } }) }); }
    return route.continue();
  });
  await file.setInputFiles({ name: 'notes.pdf', mimeType: 'application/pdf', buffer: pdf });
  await panel.getByRole('button', { name: '重試', exact: true }).waitFor(); await panel.getByRole('button', { name: '重試', exact: true }).click();
  await panel.getByRole('button', { name: '下載notes.pdf', exact: true }).waitFor(); assert.equal(uploadIds[0], uploadIds[1]);
  await page.unroute(uploadUrl);
  const zip = execFileSync('python3', ['-c', "import io,zipfile,sys\nb=io.BytesIO()\nwith zipfile.ZipFile(b,'w') as z:z.writestr('notes/readme.txt','synthetic data')\nsys.stdout.buffer.write(b.getvalue())"]);
  await page.waitForFunction(() => [...document.querySelectorAll('button')].some(el => el.textContent === '加入檔案' && !el.disabled));
  await file.setInputFiles({ name: 'notes.zip', mimeType: 'application/zip', buffer: zip }); await panel.getByRole('button', { name: '下載notes.zip', exact: true }).waitFor();
  assert.equal(contentRequests.length, 0, 'PDF/ZIP contents are never automatically previewed');
  const download = page.waitForEvent('download'); await panel.getByRole('button', { name: '下載notes.pdf', exact: true }).click(); assert.equal((await download).suggestedFilename(), 'notes.pdf');
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1050 }); await page.waitForFunction(() => innerWidth > 700 || document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    if (width <= 700) await page.getByRole('button', { name: '任務資訊', exact: true }).click();
    await panel.scrollIntoViewIfNeeded(); await page.screenshot({ path: new URL(`files-${width}.png`, artifacts).pathname });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  }
  await page.reload(); await page.getByRole('heading', { name: '附件驗收', exact: true }).waitFor(); if (await page.getByRole('button', { name: '任務資訊', exact: true }).isVisible()) await page.getByRole('button', { name: '任務資訊', exact: true }).click();
  await page.getByRole('tab', { name: '附件', exact: true }).click(); await panel.getByRole('button', { name: '下載notes.zip', exact: true }).waitFor();
  await panel.getByRole('button', { name: '下載notes.zip', exact: true }).focus(); const zipDownload = page.waitForEvent('download'); await page.keyboard.press('Enter'); assert.equal((await zipDownload).suggestedFilename(), 'notes.zip');
  assert.deepEqual(errors, []);
  writeFileSync(new URL('browser-result.json', artifacts), JSON.stringify({ passed: true, checks: ['lazy metadata', 'PDF/ZIP upload and bind', 'stable upload retry', 'no auto content preview', 'authorized download', 'reload', 'mobile expansion', 'keyboard download', 'three widths'] }, null, 2));
  console.log('PASS: general private files, retry, lazy loading, download-only PDF/ZIP, reload, keyboard and three widths');
} finally { await browser?.close(); await server?.close(); }
