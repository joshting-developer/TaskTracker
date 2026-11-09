import { onScopeDispose } from 'vue';

// 同scope共享讀取；資料可能已寫入時安排一輪尾端刷新，舊scope不能阻塞新scope。
export function useRefreshQueue({ scope, load }) {
  let flight = null;
  function refresh({ join = false } = {}) {
    const key = scope();
    if (flight?.key === key) {
      if (!join) flight.pending = true;
      return flight.promise;
    }
    const current = { key, pending: false, promise: null };
    flight = current;
    current.promise = (async () => {
      try {
        do {
          current.pending = false;
          await load();
        } while (current.pending && flight === current && scope() === key);
      } finally {
        if (flight === current) flight = null;
      }
    })();
    return current.promise;
  }
  onScopeDispose(() => { flight = null; });
  return refresh;
}
