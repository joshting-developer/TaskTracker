import assert from 'node:assert/strict';
import { computed, effectScope, nextTick, shallowRef } from 'vue';
import { useIssueQuery } from '../src/composables/useIssueQuery.js';
import { useIssueTransitions } from '../src/composables/useIssueTransitions.js';

const scope = effectScope();
const project = shallowRef({ id: 'p', statuses: [{ id: 'open', label: '待規劃' }, { id: 'closed', label: '已關閉' }] });
const issues = shallowRef(Array.from({ length: 23 }, (_, index) => ({ id: String(index), projectId: 'p', identifier: `P-${index}`, type: 'task', title: index === 22 ? '登入' : '維護', state: 'open', priority: 'normal', assignee: 'u', updatedAt: '2026-11-06' })));
const view = shallowRef('list'), taskScope = shallowRef('all'), projectId = shallowRef('p');
const query = scope.run(() => useIssueQuery({ project, issues, boardIssues: issues, members: shallowRef([{ id: 'u', name: '成員' }]), userId: shallowRef('u'), today: shallowRef('2026-11-06'), view, scope: taskScope, projectId }));
assert.equal(query.visibleIssues.value.length, 23);
assert.equal(query.pageCount.value, 2);
query.page.value = 2;
assert.equal(query.pagedIssues.value.length, 3);
query.filterInput.value = { ...query.filterInput.value, search: '登入' }; await nextTick();
assert.equal(query.page.value, 1);
assert.deepEqual(query.visibleIssues.value.map(issue => issue.id), ['22']);
query.filterInput.value = { ...query.filterInput.value, state: ['removed'] }; await nextTick();
assert.equal(query.visibleIssues.value.length, 0, '已移除狀態不放寬條件');
assert.deepEqual(query.filterWarnings.value, ['狀態已不存在']);
query.clearFilters(); await nextTick();
assert.equal(query.visibleIssues.value.length, 23);
view.value = 'board'; await nextTick();
assert.equal(query.visibleIssues.value.length, 23);
issues.value = issues.value.slice(0, 1); await nextTick();
assert.equal(query.pageCount.value, 1);
scope.stop();

const transitionsScope = effectScope();
const contextKey = shallowRef('u:w:p'), canWrite = shallowRef(true);
let fail = true, pending, saved = [], errors = [], calls = [];
const transitions = transitionsScope.run(() => useIssueTransitions({
  contextKey, canWrite, saved: async result => saved.push(result.id), refresh: async () => {}, onError: message => errors.push(message),
  update: async input => {
    calls.push(input);
    if (fail) { fail = false; throw new Error('網路失敗'); }
    return new Promise(resolve => { pending = () => resolve({ id: input.id }); });
  },
}));
const issue = { id: 'i', revision: 2, state: 'open' };
await transitions.moveIssue(issue, { state: 'closed' });
assert.deepEqual(errors, ['網路失敗']);
const retry = transitions.moveIssue(issue, { state: 'closed' });
assert.equal(calls[0].requestId, calls[1].requestId, '同版本與內容重試沿用識別碼');
await transitions.moveIssue(issue, { state: 'closed' });
assert.equal(calls.length, 2, '送出中不重複提交');
pending(); await retry;
assert.deepEqual(saved, ['i']);
const stale = transitions.moveIssue(issue, { state: 'closed' });
const finishStale = pending;
contextKey.value = 'u:other:p';
finishStale(); await stale;
assert.deepEqual(saved, ['i'], '舊工作區回應不寫入新UI');
const disposed = transitions.moveIssue(issue, { state: 'closed' });
const finishDisposed = pending;
assert.notEqual(calls.at(-1).requestId, calls[2].requestId, '工作區變更不共用舊重試識別');
transitionsScope.stop(); finishDisposed(); await disposed;
assert.deepEqual(saved, ['i'], '離頁後不呼叫已卸載畫面');
assert.equal(transitions.moving.value, '');
console.log('PASS: 任務篩選／移除狀態／分頁收斂、重試識別、送出防重複、跨工作區與卸載過期回應');

// 一般重讀可合併，寫入後／即時訊號要尾端再讀；跨scope不等舊請求。
const { useRefreshQueue } = await import('../src/composables/useRefreshQueue.js');
const refreshScope = effectScope();
let refreshKey = 'u:w', loads = 0;
const gates = [];
const refresh = refreshScope.run(() => useRefreshQueue({ scope: () => refreshKey, load: () => { loads++; return new Promise(resolve => gates.push(resolve)); } }));
const one = refresh({ join: true });
assert.equal(refresh({ join: true }), one);
assert.equal(loads, 1, '雙擊／輪詢共用一個請求');
const afterWrite = refresh();
assert.equal(afterWrite, one);
gates.shift()(); await new Promise(resolve => setImmediate(resolve));
assert.equal(loads, 2, '寫入後至少重讀一次，不能套用寫入前結果');
gates.shift()(); await afterWrite;
const old = refresh(); refresh();
refreshKey = 'u:other';
const other = refresh({ join: true });
assert.equal(loads, 4, '新空間立即開始，不等舊空間');
gates.shift()(); await old;
assert.equal(loads, 4, '舊scope未完成的尾端刷新不再執行');
gates.shift()(); await other;
refreshScope.stop();

const { buildIssueIndex } = await import('../src/domain/issueIndex.js');
const { epicSummary } = await import('../src/domain/model.js');
const fixtures = [
  { id: 'epic', type: 'epic', state: 'closed' },
  { id: 'parent', type: 'task', epicId: 'epic', state: 'closed', estimateMinutes: 40 },
  { id: 'child', type: 'bug', epicId: 'epic', parentIssueId: 'parent', state: 'open', estimateMinutes: 20 },
  { id: 'other', type: 'task', state: 'open' },
];
const entries = [{ issueId: 'epic', minutes: 10 }, { issueId: 'parent', minutes: 25 }, { issueId: 'child', minutes: 5 }, { issueId: 'child', minutes: 99, voidedAt: '2026-11-09' }, { issueId: 'other', minutes: 15 }];
const index = buildIssueIndex(fixtures, entries);
assert.deepEqual(index.summaries.get('epic'), epicSummary(fixtures[0], fixtures, entries));
assert.equal(index.summaries.get('epic').spentMinutes, 40, 'Epic自身與各子卡紀錄只加一次');
assert.equal(index.spent.get('child'), 5);
assert.deepEqual(index.children.get('parent'), [fixtures[2]]);
assert.equal(buildIssueIndex(fixtures, []).spent.size, 0, '刷新替換資料後不沿用舊索引');
console.log('PASS: 刷新合併／寫後尾端重讀／跨scope、共享卡片關聯與有效工時索引');
