import { requireIsolatedApi } from './fixtures/isolated-api.js';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
const e2ePort = Number(process.env.E2E_FRONTEND_PORT || 5175); // TT-335：Claude 5198、Codex 5199, 見.agents/skills/tasktracker-test-environments
requireIsolatedApi(); // TT-336：PAT測試只打隔離後端
let server, browser;
try {
  server = await preview({ preview: { host: '127.0.0.1', port: e2ePort, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const errors = []; page.on('pageerror', error => errors.push(error.message));
  await page.goto(`http://127.0.0.1:${e2ePort}/login`);
  await page.evaluate(async () => {
    const { csrfToken } = await (await fetch('/api/csrf-cookie')).json();
    const r = await fetch('/api/auth/register', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify({ name: 'PAT User', email: `pat-${crypto.randomUUID()}@example.test`, password: 'pat-browser-password', workspaceName: 'Token Workspace' }) });
    if (!r.ok) throw new Error('fixture registration failed');
    const csrf = await (await fetch('/api/csrf-cookie')).json();
    const verified = await fetch('/api/auth/verify-email', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: JSON.stringify({ code: 'local666' }) });
    if (!verified.ok) throw new Error('fixture verification failed');
  });
  // Laravel13接受瀏覽器可信same-origin Fetch Metadata；以非瀏覽器缺Token請求驗CSRF拒絕。
  const csrfMissing = await page.request.post(`http://127.0.0.1:${e2ePort}/api/v1/personal-tokens`, { data: {}, headers: { Accept: 'application/json' } });
  assert.equal(csrfMissing.status(), 419);
  const spec = await page.evaluate(async () => (await (await fetch('/api/public/openapi.json')).json()));
  assert.equal(spec.openapi, '3.1.0');
  // TT-336：公開規格要跟repo的正式OpenAPI一致, 不寫死路徑數（API擴充後原本的11已過時）
  const source = JSON.parse(readFileSync(new URL('../../backend/resources/openapi.json', import.meta.url), 'utf8'));
  assert.deepEqual(Object.keys(spec.paths).sort(), Object.keys(source.paths).sort());
  await page.reload();
  async function open() {
    await page.getByRole('button', { name: '帳號選單', exact: true }).click();
    await page.getByRole('menuitem', { name: '個人 API Token', exact: true }).click();
    await page.getByRole('dialog', { name: '個人 API Token', exact: true }).waitFor();
  }
  await open();
  const dialog = page.getByRole('dialog', { name: '個人 API Token', exact: true });
  await dialog.getByLabel('名稱', { exact: true }).fill('CLI tool');
  await dialog.getByLabel('Scope', { exact: true }).selectOption('write');
  await dialog.getByRole('button', { name: '建立 Token', exact: true }).click();
  const secret = dialog.getByLabel('Token', { exact: true });
  await secret.waitFor();
  const plain = await secret.inputValue();
  assert.match(plain, /^\d+\|tt_/);
  const me = await page.evaluate(async token => {
    const r = await fetch('/api/public/v1/me', { headers: { Authorization: `Bearer ${token}` } });
    const body = await r.json(); return { status: r.status, abilities: body.data?.abilities };
  }, plain);
  assert.equal(me.status, 200); assert.deepEqual(me.abilities, ['write']);
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    assert(await dialog.evaluate(el => { const b = el.getBoundingClientRect(); return b.left >= 0 && b.right <= innerWidth && b.bottom <= innerHeight; }));
  }
  await dialog.getByRole('button', { name: '關閉', exact: true }).click();
  await open();
  await dialog.getByRole('list', { name: 'Token清單' }).getByText('CLI tool', { exact: true }).waitFor();
  assert.equal(await dialog.getByLabel('Token', { exact: true }).count(), 0);
  await page.screenshot({ path: '/tmp/tt165-token-list.png' });
  const listed = await page.evaluate(async () => JSON.stringify(await (await fetch('/api/v1/personal-tokens')).json()));
  assert(!listed.includes(plain));
  page.once('dialog', d => d.accept());
  await dialog.getByRole('button', { name: '撤銷', exact: true }).click();
  await dialog.getByText(/已撤銷/).waitFor();
  assert.equal(await page.evaluate(async token => (await fetch('/api/public/v1/me', { headers: { Authorization: `Bearer ${token}` } })).status, plain), 401);
  const audit = await page.evaluate(async () => (await (await fetch('/api/v1/personal-token-audits')).json()).data);
  assert.deepEqual(audit.items.map(row => row.operation), ['personalTokens.revoke', 'personalTokens.create']);
  assert(audit.items.every(row => row.source === 'session' && row.tokenId === null));
  assert(!JSON.stringify(audit).includes(plain));
  const leaks = await page.evaluate(token => [...Object.values(localStorage), ...Object.values(sessionStorage)].some(v => v.includes(token)), plain);
  assert.equal(leaks, false);
  assert.deepEqual(errors, []);
  console.log('PASS: 帳號選單、PAT建立一次顯示、Bearer身分、重開不洩漏、撤銷401、無storage秘密、三尺寸、Session來源稽核');
} finally {
  await browser?.close();
  await new Promise(resolve => server ? server.httpServer.close(resolve) : resolve());
}
