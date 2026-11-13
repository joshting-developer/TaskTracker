import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
if(process.env.E2E_ISOLATED!=='1'||!process.env.E2E_API_URL)throw Error('Explicit isolated backend required');
const port=Number(process.env.E2E_FRONTEND_PORT||5291),base=`http://127.0.0.1:${port}`;let vite,browser;
try{
  vite=await createServer({root:new URL('../',import.meta.url).pathname,server:{host:'127.0.0.1',port,strictPort:true,proxy:{'/api':process.env.E2E_API_URL}}});await vite.listen();
  browser=await chromium.launch({headless:true,...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH?{executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH}:{})});
  const context=await browser.newContext(),page=await context.newPage();page.setDefaultTimeout(15000);
  async function call(operation,input){const csrf=await(await context.request.get(`${base}/api/csrf-cookie`)).json();const res=await context.request.post(`${base}/api/${operation==='register'?'auth':'rpc'}/${operation}`,{headers:{'X-CSRF-TOKEN':csrf.csrfToken},data:{requestId:crypto.randomUUID(),...input}});assert.equal(res.status(),200);return(await res.json()).data;}
  await call('register',{name:'搜尋測試',email:`search-${crypto.randomUUID()}@example.test`,password:'isolated-search-password',workspaceName:'搜尋驗證'});const project=await call('createProject',{name:'搜尋專案',code:'FIND'});
  await call('createIssue',{projectId:project.id,title:'會員 API 100%_!',description:'中文字中 MixedCase'});await call('createIssue',{projectId:project.id,title:'文件提到 FIND-1'});await call('createIssue',{projectId:project.id,title:'其他內容'});
  await page.goto(`${base}/app/#/projects/${project.id}`);const input=page.getByLabel('搜尋任務',{exact:true});
  for(const[term,count]of[['find-1',1],['text:FIND-1',2],['FIND-*',3],['中',1],['100%_!',1],['mixedcase',1]]){
    const response=page.waitForResponse(r=>r.url().includes(`/projects/${project.id}/issues?`)&&new URL(r.url()).searchParams.get('search')===term);await input.fill(term);assert.equal((await(await response).json()).data.total,count);await page.waitForFunction(expected=>document.querySelectorAll('.issue-table tbody tr').length===expected,count);
  }
  await input.fill(''); await page.goto(`${base}/app/#/projects/${project.id}/board`);await page.locator('.issue-card').first().waitFor();
  for(const[term,count]of[['find-1',1],['text:FIND-1',2],['FIND-*',3],['會',1]]){await input.fill(term);await page.waitForFunction(expected=>document.querySelectorAll('.issue-card').length===expected,count);}
  console.log('PASS: real remote list and local board exact/prefix/text contains, Chinese single character, escaped percent/underscore and English case');
}finally{await browser?.close();await vite?.close();}
