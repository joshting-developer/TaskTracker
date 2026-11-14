import { computed, nextTick, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
/** 紙條頁只讀目前Todo分頁，原稿／歷史快取限50筆且不保存到storage。 */
export function usePersonalQueries({ versions, resumed, draftId, onError, enabled = computed(() => true) }) {
  const scope = ref('mine'), page = ref(1), pinnedId = ref('');
  const open = shallowRef(null), counts = shallowRef(null), details = shallowRef(new Map());
  const loading = ref(false), error = ref(''), canConvert = ref(false);
  let generation = 0, controller, disposed = false;
  const itemRequests = new Map();
  const data = computed(() => {
    const rows = [...(open.value?.items || [])];
    for (const id of [draftId.value, pinnedId.value]) {
      const item = details.value.get(id);
      if (item && !rows.some(row => row.id === id))
        rows.push(item);
    }
    return { todos: rows.map(row => { const cached = details.value.get(row.id); return cached && cached.revision >= row.revision ? { ...row, ...cached } : row; }), members: [], notifications: [] };
  });
  async function loadItem(id, { history = false } = {}) {
    itemRequests.get(id)?.abort();
    const request = new AbortController();
    itemRequests.set(id, request);
    try {
      const todo = await api.todo(id, { signal: request.signal });
      canConvert.value = todo.capabilities.convert && (await api.projectOptions({ perPage: 1 }, { signal: request.signal })).total > 0;
      let events;
      if (history)
        events = await api.todoHistory(id, { page: 1, perPage: 20 }, { signal: request.signal });
      if (disposed || request.signal.aborted)
        throw Object.assign(new Error('頁面已切換'), { code: 'ABORTED' });
      const next = new Map(details.value);
      next.set(id, { ...todo, ...(events ? { history: events.items, historyPage: events } : {}) });
      while (next.size > 50) {
        const key = [...next.keys()].find(key => key !== draftId.value && key !== pinnedId.value && key !== id);
        if (!key)
          break;
        next.delete(key);
      }
      details.value = next;
      return todo;
    }
    catch (failure) {
      if (['NOT_FOUND', 'FORBIDDEN', 'UNAUTHENTICATED', 'WORKSPACE_CHANGED'].includes(failure.code)) {
        const next = new Map(details.value);
        next.delete(id);
        details.value = next;
        if (open.value)
          open.value = { ...open.value, items: open.value.items.filter(item => item.id !== id) };
        onError?.(failure);
      }
      throw failure;
    }
    finally {
      if (itemRequests.get(id) === request)
        itemRequests.delete(id);
    }
  }
  async function moreHistory(id) {
    const cached = details.value.get(id), previous = cached?.historyPage;
    if (!previous || previous.page >= previous.lastPage)
      return;
    const request = new AbortController(), key = `${id}:history`;
    itemRequests.get(key)?.abort();
    itemRequests.set(key, request);
    try {
      const data = await api.todoHistory(id, { page: previous.page + 1, perPage: 20 }, { signal: request.signal });
      if (disposed || request.signal.aborted || details.value.get(id)?.revision !== cached.revision)
        return;
      const next = new Map(details.value);
      next.set(id, { ...cached, history: [...cached.history, ...data.items], historyPage: data });
      details.value = next;
    }
    finally {
      if (itemRequests.get(key) === request)
        itemRequests.delete(key);
    }
  }
  async function refresh() {
    if (!versions.value || !enabled.value) { generation++; controller?.abort(); loading.value = false; return; }
    const current = ++generation;
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = '';
    const options = { signal: controller.signal };
    try {
      const result = await api.todos({ scope: scope.value, state: scope.value === 'mine' ? 'open' : 'all', page: page.value, perPage: 20 }, options);
      if (current !== generation)
        return;
      open.value = result;
      if (page.value > open.value.lastPage) {
        page.value = open.value.lastPage;
        return;
      }
      const visible = open.value.items;
      for (const row of visible)
        if ((row.id === draftId.value || row.id === pinnedId.value) && details.value.has(row.id) && details.value.get(row.id).revision !== row.revision)
          await loadItem(row.id, { history: Boolean(details.value.get(row.id).history) });
      if (draftId.value && !visible.some(row => row.id === draftId.value))
        await loadItem(draftId.value);
      // 通知定位的項目不在列表時（例如已退回或轉移），重讀明細讓指派／狀態正確；無法讀取則取消定位。
      if (pinnedId.value && pinnedId.value !== draftId.value && !visible.some(row => row.id === pinnedId.value)) {
        try { await loadItem(pinnedId.value); }
        catch (failure) { if (failure.code === 'ABORTED') throw failure; pinnedId.value = ''; }
      }
    }
    catch (failure) {
      if (current === generation && !['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) {
        error.value = failure.message;
        onError?.(failure);
      }
    }
    finally {
      if (current === generation)
        loading.value = false;
    }
  }
  async function focus(id, tool) {
    const item = await loadItem(id);
    scope.value = item.assigneeId === tool.userId && item.state === 'open' ? 'mine' : item.createdBy === tool.userId ? 'sent' : 'involved';
    page.value = 1;
    await nextTick();
    pinnedId.value = id;
    await refresh();
    await nextTick();
    await tool.focus(id);
  }
  watch(scope, () => { page.value = 1; pinnedId.value = ''; });
  watch([() => versions.value?.todos, resumed, scope, page, enabled], refresh, { immediate: true });
  onScopeDispose(() => { disposed = true; generation++; controller?.abort(); for (const request of itemRequests.values())
    request.abort(); itemRequests.clear(); });
  return { scope, page, open, counts, loading, error, data, loadItem, moreHistory, canConvert, refresh, focus };
}
