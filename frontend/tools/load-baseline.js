import assert from 'node:assert/strict';
import { request, chromium } from 'playwright';
// TT-184：對正式runtime容器（Nginx＋PHP-FPM多worker＋隔離MySQL）量測並行讀取延遲、寫入正確性、冷啟動與弱網頁面。
// 只連隔離測試服務；數值是本機Docker實驗室樣本，不是正式部署容量或SLO。
const base = process.env.PROD_BASE_URL;
if (!base) throw new Error('必須指定隔離正式runtime的PROD_BASE_URL');
const LEVELS = (process.env.LOAD_LEVELS || '1,4,8,16').split(',').map(Number);
const SECONDS = Number(process.env.LOAD_SECONDS || 20), CARDS = Number(process.env.LOAD_CARDS || 500);
const percentile = (values, p) => { const sorted = [...values].sort((a, b) => a - b); return sorted.length ? Math.round(sorted[Math.min(sorted.length - 1, Math.floor(sorted.length * p))]) : 0; };

async function session(credentials) {
  const api = await request.newContext({ baseURL: base, extraHTTPHeaders: { Accept: 'application/json', Origin: base } });
  let csrf = (await (await api.get('/api/csrf-cookie')).json()).csrfToken;
  async function post(path, data) {
    const response = await api.post(path, { data: { requestId: crypto.randomUUID(), ...data }, headers: { 'X-CSRF-TOKEN': csrf } });
    csrf = response.headers()['x-csrf-token'] || csrf;
    return { status: response.status(), body: await response.json() };
  }
  let login = await post(credentials.register ? '/api/auth/register' : '/api/auth/login', credentials.register || credentials);
  assert.equal(login.status, 200, `登入失敗：${JSON.stringify(login.body)}`);
  if (login.body.data.verificationRequired) login = await post('/api/auth/verify-email', { code: 'local666' });
  const workspace = login.body.data.workspace.id;
  return { api, post, rpc: (op, input) => post(`/api/rpc/${op}`, input), get: path => api.get(path, { headers: { 'X-Workspace-ID': workspace } }), identity: login.body.data };
}

const email = `load-${crypto.randomUUID()}@example.test`, password = 'isolated-test-password';
const owner = await session({ register: { name: 'Load Owner', email, password, workspaceName: 'Load' } });
const project = (await owner.rpc('createProject', { name: 'Load', code: 'LOAD' })).body.data;
const epics = [];
for (let n = 0; n < 5; n++) epics.push((await owner.rpc('createIssue', { projectId: project.id, title: `Epic ${n}`, type: 'epic' })).body.data);
const cards = [];
for (let n = 0; n < CARDS - 5; n++) cards.push((await owner.rpc('createIssue', { projectId: project.id, title: `Load ${n}`, description: 'x'.repeat(300), epicId: epics[n % 5].id, state: ['open', 'inprogress', 'closed'][n % 3] })).body.data);
const p = `/api/v1/projects/${project.id}`;
const reads = [`/api/v1/personal-version`, `${p}/boards/default/version`, `${p}/issues?page=1&perPage=20`, `${p}/boards/default/issues?page=1&perPage=100`, `${p}/issues/${cards[0].id}`, p];
const results = { cards: CARDS, seconds: SECONDS, fpmChildren: Number(process.env.LOAD_FPM_CHILDREN || 6), levels: [] };

// 1. 並行讀取：每個虛擬使用者一個獨立Session，依序循環讀取混合endpoint；先暖機5秒不計。
const users = await Promise.all(Array.from({ length: Math.max(...LEVELS) }, () => session({ email, password })));
async function run(level, seconds, record) {
  const latencies = [], perPath = {}; let errors = 0, total = 0;
  const end = Date.now() + seconds * 1000;
  await Promise.all(users.slice(0, level).map(async (user, index) => {
    let n = index;
    while (Date.now() < end) {
      const path = reads[n++ % reads.length], start = performance.now();
      const response = await user.get(path);
      const ms = performance.now() - start;
      total++;
      if (!response.ok()) errors++;
      else if (!(await response.json()).ok) errors++;
      latencies.push(ms);
      (perPath[path.replace(/[0-9a-f-]{36}/g, ':id').split('?')[0]] ||= []).push(ms);
    }
  }));
  if (record) results.levels.push({ concurrency: level, requests: total, rps: Math.round(total / seconds), errors, p50: percentile(latencies, 0.5), p95: percentile(latencies, 0.95), p99: percentile(latencies, 0.99),
    p95ByPath: Object.fromEntries(Object.entries(perPath).map(([key, values]) => [key, percentile(values, 0.95)])) });
}
await run(Math.max(...LEVELS), 5, false);
for (const level of LEVELS) { await run(level, SECONDS, true); console.log('level', JSON.stringify(results.levels.at(-1))); }

// 2. 寫入正確性：同卡同revision並行更新只能一筆成功，其餘409；最後revision等於成功次數。
const target = cards[1];
let revision = target.revision, successes = 0, conflicts = 0, other = 0;
for (let round = 0; round < 5; round++) {
  const responses = await Promise.all(users.slice(0, 8).map((user, index) => user.rpc('updateIssue', { id: target.id, title: `round ${round} user ${index}`, expectedRevision: revision })));
  for (const response of responses) response.status === 200 ? successes++ : response.status === 409 ? conflicts++ : other++;
  assert.equal(responses.filter(response => response.status === 200).length, 1, '同revision只能一筆成功');
  revision = responses.find(response => response.status === 200).body.data.revision;
}
const final = (await (await owner.get(`${p}/issues/${target.id}`)).json()).data;
assert.equal(final.revision, target.revision + successes, '沒有遺失更新');
// 並行建卡：識別碼不重複且全部成功。
const created = await Promise.all(users.slice(0, 8).flatMap(user => [0, 1, 2].map(n => user.rpc('createIssue', { projectId: project.id, title: `parallel ${n}` }))));
assert.ok(created.every(response => response.status === 200), '並行建卡全部成功');
assert.equal(new Set(created.map(response => response.body.data.identifier)).size, created.length, '並行建卡識別碼不重複');
results.writes = { contention: { rounds: 5, writers: 8, successes, conflicts, other }, parallelCreates: created.length };

// 3. 冷啟動：由外部重啟容器後量第一個與後續請求（LOAD_COLD=1時由呼叫端處理重啟）。
if (process.env.LOAD_COLD_RESTART_CMD) {
  const { execSync } = await import('node:child_process');
  const stopStart = performance.now();
  execSync(process.env.LOAD_COLD_RESTART_CMD, { stdio: 'ignore' });
  let ready = false;
  while (!ready) { try { ready = (await fetch(`${base}/api/health`)).ok; } catch { await new Promise(resolve => setTimeout(resolve, 100)); } }
  const readyMs = performance.now() - stopStart;
  const cold = await session({ email, password });
  const timings = [];
  for (let n = 0; n < 6; n++) { const start = performance.now(); await cold.get(`${p}/boards/default/issues?page=1&perPage=100`); timings.push(Math.round(performance.now() - start)); }
  results.cold = { restartToHealthMs: Math.round(readyMs), firstBoardPageMs: timings[0], warmBoardPageMs: percentile(timings.slice(1), 0.5) };
}

// 4. 弱網頁面：Chrome Slow 4G（RTT 150 ms、1.6 Mbps下行），冷快取與暖快取到500卡全部出現。
const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();
await page.goto(new URL('/login', base).href);
await page.evaluate(async ({ email, password }) => {
  const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
  await fetch('/api/auth/login', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ email, password }) });
}, { email, password });
const cdp = await context.newCDPSession(page);
await cdp.send('Network.enable');
await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: 1.6 * 1024 * 1024 / 8, uploadThroughput: 750 * 1024 / 8 });
const total = CARDS + created.length;
async function boardLoad(cache) {
  await cdp.send('Network.setCacheDisabled', { cacheDisabled: !cache });
  const start = Date.now();
  await page.goto(`${base}/#/projects/${project.id}/board`);
  await page.reload();
  try { await page.waitForFunction(count => document.querySelectorAll('.issue-card').length === count, total, { timeout: 120000 }); }
  catch (failure) { console.log('DEBUG', total, await page.evaluate(() => ({ cards: document.querySelectorAll('.issue-card').length, hash: location.hash, text: document.body.innerText.slice(0, 200) }))); throw failure; }
  const firstCard = await page.evaluate(() => performance.getEntriesByType('resource').filter(item => /\/boards\/[^/]+\/issues/.test(item.name)).map(item => Math.round(item.responseEnd))[0]);
  return { allCardsMs: Date.now() - start, firstCardPageMs: firstCard };
}
results.weakNetwork = { profile: 'RTT150ms/1.6Mbps down/750kbps up', cold: await boardLoad(false), warm: await boardLoad(true) };
await browser.close();
for (const user of [owner, ...users]) await user.api.dispose();
console.log('load baseline', JSON.stringify(results));
