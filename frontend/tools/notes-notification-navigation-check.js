import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { fixtureResponse } from './fixtures/verification.js';
const phase=process.argv[2] || 'after';
assert.ok(['before','after'].includes(phase));
assert.equal(process.env.E2E_ISOLATED,'1');
assert.equal(process.env.E2E_API_URL,'http://127.0.0.1:13199');
let server,browser;
const root=new URL('../../artifacts/tt353/',import.meta.url);
mkdirSync(root,{recursive:true});
try {
 server=await createServer({server:{host:'127.0.0.1',port:5199,strictPort:true,proxy:{'/api':{target:process.env.E2E_API_URL,changeOrigin:false}}}});await server.listen();
 browser=await chromium.launch({headless:true,executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
 const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
 const other=await browser.newPage();
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const base='http://127.0.0.1:5199';
 await page.goto(base+'/login');await other.goto(base+'/login');
 async function call(p,op,input={},group='rpc'){
  const {csrfToken}=await(await p.request.get(base+'/api/csrf-cookie')).json();
  const r=await p.request.post(base+'/api/'+group+'/'+op,{headers:{'X-CSRF-TOKEN':csrfToken},data:{requestId:crypto.randomUUID(),...input}});
  assert.equal(r.status(),200,await r.text());return(await fixtureResponse(p.request,r)).data;
 }
 const owner=await call(page,'register',{name:'交辦者',email:`owner-${crypto.randomUUID()}@example.test`,password:'isolated-notes-password',workspaceName:'紙條分頁驗收'},'auth');
 const email=`recipient-${crypto.randomUUID()}@example.test`;
 const invite=await call(page,'inviteMember',{email});
 const recipient=await call(other,'register',{name:'承辦者',email,password:'isolated-notes-password',inviteToken:invite.token},'auth');
 for(let i=0;i<21;i++){
  const row=await call(page,'createTodo',{content:`交辦 ${String(i).padStart(2,'0')}`,assigneeId:recipient.user.id});
  await call(other,'completeTodo',{id:row.id,expectedRevision:row.revision});
  // SQL timestamps have second precision; separate fixtures so UUID tie-breaking stays deterministic.
  await new Promise(resolve=>setTimeout(resolve,1050));
 }
 await page.goto(base+'/app/#/notes');
 await page.getByRole('tab',{name:'建立',exact:true}).click();
 await page.getByLabel('紙條內容',{exact:true}).fill('新增交辦');
 await page.getByLabel('交給誰',{exact:true}).selectOption(recipient.user.id);
 await page.getByRole('button',{name:'建立紙條',exact:true}).click();
 await page.getByText('紙條已建立',{exact:true}).waitFor();
 const captures=[];
 for(const [index,title] of ['交辦 20','交辦 19'].entries()){
  await page.getByRole('button',{name:/^通知/}).click();
  const inbox=page.getByRole('dialog',{name:'通知',exact:true});
  await inbox.getByRole('button').filter({hasText:title}).click();
  const drawer=page.getByRole('dialog',{name:'紙條',exact:true});await drawer.waitFor();
  await drawer.getByRole('button',{name:'關閉紙條',exact:true}).click();
  await page.waitForTimeout(150);
  const state={pagination:await page.locator('.pagination').count(),empty:await page.getByText('尚無紙條',{exact:true}).count(),staleSuccess:await page.getByText('紙條已建立',{exact:true}).count(),selectedTab:await page.getByRole('tab',{selected:true}).innerText()};
  if(phase==='before'){assert.equal(state.pagination,2);assert.equal(state.selectedTab,'待處理');}
  else {assert.equal(state.pagination,1);assert.equal(state.empty,0);assert.equal(state.staleSuccess,0);assert.equal(state.selectedTab,'我交辦的');}
  captures.push(state);
 }
 for(const width of [1440,768,390]){
  await page.setViewportSize({width,height:1000});await page.waitForTimeout(350);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  await page.screenshot({path:new URL(`${phase}-${width}.png`,root).pathname,fullPage:true,animations:'disabled',mask:[page.locator('time')]});
 }
 assert.deepEqual(errors,[]);writeFileSync(new URL(`${phase}.json`,root),JSON.stringify(captures,null,2));console.log(JSON.stringify({phase,captures,errors}));
} finally {await browser?.close();await server?.close();}
