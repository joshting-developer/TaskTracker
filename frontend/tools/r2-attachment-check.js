import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
if (process.env.E2E_ISOLATED !== '1' || process.env.E2E_API_URL !== 'http://127.0.0.1:13089') throw Error('Only the owned isolated MySQL/R2 backend is supported');
const port = Number(process.env.E2E_FRONTEND_PORT || 5291), base = `http://127.0.0.1:${port}`;
const artifact = new URL('../../artifacts/tt291/', import.meta.url); mkdirSync(artifact, { recursive: true });
let vite, browser, project, page;
try {
  vite = await createServer({ root: new URL('../', import.meta.url).pathname, server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await vite.listen();
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const context = await browser.newContext(); page = await context.newPage(); page.setDefaultTimeout(45000);
  const external = []; page.on('request', request => { if (request.url().includes('cloudflarestorage')) external.push(request.url()); });
  async function call(operation, input) {
    const csrf = await (await context.request.get(`${base}/api/csrf-cookie`)).json();
    const response = await context.request.post(`${base}/api/${operation === 'register' ? 'auth' : 'rpc'}/${operation}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...input } });
    assert.equal(response.status(), 200); return (await response.json()).data;
  }
  await call('register', { name: 'R2瀏覽器驗證', email: `r2-${crypto.randomUUID()}@example.test`, password: 'isolated-r2-browser', workspaceName: 'R2圖片' });
  project = await call('createProject', { name: 'R2驗證', code: 'R2B' });
  await page.setContent('<body style="margin:0;padding:24px;font:18px system-ui;background:white"><h1>R2圖片</h1><p>合成附件內容</p></body>');
  await page.setViewportSize({ width: 320, height: 160 }); const png = await page.screenshot();
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(`${base}/app/#/projects/${project.id}`);
  await page.getByRole('button', { name: '新增任務', exact: false }).first().click();
  const editor = page.getByRole('dialog').first();
  await editor.getByLabel(/任務標題/).fill('R2附件測試');
  const uploading = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === `/api/v1/projects/${project.id}/attachments`);
  await editor.getByLabel('上傳描述圖片', { exact: true }).setInputFiles({ name: 'r2-browser.png', mimeType: 'image/png', buffer: png });
  assert.equal((await uploading).status(), 200, 'R2 upload response');
  await page.waitForFunction(() => document.querySelector('textarea[name=description]')?.value.includes('attachment:'));
  await editor.getByRole('tab', { name: '預覽', exact: true }).first().click();
  await editor.locator('.editor-preview .image-preview img').waitFor();
  await editor.getByRole('button', { name: '建立任務', exact: true }).click(); await editor.waitFor({ state: 'hidden' });
  await page.getByRole('button', { name: 'R2附件測試', exact: true }).click();
  await page.locator('.issue-detail-description .image-preview img').waitFor();
  await page.reload();
  const image = page.locator('.issue-detail-description .image-preview img').first(); await image.waitFor();
  assert.ok(await image.evaluate(el => el.complete && el.naturalWidth === 320));
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1000 });
    await page.waitForFunction(() => innerWidth > 700 || document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
    await page.screenshot({ path: new URL(`r2-detail-${width}.png`, artifact).pathname, animations: 'disabled' });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  }
  assert.equal(external.length, 0, 'Browser only sees authorized application streams, never R2 credentials/URLs');
  console.log('PASS: real Chrome image input, Markdown preview, atomic card creation and reload from private R2, three widths, no direct R2 requests');
} catch (error) {
  await page?.screenshot({ path: new URL('browser-failure.png', artifact).pathname }).catch(() => {});
  throw error;
} finally {
  await browser?.close(); await vite?.close();
  if (project) {
    assert.match(project.id, /^[a-f0-9-]{36}$/);
    const php = String.raw`require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
      if(!$app->environment('testing')||getenv('TASKTRACKER_MYSQL_TEST')!=='1'||config('database.connections.mysql.database')!=='tasktracker_test'||config('database.connections.mysql.username')!=='tasktracker_test'||config('filesystems.disks.r2-attachments.bucket')!=='task-tracker-local')throw new RuntimeException('Isolation required');
      $p=Illuminate\Support\Facades\DB::table('projects')->where('public_id','${project.id}')->sole();
      foreach(App\Models\Attachment::where('project_id',$p->id)->get() as $row){if($row->storage_disk!=='r2-attachments')throw new RuntimeException('Unexpected disk');if(!Illuminate\Support\Facades\Storage::disk($row->storage_disk)->delete($row->storage_key))throw new RuntimeException('Cleanup failed');$row->update(['status'=>'purged','storage_key'=>'','expires_at'=>null]);}`;
    execFileSync('docker', ['compose', '-p', 'tasktracker-codex-perf', '-f', 'docker-compose.mysql-test.yml', '-f', '/tmp/tt191-compose.yml', '-f', '/tmp/tt291-compose.yml', 'exec', '-T', 'backend', 'php', '-r', php], { cwd: new URL('../../', import.meta.url).pathname, env: { ...process.env, TASKTRACKER_TEST_PORT: '13089' }, stdio: 'pipe' });
    console.log('PASS: only this browser project R2 objects cleaned');
  }
}
