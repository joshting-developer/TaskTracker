import assert from 'node:assert/strict';
import { request } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync, readdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
assert.equal(process.env.E2E_ISOLATED, '1');
const root = resolve(import.meta.dirname, '../..'), api = process.env.E2E_API_URL;
assert.equal(new URL(api).hostname, '127.0.0.1');
const fixture = JSON.parse(readFileSync(resolve(root, '.local/tt193-browser-fixture.json')));
assert.equal(fixture.api, api);
const http = await request.newContext({ storageState: fixture.state });
const folder = resolve(root, '.local/tt193-cli'); mkdirSync(folder, { recursive: true, mode: 0o700 });
const script = resolve(root, '.agents/skills/task-tracker-usage/scripts/tasktracker_api.py');
const wrapper = "import importlib.util,sys;from pathlib import Path;s=importlib.util.spec_from_file_location('tt',sys.argv[1]);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);m.PRIVATE=Path(sys.argv[2]);sys.argv=['tasktracker_api.py',*sys.argv[3:]];m.main()";
function cli(command, args = [], body) {
  const file = resolve(folder, 'input.json');
  if (body) writeFileSync(file, JSON.stringify({ requestId: crypto.randomUUID(), ...body }), { mode: 0o600 });
  return JSON.parse(execFileSync('python3', ['-B', '-c', wrapper, script, folder, '--agent', 'codex', command, '--project', fixture.project, ...args, ...(body ? ['--data-file', file] : [])], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }));
}
async function send(path, data) {
  const csrf = await (await http.get(api + '/api/csrf-cookie')).json();
  const response = await http.post(api + path, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { requestId: crypto.randomUUID(), ...data } });
  assert.equal(response.status(), 200, 'Isolated session operation failed'); return (await response.json()).data;
}
let token;
try {
  token = await send('/api/v1/personal-tokens', { name: 'isolated-tag-cli', abilities: ['write'] });
  const identity = await http.get(api + '/api/public/v1/me', { headers: { Authorization: `Bearer ${token.plainTextToken}` } });
  assert.equal(identity.status(), 200); const me = (await identity.json()).data;
  writeFileSync(resolve(folder, 'codex.json'), JSON.stringify({ agent: 'codex', baseUrl: api, token: token.plainTextToken, userId: me.userId, workspaceId: me.workspaceId, expiresAt: token.token.expiresAt, projectId: fixture.project }), { mode: 0o600 });
  assert(cli('tags').items.some(tag => tag.id === fixture.tag));
  const created = cli('tag-create', [], { name: `CLI-${crypto.randomUUID()}`, description: 'Synthetic CLI test', color: '#008866' });
  let tag = cli('tag-get', ['--tag', created.id]); assert.equal(tag.description, 'Synthetic CLI test');
  cli('tag-update', ['--tag', tag.id], { expectedRevision: tag.revision, description: 'Read before claiming' });
  tag = cli('tag-get', ['--tag', tag.id]); assert.equal(tag.description, 'Read before claiming');
  const issue = cli('get', ['--issue', 'TAG-1']);
  cli('issue-tags', ['--issue', 'TAG-1'], { expectedRevision: issue.revision, addTagIds: [tag.id] });
  const filtered = cli('list', ['--tag-id', fixture.tag, '--tag-id', tag.id]); assert.equal(filtered.total, 1); assert.equal(filtered.items[0].id, fixture.issue);
  const board = cli('board-issues', ['--board', 'default', '--tag-id', tag.id]); assert.equal(board.total, 1);
  cli('tag-delete', ['--tag', tag.id], { expectedRevision: tag.revision });
  const receipts = readdirSync(resolve(folder, 'outbox')).map(name => resolve(folder, 'outbox', name));
  const receipt = receipts.find(path => { const data = JSON.parse(readFileSync(path)); return data.operation === 'deletePublicTag' && data.ids.tag === tag.id; });
  cli('retry', ['--receipt', receipt]);
  assert.deepEqual(cli('get', ['--issue', 'TAG-1']).tags.map(tag => tag.id), [fixture.tag]);
  console.log('PASS isolated shared CLI: tag CRUD, descriptions, delta apply, AND SQL filter, board filter, delete receipt replay and retained issue');
} finally {
  if (token) await send(`/api/v1/personal-tokens/${token.token.id}/revoke`, {});
  await http.dispose();
}
