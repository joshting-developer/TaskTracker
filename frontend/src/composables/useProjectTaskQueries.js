import { onScopeDispose, ref, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import { usePolling } from './usePolling.js';
import { pollIntervals } from '../ui/pollingConfig.js';

/** 專案任務查詢與備援生命週期；不管理編輯表單或DOM操作。 */
export function useProjectTaskQueries({ onDetail, detailMode, projectId, boardId, mode, context, blocked, onProject, onError, onSuccess, listQuery, rows = shallowRef([]), hold = shallowRef(false) }) {
  const board = shallowRef(null), boardPage = shallowRef(null), issuePage = shallowRef(null);
  // TT-283：看板列表可切換看使用中或已封存。
  const listPage = ref(1), listSearch = ref(''), listArchived = ref(false), loading = ref(false), loadingCards = ref(false);
  let generation = 0, request, debounce, silent = false, deferred = false, knownVersion = '';
  async function reload(showLoading = true) {
    // 拖曳或移動送出中不換掉列資料：背景重讀（輪詢／即時通知）延後到解除後補一次。
    if (!showLoading && hold.value) { deferred = true; return; }
    if (showLoading) deferred = false;
    silent = !showLoading;
    const contentAllowed = !blocked.value;
    const current = ++generation;
    request?.abort(); request = new AbortController();
    const options = { signal: request.signal };
    if (showLoading && contentAllowed) loading.value = true;
    try {
      // 看板先取不透明版本；背景輪次版本未變就不重讀Project、看板屬性與卡片分頁。
      // 版本在讀資料之前取得：期間若有變更，下次版本必不同，最多多讀一次而不會漏。
      const boardMode = !detailMode.value && mode.value !== 'list' && mode.value !== 'boards';
      const version = boardMode ? (await api.boardVersion(projectId.value, boardId.value, options)).version : '';
      if (current !== generation) return;
      if (!showLoading && boardMode && version === knownVersion) { poller.report(null); onSuccess?.(); return; }
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
        const data = await api.boards(projectId.value, { page: listPage.value, search: listSearch.value, perPage: 20, ...(listArchived.value ? { archived: 1 } : {}) }, options);
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
        knownVersion = version;
      }
      poller.report(null); onSuccess?.();
    } catch (failure) { if (current === generation) { poller.report(failure); if (['UNAUTHENTICATED','FORBIDDEN','NOT_FOUND','WORKSPACE_CHANGED'].includes(failure.code)) { knownVersion = ''; rows.value = []; boardPage.value = null; board.value = null; } onError(failure); } }
    finally { if (current === generation) { silent = false; loading.value = false; loadingCards.value = false; } }
  }
  const poller = usePolling({
    task: () => reload(false),
    interval: pollIntervals.board,
    // 只中止請求，讓同一世代的finally仍可收回loading狀態。
    onPause: () => request?.abort(),
  });
  watch(hold, value => {
    if (value && silent) { generation++; request?.abort(); silent = false; loadingCards.value = false; deferred = true; }
    else if (!value && deferred) { deferred = false; reload(false); }
  });
  watch(context, () => { deferred = false; knownVersion = ''; rows.value = []; boardPage.value = null; board.value = null; listPage.value = 1; reload(); poller.start(); }, { immediate: true });
  watch(listArchived, () => { listPage.value = 1; });
  watch([listPage, listSearch, listArchived], () => { clearTimeout(debounce); debounce = setTimeout(() => reload(), 250); });
  watch(() => mode.value === 'list' ? JSON.stringify(listQuery.value) : '', () => { if (mode.value !== 'list') return; clearTimeout(debounce); debounce = setTimeout(() => reload(), 250); });
  onScopeDispose(() => { generation++; request?.abort(); clearTimeout(debounce); });
  return { rows, board, boardPage, issuePage, listPage, listSearch, listArchived, loading, loadingCards, pageVisible: poller.visible, reload };
}
