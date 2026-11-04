// 僅保存目前分頁的查閱位置；不保存內容、表單或授權結果。
export function contextKey(userId, workspaceId, projectId) {
  return userId && workspaceId && projectId ? `tasktracker-context:${JSON.stringify([userId, workspaceId, projectId])}` : '';
}
export function readContext(key) {
  try {
    const value = key ? JSON.parse(sessionStorage.getItem(key)) : null;
    return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
  } catch { return {}; }
}
export function writeContext(key, patch) {
  if (!key) return;
  try { sessionStorage.setItem(key, JSON.stringify({ ...readContext(key), ...patch })); } catch { /* 禁用 storage 時照常使用介面 */ }
}
export function taskScope(key) {
  const value = readContext(key).scope;
  return ['all', 'mine', 'done'].includes(value) ? value : 'all';
}
export function timeSelection(key, members, userId, today) {
  const saved = readContext(key).time || {};
  const month = typeof saved.month === 'string' && /^(?:[1-9]\d{3})-(?:0[1-9]|1[0-2])$/.test(saved.month) ? saved.month : today.slice(0, 7);
  const own = members.some(person => person.id === userId) ? userId : '';
  const memberValid = saved.member === '' || members.some(person => person.id === saved.member);
  const member = memberValid ? saved.member : own;
  const validDate = typeof saved.date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(saved.date)
    && saved.date.startsWith(`${month}-`) && !Number.isNaN(Date.parse(saved.date)) && new Date(saved.date).toISOString().slice(0, 10) === saved.date;
  return { month, member, date: validDate ? saved.date : month === today.slice(0, 7) ? today : `${month}-01`, resetMember: typeof saved.member === 'string' && !memberValid };
}
