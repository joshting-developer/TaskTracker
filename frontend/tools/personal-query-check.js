import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
if(!process.env.E2E_API_URL)throw new Error('必須指定隔離E2E_API_URL');
let server,browser;
const port=Number(process.env.E2E_FRONTEND_PORT||5175),baseURL=`http://127.0.0.1:${port}`;
try {
  server=await createServer({root:new URL('..',import.meta.url).pathname,server:{host:'127.0.0.1',port,strictPort:true,proxy:{'/api':process.env.E2E_API_URL}}});await server.listen();
  browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH});
  const page=await browser.newPage(),errors=[],requests=[];
  page.on('pageerror',e=>{errors.push(e.message);console.log('PAGEERROR',e.stack);});
  page.on('response',r=>{const url=new URL(r.url());if(url.pathname.startsWith('/api/')){requests.push(url.pathname);if(r.status()>=400)console.log('HTTP',r.status(),url.pathname);}});
  await page.goto(`${baseURL}/login`);
  async function call(target,op,input={},group='rpc') {
    return target.evaluate(async({op,input,group})=>{
      const {csrfToken}=await(await fetch('/api/csrf-cookie')).json();
      const result=await(await fetch(`/api/${group}/${op}`,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},body:JSON.stringify({requestId:crypto.randomUUID(),...input})})).json();
      if(!result.ok)throw new Error(result.error.message);if(result.data?.verificationRequired){const csrf=await(await fetch('/api/csrf-cookie')).json();const verified=await fetch('/api/auth/verify-email',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf.csrfToken},body:JSON.stringify({code:'local666'})});if(!verified.ok)throw new Error('Fixture email verification failed');result.data=(await verified.json()).data;}return result.data;
    },{op,input,group});
  }
  await call(page,'register',{name:'Personal Owner',email:`personal-${crypto.randomUUID()}@example.test`,password:'isolated-test-password',workspaceName:'Personal Test'},'auth');
  const project=await call(page,'createProject',{name:'Conversion target',code:'TARGET'});
  let latest;
  for(let i=0;i<25;i++)latest=await call(page,'createTodo',{content:`Note ${i}\n\nHidden source ${i}`});
  const invite=await call(page,'inviteMember',{email:`receiver-${crypto.randomUUID()}@example.test`});
  const receiver=await(await browser.newContext()).newPage();await receiver.goto(`${baseURL}/login`);
  const identity=await call(receiver,'register',{name:'Receiver',email:invite.email,password:'isolated-test-password',inviteToken:invite.token},'auth');
  const assigned=await call(page,'createTodo',{content:'Assigned note\n\nReceiver private detail',assigneeId:identity.user.id});
  requests.length=0;await page.goto(`${baseURL}/app/#/notes`); await page.reload();
  await page.locator(`[data-todo-id="${latest.id}"]`).waitFor();
  assert.equal(await page.locator('.notes-list li').count(),20);
  assert.equal(requests.filter(path=>/\/todos\/(?!counts$)[^/]+$/.test(path)).length,0,'未開啟不讀原稿');
  const row=page.locator(`[data-todo-id="${latest.id}"]`);
  await row.getByRole('button',{name:/^更多：/}).click();
  const note=page.getByRole('dialog',{name:'紙條',exact:true});
  await note.getByText('Hidden source 24',{exact:true}).waitFor();
  assert.ok(requests.some(path=>path.endsWith(`/todos/${latest.id}/comments`)));
  await note.getByRole('button',{name:'關閉紙條',exact:true}).click();
  const pager=page.locator('[aria-label="紙條分頁"]');
  await pager.getByRole('button',{name:'下一頁',exact:true}).click();
  await page.waitForFunction(()=>document.querySelectorAll('.notes-list li').length===5);
  await pager.getByRole('button',{name:'上一頁',exact:true}).click();
  await page.locator(`[data-todo-id="${latest.id}"]`).waitFor();
  await row.getByRole('button',{name:/^更多：/}).click();
  await note.getByRole('button',{name:'轉為卡片',exact:true}).click();
  const editor=page.getByRole('dialog',{name:'轉成正式卡片',exact:true});await editor.waitFor();
  assert.equal(await editor.getByRole('textbox',{name:'描述',exact:true}).inputValue(),'Note 24\n\nHidden source 24');
  await editor.getByRole('combobox',{name:'專案',exact:true}).selectOption(project.id);
  await editor.getByRole('button',{name:'建立卡片並完成轉換',exact:true}).click();
  await page.getByRole('region',{name:'任務完整頁',exact:true}).waitFor();
  assert.equal(requests.filter(path=>/\/(getWorkspace|getTodos)$/.test(path)).length,0);
  const inboxRequests=[];receiver.on('response',r=>{if(new URL(r.url()).pathname.startsWith('/api/'))inboxRequests.push(new URL(r.url()).pathname);});
  // 先裝假時鐘再載入，背景計時器才會受fastForward控制；時間仍自然流動。
  await receiver.clock.install();
  await receiver.reload();
  await receiver.getByRole('button',{name:/紙條通知（1 則未讀）/}).click();
  await receiver.locator('.todo-notification-list button').first().click();
  await receiver.getByRole('dialog', { name: '紙條', exact: true }).getByText('Receiver private detail', { exact: true }).waitFor();
  assert.equal(inboxRequests.filter(path=>/\/(getWorkspace|getTodos)$/.test(path)).length,0);
  await receiver.bringToFront();await receiver.waitForTimeout(200);inboxRequests.length=0;
  const checked=receiver.waitForResponse(r=>new URL(r.url()).pathname.endsWith('/personal-version')&&r.status()===200);
  await receiver.clock.fastForward(70000);await checked;await receiver.waitForTimeout(100);
  assert.equal(inboxRequests.filter(path=>/\/(todos|counts|todo-notifications)$/.test(path)).length,0,'版本未變時不重讀列表');
  assert.deepEqual(errors,[]);
  console.log('personal query PASS: 25 todos / 20+5, on-demand body/history, conversion, recipient notification/focus, idle version only; bulkReads=0');
} finally {await browser?.close();await server?.close();}
