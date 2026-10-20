import { categoryFor, fail, PRIORITIES, statusesFor, textField } from './model.js';

export const VIEW_DEFAULTS = { search: '', scope: 'all', state: '', priority: '', assignee: '', epicId: '', type: '', condition: '', sort: 'updated', view: 'list' };
export const CONDITIONS = [
  { id: '', label: '所有情況' }, { id: 'open', label: '尚未結案' },
  { id: 'overdue', label: '已逾期' }, { id: 'unassigned', label: '尚未指派' },
];
export function normalizeFilters(input = {}) {
  if (!input || typeof input !== 'object' || Array.isArray(input)) fail('INVALID_INPUT', '視圖篩選格式不正確');
  if (Object.keys(input).some((key) => !Object.hasOwn(VIEW_DEFAULTS, key))) fail('INVALID_INPUT', '視圖含不支援的篩選欄位');
  const result = Object.fromEntries(Object.entries(VIEW_DEFAULTS).map(([key, fallback]) => [key, textField(input[key] ?? fallback, '篩選條件', key === 'search' ? 180 : 100)]));
  for (const [key, allowed] of Object.entries({ scope: ['all', 'mine', 'done'], priority: ['', ...PRIORITIES.map(item => item.id)], type: ['', 'task', 'bug', 'feature'], condition: CONDITIONS.map(item => item.id), sort: ['updated', 'priority', 'due'], view: ['list', 'board'] })) {
    if (!allowed.includes(result[key])) fail('INVALID_INPUT', '視圖篩選選項不正確');
  }
  return result;
}
export function missingFilters(filters, project, members, issues) {
  const messages = [];
  if (['blocked', 'review'].includes(filters.condition)) messages.push('前置任務篩選已停用, 請改選情況並更新視圖');
  if (filters.state && !statusesFor(project).some((state) => state.id === filters.state)) messages.push('狀態已不存在');
  if (filters.assignee && !members.some((member) => member.id === filters.assignee)) messages.push('成員已不存在');
  if (filters.epicId && filters.epicId !== 'unlinked' && !issues.some((issue) => issue.id === filters.epicId && issue.type === 'epic' && issue.projectId === project.id)) messages.push('Epic 已不存在');
  return messages;
}
export function validateFilters(input, project, members, issues) {
  const filters = normalizeFilters(input);
  if (missingFilters(filters, project, members, issues).length) fail('INVALID_INPUT', '篩選參照的狀態、成員或 Epic 不存在, 請重新選擇');
  return filters;
}
export function filterIssues(issues, filters, { project, userId, members, today }) {
  const term = filters.search.trim().toLowerCase();
  return issues.filter((issue) => {
    if (issue.projectId !== project.id || issue.type === 'epic') return false;
    const category = categoryFor(issue, project), closed = ['done', 'canceled'].includes(category);
    if (filters.scope === 'mine' && issue.assignee !== userId) return false;
    if (filters.scope === 'done' && category !== 'done') return false;
    if (filters.state && issue.state !== filters.state) return false;
    if (filters.priority && issue.priority !== filters.priority) return false;
    if (filters.assignee && issue.assignee !== filters.assignee) return false;
    if (filters.type && issue.type !== filters.type) return false;
    if (filters.epicId && (filters.epicId === 'unlinked' ? Boolean(issue.epicId) : issue.epicId !== filters.epicId)) return false;
    if (filters.condition === 'open' && closed) return false;
    if (!CONDITIONS.some(condition => condition.id === filters.condition)) return false;
    if (filters.condition === 'overdue' && (closed || !issue.dueDate || issue.dueDate >= today)) return false;
    if (filters.condition === 'unassigned' && issue.assignee) return false;
    const name = members.find((member) => member.id === issue.assignee)?.name || issue.assignee;
    return !term || [issue.title, issue.description, issue.identifier, name].some((value) => String(value || '').toLowerCase().includes(term));
  }).sort((a, b) => {
    if (filters.sort === 'priority') return PRIORITIES.findIndex(item => item.id === a.priority) - PRIORITIES.findIndex(item => item.id === b.priority) || b.updatedAt.localeCompare(a.updatedAt);
    if (filters.sort === 'due') return (a.dueDate || '9999').localeCompare(b.dueDate || '9999') || b.updatedAt.localeCompare(a.updatedAt);
    return b.updatedAt.localeCompare(a.updatedAt) || a.identifier.localeCompare(b.identifier);
  });
}
