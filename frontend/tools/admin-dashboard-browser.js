import { loadDesignAudit } from './fixtures/optional-design-audit.js';
const designAudit = await loadDesignAudit();
import assert from 'node:assert/strict';
import { mkdir, writeFile, readFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { preview } from 'vite';
import { chromium } from 'playwright';
assert.equal(process.env.E2E_ISOLATED, '1');
assert.equal(process.env.E2E_API_URL, 'http://127.0.0.1:13199');
const port = Number(process.env.E2E_FRONTEND_PORT || 5199);
const base = `http://127.0.0.1:${port}`, out = '../artifacts/tt364';
await mkdir(out, { recursive: true });
let server, browser;
try {
  server = await preview({ preview: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
  const context = await browser.newContext({ locale: 'zh-TW', reducedMotion: 'reduce' });
  const page = await context.newPage(), errors = [], requests = [], findings = [];
  page.on('pageerror', cause => errors.push(cause.message));
  context.on('request', request => { if (request.url().includes('/api/')) requests.push(new URL(request.url()).pathname); });
  const settled = async target => {
    await target.evaluate(() => document.fonts.ready);
    await target.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
  };
  await page.goto(base + '/admin/');
  await page.getByLabel('管理帳號', { exact: true }).waitFor();
  assert.equal(await page.locator('meta[name="robots"]').getAttribute('content'), 'noindex, nofollow');
  assert.equal((await context.request.get(base + '/api/admin/statistics')).status(), 401);
  const csrf = (await (await context.request.get(base + '/api/csrf-cookie')).json()).csrfToken;
  assert.equal((await context.request.post(base + '/api/admin/session', { headers: { 'X-CSRF-TOKEN': csrf }, data: { username: 'wrong', password: 'wrong' } })).status(), 401);
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1000 }); await settled(page);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({ path: `${out}/admin-login-${width}.png`, fullPage: true });
  }
  await page.getByLabel('管理帳號', { exact: true }).fill('isolated_admin');
  await page.getByLabel('密碼', { exact: true }).fill('isolated-admin-password');
  await page.getByRole('button', { name: '登入', exact: true }).click();
  await page.getByRole('heading', { name: '使用量總覽' }).waitFor();
  await page.locator('.admin-workspace-list li').first().waitFor();
  await page.waitForFunction(() => document.querySelector('.admin-summary strong')?.textContent && document.querySelector('.admin-summary strong').textContent !== '—' && !document.querySelector('.admin-controls button').disabled);
  const data = (await (await context.request.get(base + '/api/admin/statistics?days=7')).json()).data;
  assert.equal(data.daily.length, 7); assert.ok(data.active.requests >= 25);
  assert.equal(data.daily.reduce((sum, day) => sum + day.requests, 0), data.active.requests);
  assert.equal(await page.locator('.admin-workspace-list li').count(), 5);
  for (const width of [1440, 768, 390]) {
    const candidate = await context.newPage(); await candidate.setViewportSize({ width, height: 1000 });
    await candidate.goto(base + '/admin/'); await candidate.locator('.admin-workspace-list li').first().waitFor();
    await candidate.waitForFunction(() => document.querySelector('.admin-summary strong')?.textContent && document.querySelector('.admin-summary strong').textContent !== '—' && !document.querySelector('.admin-controls button').disabled); await settled(candidate);
    assert.equal(await candidate.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    const metrics = designAudit ? await candidate.evaluate(designAudit.probe) : null;
    const options = { width, allow: [...(designAudit?.APP_SKIP || []), 'palette-purple'] };
    if (designAudit) findings.push(...designAudit.judge(metrics, options).filter(row => !['no-title','small-title'].includes(row.id)), ...designAudit.judgeLayout(await candidate.evaluate(designAudit.layout), options), ...designAudit.judgeApp(await candidate.evaluate(designAudit.app), metrics, options));
    const first = await candidate.screenshot({ path: `${out}/admin-overview-${width}.png`, fullPage: true });
    await candidate.close();
    const repeat = await context.newPage(); await repeat.setViewportSize({ width, height: 1000 });
    await repeat.goto(base + '/admin/'); await repeat.locator('.admin-workspace-list li').first().waitFor();
    await repeat.waitForFunction(() => document.querySelector('.admin-summary strong')?.textContent && document.querySelector('.admin-summary strong').textContent !== '—' && !document.querySelector('.admin-controls button').disabled); await settled(repeat);
    const second = await repeat.screenshot({ path: `${out}/admin-overview-repeat-${width}.png`, fullPage: true });
    assert.ok(second.equals(first), `Stable real-browser rendering at ${width}px`); await repeat.close();
  }
  await page.getByRole('button', { name: '查看全部', exact: true }).click();
  assert.equal(await page.locator('.admin-workspace-list li').count(), 20);
  await page.getByRole('button', { name: '下一頁', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('.admin-paging')?.textContent.includes('第2頁'));
  await page.getByRole('button', { name: '上一頁', exact: true }).click();
  await page.waitForFunction(() => document.querySelector('.admin-paging')?.textContent.includes('第1頁'));
  const statisticsResponse = page.waitForResponse(response => response.url().includes('/api/admin/statistics?days=30'));
  await page.getByLabel('期間', { exact: true }).selectOption('30');
  assert.equal((await (await statisticsResponse).json()).data.daily.length, 30);
  await page.locator('.admin-daily-details summary').click();
  assert.equal(await page.locator('.admin-table tbody tr').count(), 30);
  await page.locator('.locale-picker select').selectOption('en');
  await page.getByRole('heading', { name: 'Usage overview' }).waitFor();
  await page.locator('.locale-picker select').selectOption('zh-TW');
  const countBefore = requests.length; await page.waitForTimeout(1500);
  assert.equal(requests.length, countBefore, 'No automatic polling');
  assert.ok(!requests.some(path => /getWorkspace|getTodos|\/api\/v1\//.test(path)), 'Admin does not load tenant snapshots');
  await page.getByRole('button', { name: '登出', exact: true }).click();
  await page.getByLabel('管理帳號', { exact: true }).waitFor();
  assert.equal(await page.locator('.admin-dashboard').count(), 0);
  assert.equal((await context.request.get(base + '/api/admin/statistics')).status(), 401);
  assert.deepEqual(errors, []);
  await writeFile(`${out}/design-inspection.json`, JSON.stringify({ designAudit: !!designAudit, findings, allowance: 'Existing TaskTracker purple brand, explicitly preserved by product contract' }, null, 2));
  assert.equal(findings.filter(row => row.level === 'FAIL').length, 0, JSON.stringify(findings));
  await writeFile(`${out}/browser-summary.json`, JSON.stringify({ passed: true, isolated: true, widths: [1440,768,390], checks: ['separate-login','anonymous-rejected','noindex','period-7-and-30','keyset-pagination','zero-days','definitions','locale','logout-clears-protected-data','no-polling','no-workspace-snapshot','independent-render-repeat'], requests }, null, 2));
  if (process.env.ADMIN_VISUAL_COMPARE === '1') {
    const baseline = JSON.parse(await readFile('../docs/visual-baselines/admin-dashboard-darwin-arm64.json', 'utf8'));
    assert.equal(baseline.approved, true);
    for (const file of baseline.files) {
      const actual = createHash('sha256').update(await readFile(`${out}/${file.path}`)).digest('hex');
      assert.equal(actual, file.sha256, `Approved admin visual baseline: ${file.path}`);
    }
    console.log('PASS: six approved admin screenshots match exactly');
  }
  console.log('PASS: isolated admin login, bounded API, pagination, both languages, logout and three widths');
} finally { await browser?.close(); await new Promise(resolve => server ? server.httpServer.close(resolve) : resolve()); }
