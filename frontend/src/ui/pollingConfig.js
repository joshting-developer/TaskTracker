// 背景輪詢間隔；可於建置時以VITE_POLL_*_MS覆寫，低於5秒或非數字的設定忽略，避免誤設造成請求風暴。
const env = import.meta.env || {};

function milliseconds(name, fallback) {
  const value = Number(env[name]);
  return Number.isFinite(value) && value >= 5000 ? value : fallback;
}

export const pollIntervals = Object.freeze({
  board: milliseconds('VITE_POLL_BOARD_MS', 30000),
  notifications: milliseconds('VITE_POLL_NOTIFICATIONS_MS', 60000),
  time: milliseconds('VITE_POLL_TIME_MS', 30000),
});
