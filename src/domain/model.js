export const STATES = [
  { id: 'backlog', label: '待規劃', color: '#9a9da9' },
  { id: 'todo', label: '待處理', color: '#7b82e8' },
  { id: 'in_progress', label: '進行中', color: '#e7a443' },
  { id: 'done', label: '已完成', color: '#4aab8a' },
];
export const PRIORITIES = [
  { id: 'urgent', label: '緊急', color: '#e26669' },
  { id: 'high', label: '高', color: '#d89738' },
  { id: 'normal', label: '一般', color: '#85899b' },
  { id: 'low', label: '低', color: '#aeb1bd' },
];
export const TYPES = [
  { id: 'task', label: '任務' },
  { id: 'feature', label: '功能' },
  { id: 'bug', label: '問題' },
];
export const PROJECT_COLORS = ['#8d83ed', '#64b0a0', '#e5a25c', '#cf82ae', '#71a2d5'];
export const ISSUE_FIELDS = ['title', 'description', 'state', 'priority', 'type', 'assignee', 'dueDate'];
export const FIELD_LABELS = { title: '標題', description: '描述', state: '狀態', priority: '優先級', type: '類型', assignee: '負責人', dueDate: '到期日' };

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
export function validateIssue(input) {
  if (!input || typeof input !== 'object') fail('INVALID_INPUT', '任務資料格式不正確');
  const dueDate = textField(input.dueDate ?? '', '到期日', 10);
  const parsedDate = dueDate ? new Date(`${dueDate}T00:00:00Z`) : null;
  if (dueDate && (!/^\d{4}-\d{2}-\d{2}$/.test(dueDate) || Number.isNaN(parsedDate.getTime()) || parsedDate.toISOString().slice(0, 10) !== dueDate)) {
    fail('INVALID_INPUT', '到期日格式不正確');
  }
  return {
    title: textField(input.title, '標題', 180, true),
    description: textField(input.description ?? '', '描述', 6000),
    state: enumField(input.state ?? 'todo', STATES, '狀態'),
    priority: enumField(input.priority ?? 'normal', PRIORITIES, '優先級'),
    type: enumField(input.type ?? 'task', TYPES, '類型'),
    assignee: textField(input.assignee ?? '', '負責人', 60),
    dueDate,
  };
}
export function canonical(value) {
  if (Array.isArray(value)) return `[${value.map(canonical).join(',')}]`;
  if (value && typeof value === 'object') return `{${Object.keys(value).sort().map((key) => `${JSON.stringify(key)}:${canonical(value[key])}`).join(',')}}`;
  return JSON.stringify(value);
}
export function dateInTaipei() {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Taipei', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
}
