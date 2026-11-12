import { onScopeDispose, ref, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import { usePolling } from './usePolling.js';
import { pollIntervals } from '../ui/pollingConfig.js';

/** 專案任務查詢與備援生命週期；不管理編輯表單或DOM操作。 */
export function useProjectTaskQueries({ onDetail, detailMode, projectId, boardId, mode, context, blocked, onProject, onError, onSuccess, listQuery, rows = shallowRef([]) }) {
  const board = shallowRef(null), boardPage = shallowRef(null), issuePage = shallowRef(null);
  const listPage = ref(1), listSearch = ref(''), loading = ref(false), loadingCards = ref(false);
  let generation = 0, request, debounce;
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
      if (detailMode.value) { if (!showLoading) await onDetail(); return; }
      if (mode.value === 'list') {
        const data = await api.issues(projectId.value, listQuery.value, options);
        if (current !== generation) return;
        rows.value = data.items; issuePage.value = data;
      } else if (mode.value === 'boards') {
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
      poller.report(null); onSuccess?.();
    } catch (failure) { if (current === generation) { poller.report(failure); if (['UNAUTHENTICATED','FORBIDDEN','NOT_FOUND','WORKSPACE_CHANGED'].includes(failure.code)) { rows.value = []; boardPage.value = null; board.value = null; } onError(failure); } }
    finally { if (current === generation) { loading.value = false; loadingCards.value = false; } }
  }
  const poller = usePolling({
    task: () => reload(false),
    interval: pollIntervals.board,
    // 只中止請求，讓同一世代的finally仍可收回loading狀態。
    onPause: () => request?.abort(),
  });
  watch(context, () => { rows.value = []; boardPage.value = null; board.value = null; listPage.value = 1; reload(); poller.start(); }, { immediate: true });
  watch([listPage, listSearch], () => { clearTimeout(debounce); debounce = setTimeout(() => reload(), 250); });
  watch(() => mode.value === 'list' ? JSON.stringify(listQuery.value) : '', () => { if (mode.value !== 'list') return; clearTimeout(debounce); debounce = setTimeout(() => reload(), 250); });
  onScopeDispose(() => { generation++; request?.abort(); clearTimeout(debounce); });
  return { rows, board, boardPage, issuePage, listPage, listSearch, loading, loadingCards, pageVisible: poller.visible, reload };
}
