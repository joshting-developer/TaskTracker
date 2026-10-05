import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { randomUUID } from 'node:crypto';
import { createService } from '../src/domain/service.js';
import { createSqliteStore } from '../server/repository.js';

const options = { uuid: randomUUID, now: () => new Date().toISOString() };
function createProject(service, input = {}) { return service.createProject({ name: '第一版', code: 'TT', requestId: randomUUID(), ...input }); }
function createIssue(service, project, input = {}) { return service.createIssue({ projectId: project.id, title: '第一個任務', requestId: randomUUID(), ...input }); }
const backends = {
  SQLite() { const store = createSqliteStore(':memory:'); return { store, cleanup: () => store.close() }; },
};
for (const [name, factory] of Object.entries(backends)) {
  test(`${name}: 保存任務、修改、留言, 同一 requestId 重試回傳相同結果`, (t) => {
    const { store, cleanup } = factory(); t.after(cleanup);
    const service = createService(store, options);
    const project = createProject(service);
    const input = { projectId: project.id, title: '寫完第一版', requestId: randomUUID() };
    const issue = service.createIssue(input);
    assert.deepEqual(service.createIssue(input), issue);
    assert.equal(issue.identifier, 'TT-1');
    const update = { id: issue.id, state: 'in_progress', expectedRevision: 1, requestId: randomUUID() };
    const changed = service.updateIssue(update);
    assert.deepEqual(service.updateIssue(update), changed);
    assert.equal(changed.history.length, 2);
    assert.equal(changed.history[1].changes[0].field, 'state');
    const comment = { id: issue.id, content: '第一版做完再試', expectedRevision: 2, requestId: randomUUID() };
    const commented = service.addComment(comment);
    assert.deepEqual(service.addComment(comment), commented);
    assert.equal(commented.comments.length, 1);
    assert.equal(commented.revision, 3);
    assert.equal(service.getWorkspace().issues.length, 1);
    assert.equal(createIssue(service, project).identifier, 'TT-2');
  });
  test(`${name}: 拒絕舊版本覆寫、不同內容重用 requestId、重複專案代碼及無效日期`, (t) => {
    const { store, cleanup } = factory(); t.after(cleanup);
    const service = createService(store, options);
    const project = createProject(service);
    const issue = createIssue(service, project);
    const requestId = randomUUID();
    service.updateIssue({ id: issue.id, state: 'done', expectedRevision: 1, requestId });
    assert.throws(() => service.updateIssue({ id: issue.id, title: '舊修改', expectedRevision: 1, requestId: randomUUID() }), { code: 'REVISION_CONFLICT' });
    assert.throws(() => service.updateIssue({ id: issue.id, state: 'todo', expectedRevision: 1, requestId }), { code: 'REQUEST_CONFLICT' });
    assert.throws(() => createProject(service, { code: 'tt' }), { code: 'DUPLICATE_CODE' });
    for (const dueDate of ['2026-02-30', '2026-99-99', '2026-2-3']) assert.throws(() => createIssue(service, project, { dueDate }), { code: 'INVALID_INPUT' });
    assert.equal(service.getWorkspace().issues[0].state, 'done');
    assert.equal(service.getWorkspace().projects[0].nextNumber, 2);
  });
  test(`${name}: 示範資料需主動建立且不重複`, (t) => {
    const { store, cleanup } = factory(); t.after(cleanup);
    const service = createService(store, options);
    assert.equal(service.getWorkspace().issues.length, 0);
    const input = { requestId: randomUUID() };
    assert.deepEqual(service.loadDemo(input), service.loadDemo(input));
    assert.equal(service.getWorkspace().issues.length, 9);
    assert.throws(() => service.loadDemo({ requestId: randomUUID() }), { code: 'DEMO_EXISTS' });
  });
}
test('SQLite: 關閉後重新開啟仍保存資料', () => {
  const directory = mkdtempSync(join(tmpdir(), 'tasktracker-persist-'));
  const filename = join(directory, 'data.sqlite');
  let store = createSqliteStore(filename);
  try {
    const service = createService(store, options);
    const project = createProject(service);
    createIssue(service, project, { title: '重新開啟仍在' });
    store.close();
    store = createSqliteStore(filename);
    assert.equal(createService(store, options).getWorkspace().issues[0].title, '重新開啟仍在');
  } finally { store.close(); rmSync(directory, { recursive: true, force: true }); }
});
