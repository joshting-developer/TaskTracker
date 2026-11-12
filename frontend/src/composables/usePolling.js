import { onScopeDispose, readonly, shallowRef, watch } from 'vue';

/**
 * 背景輪詢生命週期：只有啟用、分頁可見且在線時排程；隱藏、離線、停用或卸載即停止並通知呼叫端取消請求。
 * 背景輪次不重疊；回到前景／恢復連線／取得焦點時若距上次開始超過minGap才立即補查，否則只重新排程。
 * 間隔、退避與jitter由呼叫端的delay決定；請求的世代與AbortController仍由呼叫端管理。
 *
 * @param {object} options
 * @param {() => Promise<unknown>|unknown} options.task 一輪背景檢查
 * @param {() => number} options.delay 下一輪等待毫秒數
 * @param {import('vue').Ref<boolean>|(() => boolean)} [options.enabled] 業務條件（登入、權限等）；轉為false即停止
 * @param {() => void} [options.onPause] 停止時取消進行中請求
 * @param {number} [options.minGap] 前景恢復時兩次檢查的最短間隔
 */
export function usePolling({ task, delay, enabled = () => true, onPause, minGap = 5000 }) {
  const isEnabled = typeof enabled === 'function' ? enabled : () => enabled.value;
  const visible = shallowRef(!document.hidden);
  let timer, inflight = null, lastStart = -Infinity, disposed = false;
  const online = () => typeof navigator === 'undefined' || navigator.onLine !== false;
  const running = () => !disposed && isEnabled() && visible.value && online();

  function schedule() {
    clearTimeout(timer);
    timer = undefined;
    if (running()) timer = setTimeout(tick, delay());
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
  /** 範圍變更等需要立即查詢時使用：取消計時並重新開始一輪。 */
  function restart() {
    clearTimeout(timer);
    timer = undefined;
    if (!running()) return Promise.resolve();
    inflight = null;
    return runTask().then(schedule);
  }

  function visibility() { visible.value = !document.hidden; if (visible.value) resume(); else pause(); }
  function offline() { pause(); }
  function focus() { if (running() && !inflight && Date.now() - lastStart >= minGap) resume(); }
  document.addEventListener('visibilitychange', visibility);
  window.addEventListener('online', resume);
  window.addEventListener('offline', offline);
  window.addEventListener('focus', focus);
  // 啟用只恢復排程；需要立即載入的範圍變更由呼叫端呼叫restart，避免同一變更送出兩次。
  watch(isEnabled, value => { if (value) schedule(); else pause(); });
  onScopeDispose(() => {
    disposed = true;
    pause();
    document.removeEventListener('visibilitychange', visibility);
    window.removeEventListener('online', resume);
    window.removeEventListener('offline', offline);
    window.removeEventListener('focus', focus);
  });
  return { visible: readonly(visible), restart, schedule };
}
