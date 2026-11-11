import { onScopeDispose, readonly, ref, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';

/** 看板查詢與備援生命週期；不管理編輯表單或DOM操作。 */
export function useBoardQueries({ projectId, boardId, mode, context, blocked, onProject, onError, onSuccess }) {
  const rows = shallowRef([]), board = shallowRef(null), boardPage = shallowRef(null);
  const listPage = ref(1), listSearch = ref(''), loading = ref(false), loadingCards = ref(false);
  const pageVisible = ref(!document.hidden);
  let generation = 0, request, timer, debounce, failures = 0, mounted = true;
  async function reload(showLoading = true) {
    const contentAllowed = !blocked.value;
    const current = ++generation;
    request?.abort(); request = new AbortController();
    const options = { signal: request.signal };
    if (showLoading && contentAllowed) loading.value = true;
    try {
      if (!showLoading || !contentAllowed) {
        const fresh = await api.project(projectId.value, options);
        if (current !== generation) return;
        onProject(fresh);
      }
      if (!contentAllowed) return;
      if (mode.value === 'boards') {
        const data = await api.boards(projectId.value, { page: listPage.value, search: listSearch.value, perPage: 20 }, options);
        if (current !== generation) return;
        boardPage.value = data;
      } else {
        const meta = await api.board(projectId.value, boardId.value, options);
        if (current !== generation) return;
        board.value = meta; loadingCards.value = true;
        // 每個HTTP／SQL頁最多100筆；完成同一次載入後才交換，避免畫面漏剩餘頁。
        const collected = new Map(); let page = 1, last = 1;
        do {
          const data = await api.boardIssues(projectId.value, boardId.value, { page, perPage: 100 }, options);
          if (current !== generation) return;
          data.items.forEach(item => collected.set(item.id, item));
          if (page === 1) last = data.lastPage;
          if (data.total !== meta.issueCount) throw Object.assign(new Error('看板資料已變更，請重新整理'), { code: 'DATA_CHANGED' });
          page++;
        } while (page <= last);
        if (collected.size !== meta.issueCount) throw Object.assign(new Error('看板資料已變更，請重新整理'), { code: 'DATA_CHANGED' });
        rows.value = [...collected.values()];
      }
      failures = 0; onSuccess?.();
    } catch (failure) { if (current === generation) { failures++; if (['UNAUTHENTICATED','FORBIDDEN','NOT_FOUND','WORKSPACE_CHANGED'].includes(failure.code)) { rows.value = []; boardPage.value = null; board.value = null; } onError(failure); } }
    finally { if (current === generation) { loading.value = false; loadingCards.value = false; } }
  }
  function schedule() {
    clearTimeout(timer);
    if (!mounted || document.hidden) return;
    const delay = Math.min(120000, 30000 * 2 ** Math.min(failures, 2));
    timer = setTimeout(async () => { await reload(false); schedule(); }, delay);
  }
  function visible() { pageVisible.value = !document.hidden; if (document.hidden) { clearTimeout(timer); request?.abort(); } else { reload(false); schedule(); } }
  watch(context, () => { rows.value = []; boardPage.value = null; board.value = null; listPage.value = 1; reload(); schedule(); }, { immediate: true });
  watch([listPage, listSearch], () => { clearTimeout(debounce); debounce = setTimeout(() => reload(), 250); });
  document.addEventListener('visibilitychange', visible);
  onScopeDispose(() => {
    mounted = false; generation++; request?.abort(); clearTimeout(timer); clearTimeout(debounce);
    document.removeEventListener('visibilitychange', visible);
  });
  return { rows, board, boardPage, listPage, listSearch, loading, loadingCards, pageVisible: readonly(pageVisible), reload };
}
