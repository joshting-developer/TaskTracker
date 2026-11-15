import { formatNumber, t } from '../i18n/index.js';
export const STATES = [
  { id: 'open', label: t('待規劃'), color: '#9a9da9' },
  { id: 'inprogress', label: t('進行中'), color: '#e7a443' },
  { id: 'closed', label: t('已關閉'), color: '#4aab8a' },
];
export const PRIORITIES = [
  { id: 'urgent', label: t('緊急'), color: '#e26669' },
  { id: 'high', label: t('高'), color: '#d89738' },
  { id: 'normal', label: t('一般'), color: '#85899b' },
  { id: 'low', label: t('低'), color: '#aeb1bd' },
];
export const TYPES = [
  { id: 'epic', label: 'Epic' },
  { id: 'task', label: 'Task' },
  { id: 'bug', label: 'Bug' },
];
export const PROJECT_COLORS = ['#8d83ed', '#64b0a0', '#e5a25c', '#cf82ae', '#71a2d5'];
export const ISSUE_FIELDS = ['title', 'description', 'state', 'priority', 'type', 'assignee', 'startDate', 'endDate', 'dueDate', 'epicId', 'estimateMinutes', 'parentIssueId', 'relatedIssueIds'];
export const FIELD_LABELS = { title: t('標題'), description: t('描述'), state: t('狀態'), priority: t('優先級'), type: t('類型'), assignee: t('負責人'), startDate: t('計畫開始日'), endDate: t('計畫結束日'), dueDate: t('到期日'), epicId: 'Epic', estimateMinutes: t('預估分鐘'), parentIssueId: t('父任務'), relatedIssueIds: t('關聯任務'), blockedBy: t('舊前置任務（已停用）') };

export function fail(code, message) {
  throw Object.assign(new Error(message), { code });
}
export function textField(value, label, max, required = false) {
  if (typeof value !== 'string') fail('INVALID_INPUT', t('{0}格式不正確', [label]));
  const text = value.trim();
  if (text.length > max || (required && !text)) fail('INVALID_INPUT', t('{0}{1}最多 {2} 字', [label, required ? t('必填且') : '', max]));
  return text;
}
function enumField(value, choices, label) {
  if (!choices.some((item) => item.id === value)) fail('INVALID_INPUT', t('{0}選項不正確', [label]));
  return value;
}
export function validateIssue(input, statuses = STATES) {
  if (!input || typeof input !== 'object') fail('INVALID_INPUT', t('任務資料格式不正確'));
  const dates = {};
  for (const [field, label] of [['startDate', '計畫開始日'], ['endDate', '計畫結束日'], ['dueDate', '到期日']]) {
    const value = textField(input[field] ?? '', t(label), 10);
    const parsed = value ? new Date(`${value}T00:00:00Z`) : null;
    if (value && (!/^\d{4}-\d{2}-\d{2}$/.test(value) || Number.isNaN(parsed.getTime()) || parsed.toISOString().slice(0, 10) !== value)) fail('INVALID_INPUT', t('日期格式不正確'));
    if (field !== 'dueDate' && value && value < '1000-01-01') fail('INVALID_INPUT', t('計畫日期需介於 1000-01-01 與 9999-12-31'));
    dates[field] = value;
  }
  if (dates.startDate && dates.endDate && dates.endDate < dates.startDate) fail('INVALID_INPUT', t('計畫結束日不可早於開始日'));
  return {
    title: textField(input.title, t('標題'), 180, true),
    description: textField(input.description ?? '', t('描述'), 6000),
    state: enumField(input.state ?? 'open', statuses, t('狀態')),
    priority: enumField(input.priority ?? 'normal', PRIORITIES, t('優先級')),
    type: enumField(input.type ?? 'task', TYPES, t('類型')),
    assignee: textField(input.assignee ?? '', t('負責人'), 60),
    ...dates,
    epicId: textField(input.epicId ?? '', 'Epic', 100),
    estimateMinutes: integer(input.estimateMinutes ?? 0, t('預估分鐘'), 0, 525600),
    parentIssueId: textField(input.parentIssueId ?? '', t('父任務'), 100),
    relatedIssueIds: idList(input.relatedIssueIds ?? [], t('關聯任務'), 200),
  };
}
export function idList(value, label, max) {
  if (!Array.isArray(value) || value.length > max) fail('INVALID_INPUT', t('{0}最多 {1} 項', [label, max]));
  const ids = value.map((id) => textField(id, label, 100, true));
  if (new Set(ids).size !== ids.length) fail('INVALID_INPUT', t('{0}不可重複', [label]));
  return ids.sort();
}
export function integer(value, label, min, max) {
  if (!Number.isSafeInteger(value) || value < min || value > max) fail('INVALID_INPUT', t('{0}需為 {1}–{2} 的整數', [label, min, max]));
  return value;
}
export function validDate(value) {
  const date = textField(value, t('工作日期'), 10, true);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !Number.isFinite(new Date(`${date}T00:00:00Z`).getTime()) || new Date(`${date}T00:00:00Z`).toISOString().slice(0, 10) !== date) fail('INVALID_INPUT', t('工作日期格式不正確'));
  return date;
}
export function statusesFor(project) { return project?.statuses || STATES; }
export function isClosed(issue) { return issue.state === 'closed'; }
export function epicSummary(epic, issues, entries, project) {
  const children = issues.filter((issue) => issue.epicId === epic.id);
  const done = children.filter((issue) => isClosed(issue)).length;
  const canceled = 0;
  const total = children.length - canceled;
  return { children, done, canceled, total, percent: total ? Math.round(done / total * 100) : null,
    estimateMinutes: children.reduce((sum, issue) => sum + (issue.estimateMinutes || 0), 0),
    spentMinutes: entries.filter((entry) => !entry.voidedAt && (entry.issueId === epic.id || children.some((issue) => issue.id === entry.issueId))).reduce((sum, entry) => sum + entry.minutes, 0),
    needsReview: isClosed(epic) && done < total };
}
export function hours(minutes) { return `${formatNumber(minutes / 60, { maximumFractionDigits: 2 })} h`; }
export function canonical(value) {
  if (Array.isArray(value)) return `[${value.map(canonical).join(',')}]`;
  if (value && typeof value === 'object') return `{${Object.keys(value).sort().map((key) => `${JSON.stringify(key)}:${canonical(value[key])}`).join(',')}}`;
  return JSON.stringify(value);
}
export function dateInTaipei() {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Taipei', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
}
