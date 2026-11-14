// TT-285：hash網址解析／組成為純函式；確認既有深層連結格式不變、組回去一模一樣。
import assert from 'node:assert/strict';
import { buildHash, parseHash, settingsHash } from '../src/ui/hashRoutes.js';

const roundTrips = [
  '#/', '#/notes', '#/projects', '#/time-analysis', '#/time-analysis/members', '#/workspace/settings', '#/workspace/settings/members',
  '#/projects/p1', '#/projects/p1/boards', '#/projects/p1/board', '#/projects/p1/board/b%2F1',
  '#/projects/p1/issues/i1', '#/projects/p1/issues/i1?detail=1', '#/projects/p1/board/b1/issues/i1?detail=1',
  '#/projects/p1/time', '#/projects/p1/settings', '#/projects/p1/members', '#/projects/p1/notifications',
];
for (const hash of roundTrips) assert.equal(buildHash(parseHash(hash)), hash, hash);

assert.deepEqual(
  (({ project, board, issue, mode, detail }) => ({ project, board, issue, mode, detail }))(parseHash('#/projects/p%201/board/b%2F1/issues/i1?detail=1')),
  { project: 'p 1', board: 'b/1', issue: 'i1', mode: 'board', detail: true },
);
assert.equal(parseHash('#/issue/abc').issue, 'abc', '舊#/issue/<id>只取卡片id');
assert.equal(parseHash('#/issue/abc').project, '');
assert.equal(parseHash('#/invite?token=t%2B1').invite, 't+1');
const epics = parseHash('#/projects/p1/epics');
assert.deepEqual([epics.retiredEpics, epics.section], [true, 'tasks'], '退役的Epic頁回任務');
assert.equal(parseHash('#/projects/p1?view=old').retiredView, true);
assert.equal(parseHash('#/projects/%E0%A4%A').malformed, true, '壞掉的編碼不丟例外');
assert.equal(parseHash('#/projects/p1/issues/i1?detail=0').detail, false);
assert.equal(parseHash('#/projects/p1/boards/issues/i1').board, 'default', 'boards後面不是看板id');
// TT-329：側邊欄「我的任務」在新分頁靠?scope=mine, 只認專案任務列表
assert.equal(parseHash('#/projects/p1?scope=mine').scope, 'mine');
assert.equal(parseHash('#/projects/p1').scope, '');
assert.equal(parseHash('#/projects/p1/boards?scope=mine').scope, '', '看板不吃scope');
assert.equal(parseHash('#/projects/p1/issues/i1?scope=mine').scope, '', '卡片詳細不吃scope');
assert.equal(parseHash('#/projects/p1/time?scope=mine').scope, '', '其他功能頁不吃scope');
assert.equal(parseHash('#/projects/p1?scope=other').scope, '');
assert.equal(buildHash({ project: 'p1', issue: 'i1', section: 'time' }), '#/projects/p1/issues/i1', '開卡一律回任務區');
assert.equal(settingsHash('members'), '#/workspace/settings/members');
console.log(`PASS: ${roundTrips.length} hash round trips, legacy issue/invite/epics/view links, malformed encoding`);
