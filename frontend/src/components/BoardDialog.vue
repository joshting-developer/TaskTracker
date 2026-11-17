<script setup>
import { t } from '../i18n/index.js';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { usePagedLookup } from '../composables/usePagedLookup.js';
import Icon from './Icon.vue';
import TagPicker from './TagPicker.vue';
import { api } from '../api/index.js';
import { statusesFor, TYPES } from '../domain/model.js';
import { includedIssues } from '../domain/boards.js';
const props = defineProps({ remote: Boolean, project: Object, issues: Array, board: Object });
const emit = defineEmits(['close', 'saved', 'reload']);
const dialog = ref(null), busy = ref(false), error = ref('');
const form = reactive({ name: props.board?.name || '', description: props.board?.description || '', selectedIssueIds: [...(props.board?.selectedIssueIds || [])],
  automatic: Boolean(props.board?.autoInclude), tagIds: [...(props.board?.autoInclude?.tagIds || [])], types: [...(props.board?.autoInclude?.types || [])], states: [...(props.board?.autoInclude?.states || [])], excludedIssueIds: [...(props.board?.excludedIssueIds || [])] });
const states = computed(() => statusesFor(props.project));
const selection = computed(() => ({ autoInclude: form.automatic ? { tagIds: form.tagIds, types: form.types, states: form.states } : null, selectedIssueIds: form.selectedIssueIds, excludedIssueIds: form.excludedIssueIds }));
const initial = JSON.stringify(form);
const baseRevision = props.board?.revision;
const dirty = computed(() => JSON.stringify(form) !== initial);
defineExpose({ dirty, saving: busy });
const optionsQuery = usePagedLookup({
  context: computed(() => `${props.project.id}:${props.board?.id || 'create'}`),
  load: (filters, options) => api.issueOptions(props.project.id, filters, options),
});
const search = optionsQuery.search, page = optionsQuery.page;
const lastPage = computed(() => optionsQuery.result.value?.lastPage || 1);
const optionsError = optionsQuery.error, optionsLoading = optionsQuery.loading;
const previewQuery = usePagedLookup({
  context: computed(() => `${props.project.id}:${JSON.stringify(selection.value)}`),
  load: (_filters, options) => api.boardPreview(props.project.id, selection.value, options),
});
const previewResult = previewQuery.result, previewLoading = previewQuery.loading, previewError = previewQuery.error;
onMounted(() => { if (props.remote) { optionsQuery.open(); previewQuery.open(); } });
const candidates = computed(() => props.remote ? optionsQuery.result.value?.items || [] : props.issues.filter(issue => [issue.title, issue.identifier, TYPES.find(type => type.id === issue.type)?.label].join(' ').toLowerCase().includes(search.value.trim().toLowerCase())));
const preview = computed(() => includedIssues({ ...form, projectId: props.project.id }, props.issues));
let pending;
function close() { if (!busy.value && (!dirty.value || window.confirm(t('捨棄尚未儲存的看板設定？')))) emit('close'); }
function beforeUnload(event) { if (dirty.value) { event.preventDefault(); event.returnValue = ''; } }
function chooseManual(id, excluded) {
  const field = excluded ? 'excludedIssueIds' : 'selectedIssueIds';
  const other = excluded ? 'selectedIssueIds' : 'excludedIssueIds';
  if (form[field].includes(id)) form[other] = form[other].filter(value => value !== id);
}
onMounted(() => { dialog.value.showModal(); window.addEventListener('beforeunload', beforeUnload); });
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
async function save() {
  if (busy.value || (props.remote && (!previewResult.value || previewLoading.value || previewError.value))) return;
  busy.value = true; error.value = '';
  const payload = { name: form.name, description: form.description, ...selection.value, projectId: props.project.id, ...(props.board ? { id: props.board.id, expectedRevision: baseRevision } : {}) };
  const signature = JSON.stringify(payload);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  try { emit('saved', await api[props.board ? 'updateBoard' : 'createBoard']({ ...payload, requestId: pending.requestId })); }
  catch (failure) { error.value = failure.message; if (failure.code === 'REVISION_CONFLICT') emit('reload'); }
  finally { busy.value = false; }
}
</script>
<template>
  <dialog ref="dialog" class="board-dialog" aria-labelledby="board-dialog-title" @cancel.prevent="close" @click="event => { if (event.target === dialog) close(); }">
    <header class="board-dialog-heading"><h2 id="board-dialog-title">{{ board ? t('編輯看板') : t('新增看板') }}</h2><button class="icon-button" :aria-label="t('關閉看板設定')" :disabled="busy" @click="close"><Icon name="close" /></button></header>

    <div v-if="error" class="error-box" role="alert">{{ t(error) }}</div>
    <form @submit.prevent="save">
      <label class="field">{{ t('看板名稱') }}<input v-model="form.name" maxlength="60" required autofocus :disabled="busy" :placeholder="t('例如：本次交付')" /></label>
      <!-- TT-309：簡述寫清楚這個看板收哪類卡片, 之後分類照這段判斷 -->
      <label class="field">{{ t('簡述') }}<textarea v-model="form.description" maxlength="300" rows="3" :disabled="busy" :placeholder="t('這個看板收哪類卡片，例如：只收量測過的效能改善')" aria-describedby="board-description-hint"></textarea><span id="board-description-hint" class="field-hint">{{ t('寫清楚收什麼、不收什麼，最多 300 字（') }}{{ form.description.length }} / 300）</span></label>
      <label v-if="remote" class="field">{{ t('收錄方式') }}<select v-model="form.automatic" :disabled="busy" :aria-label="t('收錄方式')"><option :value="false">{{ t('手動收錄') }}</option><option :value="true">{{ t('自動收錄條件') }}</option></select></label>
      <fieldset v-if="remote && form.automatic" class="board-rules" :disabled="busy">
        <legend>{{ t('自動收錄條件') }}</legend>
        <TagPicker v-model="form.tagIds" :project-id="project.id" :options="board?.autoIncludeTags || []" :label="t('符合標籤')" :disabled="busy" />
        <p class="field-hint">{{ t('同時符合所有選取標籤') }}</p>
        <fieldset class="board-rule-options"><legend>{{ t('類型（符合任一）') }}</legend><label v-for="type in TYPES" :key="type.id"><input v-model="form.types" type="checkbox" :value="type.id" :aria-label="t('自動收錄類型 {0}', [type.label])" />{{ type.label }}</label></fieldset>
        <fieldset class="board-rule-options"><legend>{{ t('狀態（符合任一）') }}</legend><label v-for="state in states" :key="state.id"><input v-model="form.states" type="checkbox" :value="state.id" :aria-label="t('自動收錄狀態 {0}', [state.label])" />{{ state.label }}</label></fieldset>
        <p class="field-hint">{{ t('所有條件群組都需符合；未選取的群組表示不限') }}</p>
      </fieldset>
      <p v-if="remote" class="board-selection-hint">{{ t('手動加入可包含 Epic 子卡片；排除優先於所有收錄方式') }}</p>
      <label class="search-field"><Icon name="search" :size="17" /><input v-model="search" :aria-label="t('搜尋收錄項目')" :placeholder="t('搜尋 Epic、卡片編號或標題…')" /></label>
      <div class="board-picker" role="group" :aria-label="t('收錄項目')">
        <div v-for="issue in candidates" :key="issue.id" class="board-picker-item"><label class="board-manual-choice"><input v-model="form.selectedIssueIds" type="checkbox" :value="issue.id" :aria-label="t('收錄 {0}', [issue.identifier])" :disabled="busy" @change="chooseManual(issue.id, false)" /><span><strong>{{ issue.identifier }} · {{ issue.title }}</strong><small>{{ t('手動加入') }} · {{ TYPES.find(type => type.id === issue.type)?.label }}</small></span></label><label v-if="remote" class="board-exclude-choice"><input v-model="form.excludedIssueIds" type="checkbox" :value="issue.id" :aria-label="t('排除 {0}', [issue.identifier])" :disabled="busy" @change="chooseManual(issue.id, true)" />{{ t('排除') }}</label></div>
        <p v-if="!candidates.length && !optionsLoading" class="subtle">{{ t('沒有符合條件的項目') }}</p>
      </div>
      <p v-if="optionsLoading" role="status">{{ t('載入選項中…') }}</p>
      <p v-if="optionsError" class="error-box" role="alert">{{ optionsError }}<button type="button" class="text-button" @click="optionsQuery.reload">{{ t('重試') }}</button></p>
      <div v-if="remote && lastPage > 1" class="pagination"><button type="button" :disabled="optionsLoading || page === 1" @click="page--">{{ t('上一頁') }}</button><span>{{ page }} / {{ lastPage }}</span><button type="button" :disabled="optionsLoading || page === lastPage" @click="page++">{{ t('下一頁') }}</button></div>
      <p class="board-preview" role="status">{{ t('已選') }} {{ form.selectedIssueIds.length }} {{ t('個項目') }}<span v-if="!remote"> {{ t('· 目前包含') }} {{ preview.length }} {{ t('張卡片（含 Epic）') }}</span></p>
      <section v-if="remote" class="board-selection-preview" :aria-label="t('收錄預覽')" :aria-busy="previewLoading">
        <p v-if="previewLoading" role="status">{{ t('載入預覽中…') }}</p>
        <p v-if="previewError" class="field-error" role="alert">{{ previewError }}<button type="button" class="text-button" @click="previewQuery.reload">{{ t('重試') }}</button></p>
        <template v-if="previewResult && !previewLoading && !previewError">
          <p role="status">{{ t('符合 {0} 張卡片', [previewResult.total]) }} · {{ t('已排除 {0} 張', [form.excludedIssueIds.length]) }}</p>
          <ul><li v-for="issue in previewResult.items.slice(0, 3)" :key="issue.id"><span class="identifier">{{ issue.identifier }}</span> {{ issue.title }}</li></ul>
          <details v-if="previewResult.items.length > 3"><summary>{{ t('顯示其餘預覽（最多 20 張）') }}</summary><ul><li v-for="issue in previewResult.items.slice(3)" :key="issue.id"><span class="identifier">{{ issue.identifier }}</span> {{ issue.title }}</li></ul></details>
        </template>
      </section>

      <button class="button primary" :disabled="busy || (remote && (!previewResult || previewLoading || Boolean(previewError)))">{{ busy ? t('儲存中…') : t('儲存看板') }}</button>
    </form>
  </dialog>
</template>

<style scoped>
.board-rules { border: 1px solid var(--border); border-radius: 6px; padding: 12px; margin: 12px 0; min-width: 0; }
.board-rules > legend { padding: 0 4px; font-weight: 500; }
.board-rule-options { display: flex; flex-wrap: wrap; gap: 4px 16px; border: 0; padding: 0; margin: 10px 0 0; }
.board-rule-options legend { font-size: 13px; color: var(--text-secondary); }
.board-rule-options label, .board-exclude-choice { display: flex; align-items: center; gap: 6px; min-height: 44px; }
.board-rule-options input { accent-color: var(--accent); }
.board-selection-hint { margin: 14px 0 10px; font-size: 13px; color: var(--text-secondary); }
.board-manual-choice { display: flex; align-items: flex-start; gap: 12px; flex: 1; min-width: 0; min-height: 44px; }
.board-exclude-choice { flex-shrink: 0; font-size: 13px; }
.board-selection-preview { border-top: 1px solid var(--border); padding-top: 12px; font-size: 13px; }
.board-selection-preview ul { padding-left: 18px; margin: 8px 0; }
.board-selection-preview li { margin-top: 6px; overflow-wrap: anywhere; }
.board-selection-preview summary { cursor: pointer; min-height: 44px; padding: 12px 0; }
.board-rules :deep(.selected-tags .icon-button) { min-width: 44px; width: 44px; min-height: 44px; height: 44px; }
</style>
