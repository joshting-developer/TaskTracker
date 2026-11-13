import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import { expandIssueProperties } from './issue-form-actions.js';
if (process.env.E2E_ISOLATED !== '1' || !process.env.E2E_API_URL)
  throw Error('Explicit isolated backend required');
const port = Number(process.env.E2E_FRONTEND_PORT || 5291), base = `http://127.0.0.1:${port}`, phase = process.env.E2E_CAPTURE_PHASE || 'after', folder = new URL(`../../artifacts/tt310/${phase}/`, import.meta.url);
mkdirSync(folder, { recursive: true });
let vite, browser;
try {
  vite = await createServer({ root: new URL('../', import.meta.url).pathname, server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await vite.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } }), page = await context.newPage(), errors = [];
  page.setDefaultTimeout(15000);
  page.on('pageerror', e => errors.push(e.message));
  async function call(operation, input, client = context) { const csrf = await (await client.request.get(`${base}/api/csrf-cookie`)).json(); const res = await client.request.post(`${base}/api/${operation === 'register' ? 'auth' : 'rpc'}/${operation}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } }); assert.equal(res.status(), 200, await res.text()); return (await res.json()).data; }
  const identity = await call('register', { name: '編輯測試', email: `inline-${crypto.randomUUID()}@example.test`, password: 'isolated-inline-password', workspaceName: '完整頁編輯' });
  const project = await call('createProject', { name: '會員系統', code: 'EDIT' }), epic = await call('createIssue', { projectId: project.id, title: '會員功能', type: 'epic' }), created = await call('createIssue', { projectId: project.id, title: '原本的標題', description: '原本的描述' }), url = `${base}/app/#/projects/${project.id}/issues/${created.id}?detail=1`;
  await page.goto(url);
  const detail = page.getByRole('region', { name: '任務完整頁', exact: true });
  await detail.getByRole('heading', { name: '原本的標題', exact: true }).waitFor();
  await detail.getByLabel('新增留言', { exact: true }).fill('完整頁編輯時保留的留言草稿');
  await detail.getByRole('button', { name: '編輯任務', exact: true }).click();
  const editor = phase === 'before' ? page.getByRole('dialog', { name: '任務詳細內容', exact: true }) : page.getByRole('region', { name: '編輯任務完整頁', exact: true });
  await editor.waitFor();
  if (phase === 'before')
    await expandIssueProperties(editor);
  else {
    assert.equal(await page.getByRole('dialog').count(), 0, 'Full-page editing must not open a dialog');
    assert.equal(await detail.isVisible(), false);
  }
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1000 });
    if (width <= 700)
      await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    await page.evaluate(() => document.activeElement?.blur());
    await page.mouse.move(1, 1);
    await page.waitForLoadState('networkidle');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    await editor.screenshot({ path: new URL(`editing-${width}.png`, folder).pathname });
    if (width === 1440)
      writeFileSync(new URL('inspect.html', folder), await editor.evaluate(el => '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' + [...document.querySelectorAll('style')].map(s => s.outerHTML).join('') + '<div class="v-application v-theme--light">' + el.outerHTML + '</div>'));
    if (width === 390 && phase !== 'before') {
      const field = editor.getByLabel('預估小時', { exact: true });
      await field.focus();
      assert.equal(await field.evaluate(el => { const r = el.getBoundingClientRect(); return document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2) === el; }), true, 'Mobile focused field must not be obscured by sticky save action');
    }
  }
  if (phase === 'before') {
    console.log('PASS: old full-page drawer before capture');
  }
  else {
    await page.setViewportSize({ width: 1440, height: 1000 });
    for (const label of ['狀態', '類型', '優先級', '負責人', '到期日', '所屬 Epic', '預估小時'])
      assert.equal(await editor.getByLabel(label, { exact: true }).count(), 1, `Missing field: ${label}`);
    const geometry = await editor.evaluate(el => ({ title: el.querySelector('[name=title]').getBoundingClientRect().x, properties: el.querySelector('.issue-properties').getBoundingClientRect().x }));
    assert.ok(geometry.properties > geometry.title + 250, 'Properties belong in the right column');
    await editor.getByLabel(/任務標題/).fill('新的會員登入標題');
    await editor.getByLabel('描述', { exact: true }).fill('**一次儲存標題、描述與屬性**');
    await editor.getByLabel('類型', { exact: true }).selectOption('bug');
    await editor.getByLabel('狀態', { exact: true }).selectOption('inprogress');
    await editor.getByLabel('優先級', { exact: true }).selectOption('high');
    await editor.getByLabel('負責人', { exact: true }).selectOption(identity.user.id);
    await editor.getByLabel('到期日', { exact: true }).fill('2026-11-20');
    await editor.getByLabel('所屬 Epic', { exact: true }).selectOption(epic.id);
    await editor.getByLabel('預估小時', { exact: true }).fill('2');
    const scratch = await context.newPage();
    await scratch.setViewportSize({ width: 320, height: 180 });
    await scratch.setContent('<p>就地編輯描述圖片</p>');
    const png = await scratch.screenshot();
    await scratch.close();
    await editor.getByLabel('上傳描述圖片').setInputFiles({ name: 'inline.png', mimeType: 'image/png', buffer: png });
    await editor.getByText('已加入描述', { exact: true }).waitFor();
    const source = await editor.getByLabel('描述', { exact: true }).inputValue();
    assert.match(source, /attachment:/);
    const descriptionEditor = editor.getByRole('group', { name: '描述編輯器', exact: true });
    await descriptionEditor.getByRole('tab', { name: '預覽', exact: true }).click();
    await descriptionEditor.locator('.image-preview img').waitFor();
    await descriptionEditor.getByRole('tab', { name: '編輯', exact: true }).click();
    assert.equal(await editor.getByLabel('描述', { exact: true }).inputValue(), source);
    let fail = true;
    const writes = [];
    await page.route('**/api/rpc/updateIssue', async (route) => { writes.push(route.request().postDataJSON()); if (fail) {
      fail = false;
      return route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { code: 'NETWORK_ERROR', message: '暫時無法儲存' } }) });
    } await new Promise(r => setTimeout(r, 200)); await route.continue(); });
    await editor.getByRole('button', { name: '儲存修改', exact: true }).click();
    await editor.getByText('暫時無法儲存', { exact: true }).waitFor();
    assert.equal(await editor.getByLabel('描述', { exact: true }).inputValue(), source);
    await editor.getByRole('button', { name: '儲存修改', exact: true }).evaluate(el => { el.click(); el.click(); });
    await editor.waitFor({ state: 'hidden' });
    await detail.getByRole('heading', { name: '新的會員登入標題', exact: true }).waitFor();
    await page.unroute('**/api/rpc/updateIssue');
    assert.equal(writes.length, 2);
    assert.equal(writes[0].requestId, writes[1].requestId);
    assert.equal(await detail.getByLabel('新增留言', { exact: true }).inputValue(), '完整頁編輯時保留的留言草稿');
    await detail.getByLabel('新增留言', { exact: true }).fill('');
    const stored = (await (await context.request.get(`${base}/api/v1/projects/${project.id}/issues/${created.id}`)).json()).data;
    for (const [key, value] of Object.entries({ title: '新的會員登入標題', description: source, type: 'bug', state: 'inprogress', priority: 'high', assignee: identity.user.id, dueDate: '2026-11-20', epicId: epic.id, estimateMinutes: 120 }))
      assert.equal(stored[key], value, key);
    await detail.getByRole('button', { name: '編輯任務', exact: true }).click();
    await editor.waitFor();
    await editor.getByLabel(/任務標題/).fill('409後保留原稿');
    await call('updateIssue', { id: created.id, expectedRevision: stored.revision, title: '另一個人的修改' });
    await editor.getByRole('button', { name: '儲存修改', exact: true }).click();
    await editor.getByText('目前修改仍保留, 重新載入會捨棄這份修改').waitFor();
    assert.equal(await editor.getByLabel(/任務標題/).inputValue(), '409後保留原稿');
    page.once('dialog', d => d.dismiss());
    await editor.getByRole('button', { name: '取消', exact: true }).click();
    assert.equal(await editor.isVisible(), true);
    page.once('dialog', d => d.dismiss());
    await page.getByRole('link', { name: 'TaskTracker 返回首頁', exact: true }).click();
    assert.equal(await editor.isVisible(), true, 'Navigation cancel retains inline draft');
    const unload = await editor.evaluate(() => { const e = new Event('beforeunload', { cancelable: true }); window.dispatchEvent(e); return e.defaultPrevented; });
    assert.equal(unload, true, 'Inline draft registers browser unload protection');
    page.once('dialog', d => d.accept());
    await editor.getByRole('button', { name: '取消', exact: true }).click();
    await editor.waitFor({ state: 'hidden' });
    await detail.waitFor();
    await detail.getByRole('button', { name: '返回任務列表', exact: true }).click();
    await page.getByRole('button', { name: '另一個人的修改', exact: true }).waitFor();
    await page.getByLabel('快速編輯 EDIT-2').click();
    await page.getByRole('dialog', { name: '任務詳細內容', exact: true }).waitFor();
    await page.getByRole('button', { name: '關閉任務', exact: true }).click();
    const email = `inline-reader-${crypto.randomUUID()}@example.test`, invite = await call('inviteMember', { email }), reader = await browser.newContext();
    const person = await call('register', { name: 'Reader', email, password: 'isolated-inline-password', inviteToken: invite.token }, reader);
    const acl = await call('getProjectMembers', { projectId: project.id });
    await call('setProjectMember', { projectId: project.id, userId: person.user.id, roleId: 'reader', expectedRevision: acl.revision });
    const readPage = await reader.newPage();
    await readPage.goto(url);
    await readPage.getByRole('heading', { name: '另一個人的修改', exact: true }).waitFor();
    assert.equal(await readPage.getByRole('button', { name: '編輯任務', exact: true }).count(), 0);
    const rcsrf = await (await reader.request.get(`${base}/api/csrf-cookie`)).json();
    const forbidden = await reader.request.post(`${base}/api/rpc/updateIssue`, { headers: { 'X-CSRF-TOKEN': rcsrf.csrfToken }, data: { id: created.id, title: 'Forbidden', expectedRevision: stored.revision, requestId: crypto.randomUUID() } });
    assert.ok([403, 404].includes(forbidden.status()));
    await reader.close();
    assert.deepEqual(errors, []);
    writeFileSync(new URL('result.json', folder), JSON.stringify({ passed: true, screenshots: 3, checks: ['in-page region, no dialog', 'field parity and right column', 'atomic multi-field save and private image', '503/retry/single-flight', 'real 409 retains source', 'cancel/navigation/unload guards', 'reading comment draft survives', 'list quick edit remains modal'] }, null, 2));
    console.log('PASS: full-page inline editing, all fields, real image, retry/conflict/drafts/leave guards and quick drawer');
  }
}
finally {
  await browser?.close();
  await vite?.close();
}
