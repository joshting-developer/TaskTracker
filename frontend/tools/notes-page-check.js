import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { fixtureResponse } from './fixtures/verification.js';
const port = Number(process.env.E2E_FRONTEND_PORT || 5175), apiUrl = process.env.E2E_API_URL;
if (process.env.E2E_ISOLATED !== '1' || !['http://127.0.0.1:13199', 'http://127.0.0.1:13198', 'http://127.0.0.1:13029'].includes(apiUrl)) throw Error('Owned isolated notes API required');
let server, browser;
mkdirSync('artifacts/notes', { recursive: true });
try {
  server = await createServer({ server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': { target: apiUrl, changeOrigin: false } } } }); assert.equal(server.config.server.proxy['/api'].target, apiUrl); await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const [owner, josh, codex] = await Promise.all(['Owner', 'Josh', 'Codex'].map(async () => (await browser.newContext({ viewport: { width: 1440, height: 1000 } })).newPage()));
  const pages = [owner, josh, codex], errors = [];
  for (const page of pages) { page.setDefaultTimeout(12000); page.on('pageerror', e => { errors.push(e.message); console.error('PAGE', e.message); }); await page.goto(`http://127.0.0.1:${port}/login`); }
  async function call(page, operation, input = {}, group = 'rpc') {
    const csrf = await (await page.request.get(`http://127.0.0.1:${port}/api/csrf-cookie`)).json();
    const response = await page.request.post(`http://127.0.0.1:${port}/api/${group}/${operation}`, { data: { requestId: crypto.randomUUID(), ...input }, headers: { 'X-CSRF-TOKEN': csrf.csrfToken } });
    assert.equal(response.status(), 200, await response.text()); return (await fixtureResponse(page.request, response)).data;
  }
  const people = {};
  people.Owner = (await call(owner, 'register', { name: 'Owner', email: `notes-${crypto.randomUUID()}@example.test`, password: 'notes-isolated-password', workspaceName: '紙條測試' }, 'auth')).user;
  for (const [name, page] of [['Josh', josh], ['Codex', codex]]) {
    const email = `notes-${crypto.randomUUID()}@example.test`, invitation = await call(owner, 'inviteMember', { email });
    people[name] = (await call(page, 'register', { name, email, password: 'notes-isolated-password', inviteToken: invitation.token }, 'auth')).user;
  }
  const project = await call(owner, 'createProject', { name: '紙條卡號驗收', code: 'REF' });
  const card = await call(owner, 'createIssue', { projectId: project.id, title: '紙條連結任務' });
  const reference = (await (await owner.request.get(`http://127.0.0.1:${port}/api/v1/projects/${project.id}/issues/${card.id}`)).json()).data.identifier;
  await call(owner, 'setProjectMember', { projectId: project.id, userId: people.Josh.id, roleId: 'developer', expectedRevision: 0 });
  const responses = []; owner.on('response', r => { if (r.request().method() === 'GET' && /\/api\/v1\/todos(?:\?|$)/.test(r.url())) responses.push(r.text().then(raw => ({ raw, url: r.url() }))); });
  const requests = []; owner.on('request', r => { if (r.url().includes('/api/')) requests.push(new URL(r.url()).pathname); });
  await owner.reload(); await owner.locator('.app-shell').waitFor();
  assert.equal(await owner.getByRole('region', { name: '紙條', exact: true }).count(), 0);
  assert.equal(requests.some(p => p.startsWith('/api/v1/todos')), false);
  await owner.getByRole('link', { name: '紙條', exact: true }).click();
  await owner.getByRole('tab', { name: '建立', exact: true }).click();
  await owner.getByLabel('紙條內容', { exact: true }).fill(`確認圖片下載資格\n\n先確認${reference}與#${reference}\n\n\`${reference}\` [原連結](https://example.com/${reference})\n\n<img src=x onerror=window.__noteXss=1> [危險](javascript:alert(1))`);
  await owner.getByLabel('交給誰', { exact: true }).selectOption(people.Josh.id);
  await owner.getByRole('button', { name: '建立紙條', exact: true }).click(); await owner.getByText('紙條已建立', { exact: true }).waitFor();
  await owner.getByRole('tab', { name: '我交辦的', exact: true }).click();
  const todo = (await (await owner.request.get(`http://127.0.0.1:${port}/api/v1/todos?scope=sent&state=all`)).json()).data.items[0];
  const openNotes = async page => { await page.goto(`http://127.0.0.1:${port}/app/#/notes`); await page.getByRole('heading', { name: '紙條', exact: true }).waitFor(); };
  const more = (page, id = todo.id) => page.locator(`[data-todo-id="${id}"]`).getByRole('button', { name: /^更多：/ });
  const drawer = page => page.getByRole('dialog', { name: '紙條', exact: true });
  await openNotes(josh); const history = []; josh.on('request', r => { if (/\/todos\/[^/]+\/(comments|handoffs)/.test(r.url())) history.push(r.url()); });
  await more(josh).waitFor(); assert.equal(history.length, 0); await more(josh).click();
  await drawer(josh).getByLabel('要傳給誰', { exact: true }).waitFor();
  assert.equal(await drawer(josh).getByRole('button', { name: /送出回覆|退回/ }).count(), 0);
  const refLink = drawer(josh).getByRole('link', { name: reference, exact: true });
  assert.equal(await refLink.count(), 1); assert.equal(await drawer(josh).getByRole('link', { name: '#'+reference, exact: true }).count(), 1);
  assert.equal(await drawer(josh).getByRole('link', { name: '原連結', exact: true }).getAttribute('href'), `https://example.com/${reference}`);
  assert.equal(await refLink.getAttribute('rel'), 'noopener noreferrer');
  assert.equal(await drawer(josh).locator('.note-content img, .note-content script, .note-content a[href^="javascript:"]').count(), 0);
  assert.equal(await josh.evaluate(() => Boolean(window.__noteXss)), false);
  const [popup] = await Promise.all([josh.waitForEvent('popup'), refLink.click()]);
  await popup.waitForURL(new RegExp(`/issues/${card.id}\\?detail=1`));
  await popup.getByRole('heading', { name: '紙條連結任務', exact: true }).waitFor(); await popup.close();
  await drawer(josh).getByLabel('回覆內容', { exact: true }).fill('確認後交給Codex實作');
  await drawer(josh).getByLabel('要傳給誰', { exact: true }).selectOption(people.Codex.id);
  await drawer(josh).getByRole('button', { name: '傳遞', exact: true }).click();
  await drawer(josh).getByText('確認後交給Codex實作', { exact: true }).waitFor();
  assert.equal(await drawer(josh).getByLabel('要傳給誰', { exact: true }).count(), 0);
  await drawer(josh).getByRole('button', { name: '關閉紙條', exact: true }).click();
  await openNotes(codex); await more(codex).click();
  assert.equal(await drawer(codex).getByLabel('要傳給誰', { exact: true }).inputValue(), '__previous__');
  assert.equal(await drawer(codex).getByRole('button', { name: '傳遞', exact: true }).isDisabled(), true);
  await drawer(codex).getByLabel('回覆內容', { exact: true }).fill('先請Josh確認卡號連結');
  await drawer(codex).getByRole('button', { name: '傳遞', exact: true }).click();
  await drawer(codex).getByText('先請Josh確認卡號連結', { exact: true }).waitFor();
  const returned = (await (await owner.request.get(`http://127.0.0.1:${port}/api/v1/todos/${todo.id}`)).json()).data;
  assert.equal(returned.assigneeId, people.Josh.id, 'Reply returns to actual path handler, rather than note creator');
  await josh.reload(); await more(josh).click();
  await drawer(josh).getByLabel('要傳給誰', { exact: true }).selectOption(people.Codex.id);
  await drawer(josh).getByLabel('回覆內容', { exact: true }).fill('確認後繼續');
  await drawer(josh).getByRole('button', { name: '傳遞', exact: true }).click();
  await drawer(josh).getByText('確認後繼續', { exact: true }).waitFor();
  await codex.reload(); await more(codex).click(); await drawer(codex).getByLabel('回覆內容', { exact: true }).fill('下載隔離已測試');
  await drawer(codex).getByRole('button', { name: '完成', exact: true }).click();
  await drawer(codex).getByText('下載隔離已測試', { exact: true }).waitFor(); await codex.keyboard.press('Escape');
  await codex.getByRole('tab', { name: '我經手的', exact: true }).click(); await more(codex).waitFor();
  assert.ok((await codex.locator(`[data-todo-id="${todo.id}"]`).innerText()).includes('已完成'));
  const self = await call(owner, 'createTodo', { content: '自己的速記' });
  await owner.reload(); await owner.getByRole('tab', { name: '我交辦的', exact: true }).click(); await more(owner, self.id).waitFor();
  await owner.getByRole('tab', { name: '待處理', exact: true }).click(); await more(owner, self.id).click();
  assert.equal(await drawer(owner).getByLabel('要傳給誰', { exact: true }).locator('option[value="__previous__"]').count(), 0);
  assert.equal(await drawer(owner).getByLabel('要傳給誰', { exact: true }).inputValue(), '');
  await drawer(owner).getByLabel('回覆內容', { exact: true }).fill('版本衝突保留的內容'); await drawer(owner).getByLabel('要傳給誰', { exact: true }).selectOption(people.Josh.id);
  await call(owner, 'commentTodo', { id: self.id, expectedRevision: self.revision, content: '模擬另一分頁更新' });
  await drawer(owner).getByRole('button', { name: '傳遞', exact: true }).click();
  await drawer(owner).getByText('紙條已更新, 草稿已保留', { exact: false }).waitFor(); assert.equal(await drawer(owner).getByLabel('回覆內容', { exact: true }).inputValue(), '版本衝突保留的內容');
  await drawer(owner).getByRole('button', { name: '使用最新版本繼續', exact: true }).click(); await drawer(owner).getByRole('button', { name: '傳遞', exact: true }).click();
  await drawer(owner).getByText('版本衝突保留的內容', { exact: true }).waitFor(); await owner.waitForFunction(() => !document.querySelector('[aria-label="關閉紙條"]').disabled); await owner.keyboard.press('Escape');
  for (const width of [1440, 768, 390]) {
    await owner.setViewportSize({ width, height: 900 }); await owner.getByRole('tab', { name: '我交辦的', exact: true }).click();
    assert.equal(await owner.evaluate(() => document.documentElement.scrollWidth > innerWidth), false); await owner.screenshot({ path: `artifacts/notes/list-${width}.png` });
    await more(owner).click(); await drawer(owner).getByText('下載隔離已測試', { exact: true }).waitFor(); assert.equal(await drawer(owner).getByLabel('回覆內容', { exact: true }).count(), 0);
    await owner.screenshot({ path: `artifacts/notes/drawer-${width}.png` }); await owner.waitForFunction(() => !document.querySelector('[aria-label="關閉紙條"]').disabled); await owner.keyboard.press('Escape');
  }
  await owner.setViewportSize({ width: 1440, height: 900 }); await owner.getByRole('tab', { name: '建立', exact: true }).click(); await owner.getByLabel('紙條內容', { exact: true }).fill('未送出草稿');
  owner.once('dialog', d => d.dismiss()); await owner.getByRole('link', { name: '專案列表', exact: true }).click(); assert.equal(await owner.getByLabel('紙條內容', { exact: true }).inputValue(), '未送出草稿');
  await owner.getByLabel('紙條內容', { exact: true }).fill(''); await owner.reload(); await owner.getByRole('heading', { name: '紙條', exact: true }).waitFor();
  assert.equal(requests.some(p => /\/(getWorkspace|getTodos)$/.test(p)), false); assert.deepEqual(errors, []);
  const samples = []; for (const { raw, url } of await Promise.all(responses)) { const body = JSON.parse(raw).data; assert.ok(body.items.length <= 20); for (const item of body.items) for (const field of ['content', 'note', 'history', 'comments', 'handoffs']) assert.equal(Object.hasOwn(item, field), false); samples.push({ scope: new URL(url).searchParams.get('scope'), rows: body.items.length, bytes: Buffer.byteLength(raw) }); }
  writeFileSync('../artifacts/tt331/network.json', JSON.stringify({ topology: 'isolated MySQL HTTP and Chrome', samples }, null, 2));
  console.log('PASS notes route, safe card links and actual popup, reply-to-previous path, four tabs, self-notes, lazy histories, 3-person transfer/completion replies, stale draft, leave guard and 3 widths');
} finally { await browser?.close(); await server?.close(); }
