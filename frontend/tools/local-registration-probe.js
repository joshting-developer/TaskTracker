// Only the named local-dev synthetic stack; never a generic CI fixture or production origin.
import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
const root = new URL('../../', import.meta.url), base = 'http://127.0.0.1:5174', mailbox = 'http://127.0.0.1:8025';
const config = JSON.parse(readFileSync(new URL('.local/local-dev-credentials.json', root)));assert.equal(config.baseUrl, base);
const phase = process.env.E2E_CAPTURE_PHASE || 'after', path = new URL('.local/tt395-registration-fixture.json', root), folder = new URL(`artifacts/tt395/${phase}/`, root);mkdirSync(folder,{recursive:true});
assert(['before','after'].includes(phase));
const stored = existsSync(path)?JSON.parse(readFileSync(path)):null;if(stored)assert.equal(stored.base,base);
const fixture = phase==='after' && !stored?.completed ? stored : null;
const browser = await chromium.launch({headless:true});
try {
 const context = await browser.newContext({locale:'zh-TW',viewport:{width:1440,height:1000},...(fixture?{storageState:fixture.state}:{})});const page = await context.newPage();page.setDefaultTimeout(15000);
 const errors=[];page.on('pageerror',e=>errors.push(e.message));let record=fixture;
 if(!record) {
  await page.goto(base+'/login?mode=register');await page.getByLabel('你的名字',{exact:true}).fill('本機驗證流程');
  const email=`local-mail-${crypto.randomUUID()}@example.test`, password=crypto.randomUUID()+'-local';await page.getByLabel('Email',{exact:true}).fill(email);await page.getByLabel('密碼',{exact:true}).fill(password);await page.getByLabel('確認密碼',{exact:true}).fill(password);await page.getByLabel('工作空間名稱',{exact:true}).fill('本機驗證流程');
  const response=page.waitForResponse(r=>new URL(r.url()).pathname==='/api/auth/register');await page.getByRole('button',{name:'建立工作空間',exact:true}).click();const result=await response;assert.equal(result.status(),200);const data=(await result.json()).data;assert.equal(data.verificationRequired,true);if(phase==='before')assert.notEqual(data.deliveryStatus,'sent');else assert.equal(data.deliveryStatus,'sent');
  record={base,email,sentOnSignup:data.deliveryStatus==='sent',state:await context.storageState()};writeFileSync(path,JSON.stringify(record),{mode:0o600});
 } else await page.goto(base+'/login');
 await page.getByRole('heading',{name:'驗證信箱',exact:true}).waitFor();
 if(phase==='after' && !record.sentOnSignup) {
  const resend=page.getByRole('button',{name:/^重寄啟用信/});await resend.waitFor();await page.waitForFunction(()=>[...document.querySelectorAll('button')].some(el=>el.textContent.trim()==='重寄啟用信'&&!el.disabled),{},{timeout:65000});
  const response=page.waitForResponse(r=>new URL(r.url()).pathname==='/api/auth/resend-verification');await resend.click();const sent=await response;assert.equal(sent.status(),200);assert.equal((await sent.json()).data.deliveryStatus,'sent');await page.getByText('啟用連結已寄到',{exact:false}).waitFor();assert.equal(await page.getByRole('alert').count(),0);
 }
 for(const width of [1440,768,390]) {await page.setViewportSize({width,height:1000});await page.evaluate(()=>document.fonts.ready);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);await page.screenshot({path:new URL(`verification-${width}.png`,folder).pathname,fullPage:true,animations:'disabled',caret:'hide'});}
 if(phase==='after') {
  const messages=await(await context.request.get(mailbox+'/api/v1/messages')).json();const mail=messages.messages.find(message=>message.To.some(to=>to.Address===record.email));assert(mail,'Captured verification mail must exist');
  const message=await(await context.request.get(mailbox+'/api/v1/message/'+mail.ID)).json();const match=message.Text.match(/http:\/\/127\.0\.0\.1:5174\/app\/#\/activate\/[a-f0-9]{64}/);assert(match,'Local activation URL required');
  const activation=await context.newPage();await activation.goto(match[0]);await activation.getByRole('button',{name:'啟用帳號',exact:true}).click();await activation.locator('.app-shell').waitFor();await page.bringToFront();await page.locator('.app-shell').waitFor();await page.reload();await page.locator('.app-shell').waitFor();record.completed=true;record.state=await context.storageState();writeFileSync(path,JSON.stringify(record),{mode:0o600});
 }
 assert.deepEqual(errors,[]);writeFileSync(new URL('result.json',folder),JSON.stringify({phase,errors,screenshots:3,receivedLocally:phase==='after',activated:phase==='after'}));console.log('PASS local registration '+phase+': dedicated local-dev, no external mail, no verification shortcut');
} finally {await browser.close();}
