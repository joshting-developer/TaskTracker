import test from 'node:test';
import assert from 'node:assert/strict';
import { callTool, ORIGIN } from '../worker/index.js';
import { TOOLS } from '../worker/contract.js';
import { readFile } from 'node:fs/promises';

const project = '11111111-1111-4111-8111-111111111111';
const issue = '22222222-2222-4222-8222-222222222222';
const workspace = '33333333-3333-4333-8333-333333333333';
const env = { TASKTRACKER_PAT: 'synthetic-only-secret', TASKTRACKER_WORKSPACE_ID: workspace };
const identity = { userId: 'synthetic-user', workspaceId: workspace, abilities: ['write'] };
const response = data => new Response(JSON.stringify({ ok: true, data }));
const errorCode = code => e => e.code === code;
function fixture({ status = 200, who = identity } = {}) {
  const calls = [];
  const transport = async (url, options) => {
    calls.push({ url, options });
    assert.ok(url.startsWith(ORIGIN + '/api/public/v1/'));
    assert.equal(options.redirect, 'manual');
    if (url.endsWith('/me')) return response(who);
    if (options.method === 'GET') return response({ id: issue, revision: 3 });
    return status === 200 ? response({ id: issue, projectId: project, revision: 4, password: 'private' }) : new Response('{}', { status });
  };
  return { calls, transport };
}

for (const [name, args] of [
  ['create_issue', { project, body: { title: 'Synthetic task', type: 'task', priority: 'normal' } }],
  ['update_issue', { project, issue, body: { expectedRevision: 3, title: 'Revised' } }],
  ['comment_issue', { project, issue, body: { expectedRevision: 3, content: 'Synthetic comment' } }],
  ['create_todo', { body: { content: 'Synthetic note' } }],
  ['update_todo', { todo: issue, body: { expectedRevision: 3, content: 'Revised note' } }],
  ['complete_todo', { todo: issue, body: { expectedRevision: 3, reply: 'Done' } }],
  ['reopen_todo', { todo: issue, body: { expectedRevision: 3 } }],
  ['return_todo', { todo: issue, body: { expectedRevision: 3, note: 'Missing information' } }],
  ['comment_todo', { todo: issue, body: { expectedRevision: 3, content: 'Synthetic reply' } }],
  ['forward_todo', { todo: issue, body: { expectedRevision: 3, assigneeId: 'synthetic-recipient', reply: 'Please check' } }],
]) test(`${name} previews without writing and confirms the identical payload with a stable requestId`, async () => {
  const f = fixture();
  const preview = (await callTool(name, args, env, f.transport)).data;
  assert.equal(preview.requiresConfirmation, true);
  assert.ok(f.calls.every(c => c.options.method === 'GET'));
  assert.doesNotMatch(JSON.stringify(preview), /synthetic-only-secret/);
  const result = await callTool(name, { ...args, confirmation: preview.confirmation }, env, f.transport);
  assert.equal(result.data.revision, 4);
  assert.equal(result.data.password, undefined);
  const first = f.calls.at(-1);
  assert.equal(first.options.method, TOOLS[name].method);
  assert.equal(first.url, ORIGIN + preview.preview.path);
  assert.deepEqual(JSON.parse(first.options.body), preview.preview.body);
  await callTool(name, { ...args, confirmation: preview.confirmation }, env, f.transport);
  assert.equal(f.calls.at(-1).options.body, first.options.body);
});

test('confirmation is bound to target, user, workspace, operation and exact changes', async () => {
  const f = fixture();
  const args = { project, body: { title: 'Synthetic' } };
  const confirmation = (await callTool('create_issue', args, env, f.transport)).data.confirmation;
  for (const changed of [{ ...args, body: { title: 'Tampered' } }, { ...args, project: issue }]) {
    await assert.rejects(callTool('create_issue', { ...changed, confirmation }, env, f.transport), errorCode('CONFIRMATION_INVALID'));
  }
  await assert.rejects(callTool('create_issue', { ...args, confirmation: confirmation + '0' }, env, f.transport), errorCode('CONFIRMATION_INVALID'));
  await assert.rejects(callTool('create_issue', { ...args, confirmation }, env,
    fixture({ who: { ...identity, userId: 'different-user' } }).transport), errorCode('CONFIRMATION_INVALID'));
  assert.ok(f.calls.every(c => c.options.method === 'GET'));
});

test('expired confirmations and read-only tokens cannot write', async t => {
  const args = { project, body: { title: 'Synthetic' } }; const f = fixture();
  const confirmation = (await callTool('create_issue', args, env, f.transport)).data.confirmation;
  t.mock.method(Date, 'now', () => new Date('2100-01-01').getTime());
  await assert.rejects(callTool('create_issue', { ...args, confirmation }, env, f.transport), errorCode('CONFIRMATION_INVALID'));
  await assert.rejects(callTool('create_issue', args, env,
    fixture({ who: { ...identity, abilities: ['read'] } }).transport), errorCode('WRITE_SCOPE_REQUIRED'));
});

test('stale preview revision and mutation conflicts are not retried or overwritten', async () => {
  const f = fixture();
  await assert.rejects(callTool('update_issue', { project, issue, body: { expectedRevision: 1, title: 'Stale' } }, env, f.transport), errorCode('CONFLICT'));
  const args = { project, issue, body: { expectedRevision: 3, title: 'Valid preview' } };
  const confirmation = (await callTool('update_issue', args, env, f.transport)).data.confirmation;
  for (const [status, code] of [[401, 'TOKEN_REJECTED'], [403, 'FORBIDDEN'], [409, 'CONFLICT']]) {
    const failed = fixture({ status });
    await assert.rejects(callTool('update_issue', { ...args, confirmation }, env, failed.transport), errorCode(code));
    assert.equal(failed.calls.filter(c => c.options.method !== 'GET').length, 1);
  }
});

test('write schemas reject spoofed identity, bad revisions and unknown payload fields', async () => {
  for (const args of [
    { project, body: { title: 'Synthetic', actor: 'owner' } },
    { project, body: { title: 'Synthetic', requestId: 'invalid' } },
    { project, body: { title: '' } },
    { project, issue, body: { expectedRevision: '3', title: 'Synthetic' } },
  ]) await assert.rejects(callTool(args.issue ? 'update_issue' : 'create_issue', args, env, async () => assert.fail('no fetch')), errorCode('INVALID_ARGUMENTS'));
});

test('mutation payload contracts match official schemas with optional generated requestId', async () => {
  const spec = JSON.parse(await readFile(new URL('../../../backend/resources/openapi.json', import.meta.url)));
  for (const tool of Object.values(TOOLS).filter(t => t.method)) {
    const op = spec.paths[tool.path][tool.method.toLowerCase()];
    assert.equal(tool.operationId, op.operationId);
    let schema = structuredClone(op.requestBody.content['application/json'].schema);
    if (schema.$ref) schema = structuredClone(spec.components.schemas[schema.$ref.split('/').at(-1)]);
    schema.additionalProperties = false;
    schema.required = schema.required.filter(k => k !== 'requestId');
    assert.deepEqual(tool.inputSchema.properties.body, schema);
    assert.equal(tool.annotations.readOnlyHint, false);
  }
});
