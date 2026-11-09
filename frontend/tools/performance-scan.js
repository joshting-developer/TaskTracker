import assert from 'node:assert/strict';
import { mkdirSync, readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';
import { preview } from 'vite';
import { chromium, request } from 'playwright';

// 只量測讀取與本地UI切換；API資料由外部明確啟動的隔離seed服務提供。
const target = new URL(process.env.E2E_API_URL || 'http://invalid');
assert.ok(['localhost', '127.0.0.1'].includes(target.hostname) && !['3002', '5174', '5175'].includes(target.port), '請指定獨立loopback測試API，拒絕主服務埠');
assert.ok(process.env.E2E_SEED_PASSWORD, '需要隔離seed測試密碼');
const phase = process.env.PERF_PHASE || 'seed';
assert.ok(['seed', 'expanded', 'cards'].includes(phase));
const artifacts = 'artifacts/performance-scan';
mkdirSync(artifacts, { recursive: true });
const summarize = values => {
  const sorted = [...values].sort((a, b) => a - b);
  const rank = p => sorted[Math.max(0, Math.ceil(sorted.length * p) - 1)];
  return { samples: values.length, min: sorted[0], median: rank(.5), p95: rank(.95), max: sorted.at(-1) };
};
let server, browser;
const result = { phase, scope: 'isolated synthetic; sequential requests; no load capacity or field Web Vitals claim', http: [], browser: [] };
async function signIn(role) {
  const client = await request.newContext({ baseURL: target.origin });
  const { csrfToken } = await (await client.get('/api/csrf-cookie')).json();
  const login = await client.post('/api/auth/login', { headers: { 'X-CSRF-TOKEN': csrfToken }, data: { email: `${role}@example.test`, password: process.env.E2E_SEED_PASSWORD } });
  assert.equal(login.status(), 200, '隔離seed登入成功');
  const token = (await (await client.get('/api/csrf-cookie')).json()).csrfToken;
  return { client, token };
}
async function read(client, token, operation, input = {}) {
  const start = performance.now();
  const response = await client.post(`/api/rpc/${operation}`, { headers: { 'X-CSRF-TOKEN': token }, data: input });
  const body = await response.body();
  const ms = performance.now() - start;
  assert.equal(response.status(), 200, `${operation} HTTP成功`);
  const envelope = JSON.parse(body);
  assert.equal(envelope.ok, true, `${operation}回應成功`);
  return { ms, bytes: body.length, data: envelope.data };
}
async function frames(page) {
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
}
try {
  let ownerState, ownerWorkspace;
  for (const role of phase === 'cards' ? ['owner'] : ['owner', 'manager', 'developer']) {
    const { client, token } = await signIn(role);
    try {
      for (const operation of role === 'developer' ? ['getWorkspace', 'getTodos'] : ['getWorkspace', 'getTodos', 'getTimeReport']) {
        const input = operation === 'getTimeReport' ? { startDate: '2025-01-01', endDate: '2025-12-31' } : {};
        for (let index = 0; index < 3; index++) await read(client, token, operation, input);
        const samples = [];
        for (let index = 0; index < 30; index++) samples.push(await read(client, token, operation, input));
        const last = samples.at(-1);
        result.http.push({ role, operation, timingsMs: summarize(samples.map(x => x.ms)), responseBytes: last.bytes,
          ...(operation === 'getWorkspace' ? { projects: last.data.projects.length, issues: last.data.issues.length, entries: last.data.timeEntries.length } : {}),
          ...(operation === 'getTimeReport' ? { entries: last.data.summary.entryCount } : {}),
        });
        if (role === 'owner' && operation === 'getWorkspace') ownerWorkspace = last.data;
      }
      if (role === 'owner') ownerState = await client.storageState();
    } finally { await client.dispose(); }
  }
  server = await preview({ preview: { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': target.origin } } });
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const first = ownerWorkspace.projects.find(project => project.code === 'FIRST');
  assert.ok(first, 'FIRST seed專案存在');
  for (let run = 0; run < (phase === 'cards' ? 3 : 5); run++) {
    const context = await browser.newContext({ storageState: ownerState, viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    page.setDefaultTimeout(20000);
    const calls = [], errors = [];
    const pendingApi = new Set();
    page.on('requestfinished', req => pendingApi.delete(req));
    page.on('requestfailed', req => pendingApi.delete(req));
    page.on('request', req => { const url = new URL(req.url()); if (url.pathname.startsWith('/api/')) { calls.push(url.pathname); pendingApi.add(req); } });
    page.on('pageerror', error => errors.push(error.message));
    await page.addInitScript(() => {
      window.__perf = { longTasks: [], eventDurations: [] };
      if (PerformanceObserver.supportedEntryTypes.includes('longtask')) new PerformanceObserver(list => window.__perf.longTasks.push(...list.getEntries().map(e => e.duration))).observe({ type: 'longtask', buffered: true });
      if (PerformanceObserver.supportedEntryTypes.includes('event')) new PerformanceObserver(list => window.__perf.eventDurations.push(...list.getEntries().filter(e => e.interactionId).map(e => e.duration))).observe({ type: 'event', buffered: true, durationThreshold: 16 });
    });
    const start = performance.now();
    await Promise.all([page.waitForResponse(r => r.url().endsWith('/api/rpc/getTodos')), page.goto('http://127.0.0.1:5175/#/')]);
    await page.locator('.todo-home').waitFor(); await frames(page);
    const homeReadyMs = performance.now() - start;
    const homeCalls = [...calls]; calls.length = 0;
    const boardStart = performance.now();
    await page.evaluate(id => { location.hash = `#/projects/${id}/board`; }, first.id);
    await page.locator('.issue-card').first().waitFor();
    await page.waitForResponse(r => r.url().endsWith('/api/rpc/getTodos'));
    await frames(page);
    const boardReadyMs = performance.now() - boardStart;
    const boardDom = await page.evaluate(() => ({ cards: document.querySelectorAll('.issue-card').length, contextBlocks: document.querySelectorAll('.issue-card .issue-context').length, totalElements: document.querySelectorAll('*').length }));
    const boardCalls = [...calls]; calls.length = 0;
    const densitySamples = [];
    for (let index = 0; index < 5; index++) {
      const before = performance.now();
      await page.getByLabel('看板卡片密度', { exact: true }).selectOption(index % 2 ? 'comfortable' : 'compact'); await frames(page);
      densitySamples.push(performance.now() - before);
    }
    const clickStart = performance.now();
    await page.getByRole('button', { name: '重新整理工作空間', exact: true }).click();
    await page.waitForResponse(r => r.url().endsWith('/api/rpc/getTodos')); await frames(page);
    const refreshReadyMs = performance.now() - clickStart;
    const refreshCalls = [...calls]; calls.length = 0;
    let burstCalls = null;
    if (run === 0) {
      await page.getByRole('button', { name: '重新整理工作空間', exact: true }).dblclick();
      const deadline = Date.now() + 20000;
      while (pendingApi.size && Date.now() < deadline) await new Promise(resolve => setTimeout(resolve, 25));
      assert.equal(pendingApi.size, 0, '雙擊刷新完成');
      await frames(page);
      burstCalls = [...calls]; calls.length = 0;
      assert.equal(burstCalls.filter(path => path.endsWith('/getWorkspace')).length, 1, '雙擊刷新共用工作區讀取');
    }
    await page.getByRole('button', { name: '工時分析', exact: true }).click();
    await page.getByRole('button', { name: /^成員投入/ }).click();
    await page.locator('.analysis-totals').waitFor(); await frames(page); calls.length = 0;
    const reportStart = performance.now();
    await page.getByRole('button', { name: '重新整理工作空間', exact: true }).click();
    await page.waitForResponse(r => r.url().endsWith('/api/rpc/getTimeReport'));
    await page.locator('.analysis-totals').waitFor(); await frames(page);
    const analysisRefreshMs = performance.now() - reportStart;
    const perf = await page.evaluate(() => ({ ...window.__perf, navigation: performance.getEntriesByType('navigation').map(n => ({ transferSize: n.transferSize, domComplete: n.domComplete })), resources: performance.getEntriesByType('resource').filter(r => r.initiatorType === 'script' || r.initiatorType === 'link' || /\.(woff2?|ttf|eot)(?:$|\?)/.test(r.name)).map(r => ({ type: r.initiatorType, name: new URL(r.name).pathname.split('/').at(-1), encodedBodySize: r.encodedBodySize, decodedBodySize: r.decodedBodySize })) }));
    result.browser.push({ run: run + 1, homeReadyMs, homeCalls, boardReadyMs, boardCalls, boardDom, densitySwitchMs: densitySamples, refreshReadyMs, refreshCalls, burstCalls, analysisRefreshMs, analysisCalls: [...calls], longTasksMs: perf.longTasks, eventDurationsMs: perf.eventDurations, staticResources: perf.resources, errors });
    assert.deepEqual(errors, [], '沒有瀏覽器runtime錯誤');
    if (run === 0) await page.screenshot({ path: `${artifacts}/${phase}-analysis.png` });
    await context.close();
  }
  result.bundle = readdirSync('dist/assets').filter(name => /\.(js|css)$/.test(name)).map(name => ({ name, bytes: statSync(`dist/assets/${name}`).size, gzipBytes: gzipSync(readFileSync(`dist/assets/${name}`)).length }));
  result.browserVersion = browser.version();
  writeFileSync(`${artifacts}/${phase}.json`, JSON.stringify(result, null, 2));
  console.log(JSON.stringify({ phase, http: result.http, browserRuns: result.browser.length, bundle: result.bundle }));
} finally {
  await browser?.close();
  if (server) await new Promise(resolve => server.httpServer.close(resolve));
}
