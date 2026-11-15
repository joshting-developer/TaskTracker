<script setup>
import { computed, onMounted, onUnmounted, shallowRef } from 'vue';
import { adminWorkspaces } from '../api/admin.js';
import { t } from '../i18n/index.js';
const emit = defineEmits(['expired']);
const data = shallowRef(null);
const expanded = shallowRef(false);
const visibleItems = computed(() => expanded.value ? data.value?.items || [] : (data.value?.items || []).slice(0,5));
const loading = shallowRef(false);
const error = shallowRef('');
const before = shallowRef(null);
const previous = shallowRef([]);
let controller;
onUnmounted(() => controller?.abort());
onMounted(() => load(null));
async function load(cursor) {
  if (loading.value) return;
  controller = new AbortController(); loading.value = true; error.value = '';
  try {
    const result = await adminWorkspaces(cursor, controller.signal);
    if (controller.signal.aborted) return false;
    data.value = result; before.value = cursor; return true;
  } catch (cause) {
    if (cause.code !== 'ABORTED') { if (cause.status === 401) emit('expired'); else error.value = cause.message; }
    return false;
  } finally { if (!controller.signal.aborted) loading.value = false; }
}
async function next() { const old = before.value; if (await load(data.value.nextBefore)) previous.value = [...previous.value, old]; }
async function back() { if (await load(previous.value.at(-1))) previous.value = previous.value.slice(0,-1); }
</script>
<template>
  <section class="admin-panel admin-workspaces" :aria-busy="loading">
    <div class="admin-panel-heading"><h2>{{ t('工作區') }}</h2><v-btn variant="text" :disabled="loading" @click="load(before)">{{ t('重新整理') }}</v-btn></div>
    <p v-if="error" class="admin-error" role="alert">{{ error }}</p>
    <p v-else-if="loading" role="status">{{ t('載入中…') }}</p>
    <template v-if="data"><ul class="admin-workspace-list"><li v-for="row in visibleItems" :key="row.id"><strong>{{ row.name }}</strong><span>{{ row.createdAt?.slice(0,10) || '—' }}</span></li></ul><p v-if="!data.items.length" class="admin-empty">{{ t('尚無工作區') }}</p><v-btn v-if="!expanded && data.items.length > 5" class="admin-view-all" variant="text" @click="expanded = true">{{ t('查看全部') }}</v-btn><div v-if="expanded" class="admin-paging"><v-btn variant="text" :disabled="loading || !previous.length" @click="back">{{ t('上一頁') }}</v-btn><span>{{ t('第{0}頁', [previous.length + 1]) }}</span><v-btn variant="text" :disabled="loading || !data.nextBefore" @click="next">{{ t('下一頁') }}</v-btn></div></template>
  </section>
</template>
<style scoped>
.admin-view-all { margin-top: 12px; }.admin-workspace-list { padding: 0; margin: 0; list-style: none; }.admin-workspace-list li { display: flex; justify-content: space-between; align-items: baseline; gap: 16px; padding: 16px 0; border-bottom: 1px solid var(--admin-border); font-size: 14px; }.admin-workspace-list strong { font-weight: 500; min-width: 0; overflow-wrap: anywhere; }.admin-workspace-list span { white-space: nowrap; color: var(--admin-muted); font-size: 12px; font-variant-numeric: tabular-nums; }.admin-paging { display: flex; align-items: center; justify-content: space-between; gap: 4px; margin-top: 16px; font-size: 12px; color: var(--admin-muted); }
</style>
