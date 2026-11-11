import { computed, onScopeDispose, ref, watch } from 'vue';

/** 留言與歷史按實際開啟的tab分頁；不改編輯器的基準revision。 */
export function useIssueActivity({ issue, tab, load }) {
  const pages = ref({}), loading = ref(false), error = ref('');
  let generation = 0, controller;
  const comments = computed(() => pages.value.comments?.items || issue.value?.comments || []);
  const children = computed(() => pages.value.children?.items || []);
  const time = computed(() => pages.value.time?.items || []);
  const history = computed(() => pages.value.history?.items || issue.value?.history || []);
  const more = computed(() => {
    const page = pages.value[tab.value];
    return page && page.page < page.lastPage;
  });
  async function fetchPage(append = false) {
    if (!load.value || !issue.value || !['comments', 'history', 'time', 'children'].includes(tab.value)) return;
    if (loading.value) return;
    const kind = tab.value, current = generation, source = issue.value.id;
    const previous = pages.value[kind];
    if (!append && previous) return;
    controller?.abort(); controller = new AbortController(); loading.value = true; error.value = '';
    try {
      const result = await load.value(source, kind, { page: append ? previous.page + 1 : 1, perPage: 20 }, { signal: controller.signal });
      if (current !== generation || issue.value?.id !== source) return;
      pages.value = { ...pages.value, [kind]: { ...result, items: append ? [...previous.items, ...result.items] : result.items } };
    } catch (failure) { if (current === generation && !['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) error.value = failure.message; }
    finally { if (current === generation) loading.value = false; }
  }
  function clear() { generation++; controller?.abort(); loading.value = false; pages.value = {}; error.value = ''; }
  watch(() => `${issue.value?.id}:${issue.value?.revision}`, () => { clear(); fetchPage(); });
  watch([tab, () => issue.value?.id], () => { controller?.abort(); loading.value = false; generation++; fetchPage(); }, { immediate: true });
  onScopeDispose(clear);
  return { comments, history, time, children, more, loading, error, fetchPage, refresh: () => { clear(); fetchPage(); } };
}
