import { onScopeDispose, readonly, shallowRef, watch } from 'vue';

const ACCESS_CODES = ['FORBIDDEN', 'NOT_FOUND', 'WORKSPACE_CHANGED'];
const IGNORED_CODES = ['ABORTED', 'STALE_RESPONSE'];
const ACTIVITY_EVENTS = ['pointerdown', 'keydown', 'wheel', 'touchstart'];

/**
 * 依失敗次數計算下一輪等待：基準間隔加倍至上限，再加±10% jitter。
 *
 * @param {number} interval 基準毫秒
 * @param {number} failures 連續失敗次數
 * @param {number} maxBackoff 退避上限毫秒
 * @param {() => number} [random] 測試可替換的亂數
 * @returns {number} 等待毫秒
 */
export function pollDelay(interval, failures, maxBackoff, random = Math.random) {
  const base = failures > 0 ? Math.min(maxBackoff, interval * 2 ** Math.min(failures, 10)) : interval;
  return Math.round(base * (0.9 + random() * 0.2));
}

/**
 * 背景輪詢生命週期：只有啟用、分頁可見且在線時排程；隱藏、離線、停用或卸載即停止並通知呼叫端取消請求。
 * 背景輪次不重疊；回到前景／恢復連線／取得焦點時若距上次開始超過minGap才立即補查，否則只重新排程。
 * 呼叫端以report回報每次載入結果：成功重設退避；5xx／429／網路錯誤退避；401停止；403／404／換Workspace停止直到restart。
 * 一段時間沒有使用者操作時延長間隔，恢復操作即回到基準。請求的世代、AbortController與畫面清理仍由呼叫端管理。
 *
 * @param {object} options
 * @param {() => Promise<unknown>|unknown} options.task 一輪背景檢查
 * @param {number} options.interval 基準間隔毫秒
 * @param {import('vue').Ref<boolean>|(() => boolean)} [options.enabled] 業務條件（登入、權限等）；轉為false即停止
 * @param {() => void} [options.onPause] 停止時取消進行中請求
 * @param {number} [options.minGap] 前景恢復時兩次檢查的最短間隔
 * @param {number} [options.maxBackoff] 失敗退避上限
 * @param {number} [options.idleAfter] 多久沒有操作視為閒置
 * @param {number} [options.idleInterval] 閒置時的間隔
 */
export function usePolling({ task, interval, enabled = () => true, onPause, minGap = 5000, maxBackoff = 300000, idleAfter = 300000, idleInterval = Math.max(interval, 120000) }) {
  const isEnabled = typeof enabled === 'function' ? enabled : () => enabled.value;
  const visible = shallowRef(!document.hidden), halted = shallowRef('');
  let timer, inflight = null, lastStart = -Infinity, lastActivity = Date.now(), failures = 0, disposed = false;
  const online = () => typeof navigator === 'undefined' || navigator.onLine !== false;
  const running = () => !disposed && !halted.value && isEnabled() && visible.value && online();
  const idle = () => Date.now() - lastActivity >= idleAfter;

  function schedule() {
    clearTimeout(timer);
    timer = undefined;
    if (running()) timer = setTimeout(tick, pollDelay(failures ? interval : idle() ? idleInterval : interval, failures, maxBackoff));
  }
  async function tick() {
    timer = undefined;
    // 上一輪尚未結束時不疊加，等它完成後再排下一輪。
    if (!inflight) await runTask();
    schedule();
  }
  function runTask() {
    if (!running()) return Promise.resolve();
    lastStart = Date.now();
    const current = inflight = Promise.resolve().then(task).catch(() => {}).finally(() => { if (inflight === current) inflight = null; });
    return current;
  }
  function pause() {
    clearTimeout(timer);
    timer = undefined;
    inflight = null;
    onPause?.();
  }
  function resume() {
    if (!running()) { pause(); return; }
    if (!inflight && Date.now() - lastStart >= minGap) runTask().then(schedule);
    else schedule();
  }
  /** 範圍變更或重新取得資格後使用：解除停止狀態並立即查詢一輪。 */
  function restart() {
    halted.value = '';
    failures = 0;
    clearTimeout(timer);
    timer = undefined;
    if (!running()) return Promise.resolve();
    inflight = null;
    return runTask().then(schedule);
  }
  /** 解除停止狀態並只恢復排程，適用呼叫端已自行送出首次載入時。 */
  function start() {
    halted.value = '';
    failures = 0;
    schedule();
  }
  /**
   * 回報一次載入結果（背景或前景皆可），決定退避或停止。
   *
   * @param {{code?:string,status?:number}|null|undefined} failure 失敗；成功傳null
   */
  function report(failure) {
    if (!failure) {
      failures = 0;
      if (halted.value) { halted.value = ''; schedule(); }
      return;
    }
    if (IGNORED_CODES.includes(failure.code)) return;
    if (failure.code === 'UNAUTHENTICATED' || failure.status === 401) { halted.value = 'unauthenticated'; pause(); return; }
    if (ACCESS_CODES.includes(failure.code)) { halted.value = 'access'; pause(); return; }
    failures++;
    // 已排定的下一輪改用退避間隔，避免前景失敗後仍照原間隔重打。
    if (timer) schedule();
  }

  function visibility() { visible.value = !document.hidden; if (visible.value) resume(); else pause(); }
  function offline() { pause(); }
  function focus() { if (running() && !inflight && Date.now() - lastStart >= minGap) resume(); }
  function activity() {
    const wasIdle = idle();
    lastActivity = Date.now();
    if (wasIdle && running()) resume();
  }
  document.addEventListener('visibilitychange', visibility);
  window.addEventListener('online', resume);
  window.addEventListener('offline', offline);
  window.addEventListener('focus', focus);
  ACTIVITY_EVENTS.forEach(name => window.addEventListener(name, activity, { passive: true }));
  // 啟用只恢復排程；需要立即載入的範圍變更由呼叫端呼叫restart，避免同一變更送出兩次。
  watch(isEnabled, value => { if (value) schedule(); else pause(); });
  onScopeDispose(() => {
    disposed = true;
    pause();
    document.removeEventListener('visibilitychange', visibility);
    window.removeEventListener('online', resume);
    window.removeEventListener('offline', offline);
    window.removeEventListener('focus', focus);
    ACTIVITY_EVENTS.forEach(name => window.removeEventListener(name, activity));
  });
  return { visible: readonly(visible), halted: readonly(halted), restart, start, report };
}
