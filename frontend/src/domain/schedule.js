// TT-383：時程三視圖共用的日期真值與排版（純函式）。只用計畫開始／結束日, 到期日另外顯示不當條狀。

/** 單日毫秒數 */
const DAY = 86400000;

/**
 * YYYY-MM-DD 位移天數, 以 UTC 計算避免時區與夏令時間影響。
 *
 * @param {string} date 計畫日
 * @param {number} days 位移天數
 * @returns {string} 位移後日期
 */
export function addDays(date, days) {
  return new Date(Date.parse(`${date}T00:00:00Z`) + days * DAY).toISOString().slice(0, 10);
}

/**
 * 兩日相差天數（to - from）。
 *
 * @param {string} from 起日
 * @param {string} to 迄日
 * @returns {number} 天數
 */
export function daysBetween(from, to) {
  return Math.round((Date.parse(`${to}T00:00:00Z`) - Date.parse(`${from}T00:00:00Z`)) / DAY);
}

/**
 * 卡片的排程區間；單端視為單日, 兩端皆空回 null（未排程）。與後端 schedule 查詢條件一致。
 *
 * @param {{startDate?: string, endDate?: string}} issue 卡片
 * @returns {{start: string, end: string}|null} 含首尾的區間
 */
export function scheduleSpan(issue) {
  const start = issue.startDate || issue.endDate || '', end = issue.endDate || issue.startDate || '';
  return start ? { start, end } : null;
}

/**
 * 日期所在週, 週一起算。
 *
 * @param {string} date 任一日
 * @returns {{from: string, to: string, days: string[]}} 週一至週日
 */
export function weekOf(date) {
  const offset = (new Date(`${date}T00:00:00Z`).getUTCDay() + 6) % 7, from = addDays(date, -offset);
  const days = Array.from({ length: 7 }, (_, index) => addDays(from, index));
  return { from, to: days[6], days };
}

/**
 * 把區間內的卡片排成不互相遮蓋的列：依開始日、結束日排序後, 放進第一個已空出的列。
 * 超出區間的部分裁掉並標記, 畫面可顯示「延續」。
 *
 * @param {Array<object>} issues 後端已依時程排序的卡片
 * @param {string} from 區間起日（含）
 * @param {string} to 區間迄日（含）
 * @returns {Array<{issue: object, start: number, end: number, lane: number, continuesBefore: boolean, continuesAfter: boolean}>} 以區間第0天起算的欄位
 */
export function layoutBars(issues, from, to) {
  const lanes = [], width = daysBetween(from, to);
  return issues.map(issue => ({ issue, span: scheduleSpan(issue) }))
    .filter(({ span }) => span && span.start <= to && span.end >= from)
    .sort((a, b) => a.span.start.localeCompare(b.span.start) || a.span.end.localeCompare(b.span.end))
    .map(({ issue, span }) => {
      const start = Math.max(0, daysBetween(from, span.start)), end = Math.min(width, daysBetween(from, span.end));
      let lane = lanes.findIndex(last => last < start);
      if (lane < 0) lane = lanes.push(end) - 1;
      else lanes[lane] = end;
      return { issue, start, end, lane, continuesBefore: span.start < from, continuesAfter: span.end > to };
    });
}
