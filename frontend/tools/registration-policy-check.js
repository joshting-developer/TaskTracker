import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium, request } from 'playwright';
import { fixtureResponse } from './fixtures/verification.js';
if(process.env.E2E_ISOLATED!=='1'||!process.env.E2E_API_URL||!process.env.E2E_SETUP_API_URL)throw new Error('Isolated open and invitation-only APIs required');
let server,browser,setup;
try {
  server=await createServer({root:new URL('..',import.meta.url).pathname,server:{host:'127.0.0.1',port:5292,strictPort:true,proxy:{'/api':process.env.E2E_API_URL}}});await server.listen();
  browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH});
  setup=await request.newContext({baseURL:process.env.E2E_SETUP_API_URL,extraHTTPHeaders:{Origin:'http://127.0.0.1:5291'}});
  async function call(op,input,group='rpc') {const csrf=await(await setup.get('/api/csrf-cookie')).json();const response=await setup.post(`/api/${group}/${op}`,{headers:{'X-CSRF-TOKEN':csrf.csrfToken},data:{requestId:crypto.randomUUID(),...input}});const result=await fixtureResponse(setup,response);assert.equal(result.ok,true);return result.data;}
  const email=`policy-owner-${crypto.randomUUID()}@example.test`,password='isolated-policy-password';
  await call('register',{name:'Policy Owner',email,password,workspaceName:'Policy Lab'},'auth');
  const invite=await call('inviteMember',{email:`invite-${crypto.randomUUID()}@example.test`});
  const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:5292/login?mode=register');
  await page.getByText('目前僅接受邀請註冊',{exact:true}).waitFor();
  assert.equal(await page.getByLabel('你的名字',{exact:true}).count(),0);
  assert.equal(await page.getByRole('button',{name:'第一次使用？建立工作空間',exact:true}).count(),0);
  assert.equal(await page.getByRole('link',{name:/免費.*建立工作空間/}).count(),0);
  const blocked=await page.evaluate(async()=>{const csrf=await(await fetch('/api/csrf-cookie')).json();const result=await fetch('/api/auth/register',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf.csrfToken},body:JSON.stringify({name:'Denied',email:`denied-${crypto.randomUUID()}@example.test`,password:'isolated-policy-password',workspaceName:'Denied'})});return result.status;});assert.equal(blocked,403);
  const folder=new URL('../../artifacts/tt185/',import.meta.url).pathname;mkdirSync(folder,{recursive:true});
  for(const width of [1440,768,390]){await page.setViewportSize({width,height:1000});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);await page.screenshot({path:`${folder}/invitation-only-${width}.png`});}
  await page.getByLabel('Email',{exact:true}).fill(email);await page.getByLabel('密碼',{exact:true}).fill(password);await page.getByRole('button',{name:'登入',exact:true}).click();await page.locator('.app-shell').waitFor();
  await page.evaluate(async()=>{await(await import('/src/api/index.js')).api.logout();});
  await page.goto(`http://127.0.0.1:5292/login#/invite?token=${invite.token}`);
  await page.getByLabel('Email',{exact:true}).fill(invite.email);await page.getByLabel('你的名字',{exact:true}).fill('Invited Person');await page.getByLabel('密碼',{exact:true}).fill(password);await page.getByRole('button',{name:'加入工作空間',exact:true}).click();
  await page.getByRole('heading',{name:'驗證信箱',exact:true}).waitFor();
  await page.getByLabel('驗證碼',{exact:true}).fill('local666');await page.getByRole('button',{name:'驗證並登入',exact:true}).click();await page.locator('.app-shell').waitFor();
  // Policy failure cannot enable registration or prevent an existing account from seeing login.
  await page.evaluate(async()=>{await(await import('/src/api/index.js')).api.logout();});
  await page.route('**/api/auth/registration-policy',r=>r.abort());await page.goto('http://127.0.0.1:5292/login?mode=register');await page.getByRole('alert').filter({hasText:'無法讀取註冊設定'}).waitFor();
  assert.equal(await page.getByRole('button',{name:'建立工作空間',exact:true}).count(),0);
  await page.unroute('**/api/auth/registration-policy');await page.getByRole('button',{name:'重試',exact:true}).click();await page.getByRole('button',{name:'登入',exact:true}).waitFor();
  assert.deepEqual(errors,[]);console.log('PASS invitation-only UI/API deny, existing login, valid invitation with verification, fail-closed policy retry, 3 widths');
} finally {await setup?.dispose();await browser?.close();await server?.close();}
