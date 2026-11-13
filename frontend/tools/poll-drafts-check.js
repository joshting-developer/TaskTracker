import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { moveBoardCard } from './board-actions.js';
// TT-149：拖曳立即回饋、送出中暫停背景換列、失敗／衝突還原；只連隔離E2E服務。
if (!process.env.E2E_API_URL) throw new Error('必須指定隔離E2E_API_URL');
// 看板輪詢縮為10秒，讓背景輪次落在HTTP 20秒逾時之前，同時驗證VITE_POLL_BOARD_MS覆寫。
process.env.VITE_POLL_BOARD_MS = '10000';
let server, browser;
try {
  server = await createServer({ root: new URL('..', import.meta.url).pathname, server: { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } }), errors = [], requests = [];
  page.setDefaultTimeout(10000);
  page.on('pageerror', error => errors.push(error.message));
  const sizes = { version: 0, issues: 0 };
  page.on('response', async response => { const path = new URL(response.url()).pathname; const key = /\/version$/.test(path) ? 'version' : /\/boards\/[^/]+\/issues$/.test(path) ? 'issues' : ''; if (key) try { sizes[key] = (await response.body()).length; } catch { /* 導覽後不可取 */ } });
  page.on('request', request => { const url = new URL(request.url()); if (url.pathname.startsWith('/api/')) requests.push(url.pathname); });
  await page.goto('http://127.0.0.1:5175/login');
  async function call(op, input = {}, group = 'rpc') {
    return page.evaluate(async ({ op, input, group }) => {
      const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
      const result = await (await fetch(`/api/${group}/${op}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
      if (!result.ok) throw new Error(`${op}: ${result.error.message}`);
      if (result.data?.verificationRequired) { const csrf = await (await fetch('/api/csrf-cookie')).json(); const verified = await fetch('/api/auth/verify-email', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: JSON.stringify({ code: 'local666' }) }); if (!verified.ok) throw new Error('Fixture email verification failed'); result.data = (await verified.json()).data; } return result.data;
    }, { op, input, group });
  }
  await call('register', { name: 'Drag Owner', email: `drag-${crypto.randomUUID()}@example.test`, password: 'isolated-test-password', workspaceName: 'Drag Test' }, 'auth');
  const project = await call('createProject', { name: 'Drag', code: 'DRG' });
  const slow = await call('createIssue', { projectId: project.id, title: '慢速移動' });
  const failing = await call('createIssue', { projectId: project.id, title: '失敗移動' });
  const stale = await call('createIssue', { projectId: project.id, title: '衝突移動' });

  // 先裝假時鐘再載入，背景輪詢計時器受fastForward控制。
  await page.clock.install();
  await page.goto(`http://127.0.0.1:5175/app/#/projects/${project.id}/board`);
  await page.reload();
  const card = identifier => page.locator(`.issue-card[data-issue="${identifier}"]`);
  const inColumn = (identifier, state) => page.locator(`[data-column="${state}"] [data-issue="${identifier}"]`);
  await card(slow.identifier).waitFor();
  const background = () => requests.filter(path => new RegExp(`/api/v1/projects/${project.id}(/boards/[^/]+(/issues|/version)?)?$`).test(path)).length;
  const count = pattern => requests.filter(path => pattern.test(path)).length;

  // 1. 慢速寫入：放下即移到目標欄，送出中背景輪詢不換列，完成後由寫入後重讀交回伺服器資料。
  let release;
  await page.route('**/api/rpc/updateIssue', route => { release = () => route.continue(); });
  await moveBoardCard(page, slow.identifier, 'closed', { settle: false });
  assert.ok(release, '寫入仍在送出中');
  assert.equal(await card(slow.identifier).evaluate(node => node.classList.contains('moving')), true, '送出中標示');
  const before = background();
  await page.clock.fastForward(12000);
  await page.waitForTimeout(300);
  assert.equal(background(), before, '送出中不做背景重讀');
  await inColumn(slow.identifier, 'closed').waitFor();
  const settled = page.waitForResponse(response => /\/boards\/[^/]+\/issues/.test(response.url()));
  await release();
  await settled;
  await page.unroute('**/api/rpc/updateIssue');
  await inColumn(slow.identifier, 'closed').waitFor();
  await page.locator('.issue-card.moving').waitFor({ state: 'detached' });

  // 2. 寫入失敗：先移動，失敗後回到原欄並提示。
  let fail;
  await page.route('**/api/rpc/updateIssue', route => { fail = () => route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { code: 'HTTP_ERROR', message: '暫時無法儲存' } }) }); });
  await moveBoardCard(page, failing.identifier, 'inprogress', { settle: false });
  await inColumn(failing.identifier, 'inprogress').waitFor();
  await fail();
  await inColumn(failing.identifier, 'open').waitFor();
  await page.getByText('暫時無法儲存').first().waitFor();
  await page.unroute('**/api/rpc/updateIssue');

  // 3. 版本衝突：他人已更新，移動被拒後還原並重讀，顯示伺服器的新內容，不靜默覆寫。
  await call('updateIssue', { id: stale.id, title: '衝突移動（他人已改）', expectedRevision: stale.revision });
  let conflict;
  await page.route('**/api/rpc/updateIssue', route => { conflict = () => route.continue(); });
  await moveBoardCard(page, stale.identifier, 'closed', { settle: false });
  await conflict();
  await page.getByText(/清單已更新/).first().waitFor();
  await page.unroute('**/api/rpc/updateIssue');
  await inColumn(stale.identifier, 'open').filter({ hasText: '衝突移動（他人已改）' }).waitFor();
  const detail = await page.evaluate(async id => (await (await fetch(`/api/v1/projects/${location.hash.split('/')[2]}/issues/${id}`)).json()).data, stale.id);
  assert.equal(detail.state, 'open', '衝突時伺服器狀態未被覆寫');

  // 4. 解除暫停後背景輪詢照常。
  const resumed = background();
  await page.clock.fastForward(12000);
  await page.waitForTimeout(300);
  assert.ok(background() > resumed, '移動結束後恢復背景輪詢');

  // 5. 輕量變更檢查（TT-148）：版本不變只送version，不重讀Project／看板／卡片；他人改動後才重讀。
  const issuesPath = /\/boards\/[^/]+\/issues$/, versionPath = /\/boards\/[^/]+\/version$/;
  const idle = { issues: count(issuesPath), version: count(versionPath), project: count(new RegExp(`/api/v1/projects/${project.id}$`)) };
  await page.clock.fastForward(12000);
  await page.waitForTimeout(300);
  assert.ok(count(versionPath) > idle.version, '背景輪次有版本檢查');
  assert.equal(count(issuesPath), idle.issues, '版本不變不重讀卡片');
  assert.equal(count(new RegExp(`/api/v1/projects/${project.id}$`)), idle.project, '版本不變不重讀Project');
  const latest = await page.evaluate(async id => (await (await fetch(`/api/v1/projects/${location.hash.split('/')[2]}/issues/${id}`)).json()).data, failing.id);
  await call('updateIssue', { id: failing.id, title: '他人更新的標題', expectedRevision: latest.revision });
  await page.clock.fastForward(12000);
  await card(failing.identifier).filter({ hasText: '他人更新的標題' }).waitFor();
  assert.ok(count(issuesPath) > idle.issues, '版本變更後重讀卡片');
  assert.deepEqual(errors, []);
  console.log('poll drafts PASS', JSON.stringify({ heldDuringMove: true, versionSkip: true, versionBytes: sizes.version, boardIssuesBytes: sizes.issues }));
} finally {
  await browser?.close();
  await server?.close();
}
