import { readonly, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import { usePolling } from './usePolling.js';
import { pollIntervals } from '../ui/pollingConfig.js';
/** 個人同步只輪詢無內容版本；變更時才通知頁面及重讀未讀數。 */
export function usePersonalSync({ active, context, onError }) {
  const versions = shallowRef(null), unread = shallowRef(0), resumed = shallowRef(0);
  let generation = 0, controller;
  const poller = usePolling({
    task: check,
    enabled: () => active.value,
    interval: pollIntervals.notifications,
    onPause: () => { generation++; controller?.abort(); },
  });
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
      poller.report(null);
    }
    catch (failure) {
      if (current === generation && !['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) {
        poller.report(failure);
        onError?.(failure);
      }
    }
  }
  watch([active, context], () => {
    generation++;
    controller?.abort();
    versions.value = null;
    unread.value = 0;
    poller.restart();
  }, { immediate: true });
  // 回到前景時通知頁面重讀；補查本身由輪詢生命週期處理最短間隔與去重。
  watch(poller.visible, value => { if (value && active.value) resumed.value++; });
  return { versions: readonly(versions), unread: readonly(unread), resumed: readonly(resumed), refresh: check };
}
