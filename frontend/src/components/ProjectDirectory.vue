<script setup>
import { onScopeDispose, ref, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import ProjectHome from './ProjectHome.vue';
const props = defineProps({ workspaceId: String, canCreate: Boolean, demoLoading: Boolean });
const emit = defineEmits(['create', 'edit', 'open', 'demo', 'access']);
const page = ref(1), search = ref(''), result = shallowRef(null), loading = ref(false), error = ref('');
let generation = 0, request, debounce;
async function refresh() {
  const current = ++generation;
  request?.abort();
  request = new AbortController();
  loading.value = true;
  error.value = '';
  try {
    const data = await api.projects({ page: page.value, perPage: 20, search: search.value }, { signal: request.signal });
    if (current !== generation)
      return;
    result.value = data;
    if (page.value > data.lastPage)
      page.value = data.lastPage;
  }
  catch (failure) {
    if (current === generation && !['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) {
      error.value = failure.message;
      if (['UNAUTHENTICATED', 'WORKSPACE_CHANGED'].includes(failure.code))
        emit('access');
    }
  }
  finally {
    if (current === generation)
      loading.value = false;
  }
}
watch(() => props.workspaceId, () => { result.value = null; page.value = 1; search.value = ''; refresh(); }, { immediate: true });
watch(search, () => { page.value = 1; clearTimeout(debounce); request?.abort(); generation++; debounce = setTimeout(refresh, 250); });
watch(page, () => { clearTimeout(debounce); debounce = setTimeout(refresh, 0); });
onScopeDispose(() => { generation++; request?.abort(); clearTimeout(debounce); });
defineExpose({ refresh });
</script>
<template>
  <section>
    <ProjectHome remote :total="result?.total" :projects="result?.items || []" :members="[]" :can-create="canCreate" :loading="loading" :error="error" :demo-loading="demoLoading" @search="search=$event" @create="emit('create')" @edit="emit('edit',$event)" @open="emit('open',$event)" @demo="emit('demo')" @retry="refresh" />
    <div v-if="result?.lastPage>1" class="pagination" aria-label="專案分頁"><button class="text-button" :disabled="loading||page<=1" @click="page--">上一頁</button><span>{{ page }} / {{ result.lastPage }}</span><button class="text-button" :disabled="loading||page>=result.lastPage" @click="page++">下一頁</button></div>
  </section>
</template>
