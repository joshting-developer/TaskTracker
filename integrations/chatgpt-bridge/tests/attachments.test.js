import test from 'node:test';
import assert from 'node:assert/strict';
import { callTool, handle, ORIGIN } from '../worker/index.js';

const project = '11111111-1111-4111-8111-111111111111';
const attachment = '22222222-2222-4222-8222-222222222222';
const workspace = '33333333-3333-4333-8333-333333333333';
const env = { TASKTRACKER_PAT: 'synthetic-file-token', TASKTRACKER_WORKSPACE_ID: workspace, TASKTRACKER_OWNER_EMAIL: 'owner@example.test' };
const identity = { userId: 'synthetic-user', workspaceId: workspace, abilities: ['write'] };
const response = data => new Response(JSON.stringify({ ok: true, data }));

test('upload previews only file metadata and converts confirmed bytes to multipart with stable requestId', async () => {
  const args = { project, body: { name: 'Synthetic.txt', mime: 'text/plain', fileBase64: btoa('hello') } };
  const calls = [];
  const transport = async (url, options) => {
    calls.push({ url, options });
    if (url.endsWith('/me')) return response(identity);
    assert.equal(url, ORIGIN + `/api/public/v1/projects/${project}/attachments`);
    assert.ok(options.body instanceof FormData);
    assert.equal(options.headers['Content-Type'], undefined);
    const file = options.body.get('file');
    assert.equal(file.name, 'Synthetic.txt');
    assert.equal(file.type, 'text/plain');
    assert.equal(await file.text(), 'hello');
    return response({ id: attachment, name: file.name, mime: file.type, bytes: file.size, revision: 1 });
  };
  const preview = (await callTool('upload_attachment', args, env, transport)).data;
  assert.equal(calls.length, 1);
  assert.equal(preview.preview.body.bytes, 5);
  assert.match(preview.preview.body.sha256, /^[a-f0-9]{64}$/);
  assert.doesNotMatch(JSON.stringify(preview), /aGVsbG8=|synthetic-file-token/);
  const result = await callTool('upload_attachment', { ...args, confirmation: preview.confirmation }, env, transport);
  assert.equal(result.data.id, attachment);
  assert.equal(calls.at(-1).options.body.get('requestId'), preview.preview.body.requestId);
  await assert.rejects(callTool('upload_attachment', { ...args, body: { ...args.body, fileBase64: btoa('changed') }, confirmation: preview.confirmation }, env, transport), e => e.code === 'CONFIRMATION_INVALID');
});

test('binary download returns actual bounded bytes as an MCP embedded resource', async () => {
  const transport = async (url, options) => {
    assert.equal(options.redirect, 'manual');
    return url.endsWith('/me') ? response(identity) : new Response('hello', { headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
  };
  const request = new Request('https://private.example.test/mcp', { method: 'POST',
    headers: { 'oai-authenticated-user-email': env.TASKTRACKER_OWNER_EMAIL, 'oai-authenticated-user-id': 'owner-id' },
    body: JSON.stringify({ jsonrpc: '2.0', id: 1, method: 'tools/call', params: { name: 'get_attachment_content', arguments: { project, attachment, download: true } } }) });
  const result = await (await handle(request, env, transport)).json();
  const resource = result.result.content.find(c => c.type === 'resource').resource;
  assert.equal(resource.mimeType, 'text/plain');
  assert.equal(atob(resource.blob), 'hello');
  assert.equal(resource.uri, `urn:tasktracker:attachment:${project}:${attachment}`);
  assert.doesNotMatch(JSON.stringify(result), /synthetic-file-token/);
  await assert.rejects(callTool('get_attachment_content', { project, attachment }, env, async url => url.endsWith('/me') ? response(identity)
    : new Response('x', { headers: { 'Content-Length': String(6 * 1024 * 1024 + 1) } })), e => e.code === 'RESPONSE_TOO_LARGE');
});

test('file URLs, path injection and invalid base64 are rejected without uploading', async () => {
  for (const body of [
    { name: '../bad.txt', mime: 'text/plain', fileBase64: btoa('hello') },
    { name: 'bad.txt', mime: 'text/plain', fileUrl: 'https://evil.test/file' },
  ]) await assert.rejects(callTool('upload_attachment', { project, body }, env, async () => assert.fail('no fetch')), e => e.code === 'INVALID_ARGUMENTS');
  await assert.rejects(callTool('upload_attachment', { project, body: { name: 'bad.txt', mime: 'text/plain', fileBase64: 'not-base64!' } }, env,
    async () => response(identity)), e => e.code === 'INVALID_FILE');
});
