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
    if (options.method === 'GET') return response({ id: issue, identifier: 'EX-1', title: 'Synthetic target', revision: 3 });
    return status === 200 ? response({ id: issue, projectId: project, revision: 4, password: 'private' }) : new Response('{}', { status });
  };
  return { calls, transport };
}

for (let [name, args] of [
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
  ['create_board', { project, body: { name: 'Synthetic board', description: 'Synthetic only' } }],
  ['update_board', { project, board: issue, body: { expectedRevision: 3, addIssueIds: [issue], removeIssueIds: [] } }],
  ['delete_issue', { project, issue, body: { expectedRevision: 3 } }],
  ['upsert_external_issue', { project, source: 'synthetic', externalId: 'item-1', body: { title: 'Synthetic upsert' } }],
  ['bind_attachment', { project, attachment: issue, body: { expectedRevision: 3, issueId: issue, issueRevision: 3 } }],
]) test(`${name} uses direct writes except deletion, preserving the requestId`, async () => {
  const f = fixture();
  args = { ...args, body: { ...args.body, requestId: issue } };
  let input = args;
  if (TOOLS[name].annotations.destructiveHint) {
    const preview = (await callTool(name, args, env, f.transport)).data;
    assert.equal(preview.requiresConfirmation, true);
    assert.ok(f.calls.every(c => c.options.method === 'GET'));
    input = { ...args, confirmation: preview.confirmation };
  }
  const result = await callTool(name, input, env, f.transport);
  assert.equal(result.data.revision, 4);
  assert.equal(result.data.password, undefined);
  assert.equal(result.data.requiresConfirmation, undefined);
  const first = f.calls.at(-1);
  assert.equal(first.options.method, TOOLS[name].method);
  assert.deepEqual(JSON.parse(first.options.body), args.body);
  if (!TOOLS[name].annotations.destructiveHint) assert.equal(f.calls.length, 2, 'identity plus write, no preflight');
  await callTool(name, input, env, f.transport);
  assert.equal(f.calls.at(-1).options.body, first.options.body);
});

test('delete confirmation remains bound to target, user, workspace and exact changes', async () => {
  const f = fixture();
  const args = { project, issue, body: { expectedRevision: 3 } };
  const confirmation = (await callTool('delete_issue', args, env, f.transport)).data.confirmation;
  await assert.rejects(callTool('delete_issue', { ...args, issue: project, confirmation }, env, f.transport), errorCode('CONFIRMATION_INVALID'));
  await assert.rejects(callTool('delete_issue', { ...args, confirmation: confirmation + '0' }, env, f.transport), errorCode('CONFIRMATION_INVALID'));
  await assert.rejects(callTool('delete_issue', { ...args, confirmation }, env,
    fixture({ who: { ...identity, userId: 'different-user' } }).transport), errorCode('CONFIRMATION_INVALID'));
  assert.ok(f.calls.every(c => c.options.method === 'GET'));
});

test('expired delete confirmations and read-only tokens cannot write', async t => {
  const f = fixture();
  const args = { project, issue, body: { expectedRevision: 3 } };
  const confirmation = (await callTool('delete_issue', args, env, f.transport)).data.confirmation;
  t.mock.method(Date, 'now', () => new Date('2100-01-01').getTime());
  await assert.rejects(callTool('delete_issue', { ...args, confirmation }, env, f.transport), errorCode('CONFIRMATION_INVALID'));
  await assert.rejects(callTool('create_issue', { project, body: { title: 'Synthetic', requestId: issue } }, env,
    fixture({ who: { ...identity, abilities: ['read'] } }).transport), errorCode('WRITE_SCOPE_REQUIRED'));
});

test('API conflicts and revocation are returned without retries or replacement revisions', async () => {
  const args = { project, issue, body: { requestId: issue, expectedRevision: 2, title: 'Stale' } };
  for (const [status, code] of [[409, 'CONFLICT'], [403, 'FORBIDDEN'], [401, 'TOKEN_REJECTED']]) {
    const f = fixture({ status });
    await assert.rejects(callTool('update_issue', args, env, f.transport), errorCode(code));
    assert.equal(f.calls.length, 2);
    assert.deepEqual(JSON.parse(f.calls.at(-1).options.body), args.body);
  }
});

test('write schemas reject spoofed identity, bad revisions and unknown payload fields', async () => {
  for (const args of [
    { project, body: { title: 'Synthetic', actor: 'owner' } },
    { project, body: { title: 'Synthetic', requestId: 'invalid' } },
    { project, body: { title: '' } },
    { project, body: { title: 'Missing request ID' } },
    { project, body: { title: 'Direct', requestId: issue }, confirmation: 'obsolete' },
    { project, issue, body: { expectedRevision: '3', title: 'Synthetic' } },
  ]) await assert.rejects(callTool(args.issue ? 'update_issue' : 'create_issue', args, env, async () => assert.fail('no fetch')), errorCode('INVALID_ARGUMENTS'));
});

test('board updates require a change and reject Default and whole-selection replacement', async () => {
  for (const args of [
    { project, board: issue, body: { expectedRevision: 3 } },
    { project, board: 'default', body: { expectedRevision: 3, name: 'Default changed' } },
    { project, board: issue, body: { expectedRevision: 3, selectedIssueIds: [issue] } },
  ]) await assert.rejects(callTool('update_board', args, env, async () => assert.fail('no fetch')), errorCode('INVALID_ARGUMENTS'));
});

test('deletion is destructive and preview includes exact identifier, title and permanent effect', async () => {
  const f = fixture();
  const preview = (await callTool('delete_issue', { project, issue, body: { expectedRevision: 3 } }, env, f.transport)).data.preview;
  assert.equal(TOOLS.delete_issue.annotations.destructiveHint, true);
  assert.equal(preview.target.id, issue);
  assert.equal(preview.target.identifier, 'EX-1');
  assert.equal(preview.target.title, 'Synthetic target');
  assert.match(preview.effect, /Permanently/);
  assert.ok(f.calls.every(c => c.options.method === 'GET'));
});

test('batch upsert executes directly and keeps per-item results', async () => {
  const args = { project, body: { requestId: issue, createOnly: true, items: [{ source: 'synthetic', externalId: 'one', title: 'One' }] } };
  const calls = [];
  const transport = async (url, options) => {
    calls.push({ url, options });
    return response(url.endsWith('/me') ? identity : { items: [{ index: 0, status: 'failed', error: { code: 'CONFLICT', message: 'Conflict', internal: 'hidden' } }], summary: { failed: 1 } });
  };
  const result = (await callTool('batch_upsert_issues', args, env, transport)).data;
  assert.equal(calls.length, 2);
  assert.equal(result.items[0].error.code, 'CONFLICT');
  assert.equal(result.summary.failed, 1);
  assert.doesNotMatch(JSON.stringify(result), /hidden/);
});

test('mutation payload contracts match official schemas with requestId required for direct writes', async () => {
  const spec = JSON.parse(await readFile(new URL('../../../backend/resources/openapi.json', import.meta.url)));
  const resolve = value => {
    if (Array.isArray(value)) return value.map(resolve);
    if (!value || typeof value !== 'object') return value;
    if (value.$ref) return resolve(spec.components.schemas[value.$ref.split('/').at(-1)]);
    return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, resolve(item)]));
  };
  for (const tool of Object.values(TOOLS).filter(t => t.method)) {
    const op = spec.paths[tool.path][tool.method.toLowerCase()];
    assert.equal(tool.operationId, op.operationId);
    if (tool.encoding === 'multipart') {
      assert.ok(op.requestBody.content['multipart/form-data']);
      assert.ok(tool.inputSchema.properties.body.properties.fileBase64);
      continue;
    }
    const schema = resolve(op.requestBody.content['application/json'].schema);
    schema.additionalProperties = false;
    if (tool.annotations.destructiveHint) schema.required = schema.required.filter(k => k !== 'requestId');
    assert.deepEqual(tool.inputSchema.properties.body, schema);
    assert.equal(tool.annotations.readOnlyHint, op.operationId === 'previewPublicBoard');
  }
});
