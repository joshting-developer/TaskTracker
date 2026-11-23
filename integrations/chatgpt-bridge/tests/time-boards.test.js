import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { callTool, ORIGIN } from '../worker/index.js';
import { TOOLS } from '../worker/contract.js';
const project='11111111-1111-4111-8111-111111111111', issue='22222222-2222-4222-8222-222222222222', entry='33333333-3333-4333-8333-333333333333', workspace='44444444-4444-4444-8444-444444444444';
const env={TASKTRACKER_PAT:'synthetic-time-token',TASKTRACKER_WORKSPACE_ID:workspace};
const response=data=>new Response(JSON.stringify({ok:true,data}));
const row={id:entry,projectId:project,issueId:issue,userId:'synthetic',userName:'Synthetic',workDate:'2026-11-18',minutes:30,note:'Synthetic note',revision:2,issue:{id:issue,identifier:'EX-1',title:'Synthetic',type:'task',projectId:project,secret:'hidden'},private:'hidden'};
function fixture(data, abilities=['write']) { const calls=[];return {calls,transport:async(url,options)=>{calls.push({url,options});assert.ok(url.startsWith(ORIGIN+'/api/public/v1/'));return response(url.endsWith('/me')?{workspaceId:workspace,userId:'synthetic',abilities}:data);}}; }
test('every public operation has exactly one tool including future contract additions',async()=>{
 const spec=JSON.parse(await readFile(new URL('../../../backend/resources/openapi.json',import.meta.url)));
 const ops=Object.entries(spec.paths).flatMap(([path,methods])=>path.startsWith('/api/public/v1/')?Object.entries(methods).filter(([method])=>['get','post','put','patch','delete'].includes(method)).map(([method,op])=>[op.operationId,{method:method.toUpperCase(),path}]):[]);
 assert.deepEqual(Object.values(TOOLS).map(t=>t.operationId).sort(),ops.map(([id])=>id).sort());
 for(const t of Object.values(TOOLS))assert.deepEqual({method:t.method??'GET',path:t.path},Object.fromEntries(ops)[t.operationId]);
});
for(const name of ['list_time_entries','list_issue_time_entries']) test(`${name} preserves bounded rows and filters without leaking fields`,async()=>{
 const f=fixture({items:[row],page:1,perPage:20,total:1,lastPage:1});
 const args={project,...(name==='list_issue_time_entries'?{issue}:{from:'2026-11-01',to:'2026-11-18',issueId:issue})};
 const data=(await callTool(name,args,env,f.transport)).data;
 assert.equal(data.items[0].minutes,30);assert.equal(data.items[0].note,'Synthetic note');assert.equal(data.items[0].issue.identifier,'EX-1');assert.doesNotMatch(JSON.stringify(data),/hidden/);
 assert.match(f.calls.at(-1).url,/perPage=20/);
 await assert.rejects(callTool(name,{...args,perPage:21},env,f.transport),e=>e.code==='INVALID_ARGUMENTS');
 const overflow=fixture({items:Array(21).fill(row)});await assert.rejects(callTool(name,args,env,overflow.transport),e=>e.code==='INVALID_RESPONSE');
});
for(const [name,body] of [['create_time_entry',{issueId:issue,workDate:row.workDate,minutes:30,note:'Synthetic'}],['update_time_entry',{expectedRevision:2,workDate:row.workDate,minutes:45}],['void_time_entry',{expectedRevision:2}]])test(`${name} uses direct writes with a stable requestId and scope checks`,async()=>{
 const f=fixture(row);const args={project,...(name==='create_time_entry'?{}:{entry}),body:{...body,requestId:issue}};
 const result=(await callTool(name,args,env,f.transport)).data;assert.equal(result.minutes,30);assert.equal(result.workDate,row.workDate);assert.equal(result.revision,2);assert.equal(result.requiresConfirmation,undefined);
 assert.equal(f.calls.length,2);const first=f.calls.at(-1);await callTool(name,args,env,f.transport);assert.equal(f.calls.at(-1).options.body,first.options.body);
 await assert.rejects(callTool(name,args,env,fixture(row,['read']).transport),e=>e.code==='WRITE_SCOPE_REQUIRED');
 await assert.rejects(callTool(name,args,env,async(url,options)=>url.endsWith('/me')?response({workspaceId:workspace,abilities:['write']}):new Response('{}',{status:409})),e=>e.code==='CONFLICT');
 await assert.rejects(callTool(name,{...args,body:{...args.body,userId:'someone-else'}},env,f.transport),e=>e.code==='INVALID_ARGUMENTS');
});
test('board preview is a read-only POST, not a mutation or confirmation flow',async()=>{
 const f=fixture({items:[{id:issue,identifier:'EX-1',title:'Synthetic',type:'task',private:'hidden'}],page:1,perPage:20,total:1,lastPage:1},['read']);
 const args={project,body:{autoInclude:{tagIds:[],types:['task'],states:['open']},selectedIssueIds:[],excludedIssueIds:[issue]}};
 const result=await callTool('preview_board',args,env,f.transport);assert.equal(f.calls.length,2);assert.equal(f.calls.at(-1).options.method,'POST');assert.deepEqual(JSON.parse(f.calls.at(-1).options.body),args.body);assert.equal(result.data.items[0].identifier,'EX-1');assert.equal(result.data.items[0].private,undefined);assert.equal(result.data.requiresConfirmation,undefined);assert.equal(TOOLS.preview_board.annotations.readOnlyHint,true);
});
test('board rules support null, explicit empty rules and known delta fields only',async()=>{
 for(const autoInclude of [null,{}, {types:['task'],states:['open'],tagIds:[]}]) {
  const f=fixture({id:issue,projectId:project,revision:2});await callTool('update_board',{project,board:issue,body:{requestId:entry,expectedRevision:1,autoInclude,excludeIssueIds:[issue],unexcludeIssueIds:[]}},env,f.transport);
  assert.deepEqual(JSON.parse(f.calls.at(-1).options.body).autoInclude,autoInclude);
 }
 await assert.rejects(callTool('update_board',{project,board:issue,body:{requestId:entry,expectedRevision:1,autoInclude:{unknown:true}}},env,async()=>assert.fail()),e=>e.code==='INVALID_ARGUMENTS');
});
test('board details retain rule inputs needed for preview while dropping internal nested fields',async()=>{
 const f=fixture({id:issue,selectedIssueIds:[entry],excludedIssueIds:[project],autoInclude:{tagIds:[entry],types:['task'],states:['open'],secret:'hidden'},autoIncludeTags:[{id:entry,name:'Synthetic',color:'#6653bd',secret:'hidden'}],secret:'hidden'});
 const data=(await callTool('get_board',{project,board:issue},env,f.transport)).data;
 assert.deepEqual(data.selectedIssueIds,[entry]);assert.deepEqual(data.excludedIssueIds,[project]);assert.deepEqual(data.autoInclude,{tagIds:[entry],types:['task'],states:['open']});assert.equal(data.autoIncludeTags[0].name,'Synthetic');assert.doesNotMatch(JSON.stringify(data),/hidden/);
});
