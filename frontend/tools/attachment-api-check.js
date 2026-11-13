import { fixtureResponse } from './fixtures/verification.js';
import assert from 'node:assert/strict';
import { request } from 'playwright';
if (!process.env.E2E_API_URL || process.env.E2E_ISOLATED !== '1') throw new Error('Explicit isolated E2E_API_URL and E2E_ISOLATED=1 required');
const base = process.env.E2E_API_URL;
const owner = await request.newContext({ baseURL: base });
const outsider = await request.newContext({ baseURL: base });
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1cAAAAASUVORK5CYII=', 'base64');
async function call(client, path, data) {
  const csrf = await (await client.get('/api/csrf-cookie')).json();
  const response = await client.post(path, { data: { requestId: crypto.randomUUID(), ...data }, headers: { 'X-CSRF-TOKEN': csrf.csrfToken } });
  const result = await fixtureResponse(client, response); assert.equal(response.status(), 200, JSON.stringify(result)); return result.data;
}
try {
  const identity = await call(owner, '/api/auth/register', { name: 'Attachment HTTP', email: `attachment-${crypto.randomUUID()}@example.test`, password: 'isolated-attachment-test', workspaceName: 'Private images' });
  const project = await call(owner, '/api/rpc/createProject', { name: 'Private images', code: 'IMAGE' });
  const issue = await call(owner, '/api/rpc/createIssue', { projectId: project.id, title: 'Attachment HTTP' });
  const csrf = await (await owner.get('/api/csrf-cookie')).json();
  const root = `/api/v1/projects/${project.id}/attachments`;
  const id = crypto.randomUUID();
  const upload = () => owner.post(root, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken, 'X-Workspace-ID': identity.workspace.id }, multipart: { requestId: id, file: { name: 'screen.png', mimeType: 'text/html', buffer: png } } });
  const response = await upload(); assert.equal(response.status(), 200, await response.text());
  const attachment = (await fixtureResponse(client, response)).data;
  assert.equal(attachment.mime, 'image/png', 'Server sniffs bytes, never trusts browser MIME');
  assert.equal((await (await upload()).json()).data.id, attachment.id);
  for (const key of ['storage_disk', 'storage_key', 'checksum']) assert.equal(key in attachment, false);
  const url = `${root}/${attachment.id}/content`;
  let content = await owner.get(url); assert.equal(content.status(), 200); assert.deepEqual(await content.body(), png);
  assert.equal(content.headers()['content-type'], 'image/png'); assert.equal(content.headers()['x-content-type-options'], 'nosniff');
  assert.match(content.headers()['cache-control'], /no-store/); assert.equal(content.headers()['cross-origin-resource-policy'], 'same-origin');
  assert.equal((await outsider.get(url)).status(), 401);
  const bad = await owner.post(root, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, multipart: { requestId: crypto.randomUUID(), file: { name: 'screen.png', mimeType: 'image/png', buffer: Buffer.from('<svg onload=alert(1)>') } } }); assert.equal(bad.status(), 400);
  const max = 10 * 1024 * 1024;
  const boundary = await owner.post(root, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, multipart: { requestId: crypto.randomUUID(), file: { name: 'boundary.png', mimeType: 'image/png', buffer: Buffer.concat([png, Buffer.alloc(max - png.length)]) } } });
  assert.equal(boundary.status(), 200, await boundary.text());
  const boundaryData = (await fixtureResponse(owner, boundary)).data; assert.equal(boundaryData.bytes, max);
  const tooLarge = await owner.post(root, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, multipart: { requestId: crypto.randomUUID(), file: { name: 'too-large.png', mimeType: 'image/png', buffer: Buffer.concat([png, Buffer.alloc(max + 1 - png.length)]) } } });
  assert.equal(tooLarge.status(), 400, await tooLarge.text());
  const bound = await call(owner, `${root}/${attachment.id}/bind`, { expectedRevision: 1, issueId: issue.id, issueRevision: 1 });
  assert.equal(bound.issueRevision, 2);
  const list = await (await owner.get(`/api/v1/projects/${project.id}/issues/${issue.id}/attachments`)).json(); assert.equal(list.data.items.length, 1);
  await call(outsider, '/api/auth/register', { name: 'Other', email: `attachment-other-${crypto.randomUUID()}@example.test`, password: 'isolated-attachment-test', workspaceName: 'Other' });
  assert.equal((await outsider.get(url)).status(), 404);
  const deletion = await owner.delete(`${root}/${attachment.id}`, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), expectedRevision: 2, issueId: issue.id, issueRevision: 2 } }); assert.equal(deletion.status(), 200, await deletion.text());
  assert.equal((await owner.get(url)).status(), 404);
  console.log('PASS: real HTTP multipart/private local disk, byte-sniffed MIME, exact image stream, safe headers, retry, exact 10MiB / over-limit rejection, bind/list/delete, anonymous and cross-tenant denial');
} finally { await owner.dispose(); await outsider.dispose(); }
