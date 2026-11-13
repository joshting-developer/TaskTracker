import { isClosed, PRIORITIES, statusesFor } from './model.js';

export const CONDITIONS = [
  { id: '', label: '所有情況' },
  { id: 'overdue', label: '已逾期' }, { id: 'unassigned', label: '尚未指派' },
];
export function selectedStates(value) { return Array.isArray(value) ? value : value ? [value] : []; }
export function missingFilters(filters, project, members, issues) {
  const messages = [];
  if (selectedStates(filters.state).some(id => !statusesFor(project).some(state => state.id === id))) messages.push('狀態已不存在');
  if (filters.assignee && !members.some((member) => member.id === filters.assignee)) messages.push('成員已不存在');
  if (filters.epicId && filters.epicId !== 'unlinked' && !issues.some((issue) => issue.id === filters.epicId && issue.type === 'epic' && issue.projectId === project.id)) messages.push('Epic 已不存在');
  return messages;
}
export function filterIssues(issues, filters, { project, userId, members, today, includeEpics = false }) {
  const term = filters.search.trim().toLowerCase();
  const exact = /^[a-z][a-z0-9_]{0,11}-[1-9][0-9]*$/.test(term);
  const prefix = /^[a-z][a-z0-9_]{0,11}-[0-9]*\*$/.test(term);
  const literal = term.startsWith('text:') ? term.slice(5) : term;
  return issues.filter((issue) => {
    if (issue.projectId !== project.id || (!includeEpics && issue.type === 'epic')) return false;
    const closed = isClosed(issue);
    if (filters.scope === 'mine' && issue.assignee !== userId) return false;
    if (selectedStates(filters.state).length && !selectedStates(filters.state).includes(issue.state)) return false;
    if (filters.priority && issue.priority !== filters.priority) return false;
    if (filters.assignee && issue.assignee !== filters.assignee) return false;
    if (filters.type && issue.type !== filters.type) return false;
    const epicId = issue.type === 'epic' ? issue.id : issue.epicId;
    if (filters.epicId && (filters.epicId === 'unlinked' ? Boolean(epicId) : epicId !== filters.epicId)) return false;
    if (!CONDITIONS.some(condition => condition.id === filters.condition)) return false;
    if (filters.condition === 'overdue' && (closed || !issue.dueDate || issue.dueDate >= today)) return false;
    if (filters.condition === 'unassigned' && issue.assignee) return false;
    const name = members.find((member) => member.id === issue.assignee)?.name || issue.assignee;
    if (exact) return issue.identifier.toLowerCase() === term;
    if (prefix) return issue.identifier.toLowerCase().startsWith(term.slice(0, -1));
    return !term || [issue.title, issue.description, issue.identifier, name].some((value) => String(value || '').toLowerCase().includes(literal));
  }).sort((a, b) => {
    // TT-306：跟後端同一套規則；sign=1是各排序的預設方向, 反向時整個主排序反過來（到期日沒填的也跟著換到最前）。
    const sign = filters.direction && filters.direction !== defaultDirection(filters.sort) ? -1 : 1;
    const tie = (b.updatedAt || '').localeCompare(a.updatedAt || '') || a.identifier.localeCompare(b.identifier);
    if (filters.sort === 'priority') return sign * (PRIORITIES.findIndex(item => item.id === a.priority) - PRIORITIES.findIndex(item => item.id === b.priority)) || tie;
    if (filters.sort === 'due') return sign * (a.dueDate || '9999').localeCompare(b.dueDate || '9999') || tie;
    if (filters.sort === 'created') return sign * (b.createdAt || '').localeCompare(a.createdAt || '') || tie;
    return sign * (b.updatedAt || '').localeCompare(a.updatedAt || '') || a.identifier.localeCompare(b.identifier);
  });
}

/**
 * 各排序沒指定方向時的預設：更新／建立新到舊, 優先級緊急到低, 到期日近到遠。
 *
 * @param {string} sort updated／created／priority／due
 * @returns {'asc'|'desc'} 預設方向
 */
export function defaultDirection(sort) {
  return sort === 'priority' || sort === 'due' ? 'asc' : 'desc';
}
