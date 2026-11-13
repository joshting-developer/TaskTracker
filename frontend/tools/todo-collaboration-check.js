import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { createServer } from 'vite';
import { chromium } from 'playwright';
if (!process.env.E2E_API_URL || process.env.E2E_ISOLATED !== '1') throw new Error('Isolated API required');
let server, browser;
const dir = new URL('../../artifacts/tt325/', import.meta.url).pathname;
mkdirSync(dir, { recursive: true });
try {
  server = await createServer({ root: new URL('..', import.meta.url).pathname, server: { host: '127.0.0.1', port: 5291, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } }); await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const pages = await Promise.all(['Claude', 'Josh', 'Codex'].map(async () => (await browser.newContext({ viewport: { width: 1440, height: 1000 } })).newPage()));
  const [claude, josh, codex] = pages, errors = [], network = [];
  for (const page of pages) { page.setDefaultTimeout(12000); page.on('pageerror', e => errors.push(e.message)); await page.goto('http://127.0.0.1:5291/login'); }
  async function call(page, operation, input = {}, group = 'rpc') {
    return page.evaluate(async ({operation, input, group}) => {
      const csrf = await (await fetch('/api/csrf-cookie')).json();
      const r = await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf.csrfToken}, body: JSON.stringify({requestId:crypto.randomUUID(),...input}) });
      const result = await r.json(); if (!result.ok) throw new Error(result.error.message);
      if (result.data?.verificationRequired) { const check = await fetch('/api/auth/verify-email',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf.csrfToken},body:JSON.stringify({code:'local666'})}); const verified = await check.json(); if (!verified.ok) throw new Error(verified.error.message); return verified.data; }
      return result.data;
    }, {operation, input, group});
  }
  const owner = await call(claude, 'register', {name:'Claude', email:`collab-${crypto.randomUUID()}@example.test`, password:'collaboration-test-password', workspaceName:'Collaboration Lab'}, 'auth');
  const people = {Claude:owner.user};
  for (const [name,page] of [['Josh',josh],['Codex',codex]]) { const email=`collab-${crypto.randomUUID()}@example.test`; const invite=await call(claude,'inviteMember',{email}); const member=await call(page,'register',{name,email,password:'collaboration-test-password',inviteToken:invite.token},'auth'); people[name]=member.user; }
  const todo=await call(claude,'createTodo',{content:'確認附件權限\n\n測試圖片與PDF的下載資格',assigneeId:people.Josh.id});
  josh.on('response', r => { const p=new URL(r.url()).pathname; if(p.startsWith('/api/')) network.push(p); });
  await josh.reload(); const row=josh.locator(`[data-todo-id="${todo.id}"]`); await row.waitFor();
  assert.equal(await row.locator('.todo-content').getAttribute('open'),null);
  assert.equal(network.some(p=>p.endsWith('/comments')||p.endsWith('/handoffs')),false,'No conversation fetch before expanded');
  await row.getByLabel('回覆',{exact:true}).fill('確認採用目前權限規則');
  await row.getByRole('button',{name:'轉發',exact:true}).click();
  await row.getByLabel('轉發給誰',{exact:true}).selectOption(people.Codex.id);
  await row.getByRole('button',{name:'確認轉發',exact:true}).click();
  await josh.getByRole('status').filter({hasText:'已轉發'}).waitFor();
  await josh.getByRole('tab',{name:/我經手的/}).click(); await row.waitFor();
  await row.locator('.conversation-history > summary').click(); await row.getByText('確認採用目前權限規則',{exact:true}).waitFor();
  await codex.reload(); const received=codex.locator(`[data-todo-id="${todo.id}"]`); await received.waitFor();
  await received.getByLabel('回覆',{exact:true}).fill('已完成下載資格測試');
  await received.getByRole('button',{name:'完成',exact:true}).click();
  await codex.getByRole('status').filter({hasText:'待辦已完成'}).waitFor();
  await josh.reload(); await josh.getByRole('tab',{name:/我經手的/}).click(); await josh.getByRole('button',{name:/顯示已完成/}).click(); await row.waitFor();
  await row.locator('.conversation-history > summary').click(); await row.getByText('已完成下載資格測試',{exact:true}).waitFor();
  assert.equal(await row.getByRole('button',{name:'完成',exact:true}).count(),0);
  // Concurrent edit keeps draft, requires explicit revision acknowledgement.
  await row.getByLabel('回覆',{exact:true}).fill('驗收草稿保留');
  const latest=await josh.evaluate(async id=>(await(await fetch(`/api/v1/todos/${id}`)).json()).data,todo.id);
  await call(claude,'commentTodo',{id:todo.id,expectedRevision:latest.revision,content:'另一個經手者補充'});
  await row.getByRole('button',{name:'送出回覆',exact:true}).click();
  await josh.getByRole('alert').filter({hasText:'待辦已更新'}).waitFor();
  assert.equal(await row.getByLabel('回覆',{exact:true}).inputValue(),'驗收草稿保留');
  await row.getByRole('button',{name:'使用最新版本繼續',exact:true}).click();
  await row.getByRole('button',{name:'送出回覆',exact:true}).click();
  await josh.getByRole('status').filter({hasText:'已送出回覆'}).waitFor();
  assert.equal(await row.getByLabel('回覆',{exact:true}).inputValue(),'');
  for (const width of [1440,768,390]) { await josh.setViewportSize({width,height:1000}); await josh.waitForTimeout(250); assert.equal(await josh.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false); await josh.screenshot({path:`${dir}/collaboration-${width}.png`,fullPage:true}); }
  await claude.reload(); await claude.getByRole('tab',{name:/我交辦的/}).click(); await claude.getByRole('button',{name:/顯示已完成/}).click(); const source=claude.locator(`[data-todo-id="${todo.id}"]`); await source.waitFor(); await source.locator('.conversation-history > summary').click(); await source.getByText('驗收草稿保留',{exact:true}).waitFor();
  // A separate synthetic assignment verifies the actual PAT CLI against these HTTP routes.
  const cliDir = `${dir}/cli-private`; mkdirSync(cliDir, { recursive:true, mode:0o700 });
  try {
    const token = await codex.evaluate(async () => (await import('/src/api/index.js')).api.createPersonalToken({name:'TT325 isolated CLI',abilities:['write'],requestId:crypto.randomUUID()}));
    writeFileSync(`${cliDir}/codex.json`, JSON.stringify({agent:'codex',baseUrl:process.env.E2E_API_URL,userId:people.Codex.id,workspaceId:owner.workspace.id,token:token.plainTextToken,expiresAt:token.token.expiresAt}), {mode:0o600});
    const created = await call(claude,'createTodo',{title:'CLI follow-up',assigneeId:people.Josh.id});
    await call(josh,'forwardTodo',{id:created.id,expectedRevision:1,assigneeId:people.Codex.id,reply:'CLI handoff decision'});
    const run = args => JSON.parse(execFileSync('python3',['-c',"import importlib.util,sys;from pathlib import Path;s=importlib.util.spec_from_file_location('api','.agents/skills/task-tracker-usage/scripts/tasktracker_api.py');m=importlib.util.module_from_spec(s);s.loader.exec_module(m);m.PRIVATE=Path(sys.argv.pop(1));m.main()",cliDir,'--agent','codex',...args],{cwd:new URL('../..',import.meta.url).pathname,encoding:'utf8',stdio:['pipe','pipe','pipe']}));
    assert.equal(run(['todo-get','--todo',created.id]).state,'open');
    assert.equal(run(['todo-comments','--todo',created.id]).items[0].content,'CLI handoff decision');
    writeFileSync(`${cliDir}/reply.txt`,'CLI completed and tested',{mode:0o600});
    run(['todo-complete','--todo',created.id,'--reply-file',`${cliDir}/reply.txt`]);
    assert.equal(run(['todo-comments','--todo',created.id]).items[1].content,'CLI completed and tested');
    assert.equal(run(['todo-get','--todo',created.id]).state,'done');
    const result = await claude.evaluate(async id=>(await(await fetch(`/api/v1/todos/${id}/comments`)).json()).data,created.id);
    assert.deepEqual(result.items.map(x=>x.author),['Josh','Codex']);
    await codex.evaluate(async id=>(await import('/src/api/index.js')).api.revokePersonalToken(id),token.token.id);
  } finally { rmSync(cliDir,{recursive:true,force:true}); }
  assert.equal(network.some(p=>/\/(getWorkspace|getTodos)$/.test(p)),false); assert.deepEqual(errors,[]);
  console.log('PASS 3-person forward/reply/complete, former-handler visibility, lazy bounded conversation, stale draft preservation, 3 widths, actual PAT CLI handoff/completion and no bulk workspace read');
} finally { await browser?.close(); await server?.close(); }
