import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { request } from 'node:http';
import { createApiServer } from '../server/app.js';
import { createService } from '../src/domain/service.js';
import { createSqliteStore } from '../server/repository.js';

test('HTTP API: 真實保存與重試, 拒絕跨站、未知操作、無效 JSON 與版本衝突', async (t) => {
  const store = createSqliteStore(':memory:');
  const service = createService(store, { uuid: randomUUID, now: () => new Date().toISOString() });
  const server = createApiServer(service);
  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  t.after(async () => { await new Promise((resolve) => server.close(resolve)); store.close(); });
  const base = `http://127.0.0.1:${server.address().port}`;
  const call = (operation, body, extra = {}) => fetch(`${base}/api/rpc/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json', ...extra }, body: JSON.stringify(body) });
  assert.equal((await fetch(`${base}/api/health`)).status, 200);
  assert.equal((await call('getWorkspace', {}, { Origin: 'https://external.example' })).status, 403);
  const wrongHostStatus = await new Promise((resolve, reject) => {
    const req = request(`${base}/api/rpc/getWorkspace`, { method: 'POST', headers: { Host: 'evil.example', 'Content-Type': 'application/json' } }, (response) => { response.resume(); resolve(response.statusCode); });
    req.on('error', reject);
    req.end('{}');
  });
  assert.equal(wrongHostStatus, 403);
  assert.equal((await call('getWorkspace', {}, { 'Sec-Fetch-Site': 'cross-site' })).status, 403);
  assert.equal((await call('setup', {})).status, 404);
  assert.equal((await fetch(`${base}/api/rpc/getWorkspace`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: 'broken' })).status, 400);
  const project = (await (await call('createProject', { name: 'API', code: 'API', requestId: randomUUID() })).json()).data;
  const input = { projectId: project.id, title: '瀏覽器保存', requestId: randomUUID() };
  const first = await (await call('createIssue', input)).json();
  assert.deepEqual(await (await call('createIssue', input)).json(), first);
  assert.equal((await call('updateIssue', { id: first.data.id, state: 'done', expectedRevision: 0, requestId: randomUUID() })).status, 409);
  assert.equal((await (await call('getWorkspace', {})).json()).data.issues.length, 1);
});
