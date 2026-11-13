import { fixtureResponse } from './fixtures/verification.js';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createServer } from 'vite';
import { chromium } from 'playwright';
if (process.env.E2E_ISOLATED !== '1' || process.env.E2E_API_URL !== 'http://127.0.0.1:13089') throw Error('Only the owned isolated MySQL backend is supported');
const port = Number(process.env.E2E_FRONTEND_PORT || 5291), base = `http://127.0.0.1:${port}`;
let vite, browser;
try {
  vite = await createServer({ root: new URL('../', import.meta.url).pathname, server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  await vite.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const context = await browser.newContext(), page = await context.newPage(); page.setDefaultTimeout(30000);
  async function call(operation, input) {
    const csrf = await (await context.request.get(`${base}/api/csrf-cookie`)).json();
    const response = await context.request.post(`${base}/api/${operation === 'register' ? 'auth' : 'rpc'}/${operation}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), 200); return (await fixtureResponse(context.request, response)).data;
  }
  await call('register', { name: '分頁驗證', email: `pages-${crypto.randomUUID()}@example.test`, password: 'isolated-pagination-password', workspaceName: '分頁測試' });
  const project = await call('createProject', { name: '分頁專案', code: 'PAGE' });
  await call('createIssue', { projectId: project.id, title: '合成分頁資料' });
  // The random project belongs to this test alone; never reset a business database.
  assert.match(project.id, /^[a-f0-9-]{36}$/);
  const php = `require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
    if(!$app->environment('testing')||getenv('TASKTRACKER_MYSQL_TEST')!=='1'||config('database.connections.mysql.database')!=='tasktracker_test'||config('database.connections.mysql.username')!=='tasktracker_test')throw new RuntimeException('Isolation required');
    $project=Illuminate\\Support\\Facades\\DB::table('projects')->where('public_id','${project.id}')->sole();
    $row=(array)Illuminate\\Support\\Facades\\DB::table('issues')->where('project_id',$project->id)->sole(); unset($row['id']);
    for($first=2;$first<=10025;$first+=500){$batch=[];for($id=$first;$id<=min(10025,$first+499);$id++)$batch[]=[...$row,'public_id'=>(string)Illuminate\\Support\\Str::uuid(),'identifier'=>'PAGE-'.$id,'storage_position'=>$id];Illuminate\\Support\\Facades\\DB::table('issues')->insert($batch);}`;
  execFileSync('docker', ['compose', '-p', 'tasktracker-codex-perf', '-f', 'docker-compose.mysql-test.yml', '-f', '/tmp/tt191-compose.yml', 'exec', '-T', 'backend', 'php', '-r', php], { cwd: new URL('../../', import.meta.url).pathname, env: { ...process.env, TASKTRACKER_TEST_PORT: '13089' }, stdio: 'pipe' });
  await page.goto(`${base}/app/#/projects/${project.id}`);
  await page.getByText('10025 筆結果', { exact: false }).waitFor();
  await page.getByText('1 / 501', { exact: true }).waitFor();
  await page.getByText('請縮小篩選範圍以查看其餘結果', { exact: false }).waitFor();
  assert.equal(await page.locator('.issue-table tbody tr').count(), 20);
  await page.getByRole('button', { name: '下一頁', exact: true }).click();
  await page.getByText('2 / 501', { exact: true }).waitFor();
  const boundary = await context.request.get(`${base}/api/v1/projects/${project.id}/issues?perPage=20&page=501`);
  assert.equal(boundary.status(), 200); const data = (await boundary.json()).data;
  assert.equal(data.total, 10025); assert.equal(data.lastPage, 502); assert.equal(data.accessibleLastPage, 501); assert.equal(data.items.length, 20);
  assert.equal((await context.request.get(`${base}/api/v1/projects/${project.id}/issues?perPage=20&page=502`)).status(), 400);
  console.log('PASS: real isolated 10025-row MySQL list, honest 502 total pages / 501 accessible, bounded 20 DOM rows, next-page navigation and rejected deep OFFSET');
} finally { await browser?.close(); await vite?.close(); }
