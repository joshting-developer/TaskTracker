import { onScopeDispose, ref, shallowRef, watch } from 'vue';

/** 選項每次只保留一頁，取消／generation防止舊搜尋蓋過新輸入。 */
export function usePagedLookup({ context, load }) {
  const search = ref(''), page = ref(1), result = shallowRef(null), loading = ref(false), error = ref('');
  let generation = 0, controller, debounce, active = false;
  async function fetchPage() {
    const current = ++generation;
    controller?.abort(); controller = new AbortController(); loading.value = true; error.value = '';
    try {
      const data = await load({search: search.value, page: page.value, perPage: 30}, {signal: controller.signal});
      if (current === generation) result.value = data;
    } catch (failure) { if (current === generation && !['ABORTED','STALE_RESPONSE'].includes(failure.code)) error.value = failure.message; }
    finally { if (current === generation) loading.value = false; }
  }
  function open() { active = true; if (!result.value && !loading.value) fetchPage(); }
  watch(search, () => { page.value = 1; clearTimeout(debounce); controller?.abort(); generation++; if (active) debounce = setTimeout(fetchPage, 250); });
  watch(page, () => { if (active) { clearTimeout(debounce); debounce = setTimeout(fetchPage, 0); } });
  watch(context, () => { generation++; controller?.abort(); result.value = null; search.value = ''; page.value = 1; loading.value = false; error.value = ''; if (active) fetchPage(); });
  onScopeDispose(() => { generation++; controller?.abort(); clearTimeout(debounce); });
  return {search, page, result, loading, error, open, reload: fetchPage};
}
