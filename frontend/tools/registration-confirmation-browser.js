import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fixtureResponse } from './fixtures/verification.js';
assert.equal(process.env.E2E_ISOLATED, '1');
assert.match(process.env.E2E_API_URL || '', /^http:\/\/127\.0\.0\.1:13\d{3}$/);
const phase = process.env.E2E_CAPTURE_PHASE || 'after', root = new URL('../../', import.meta.url), folder = new URL(`artifacts/tt394/${phase}/`, root);
mkdirSync(folder, { recursive: true });
let server, browser;
try {
 const port = Number(process.env.E2E_FRONTEND_PORT || 5199), base = `http://127.0.0.1:${port}`;
 server = await createServer({ root: new URL('../', import.meta.url).pathname, plugins: [{ name:'tt394-before', load(id) {
  if (phase === 'before' && id.endsWith('/src/components/AuthGate.vue')) return execFileSync('git',['show','e8468fe:frontend/src/components/AuthGate.vue'],{cwd:root,encoding:'utf8'});
 }}], server:{host:'127.0.0.1',port,strictPort:true,proxy:{'/api':process.env.E2E_API_URL}} }); await server.listen();
 browser = await chromium.launch({headless:true}); const page = await browser.newPage({locale:'zh-TW',viewport:{width:1440,height:1000}});
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 for(const locale of ['zh-TW','en']) {
  await page.goto(base+'/login?mode=register');await page.locator('input[autocomplete="name"]').waitFor();await page.locator('.locale-picker select').selectOption(locale);
  assert.equal(await page.locator('input[autocomplete="new-password"]').count(),phase==='before'?1:2);
  for(const width of [1440,768,390]) {
   await page.setViewportSize({width,height:1000});await page.waitForLoadState('networkidle');await page.evaluate(async()=>{await document.fonts.ready;document.activeElement?.blur();await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));});
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
   await page.screenshot({path:new URL(`register-${locale}-${width}.png`,folder).pathname,fullPage:true,animations:'disabled',caret:'hide'});
  }
 }
 if(phase==='after') {
  await page.locator('.locale-picker select').selectOption('zh-TW');
  await page.getByLabel('你的名字',{exact:true}).fill('確認密碼驗證');await page.getByLabel('Email',{exact:true}).fill(`confirm-${crypto.randomUUID()}@example.test`);
  await page.getByLabel('密碼',{exact:true}).fill('synthetic-confirm-password');await page.getByLabel('確認密碼',{exact:true}).fill('synthetic-mismatch-password');await page.getByLabel('工作空間名稱',{exact:true}).fill('確認密碼驗證');
  let registrations=0;page.on('request',r=>{if(new URL(r.url()).pathname==='/api/auth/register')registrations++;});
  await page.getByRole('button',{name:'建立工作空間',exact:true}).click();await page.getByRole('alert').getByText('兩次密碼不一致',{exact:true}).waitFor();assert.equal(registrations,0);
  await page.getByLabel('確認密碼',{exact:true}).fill('synthetic-confirm-password');
  const response=page.waitForResponse(r=>new URL(r.url()).pathname==='/api/auth/register');await page.getByRole('button',{name:'建立工作空間',exact:true}).click();const registered=await response;assert.equal(registered.status(),200);assert.equal(registrations,1);await fixtureResponse(page.request,registered);
  await page.goto(base+'/app/');await page.locator('.app-shell').waitFor();
  const csrf=await(await page.request.get(base+'/api/csrf-cookie')).json();assert.equal((await page.request.post(base+'/api/auth/logout',{headers:{'X-CSRF-TOKEN':csrf.csrfToken},data:{}})).status(),200);
  await page.goto(base+'/login');await page.locator('input[autocomplete="current-password"]').waitFor();assert.equal(await page.getByLabel('確認密碼',{exact:true}).count(),0);
 }
 assert.deepEqual(errors,[]);writeFileSync(new URL('result.json',folder),JSON.stringify({phase,screenshots:6,errors,mismatchBlocksRequest:phase==='after',realRegistration:phase==='after'}));console.log('PASS registration confirmation: bilingual responsive form, mismatch blocks request, matching registration and unchanged login');
} finally {await browser?.close();await server?.close();}
