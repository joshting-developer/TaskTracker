import assert from 'node:assert/strict';
import { preview } from 'vite';
import { chromium } from 'playwright';
// TT-256：大型看板初次掛載量測。production build、CDP CPU節流、真實API與100筆分頁；A／B兩份build交錯取樣。
// MOUNT_DIST_A／MOUNT_DIST_B為build輸出目錄（未給B時只量A）。只連隔離E2E服務。
if (!process.env.E2E_API_URL) throw new Error('必須指定隔離E2E_API_URL');
const root = new URL('..', import.meta.url).pathname;
const CARDS = Number(process.env.MOUNT_CARDS || 500), CPU = Number(process.env.MOUNT_CPU || 4), SAMPLES = Number(process.env.MOUNT_SAMPLES || 3);
const variants = [['A', process.env.MOUNT_DIST_A || `${root}dist`], ...(process.env.MOUNT_DIST_B ? [['B', process.env.MOUNT_DIST_B]] : [])];
let browser;
const median = values => { const sorted = [...values].sort((a, b) => a - b); return sorted[Math.floor(sorted.length / 2)]; };
async function serve(outDir) { return preview({ root, build: { outDir }, preview: { host: '127.0.0.1', port: 5175, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); }
try {
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage(), errors = [];
  page.setDefaultTimeout(60000);
  page.on('pageerror', error => errors.push(error.message));
  let server = await serve(variants[0][1]);
  await page.goto('http://127.0.0.1:5175/login');
  async function call(op, input = {}, group = 'rpc') {
    return page.evaluate(async ({ op, input, group }) => {
      const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
      const result = await (await fetch(`/api/${group}/${op}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ requestId: crypto.randomUUID(), ...input }) })).json();
      if (!result.ok) throw new Error(`${op}: ${result.error.message}`);
      return result.data;
    }, { op, input, group });
  }
  const identity = await call('register', { name: 'Mount Owner', email: `mount-${crypto.randomUUID()}@example.test`, password: 'isolated-test-password', workspaceName: 'Mount' }, 'auth');
  const project = await call('createProject', { name: 'Mount', code: 'MNT' });
  const epics = [];
  for (let n = 0; n < 5; n++) epics.push(await call('createIssue', { projectId: project.id, title: `Epic ${n + 1}`, type: 'epic' }));
  for (let n = 0; n < CARDS - 5; n++) await call('createIssue', { projectId: project.id, title: `Work ${String(n + 1).padStart(3, '0')} ${n % 3 ? '工作卡片' : '較長的卡片標題與說明文字用來驗證換行與捲動行為'}`, epicId: epics[n % 5].id, state: ['open', 'inprogress', 'closed'][n % 3], assignee: n % 2 ? identity.user.id : '', estimateMinutes: n % 4 * 30 });
  await page.addInitScript(() => {
    window.mount = { longTasks: [], first: 0, done: 0 };
    new PerformanceObserver(list => window.mount.longTasks.push(...list.getEntries().map(item => ({ start: item.startTime, duration: item.duration })))).observe({ type: 'longtask', buffered: true });
  });
  const cdp = await context.newCDPSession(page);
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: CPU });
  const url = `http://127.0.0.1:5175/app/#/projects/${project.id}/board`;
  const samples = [];
  for (let round = 0; round < SAMPLES; round++) {
    for (const [name, outDir] of variants) {
      if (variants.length > 1 || round > 0) { await server.close(); server = await serve(outDir); }
      await page.goto(url); await page.reload();
      // 量到全部卡片出現；API完成時間取最後一個卡片頁responseEnd。
      const result = await page.evaluate(total => new Promise(resolve => {
        const check = () => {
          const count = document.querySelectorAll('.issue-card').length;
          if (count && !window.mount.first) window.mount.first = performance.now();
          // 分批中欄位標題仍顯示完整數量。
          if (count && count < total && !window.mount.headers) window.mount.headers = [...document.querySelectorAll('.column-header b')].reduce((sum, node) => sum + Number(node.textContent), 0);
          if (count === total) {
            requestAnimationFrame(() => setTimeout(() => {
              const done = performance.now();
              const apiDone = Math.max(...performance.getEntriesByType('resource').filter(item => /\/boards\/[^/]+\/issues/.test(item.name)).map(item => item.responseEnd));
              const after = window.mount.longTasks.filter(task => task.start + task.duration > apiDone && task.start < done);
              resolve({ headerTotal: window.mount.headers ?? total, apiDoneMs: Math.round(apiDone), firstCardMs: Math.round(window.mount.first), allCardsMs: Math.round(done), renderMs: Math.round(done - apiDone),
                maxLongTaskMs: Math.round(Math.max(0, ...after.map(task => task.duration))), longTaskSumMs: Math.round(after.reduce((sum, task) => sum + task.duration, 0)), longTasks: after.length });
            }, 0));
          } else requestAnimationFrame(check);
        };
        check();
      }), CARDS);
      assert.equal(result.headerTotal, CARDS, `${name}分批中欄位計數為完整數量`);
      samples.push({ variant: name, round, ...result });
      console.log(name, round, JSON.stringify(result));
    }
  }
  const summary = Object.fromEntries(variants.map(([name]) => {
    const rows = samples.filter(item => item.variant === name);
    return [name, Object.fromEntries(['apiDoneMs', 'firstCardMs', 'allCardsMs', 'renderMs', 'maxLongTaskMs', 'longTaskSumMs', 'longTasks'].map(key => [key, median(rows.map(item => item[key]))]))];
  }));
  assert.deepEqual(errors, []);
  console.log('board mount', JSON.stringify({ cards: CARDS, cpu: CPU, samples: SAMPLES, median: summary }));
  await server.close();
} finally {
  await browser?.close();
}
