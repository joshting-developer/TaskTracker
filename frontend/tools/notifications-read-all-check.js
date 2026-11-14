import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { fixtureResponse } from './fixtures/verification.js';
const phase=process.argv[2] || 'after';
assert.ok(['before','after'].includes(phase));
assert.equal(process.env.E2E_ISOLATED,'1');
assert.equal(process.env.E2E_API_URL,'http://127.0.0.1:13199');
let server,browser;
const root=new URL('../../artifacts/tt354/',import.meta.url);
mkdirSync(root,{recursive:true});
try {
 const previous = new Set(['App.vue','components/PersonalNotifications.vue','components/TodoNotifications.vue']);
 server=await createServer({plugins: phase === 'before' ? [{name:'tt354-before',enforce:'pre',load(id){const relative=id.split('/frontend/src/')[1]; if(previous.has(relative))return execFileSync('git',['show',`b005dc5:frontend/src/${relative}`],{encoding:'utf8'});}}] : [],server:{host:'127.0.0.1',port:5199,strictPort:true,proxy:{'/api':{target:process.env.E2E_API_URL,changeOrigin:false}}}});await server.listen();
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
 for(let i=0;i<3;i++){
  const row=await call(page,'createTodo',{content:`通知測試 ${i+1}`,assigneeId:recipient.user.id});
  await call(other,'completeTodo',{id:row.id,expectedRevision:row.revision});
  await new Promise(resolve=>setTimeout(resolve,1050));
 }
 await page.goto(base+'/app/#/notes');
 await page.getByRole('button',{name:/^通知/}).click();
 const inbox=page.getByRole('dialog',{name:'通知',exact:true});
 await inbox.getByText('通知測試 3',{exact:true}).waitFor();
 for(const width of [1440,768,390]){
  await page.setViewportSize({width,height:1000});await page.waitForTimeout(350);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  await page.screenshot({path:new URL(`${phase}-${width}.png`,root).pathname,fullPage:true,animations:'disabled',mask:[page.locator('time')]});
 }
 let captures={};
 if(phase==='after'){
  const readAll=inbox.getByRole('button',{name:'全部標為已讀',exact:true});
  assert.equal(await readAll.isEnabled(),true);
  let requests=0;page.on('request',r=>{if(r.url().endsWith('/todo-notifications/read-all') && r.method()==='POST')requests++;});
  await readAll.click();
  await page.waitForFunction(()=>document.querySelectorAll('.notification-state').length===3 && [...document.querySelectorAll('.notification-state')].every(e=>e.textContent==='已讀'));
  await page.waitForFunction(()=>document.querySelector('.todo-notification-toggle')?.getAttribute('aria-label')==='通知');
  assert.equal(await readAll.isDisabled(),true);
  assert.equal(requests,1);
  const unread=await(await page.request.get(base+'/api/v1/todo-notifications/unread')).json();assert.equal(unread.data.unread,0);
  captures={markedRead:3,requests,unread:0,disabled:true};
 }
 assert.deepEqual(errors,[]);writeFileSync(new URL(`${phase}.json`,root),JSON.stringify(captures,null,2));console.log(JSON.stringify({phase,captures,errors}));
} finally {await browser?.close();await server?.close();}
