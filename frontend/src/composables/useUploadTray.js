import { computed, onBeforeUnmount, ref, shallowReactive } from 'vue';

// One app-wide list feeds the floating panel; each editor still owns its rows, retries and binds.
const entries = shallowReactive([]);
const hosts = shallowReactive([]);
const collapsed = ref(false);
const OPEN = ['queued', 'uploading', 'binding', 'uploaded', 'failed'];

/** Show rows an owner just accepted; actions stay with the owner so the tray never uploads on its own. */
export function trackUploads(owner, rows, actions) {
  if (!rows.length) return;
  for (const row of rows) entries.push({ row, owner, ...actions });
  collapsed.value = false;
}

/** The owner unmounted: anything not finished was dropped with it, so say so instead of implying success. */
export function releaseUploads(owner) {
  for (const entry of entries) {
    if (entry.owner !== owner) continue;
    if (OPEN.includes(entry.row.state)) { entry.row.state = 'canceled'; entry.row.error = ''; }
    entry.retry = entry.cancel = null;
  }
}

export function useUploadTray() {
  // Modal dialogs make the page behind them inert, so only the newest host (App or an open dialog) renders.
  const host = Symbol('upload-tray-host');
  hosts.push(host);
  onBeforeUnmount(() => hosts.splice(hosts.indexOf(host), 1));
  const counts = computed(() => {
    const result = { total: 0, done: 0, waiting: 0, active: 0, failed: 0, canceled: 0 };
    for (const { row } of entries) {
      result.total++;
      if (row.state === 'done') result.done++;
      else if (row.state === 'canceled') result.canceled++;
      else if (row.state === 'failed' || row.state === 'rejected' || (row.state === 'uploaded' && row.error)) result.failed++;
      else if (row.state === 'uploaded') result.waiting++;
      else result.active++;
    }
    return result;
  });
  function dismiss() { entries.splice(0); collapsed.value = false; }
  return { visible: computed(() => hosts.at(-1) === host && entries.length > 0), entries, counts, collapsed, dismiss };
}
