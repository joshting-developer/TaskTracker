<script setup>
import { computed, onBeforeUnmount, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import { t } from '../i18n/index.js';
import TagDefinitionForm from './TagDefinitionForm.vue';
import Icon from './Icon.vue';
const props = defineProps({ project: Object });
const emit = defineEmits(['updated', 'access']);
const records = shallowRef(null), page = shallowRef(1), search = shallowRef(''), loading = shallowRef(false), error = shallowRef('');
const editor = shallowRef(null), formTool = shallowRef(null);
let request, generation = 0, debounce, opener;
async function load() {
  request?.abort(); request = new AbortController(); const current = ++generation;
  loading.value = true; error.value = '';
  try { const result = await api.tags(props.project.id, { page: page.value, perPage: 20, search: search.value }, { signal: request.signal }); if (current === generation) records.value = result; }
  catch (failure) { if (current === generation && failure.code !== 'ABORTED') { error.value = failure.message; if (['FORBIDDEN', 'NOT_FOUND'].includes(failure.code)) emit('access'); } }
  finally { if (current === generation) loading.value = false; }
}
watch(() => props.project.id, () => { page.value = 1; records.value = null; load(); }, { immediate: true });
watch(page, load);
watch(search, () => { clearTimeout(debounce); debounce = setTimeout(() => { if (page.value !== 1) page.value = 1; else load(); }, 250); });
function edit(tag, event) { opener = event.currentTarget; editor.value = { tag }; }
function close() { editor.value = null; opener?.focus(); }
async function saved() { close(); await load(); if (records.value && !records.value.items.length && page.value > 1) page.value--; emit('updated'); }
onBeforeUnmount(() => { generation++; request?.abort(); clearTimeout(debounce); });
defineExpose({ dirty: computed(() => Boolean(formTool.value?.dirty)), saving: computed(() => Boolean(formTool.value?.saving)), refresh: load });
</script>
<template>
  <section class="project-tags" :aria-label="t('標籤管理')">
    <header><h2>{{ t('標籤管理') }}</h2><button class="button primary" @click="edit(null, $event)"><Icon name="plus" :size="16" />{{ t('新增標籤') }}</button></header>
    <label class="tag-search">{{ t('搜尋標籤') }}<input v-model="search" type="search" maxlength="200" /></label>
    <p v-if="error" class="error-box" role="alert">{{ t(error) }} <button class="text-button" @click="load">{{ t('重試') }}</button></p>
    <p v-if="loading" class="subtle" role="status">{{ t('載入中…') }}</p>
    <table v-if="records?.items.length" class="tags-table"><thead><tr><th scope="col">{{ t('標籤') }}</th><th scope="col">{{ t('說明') }}</th><th scope="col"><span class="sr-only">{{ t('操作') }}</span></th></tr></thead><tbody>
      <tr v-for="tag in records.items" :key="tag.id"><td><button class="tag-name" @click="edit(tag, $event)"><i :style="{ backgroundColor: tag.color }" aria-hidden="true"></i>{{ tag.name }}</button></td><td>{{ tag.description || '—' }}</td><td><button class="icon-button" :aria-label="t('編輯標籤 {0}', [tag.name])" @click="edit(tag, $event)"><Icon name="edit" :size="16" /></button></td></tr>
    </tbody></table>
    <p v-else-if="records && !loading" class="quiet-message">{{ t('尚無標籤') }}</p>
    <footer v-if="records"><span>{{ records.total }} {{ t('筆結果') }}</span><div v-if="records.lastPage > 1" class="pagination"><button class="button secondary" :disabled="loading || page <= 1" @click="page--">{{ t('上一頁') }}</button><span>{{ page }} / {{ records.lastPage }}</span><button class="button secondary" :disabled="loading || page >= records.lastPage" @click="page++">{{ t('下一頁') }}</button></div></footer>
    <TagDefinitionForm v-if="editor" ref="formTool" :project-id="project.id" :tag="editor.tag" @close="close" @saved="saved" />
  </section>
</template>
<style scoped>
.project-tags { padding: 24px; }
header, footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
h2 { font-size: 20px; margin: 0; }
.tag-search { display: flex; flex-direction: column; gap: 8px; max-width: 420px; margin: 24px 0; font-size: 14px; }
.tag-search input { height: 40px; padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; }
.tags-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.tags-table th, .tags-table td { text-align: left; padding: 14px 12px; border-bottom: 1px solid var(--border); vertical-align: top; overflow-wrap: anywhere; }
.tags-table th { color: var(--text-secondary); font-size: 13px; font-weight: 500; }
.tags-table th:first-child { width: 32%; }
.tags-table th:last-child { width: 44px; }
.tag-name { display: inline-flex; align-items: baseline; gap: 8px; padding: 4px 0; border: 0; background: none; text-align: left; font-weight: 600; color: var(--text-primary); cursor: pointer; overflow-wrap: anywhere; }
.tag-name i { flex: 0 0 8px; width: 8px; height: 8px; border-radius: 50%; }
footer { padding-top: 20px; color: var(--text-secondary); font-size: 13px; }
@media (max-width: 700px) { .project-tags { padding: 20px 16px; } .tags-table th, .tags-table td { padding: 12px 6px; } }
</style>
