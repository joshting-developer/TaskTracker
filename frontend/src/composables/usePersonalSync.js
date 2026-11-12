import { onScopeDispose, readonly, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
/** 個人同步只輪詢無內容版本；變更時才通知頁面及重讀未讀數。 */
export function usePersonalSync({ active, context, onError }) {
  const versions = shallowRef(null), unread = shallowRef(0), resumed = shallowRef(0);
  let generation = 0, controller, timer, failures = 0, mounted = true;
  function schedule() {
    clearTimeout(timer);
    if (!mounted || !active.value || document.hidden)
      return;
    const base = Math.min(120000, 30000 * 2 ** Math.min(failures, 2));
    timer = setTimeout(async () => { await check(); schedule(); }, base * (0.9 + Math.random() * 0.2));
  }
  async function check() {
    if (!active.value || document.hidden)
      return;
    const current = ++generation;
    controller?.abort();
    controller = new AbortController();
    const options = { signal: controller.signal };
    try {
      const next = await api.personalVersion(options);
      if (current !== generation)
        return;
      const noticeChanged = next.notifications !== versions.value?.notifications;
      // 先取得需要的未讀數，最後一次交換同一scope的版本。
      if (noticeChanged) {
        const count = await api.todoUnread(options);
        if (current !== generation)
          return;
        unread.value = count.unread;
      }
      versions.value = next;
      failures = 0;
    }
    catch (failure) {
      if (current === generation && !['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) {
        failures++;
        onError?.(failure);
      }
    }
  }
  watch([active, context], () => {
    generation++;
    controller?.abort();
    clearTimeout(timer);
    versions.value = null;
    unread.value = 0;
    failures = 0;
    if (active.value) {
      check();
      schedule();
    }
  }, { immediate: true });

  function visibility() {
    if (document.hidden) {
      generation++;
      controller?.abort();
      clearTimeout(timer);
    } else if (active.value) {
      resumed.value++;
      check();
      schedule();
    }
  }
  document.addEventListener('visibilitychange', visibility);
  onScopeDispose(() => {
    mounted = false;
    generation++;
    controller?.abort();
    clearTimeout(timer);
    document.removeEventListener('visibilitychange', visibility);
  });
  return { versions: readonly(versions), unread: readonly(unread), resumed: readonly(resumed), refresh: check };
}
