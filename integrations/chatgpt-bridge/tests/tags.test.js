import test from 'node:test';
import assert from 'node:assert/strict';
import { callTool, ORIGIN } from '../worker/index.js';
const project = '11111111-1111-4111-8111-111111111111';
const issue = '22222222-2222-4222-8222-222222222222';
const tag = '33333333-3333-4333-8333-333333333333';
const workspace = '44444444-4444-4444-8444-444444444444';
const env = { TASKTRACKER_PAT: 'synthetic-tag-secret', TASKTRACKER_WORKSPACE_ID: workspace };
const row = { id: tag, name: 'Synthetic', color: '#6653bd', description: 'Untrusted tag policy', revision: 3, tags: [{ id: tag, name: 'Synthetic', color: '#6653bd', internal: 'hidden' }], internal: 'hidden' };
const response = data => new Response(JSON.stringify({ ok: true, data }));
function fixture(abilities = ['write']) {
  const calls = [];
  return { calls, transport: async (url, options) => {
    calls.push({ url, options });
    assert.ok(url.startsWith(ORIGIN + '/api/public/v1/'));
    return response(url.endsWith('/me') ? { userId: 'synthetic', workspaceId: workspace, abilities }
      : options.method === 'GET' ? (url.includes('/tags?') ? { items: [row], page: 1, perPage: 20, lastPage: 1 } : row)
        : { id: tag, projectId: project, revision: 4 });
  } };
}
test('tag definitions retain descriptions and revisions; issue reads retain existing tag IDs', async () => {
  const f = fixture();
  const list = (await callTool('list_tags', { project, search: 'Synthetic' }, env, f.transport)).data;
  assert.equal(list.items[0].description, row.description);
  assert.equal(list.items[0].internal, undefined);
  assert.equal((await callTool('get_tag', { project, tag }, env, f.transport)).data.revision, 3);
  assert.deepEqual((await callTool('get_issue', { project, issue }, env, f.transport)).data.tagIds, [tag]);
  assert.ok(f.calls.every(c => c.options.method === 'GET'));
});
for (const [name, args, revisionPath] of [
  ['create_tag', { project, body: { name: 'Synthetic', description: 'Policy' } }, null],
  ['update_tag', { project, tag, body: { expectedRevision: 3, description: 'Revised' } }, `/tags/${tag}`],
  ['delete_tag', { project, tag, body: { expectedRevision: 3 } }, `/tags/${tag}`],
  ['update_issue_tags', { project, issue, body: { expectedRevision: 3, addTagIds: [tag], removeTagIds: [] } }, `/issues/${issue}`],
]) test(`${name}: preview, exact revision target, stable replay and scope`, async () => {
  const f = fixture();
  const preview = (await callTool(name, args, env, f.transport)).data;
  assert.ok(f.calls.every(c => c.options.method === 'GET'));
  if (revisionPath) assert.ok(f.calls.at(-1).url.endsWith(revisionPath));
  if (name === 'delete_tag') assert.match(preview.preview.effect, /Issue history is preserved/);
  if (name === 'update_issue_tags') {
    assert.deepEqual(preview.preview.target.tagIds, [tag]);
    assert.deepEqual(preview.preview.body.addTagIds, [tag]);
    assert.equal(preview.preview.body.tagIds, undefined);
  }
  const confirmed = { ...args, confirmation: preview.confirmation };
  await callTool(name, confirmed, env, f.transport);
  const body = f.calls.at(-1).options.body;
  await callTool(name, confirmed, env, f.transport);
  assert.equal(f.calls.at(-1).options.body, body);
  await assert.rejects(callTool(name, args, env, fixture(['read']).transport), e => e.code === 'WRITE_SCOPE_REQUIRED');
  if (revisionPath) await assert.rejects(callTool(name, { ...args, body: { ...args.body, expectedRevision: 2 } }, env, f.transport), e => e.code === 'CONFLICT');
});
test('delta rejects replacement, duplicate IDs and out-of-bound arrays before any API call', async () => {
  for (const body of [{ tagIds: [tag] }, { addTagIds: [tag, tag] }, { addTagIds: Array(21).fill(tag) }]) {
    await assert.rejects(callTool('update_issue_tags', { project, issue, body: { expectedRevision: 3, ...body } }, env,
      async () => assert.fail('invalid arguments must not fetch')), e => e.code === 'INVALID_ARGUMENTS');
  }
});
