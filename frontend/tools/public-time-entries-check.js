import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { chromium, request } from 'playwright';
import { preview } from 'vite';
import { requireIsolatedApi } from './fixtures/isolated-api.js';
import { fixtureResponse } from './fixtures/verification.js';

const api = requireIsolatedApi({ requireIsolatedFlag: true });
const port = Number(process.env.E2E_FRONTEND_PORT || 5199), base = `http://127.0.0.1:${port}`;
let server, browser, external;
try {
  server = await preview({ preview: { port, host: '127.0.0.1', strictPort: true, proxy: { '/api': api } } });
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const context = await browser.newContext({ locale: 'zh-TW', timezoneId: 'Asia/Taipei', reducedMotion: 'reduce' });
  const page = await context.newPage(), errors = [];
  page.setDefaultTimeout(15000);
  page.on('pageerror', error => errors.push(error.message));
  async function session(path, input) {
    const csrf = await (await context.request.get(base + '/api/csrf-cookie')).json();
    const response = await context.request.post(base + path, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    const result = await fixtureResponse(context.request, response, input);
    assert.equal(result.ok, true, 'Synthetic setup must succeed');
    return result.data;
  }
  await session('/api/auth/register', { name: 'API工時測試者', email: `public-time-${crypto.randomUUID()}@example.test`, password: 'isolated-public-time-browser', workspaceName: '工時API測試' });
  const project = await session('/api/rpc/createProject', { name: 'API工時專案', code: 'PTIME' });
  const issue = await session('/api/rpc/createIssue', { projectId: project.id, title: 'PAT填報工作' });
  const token = await session('/api/v1/personal-tokens', { name: 'isolated browser', abilities: ['write'] });
  // Independent client carries only the synthetic PAT, no browser cookies or CSRF.
  external = await request.newContext({ baseURL: base, extraHTTPHeaders: { Authorization: `Bearer ${token.plainTextToken}` } });
  const path = `/api/public/v1/projects/${project.id}/time-entries`;
  const day = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Taipei', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
  async function send(method, target, data) {
    const response = await external.fetch(target, { method, data });
    assert.equal(response.headers()['set-cookie'], undefined);
    return { status: response.status(), ...await response.json() };
  }
  const race = await Promise.all([1, 2].map(() => send('POST', path, { requestId: crypto.randomUUID(), issueId: issue.id, workDate: day, minutes: 800 })));
  assert.deepEqual(race.map(row => row.status).sort(), [200, 400]);
  const first = race.find(row => row.ok).data;
  assert.equal((await send('POST', `${path}/${first.id}/void`, { requestId: crypto.randomUUID(), expectedRevision: first.revision })).status, 200);
  const input = { requestId: crypto.randomUUID(), issueId: issue.id, workDate: day, minutes: 30, note: 'Synthetic PAT work' };
  const created = await send('POST', path, input);
  assert.equal(created.status, 200);
  assert.deepEqual((await send('POST', path, input)).data, created.data);
  const listed = await send('GET', path);
  assert.equal(listed.data.items[0].id, created.data.id);
  assert.equal(listed.data.items[0].note, input.note);
  const changed = await send('PATCH', `${path}/${created.data.id}`, { requestId: crypto.randomUUID(), expectedRevision: created.data.revision, workDate: day, minutes: 60, note: 'Updated PAT work' });
  assert.equal(changed.status, 200);
  mkdirSync('artifacts/public-time', { recursive: true });
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1000 });
    await page.goto(`${base}/app/?capture=${width}#/projects/${project.id}/time`);
    await page.getByRole('button', { name: `${day} 工時 1 h`, exact: true }).waitFor();
    await page.evaluate(() => document.fonts.ready);
    if (width === 390) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({ path: `artifacts/public-time/calendar-${width}.png`, fullPage: true });
  }
  assert.equal((await send('POST', `${path}/${created.data.id}/void`, { requestId: crypto.randomUUID(), expectedRevision: changed.data.revision })).status, 200);
  assert.equal((await send('GET', path)).data.total, 0);
  await page.reload();
  await page.getByRole('button', { name: `${day} 工時 0 h`, exact: true }).waitFor();
  const example = await promisify(execFile)(process.env.PYTHON_BINARY || 'python3', ['../backend/resources/developer-docs/workflow.py'], { env: { ...process.env, BASE_URL: base, TASKTRACKER_TOKEN: token.plainTextToken, PROJECT_ID: project.id, CONFIRM_WRITE: '1', WORK_DATE: day }, encoding: 'utf8', timeout: 60000 });
  assert.match(example.stdout, /PASS/);
  assert.deepEqual(errors, []);
  writeFileSync('artifacts/public-time/result.json', JSON.stringify({ passed: true, publicDailyLimitRace: true, replayOnce: true, webSeesUpdatedMinutes: 60, voidExcluded: true, widths: [1440, 768, 390], pythonExample: true, errors }, null, 2));
  console.log('PASS public PAT time lifecycle, daily-limit race, web calendar, three widths and Python example');
} finally {
  await external?.dispose();
  await browser?.close();
  await new Promise(resolve => server ? server.httpServer.close(resolve) : resolve());
}
