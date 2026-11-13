import { fixtureResponse } from './fixtures/verification.js';
import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
if (process.env.E2E_ISOLATED !== '1' || !process.env.E2E_API_URL) throw Error('Explicit isolated backend required');
const port = Number(process.env.E2E_FRONTEND_PORT || 5291), base = `http://127.0.0.1:${port}`, folder = new URL('../../artifacts/tt288/', import.meta.url); mkdirSync(folder, { recursive: true });
let vite, browser;
try {
  vite = await createServer({ root: new URL('../', import.meta.url).pathname, server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await vite.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } }), page = await context.newPage(), errors = []; page.setDefaultTimeout(15000); page.on('pageerror', e => errors.push(e.message));
  async function call(operation, input, group = 'rpc', client = context) {
    const csrf = await (await client.request.get(`${base}/api/csrf-cookie`)).json(); const response = await client.request.post(`${base}/api/${group}/${operation}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } }); assert.equal(response.status(), 200, await response.text()); return (await fixtureResponse(client.request, response)).data;
  }
  const identity = await call('register', { name: '表單測試', email: `form-${crypto.randomUUID()}@example.test`, password: 'isolated-form-password', workspaceName: '表單驗證' }, 'auth');
  const project = await call('createProject', { name: '會員介面', code: 'FORM' }); const root = `${base}/app/#/projects/${project.id}`; await page.goto(root);
  await page.getByRole('button', { name: '新增任務', exact: false }).first().click();
  let editor = page.getByRole('dialog', { name: '新增一個任務', exact: true }); const title = () => editor.getByLabel(/任務標題/), description = () => editor.getByLabel('描述', { exact: true });
  await editor.waitFor(); assert.equal(await editor.locator('.issue-properties').getAttribute('open'), null); assert.equal(await editor.getByRole('heading', { level: 2 }).count(), 1);
  await editor.getByRole('button', { name: '建立任務', exact: true }).click(); await editor.getByText('請輸入任務標題', { exact: true }).waitFor(); assert.equal(await title().evaluate(el => el === document.activeElement), true);
  await title().fill('   '); await editor.getByRole('button', { name: '建立任務', exact: true }).click(); await editor.getByText('請輸入任務標題', { exact: true }).waitFor();
  await title().fill('表單體驗驗證'); await description().fill('保留的草稿內容');
  async function capture(name) {
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 1000 }); await editor.locator('.panel-content').evaluate(el => el.scrollTop = 0); await page.mouse.move(1, 1); await page.evaluate(() => document.activeElement?.blur()); await page.waitForLoadState('networkidle');
      assert.equal(await editor.evaluate(el => el.scrollWidth <= el.clientWidth), true);
      const save = editor.getByRole('button', { name: /^(建立任務|儲存修改)$/ });
      assert.equal(await save.evaluate(el => { const r = el.getBoundingClientRect(); return document.elementFromPoint(r.x+r.width/2,r.y+r.height/2) === el || el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2)); }), true);
      await editor.screenshot({ path: new URL(`${name}-${width}.png`, folder).pathname });
      if (name === 'properties' && width === 390) writeFileSync(new URL('inspect.html', folder), await editor.evaluate(el => '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' + [...document.querySelectorAll('style')].map(style => style.outerHTML).join('') + '<div class="v-application v-theme--light">' + el.outerHTML + '</div>'));
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
  }
  await capture('primary');
  await editor.locator('.issue-properties > summary').focus(); await page.keyboard.press('Space'); assert.equal(await editor.locator('.issue-properties').evaluate(el => el.open), true); await editor.getByLabel('類型', { exact: true }).selectOption('bug'); await editor.getByLabel('優先級', { exact: true }).selectOption('high'); await editor.getByLabel('到期日', { exact: true }).fill('2026-11-20');
  await capture('properties');
  const requests = []; let fail = true;
  await page.route('**/api/rpc/createIssue', async route => {
    requests.push(route.request().postDataJSON());
    if (fail) { fail = false; return route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { code: 'NETWORK_ERROR', message: '暫時無法儲存, 請重試' } }) }); }
    await new Promise(resolve => setTimeout(resolve, 300)); await route.continue();
  });
  await editor.getByRole('button', { name: '建立任務', exact: true }).click(); await editor.getByRole('alert').getByText('暫時無法儲存, 請重試', { exact: false }).waitFor();
  assert.equal(await editor.locator('.error-box').evaluate(el => el === document.activeElement), true); assert.equal(await description().inputValue(), '保留的草稿內容'); assert.equal(await title().inputValue(), '表單體驗驗證');
  await editor.getByRole('button', { name: '建立任務', exact: true }).evaluate(el => { el.click(); el.click(); }); await editor.waitFor({ state: 'hidden' });
  assert.equal(requests.length, 2); assert.equal(requests[0].requestId, requests[1].requestId);
  await page.unroute('**/api/rpc/createIssue');
  const list = await (await context.request.get(`${base}/api/v1/projects/${project.id}/issues?perPage=20&page=1`)).json(); const issue = list.data.items.find(row => row.title === '表單體驗驗證'); assert.ok(issue);
  // Two actors editing the same version: the browser's unsaved source must survive the 409.
  await page.getByRole('button', { name: `快速編輯 ${issue.identifier}`, exact: true }).click();
  editor = page.getByRole('dialog', { name: '任務詳細內容', exact: true }); await editor.waitFor(); await description().fill('衝突後仍保留的內容');
  await call('updateIssue', { id: issue.id, expectedRevision: issue.revision, title: '另一個人的更新' });
  await editor.getByRole('button', { name: '儲存修改', exact: true }).click(); await editor.getByText('目前修改仍保留, 重新載入會捨棄這份修改').waitFor(); assert.equal(await description().inputValue(), '衝突後仍保留的內容');
  await capture('conflict');
  page.once('dialog', dialog => dialog.dismiss()); await editor.getByRole('button', { name: '重新載入最新版本' }).click(); assert.equal(await description().inputValue(), '衝突後仍保留的內容');
  page.once('dialog', dialog => dialog.dismiss()); await editor.getByRole('button', { name: '關閉任務', exact: true }).click(); await editor.waitFor(); assert.equal(await description().inputValue(), '衝突後仍保留的內容');
  page.once('dialog', dialog => dialog.accept()); await editor.getByRole('button', { name: '關閉任務', exact: true }).click();
  const other = await call('createProject', { name: '其他專案', code: 'OTHER' });
  await call('createTodo', { content: '專案切換草稿', assigneeId: identity.user.id }); await page.goto(`${base}/app/`);
  const row = page.locator('.todo-row').filter({ hasText: '專案切換草稿' }); await row.getByRole('button', { name: '轉為卡片', exact: true }).click();
  const conversion = page.getByRole('dialog', { name: '轉成正式卡片', exact: true }); const projectSelect = conversion.getByLabel('專案', { exact: true }); await projectSelect.selectOption(project.id);
  await page.waitForFunction(() => document.querySelector('button[aria-label="選擇描述圖片"]:not(:disabled)'));
  const scratch = await context.newPage(); await scratch.setViewportSize({ width: 320, height: 180 }); await scratch.setContent('<p>隔離圖片草稿</p>'); const png = await scratch.screenshot(); await scratch.close();
  await conversion.getByLabel('上傳描述圖片').setInputFiles({ name: 'scope.png', mimeType: 'image/png', buffer: png }); await conversion.getByText('已加入描述', { exact: true }).waitFor();
  const draft = await conversion.getByLabel('描述', { exact: true }).inputValue();
  await projectSelect.selectOption(other.id); await conversion.getByText('描述仍有圖片或未完成上傳, 請先處理圖片再切換專案').waitFor();
  assert.equal(await projectSelect.inputValue(), project.id); assert.equal(await conversion.getByLabel('描述', { exact: true }).inputValue(), draft);
  page.once('dialog', dialog => dialog.accept()); await conversion.getByRole('button', { name: '關閉任務', exact: true }).click();
  const email = `reader-${crypto.randomUUID()}@example.test`, invite = await call('inviteMember', { email });
  const reader = await browser.newContext(); const person = await call('register', { name: 'Reader', email, password: 'isolated-form-password', inviteToken: invite.token }, 'auth', reader);
  const acl = await call('getProjectMembers', { projectId: project.id }); await call('setProjectMember', { projectId: project.id, userId: person.user.id, roleId: 'reader', expectedRevision: acl.revision });
  const readPage = await reader.newPage(); await readPage.goto(root); await readPage.getByRole('button', { name: '另一個人的更新', exact: true }).waitFor();
  assert.equal(await readPage.getByRole('button', { name: /新增任務/ }).count(), 0); await readPage.getByRole('button', { name: '另一個人的更新', exact: true }).click(); assert.equal(await readPage.getByRole('button', { name: '編輯任務', exact: true }).count(), 0);
  const readerCsrf = await (await reader.request.get(`${base}/api/csrf-cookie`)).json(); const denied = await reader.request.post(`${base}/api/rpc/updateIssue`, { headers: { 'X-CSRF-TOKEN': readerCsrf.csrfToken }, data: { id: issue.id, title: 'Forbidden edit', expectedRevision: 2, requestId: crypto.randomUUID() } }); assert.ok([403,404].includes(denied.status())); await reader.close();
  assert.deepEqual(errors, []); writeFileSync(new URL('result.json', folder), JSON.stringify({ passed: true, screenshots: 9, checks: ['progressive disclosure with retained values', 'one heading', 'inline validation focus', '503 keeps source', 'double-submit single flight and stable requestId', 'real 409 keeps source', 'reload/leave cancellation keeps source', 'three widths/save action reachable', 'pending image blocks cross-project switch without source loss', 'reader cannot edit in UI or HTTP'] }, null, 2));
  console.log('PASS: real create/edit form, validation focus, disclosure, 503/retry/double-click, actual conflict, leave protection and three widths');
} finally { await browser?.close(); await vite?.close(); }
