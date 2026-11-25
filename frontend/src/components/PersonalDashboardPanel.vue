<script setup>
import { computed, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import { formatDate, t } from '../i18n/index.js';
const props = defineProps({ kind: String, title: String, refreshKey: Number, perPage: { type: Number, default: 5 } });
const emit = defineEmits(['access']);
const page = ref(1), result = shallowRef(null), loading = ref(false), error = ref('');
const notes = computed(() => props.kind === 'notes');
let generation = 0, controller;
async function refresh() {
  const current = ++generation;
  controller?.abort(); controller = new AbortController();
  loading.value = true; error.value = '';
  try {
    const filters = { page: page.value, perPage: props.perPage };
    const data = notes.value
      ? await api.todos({ ...filters, scope: 'mine', state: 'open' }, { signal: controller.signal })
      : await api.personalDashboard({ ...filters, kind: props.kind }, { signal: controller.signal });
    if (current !== generation) return;
    result.value = data;
    if (page.value > data.lastPage) page.value = data.lastPage;
  } catch (failure) {
    if (current !== generation || ['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) return;
    error.value = failure.message;
    // 權限錯誤不保留先前已無資格讀取的摘要。
    result.value = null;
    if (['FORBIDDEN', 'UNAUTHENTICATED', 'WORKSPACE_CHANGED'].includes(failure.code)) emit('access');
  } finally { if (current === generation) loading.value = false; }
}
function href(item) { return notes.value ? '#/notes' : `#/projects/${encodeURIComponent(item.projectId)}/issues/${encodeURIComponent(item.id)}?detail=1`; }
watch([page, () => props.refreshKey], refresh, { immediate: true });
watch(() => props.perPage, () => { if (page.value !== 1) page.value = 1; else refresh(); });
onScopeDispose(() => { generation++; controller?.abort(); });
</script>
<template>
  <section class="dashboard-panel" :aria-label="t(title)" :aria-busy="loading">
    <header><h2>{{ t(title) }} <span v-if="result">{{ result.total }}</span></h2><a v-if="notes" href="#/notes" class="text-button">{{ t('查看紙條') }}</a></header>
    <p v-if="error" class="error-box" role="alert">{{ t(error) }} <button class="text-button" @click="refresh">{{ t('重試') }}</button></p>
    <p v-else-if="loading && !result" class="dashboard-empty" role="status">{{ t('載入中…') }}</p>
    <template v-else-if="result">
      <p v-if="!result.items.length" class="dashboard-empty">{{ t(notes ? '尚無待處理紙條' : '此範圍沒有任務') }}</p>
      <ul v-else class="dashboard-rows"><li v-for="item in result.items" :key="item.id">
        <a :href="href(item)"><span class="dashboard-identity" v-if="!notes">{{ item.identifier }} · {{ item.projectName }}</span><strong>{{ item.title }}</strong>
          <span class="dashboard-meta" v-if="!notes">{{ t(item.stateLabel) }}<template v-if="kind === 'starting'"> · {{ t('開始') }} {{ item.startDate }}</template><template v-else-if="kind === 'recent'"> · {{ t('更新時間') }} {{ formatDate(item.updatedAt, { dateStyle: 'short', timeStyle: 'short' }) }}</template><template v-else-if="item.dueDate"> · {{ t('到期') }} {{ item.dueDate }}</template></span>
          <span class="dashboard-meta" v-else>{{ t('來自') }} {{ item.creatorName }}</span>
        </a>
      </li></ul>
      <nav v-if="result.lastPage > 1" class="dashboard-pagination" :aria-label="t('{0}分頁', [t(title)])"><button class="text-button" :disabled="page <= 1 || loading" @click="page--">{{ t('上一頁') }}</button><span>{{ page }} / {{ result.lastPage }}</span><button class="text-button" :disabled="page >= result.lastPage || page >= 501 || loading" @click="page++">{{ t('下一頁') }}</button></nav>
    </template>
  </section>
</template>
<style scoped>
.dashboard-panel { height: 100%; overflow: auto; box-sizing: border-box; min-width: 0; border: 1px solid #e2e3eb; border-radius: 8px; background: #fff; padding: 20px; }
header { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 44px; }
h2 { font-size: 17px; margin: 0; } h2 span { color: #6653bd; font-size: 15px; margin-left: 8px; font-variant-numeric: tabular-nums; }
.dashboard-rows { padding: 0; margin: 8px 0 0; list-style: none; }
.dashboard-rows li + li { border-top: 1px solid #ececf2; }
.dashboard-rows a { display: grid; gap: 5px; padding: 12px 0; min-height: 44px; text-decoration: none; color: inherit; overflow-wrap: anywhere; }
.dashboard-rows a:hover strong { color: #5844a0; text-decoration: underline; }
.dashboard-rows strong { font-size: 15px; font-weight: 550; }
.dashboard-identity, .dashboard-meta { font-size: 13px; color: #5f6673; }
.dashboard-empty { color: #5f6673; padding: 16px 0; }
.dashboard-pagination { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 13px; margin-top: 8px; }
.dashboard-pagination button { min-height: 44px; }
</style>
