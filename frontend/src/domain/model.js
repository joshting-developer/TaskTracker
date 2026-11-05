export const STATES = [
  { id: 'backlog', label: '待規劃', color: '#9a9da9', category: 'backlog' },
  { id: 'todo', label: '待處理', color: '#7b82e8', category: 'active' },
  { id: 'in_progress', label: '進行中', color: '#e7a443', category: 'active' },
  { id: 'done', label: '已完成', color: '#4aab8a', category: 'done' },
];
export const PRIORITIES = [
  { id: 'urgent', label: '緊急', color: '#e26669' },
  { id: 'high', label: '高', color: '#d89738' },
  { id: 'normal', label: '一般', color: '#85899b' },
  { id: 'low', label: '低', color: '#aeb1bd' },
];
export const TYPES = [
  { id: 'epic', label: 'Epic' },
  { id: 'task', label: 'Task' },
  { id: 'bug', label: 'Bug' },
];
export const PROJECT_COLORS = ['#8d83ed', '#64b0a0', '#e5a25c', '#cf82ae', '#71a2d5'];
export const ISSUE_FIELDS = ['title', 'description', 'state', 'priority', 'type', 'assignee', 'dueDate', 'epicId', 'estimateMinutes', 'parentIssueId', 'relatedIssueIds'];
export const FIELD_LABELS = { title: '標題', description: '描述', state: '狀態', priority: '優先級', type: '類型', assignee: '負責人', dueDate: '到期日', epicId: 'Epic', estimateMinutes: '預估分鐘', parentIssueId: '父任務', relatedIssueIds: '關聯任務', blockedBy: '舊前置任務（已停用）' };

export function fail(code, message) {
  throw Object.assign(new Error(message), { code });
}
export function textField(value, label, max, required = false) {
  if (typeof value !== 'string') fail('INVALID_INPUT', `${label}格式不正確`);
  const text = value.trim();
  if (text.length > max || (required && !text)) fail('INVALID_INPUT', `${label}${required ? '必填且' : ''}最多 ${max} 字`);
  return text;
}
function enumField(value, choices, label) {
  if (!choices.some((item) => item.id === value)) fail('INVALID_INPUT', `${label}選項不正確`);
  return value;
}
export function validateIssue(input, statuses = STATES) {
  if (!input || typeof input !== 'object') fail('INVALID_INPUT', '任務資料格式不正確');
  const dueDate = textField(input.dueDate ?? '', '到期日', 10);
  const parsedDate = dueDate ? new Date(`${dueDate}T00:00:00Z`) : null;
  if (dueDate && (!/^\d{4}-\d{2}-\d{2}$/.test(dueDate) || Number.isNaN(parsedDate.getTime()) || parsedDate.toISOString().slice(0, 10) !== dueDate)) {
    fail('INVALID_INPUT', '到期日格式不正確');
  }
  return {
    title: textField(input.title, '標題', 180, true),
    description: textField(input.description ?? '', '描述', 6000),
    state: enumField(input.state ?? statuses.find((state) => !['done', 'canceled'].includes(state.category))?.id, statuses, '狀態'),
    priority: enumField(input.priority ?? 'normal', PRIORITIES, '優先級'),
    type: enumField(input.type ?? 'task', TYPES, '類型'),
    assignee: textField(input.assignee ?? '', '負責人', 60),
    dueDate,
    epicId: textField(input.epicId ?? '', 'Epic', 100),
    estimateMinutes: integer(input.estimateMinutes ?? 0, '預估分鐘', 0, 525600),
    parentIssueId: textField(input.parentIssueId ?? '', '父任務', 100),
    relatedIssueIds: idList(input.relatedIssueIds ?? [], '關聯任務', 200),
  };
}
export function idList(value, label, max) {
  if (!Array.isArray(value) || value.length > max) fail('INVALID_INPUT', `${label}最多 ${max} 項`);
  const ids = value.map((id) => textField(id, label, 100, true));
  if (new Set(ids).size !== ids.length) fail('INVALID_INPUT', `${label}不可重複`);
  return ids.sort();
}
export function integer(value, label, min, max) {
  if (!Number.isSafeInteger(value) || value < min || value > max) fail('INVALID_INPUT', `${label}需為 ${min}–${max} 的整數`);
  return value;
}
export function validDate(value) {
  const date = textField(value, '工作日期', 10, true);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !Number.isFinite(new Date(`${date}T00:00:00Z`).getTime()) || new Date(`${date}T00:00:00Z`).toISOString().slice(0, 10) !== date) fail('INVALID_INPUT', '工作日期格式不正確');
  return date;
}
export function statusesFor(project) { return project?.statuses || STATES; }
export function categoryFor(issue, project) { return statusesFor(project).find((state) => state.id === issue.state)?.category || 'active'; }
export function epicSummary(epic, issues, entries, project) {
  const children = issues.filter((issue) => issue.epicId === epic.id);
  const done = children.filter((issue) => categoryFor(issue, project) === 'done').length;
  const canceled = children.filter((issue) => categoryFor(issue, project) === 'canceled').length;
  const total = children.length - canceled;
  return { children, done, canceled, total, percent: total ? Math.round(done / total * 100) : null,
    estimateMinutes: children.reduce((sum, issue) => sum + (issue.estimateMinutes || 0), 0),
    spentMinutes: entries.filter((entry) => !entry.voidedAt && (entry.issueId === epic.id || children.some((issue) => issue.id === entry.issueId))).reduce((sum, entry) => sum + entry.minutes, 0),
    needsReview: categoryFor(epic, project) === 'done' && done < total };
}
export function hours(minutes) { return `${Number((minutes / 60).toFixed(2))} h`; }
export function canonical(value) {
  if (Array.isArray(value)) return `[${value.map(canonical).join(',')}]`;
  if (value && typeof value === 'object') return `{${Object.keys(value).sort().map((key) => `${JSON.stringify(key)}:${canonical(value[key])}`).join(',')}}`;
  return JSON.stringify(value);
}
export function dateInTaipei() {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Taipei', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
}
