// Receives only synthetic credentials from the isolated fixture over stdin.
import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';
import { callTool, ORIGIN } from '../worker/index.js';

const fixture = JSON.parse(readFileSync(0, 'utf8'));
assert.equal(fixture.baseUrl, 'http://127.0.0.1:13049');
const env = { TASKTRACKER_PAT: fixture.token, TASKTRACKER_WORKSPACE_ID: fixture.workspaceId };
const transport = async (url, options) => {
  assert.ok(url.startsWith(ORIGIN + '/api/public/v1/'));
  // Stay below the real 120/minute limit without changing server policy.
  await new Promise(resolve => setTimeout(resolve, 600));
  return fetch(fixture.baseUrl + url.slice(ORIGIN.length), options);
};
const read = async (name, args = {}) => (await callTool(name, args, env, transport)).data;
const write = async (name, args) => {
  const preview = await read(name, args);
  assert.equal(preview.requiresConfirmation, true);
  const confirmed = { ...args, confirmation: preview.confirmation };
  return { result: await read(name, confirmed), confirmed };
};
const project = fixture.projectId;
assert.equal((await read('who_am_i')).workspaceId, fixture.workspaceId);
assert.equal((await read('get_project', { project })).id, project);
assert.ok((await read('list_project_members', { project })).items.length > 0);
assert.ok((await read('list_project_statuses', { project })).items.some(s => s.id === 'open'));
const { result: created, confirmed } = await write('create_issue', { project, body: { title: 'Synthetic Bridge HTTP task', type: 'task' } });
assert.equal((await read('create_issue', confirmed)).id, created.id);
const current = await read('get_issue', { project, issue: created.id });
const { result: updated } = await write('update_issue', { project, issue: created.id, body: { expectedRevision: current.revision, title: 'Updated synthetic task' } });
assert.ok(updated.revision > current.revision);
await assert.rejects(read('update_issue', { project, issue: created.id, body: { expectedRevision: current.revision, title: 'Stale' } }), e => e.code === 'CONFLICT');
await write('comment_issue', { project, issue: created.id, body: { expectedRevision: updated.revision, content: 'Synthetic HTTP comment' } });
const comments = await read('list_issue_comments', { project, issue: created.id });
assert.equal(comments.items.length, 1);
assert.equal(comments.items[0].content, 'Synthetic HTTP comment');
assert.equal((await read('get_issue', { project, issue: current.identifier })).id, created.id);
const readOnlyEnv = { ...env, TASKTRACKER_PAT: fixture.readToken };
await assert.rejects(callTool('create_issue', { project, body: { title: 'Must not exist' } }, readOnlyEnv, transport), e => e.code === 'WRITE_SCOPE_REQUIRED');
console.log('Isolated MySQL/HTTP: project candidates, create/update/comment, identifier lookup, replay, conflict and read-only scope passed');

const noteResponse = await transport(ORIGIN + '/api/public/v1/todos', { method: 'POST', redirect: 'manual',
  headers: { Authorization: `Bearer ${fixture.token}`, 'Content-Type': 'application/json', Accept: 'application/json' },
  body: JSON.stringify({ content: 'Synthetic private note', requestId: crypto.randomUUID() }) });
const noteBody = await noteResponse.json();
assert.equal(noteBody.ok, true);
const todo = noteBody.data.id;
const note = await read('get_todo', { todo });
assert.ok(JSON.stringify(note).includes('Synthetic private note'));
assert.ok((await read('list_todos', { scope: 'mine', state: 'open' })).items.some(t => t.id === todo));
assert.ok(Array.isArray((await read('list_todo_assignees')).items));
assert.equal((await read('list_todo_comments', { todo })).items.length, 0);
assert.equal((await read('list_todo_handoffs', { todo })).items.length, 0);
console.log('Isolated MySQL/HTTP: all five todo read tools passed');

const { result: ownTodo } = await write('create_todo', { body: { content: 'Synthetic writable note' } });
const { result: revisedTodo } = await write('update_todo', { todo: ownTodo.id, body: { expectedRevision: ownTodo.revision, content: 'Revised synthetic note' } });
const { result: repliedTodo } = await write('comment_todo', { todo: ownTodo.id, body: { expectedRevision: revisedTodo.revision, content: 'Synthetic reply' } });
const { result: completedTodo } = await write('complete_todo', { todo: ownTodo.id, body: { expectedRevision: repliedTodo.revision, reply: 'Synthetic done' } });
assert.equal((await read('get_todo', { todo: ownTodo.id })).state, 'done');
const { result: reopenedTodo } = await write('reopen_todo', { todo: ownTodo.id, body: { expectedRevision: completedTodo.revision } });
assert.equal((await read('get_todo', { todo: ownTodo.id })).state, 'open');
assert.equal((await read('list_todo_comments', { todo: ownTodo.id })).items.length, 2);
console.log('Isolated MySQL/HTTP: create/update/comment/complete/reopen todo passed');
const { result: forwardedTodo } = await write('forward_todo', { todo: ownTodo.id,
  body: { expectedRevision: reopenedTodo.revision, assigneeId: fixture.recipientId, reply: 'Synthetic handoff' } });
const recipientEnv = { ...env, TASKTRACKER_PAT: fixture.recipientToken };
const returnArgs = { todo: ownTodo.id, body: { expectedRevision: forwardedTodo.revision, note: 'Synthetic return' } };
const returnPreview = (await callTool('return_todo', returnArgs, recipientEnv, transport)).data;
await callTool('return_todo', { ...returnArgs, confirmation: returnPreview.confirmation }, recipientEnv, transport);
assert.equal((await read('list_todo_handoffs', { todo: ownTodo.id })).items.length, 2);
console.log('Isolated MySQL/HTTP: todo forward and return between two synthetic members passed');

const { result: newBoard } = await write('create_board', { project, body: { name: 'Synthetic MCP board', description: 'Only synthetic HTTP checks' } });
const { result: collectedBoard } = await write('update_board', { project, board: newBoard.id,
  body: { expectedRevision: newBoard.revision, addIssueIds: [created.id] } });
assert.ok((await read('list_board_issues', { project, board: newBoard.id })).items.some(i => i.id === created.id));
await write('update_board', { project, board: newBoard.id, body: { expectedRevision: collectedBoard.revision, removeIssueIds: [created.id] } });
assert.equal((await read('list_board_issues', { project, board: newBoard.id })).items.length, 0);
console.log('Isolated MySQL/HTTP: board create and differential issue collection passed');
const beforeDelete = await read('get_issue', { project, issue: created.id });
await write('delete_issue', { project, issue: created.id, body: { expectedRevision: beforeDelete.revision } });
await assert.rejects(read('get_issue', { project, issue: created.id }), e => e.code === 'NOT_FOUND');
console.log('Isolated MySQL/HTTP: confirmed deletion of this run synthetic issue passed');
