import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { createSqliteStore } from '../server/repository.js';
import { createService } from '../src/domain/service.js';
import { liveBlockers } from '../src/domain/model.js';

const request = (input) => ({ ...input, requestId: randomUUID() });
function fixture(t) {
  const store = createSqliteStore(':memory:'); t.after(() => store.close());
  const options = { uuid: randomUUID, now: () => new Date().toISOString(), userId: 'alice', actor: 'Alice' };
  const service = createService(store, options);
  const project = service.createProject(request({ name: '工作流程', code: 'FLOW' }));
  const make = (input) => service.createIssue(request({ projectId: project.id, title: '工作', ...input }));
  const edit = (issue, input) => service.updateIssue(request({ id: issue.id, expectedRevision: issue.revision, ...input }));
  return { store, service, project, make, edit, options };
}
test('依賴是同專案 DAG, 拒絕循環、自我、Epic 及其他專案', (t) => {
  const { service, project, make, edit } = fixture(t);
  const a = make({ title: 'A' }), b = make({ title: 'B', blockedBy: [a.id] }), c = make({ title: 'C', blockedBy: [b.id] });
  assert.throws(() => edit(a, { blockedBy: [c.id] }), { code: 'INVALID_INPUT' });
  assert.throws(() => edit(a, { blockedBy: [a.id] }), { code: 'INVALID_INPUT' });
  assert.throws(() => edit(a, { type: 'epic' }), { code: 'INVALID_INPUT' });
  const epic = make({ type: 'epic' });
  assert.throws(() => edit(c, { blockedBy: [epic.id] }), { code: 'INVALID_INPUT' });
  assert.throws(() => make({ type: 'epic', blockedBy: [a.id] }), { code: 'INVALID_INPUT' });
  const other = service.createProject(request({ name: '其他', code: 'OTHER' }));
  assert.throws(() => service.createIssue(request({ projectId: other.id, title: '跨專案', blockedBy: [a.id] })), { code: 'INVALID_INPUT' });
  assert.throws(() => edit(c, { blockedBy: [b.id, b.id] }), { code: 'INVALID_INPUT' });
  const noChange = edit(c, { blockedBy: [b.id] });
  assert.equal(noChange.revision, 1, '相同依賴沒有不必要的異動');
  assert.equal(service.getWorkspace().issues.find(item => item.id === a.id).revision, 1, '無效操作整筆回滾');
  assert.equal(service.getWorkspace().projects.find(item => item.id === project.id).nextNumber, 5);
});
test('前置完成才可結案, 取消不解鎖, 重開保留後續狀態並標示依賴', (t) => {
  const { service, project, make, edit } = fixture(t);
  let a = make({ title: 'API' }), b = make({ title: '介面', blockedBy: [a.id] });
  const configured = service.updateStatuses(request({ projectId: project.id, expectedRevision: 1, statuses: [...project.statuses, { id: 'canceled', label: '取消', color: '#999999', category: 'canceled' }] }));
  assert.throws(() => edit(b, { state: 'done' }), { code: 'INVALID_INPUT' });
  a = edit(a, { state: 'canceled' });
  assert.throws(() => edit(b, { state: 'done' }), { code: 'INVALID_INPUT' });
  assert.equal(liveBlockers(b, service.getWorkspace().issues, configured).length, 1);
  a = edit(a, { state: 'done' });
  b = edit(b, { state: 'done' });
  assert.equal(liveBlockers(b, service.getWorkspace().issues, configured).length, 0);
  a = edit(a, { state: 'todo' });
  assert.equal(service.getWorkspace().issues.find(item => item.id === b.id).state, 'done');
  assert.equal(liveBlockers(b, service.getWorkspace().issues, configured)[0].id, a.id);
  const retry = request({ id: b.id, expectedRevision: b.revision, blockedBy: [] });
  const unlinked = service.updateIssue(retry);
  assert.deepEqual(service.updateIssue(retry), unlinked);
  assert.equal(unlinked.history.at(-1).changes[0].field, 'blockedBy');
  assert.equal(liveBlockers(unlinked, service.getWorkspace().issues, configured).length, 0);
});
test('兩位使用者反向建立依賴, 後一筆不能形成循環；舊資料預設無依賴', (t) => {
  const { store, service, project, make, options } = fixture(t);
  const a = make({ title: 'A' }), b = make({ title: 'B' });
  const peer = createService(store, { ...options, userId: 'bob', actor: 'Bob', role: 'member' });
  service.updateIssue(request({ id: a.id, expectedRevision: 1, blockedBy: [b.id] }));
  assert.throws(() => peer.updateIssue(request({ id: b.id, expectedRevision: 1, blockedBy: [a.id] })), { code: 'INVALID_INPUT' });
  store.transaction(tx => { const record = tx.get('issues', b.id); delete record.blockedBy; tx.put('issues', b.id, record); });
  const old = service.getWorkspace().issues.find(item => item.id === b.id);
  assert.deepEqual(liveBlockers(old, service.getWorkspace().issues, project), []);
  assert.equal(service.updateIssue(request({ id: b.id, expectedRevision: 1, title: old.title })).revision, 1);
});
