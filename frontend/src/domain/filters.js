import { categoryFor, PRIORITIES, statusesFor } from './model.js';

export const CONDITIONS = [
  { id: '', label: '所有情況' }, { id: 'open', label: '尚未結案' },
  { id: 'overdue', label: '已逾期' }, { id: 'unassigned', label: '尚未指派' },
];
export function missingFilters(filters, project, members, issues) {
  const messages = [];
  if (filters.state && !statusesFor(project).some((state) => state.id === filters.state)) messages.push('狀態已不存在');
  if (filters.assignee && !members.some((member) => member.id === filters.assignee)) messages.push('成員已不存在');
  if (filters.epicId && filters.epicId !== 'unlinked' && !issues.some((issue) => issue.id === filters.epicId && issue.type === 'epic' && issue.projectId === project.id)) messages.push('Epic 已不存在');
  return messages;
}
export function filterIssues(issues, filters, { project, userId, members, today, includeEpics = false }) {
  const term = filters.search.trim().toLowerCase();
  return issues.filter((issue) => {
    if (issue.projectId !== project.id || (!includeEpics && issue.type === 'epic')) return false;
    const category = categoryFor(issue, project), closed = ['done', 'canceled'].includes(category);
    if (filters.scope === 'mine' && issue.assignee !== userId) return false;
    if (filters.scope === 'done' && category !== 'done') return false;
    if (filters.state && issue.state !== filters.state) return false;
    if (filters.priority && issue.priority !== filters.priority) return false;
    if (filters.assignee && issue.assignee !== filters.assignee) return false;
    if (filters.type && issue.type !== filters.type) return false;
    const epicId = issue.type === 'epic' ? issue.id : issue.epicId;
    if (filters.epicId && (filters.epicId === 'unlinked' ? Boolean(epicId) : epicId !== filters.epicId)) return false;
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
