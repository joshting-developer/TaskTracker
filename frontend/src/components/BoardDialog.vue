<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { usePagedLookup } from '../composables/usePagedLookup.js';
import Icon from './Icon.vue';
import { api } from '../api/index.js';
import { TYPES } from '../domain/model.js';
import { includedIssues } from '../domain/boards.js';
const props = defineProps({ remote: Boolean, project: Object, issues: Array, board: Object });
const emit = defineEmits(['close', 'saved', 'reload']);
const dialog = ref(null), busy = ref(false), error = ref('');
const form = reactive({ name: props.board?.name || '', selectedIssueIds: [...(props.board?.selectedIssueIds || [])] });
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
onMounted(() => { if (props.remote) optionsQuery.open(); });
const candidates = computed(() => props.remote ? optionsQuery.result.value?.items || [] : props.issues.filter(issue => [issue.title, issue.identifier, TYPES.find(type => type.id === issue.type)?.label].join(' ').toLowerCase().includes(search.value.trim().toLowerCase())));
const preview = computed(() => includedIssues({ ...form, projectId: props.project.id }, props.issues));
let pending;
function close() { if (!busy.value && (!dirty.value || window.confirm('捨棄尚未儲存的看板設定？'))) emit('close'); }
function beforeUnload(event) { if (dirty.value) { event.preventDefault(); event.returnValue = ''; } }
onMounted(() => { dialog.value.showModal(); window.addEventListener('beforeunload', beforeUnload); });
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
async function save() {
  if (busy.value) return;
  busy.value = true; error.value = '';
  const payload = { ...form, projectId: props.project.id, selectedIssueIds: [...form.selectedIssueIds], ...(props.board ? { id: props.board.id, expectedRevision: baseRevision } : {}) };
  const signature = JSON.stringify(payload);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  try { emit('saved', await api[props.board ? 'updateBoard' : 'createBoard']({ ...payload, requestId: pending.requestId })); }
  catch (failure) { error.value = failure.message; if (failure.code === 'REVISION_CONFLICT') emit('reload'); }
  finally { busy.value = false; }
}
</script>
<template>
  <dialog ref="dialog" class="board-dialog" aria-labelledby="board-dialog-title" @cancel.prevent="close" @click="event => { if (event.target === dialog) close(); }">
    <header class="board-dialog-heading"><h2 id="board-dialog-title">{{ board ? '編輯看板' : '新增看板' }}</h2><button class="icon-button" aria-label="關閉看板設定" :disabled="busy" @click="close"><Icon name="close" /></button></header>

    <div v-if="error" class="error-box" role="alert">{{ error }}</div>
    <form @submit.prevent="save">
      <label class="field">看板名稱<input v-model="form.name" maxlength="60" required autofocus :disabled="busy" placeholder="例如：本次交付" /></label>
      <label class="search-field"><Icon name="search" :size="17" /><input v-model="search" aria-label="搜尋收錄項目" placeholder="搜尋 Epic、卡片編號或標題…" /></label>
      <div class="board-picker" role="group" aria-label="收錄項目">
        <label v-for="issue in candidates" :key="issue.id" class="board-picker-item"><input v-model="form.selectedIssueIds" type="checkbox" :value="issue.id" :aria-label="`收錄 ${issue.identifier}`" :disabled="busy" /><span><strong>{{ issue.identifier }} · {{ issue.title }}</strong><small>{{ TYPES.find(type => type.id === issue.type)?.label }}</small></span></label>
        <p v-if="!candidates.length && !optionsLoading" class="subtle">沒有符合條件的項目</p>
      </div>
      <p v-if="optionsLoading" role="status">載入選項中…</p>
      <p v-if="optionsError" class="error-box" role="alert">{{ optionsError }}<button type="button" class="text-button" @click="optionsQuery.reload">重試</button></p>
      <div v-if="remote && lastPage > 1" class="pagination"><button type="button" :disabled="optionsLoading || page === 1" @click="page--">上一頁</button><span>{{ page }} / {{ lastPage }}</span><button type="button" :disabled="optionsLoading || page === lastPage" @click="page++">下一頁</button></div>
      <p class="board-preview" role="status">已選 {{ form.selectedIssueIds.length }} 個項目<span v-if="!remote"> · 目前包含 {{ preview.length }} 張卡片（含 Epic）</span></p>

      <button class="button primary" :disabled="busy">{{ busy ? '儲存中…' : '儲存看板' }}</button>
    </form>
  </dialog>
</template>
