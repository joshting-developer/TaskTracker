// 舊待辦只在編輯時組合原標題及說明，新格式以完整content為準。
export function todoSource(todo) {
  return typeof todo.content === 'string' ? todo.content : [todo.title, todo.note].filter(Boolean).join('\n\n');
}

const TAIPEI = 'Asia/Taipei';
const parts = value => Object.fromEntries(new Intl.DateTimeFormat('en-CA', { timeZone: TAIPEI, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(new Date(value)).map(part => [part.type, part.value]));

/**
 * TT-313：小待辦列表要顯示的日期；完成看完成時間, 退回看退回時間（回到自己手上的時間）, 其餘看交辦／建立時間。
 * 一律以台北時區換算, 今年只顯示月/日, 完整日期時間放在title。
 *
 * @param {object} item 待辦摘要
 * @param {string} userId 目前使用者
 * @param {Date} [now] 判斷是否同一年用的現在時間
 * @returns {{label: string, full: string, at: string}} 顯示文字、完整時間與ISO時間
 */
export function todoDate(item, userId, now = new Date()) {
  const [at, verb] = item.state === 'done' && item.completedAt ? [item.completedAt, '完成']
    : item.returnedAt ? [item.returnedAt, '退回']
      : [item.createdAt, item.createdBy === userId && item.initialAssigneeId === userId ? '建立' : '交辦'];
  if (!at) return { label: '', full: '', at: '' };
  const date = parts(at), thisYear = parts(now).year;
  const short = `${Number(date.month)}/${Number(date.day)}`;
  return { label: `${date.year === thisYear ? short : `${date.year}/${short}`} ${verb}`, full: `${date.year}-${date.month}-${date.day} ${date.hour}:${date.minute}`, at };
}
