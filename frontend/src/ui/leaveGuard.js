import { inject, onBeforeUnmount, provide } from 'vue';

// TT-285：草稿owner掛載時自己登記離開保護、卸載時移除；App只問registry, 新增可編輯畫面不用再改App。
const LEAVE_GUARDS = Symbol('leave-guards');

/**
 * 在App建立registry。
 *
 * @returns {{ saving: () => boolean, dirty: (options?: { unload?: boolean }) => boolean }} 是否有任何儲存中／未儲存內容；unload只看要在關閉分頁時提醒的owner
 */
export function provideLeaveGuards() {
  const guards = new Set();
  provide(LEAVE_GUARDS, guards);
  return {
    saving: () => [...guards].some(guard => guard.saving()),
    dirty: ({ unload = false } = {}) => [...guards].some(guard => (!unload || guard.unload) && guard.dirty()),
  };
}

/**
 * 草稿owner登記自己的狀態；不在App底下（例如單獨測試）時不做事。
 *
 * @param {{ dirty?: () => boolean, saving?: () => boolean, unload?: boolean }} guard dirty／saving讀取函式；unload=true時關閉分頁也提醒
 * @returns {void}
 */
export function useLeaveGuard({ dirty = () => false, saving = () => false, unload = false }) {
  const guards = inject(LEAVE_GUARDS, null);
  if (!guards) return;
  const guard = { dirty, saving, unload };
  guards.add(guard);
  onBeforeUnmount(() => guards.delete(guard));
}
