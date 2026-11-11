<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import Icon from './Icon.vue';
import { api } from '../api/index.js';
import { TYPES } from '../domain/model.js';
import { includedIssues } from '../domain/boards.js';
const props = defineProps({ remote: Boolean, project: Object, issues: Array, board: Object });
const emit = defineEmits(['close', 'saved', 'reload']);
const dialog = ref(null), busy = ref(false), error = ref(''), search = ref('');
const form = reactive({ name: props.board?.name || '', selectedIssueIds: [...(props.board?.selectedIssueIds || [])] });
const initial = JSON.stringify(form);
const baseRevision = props.board?.revision;
const dirty = computed(() => JSON.stringify(form) !== initial);
defineExpose({ dirty, saving: busy });
const remoteOptions = ref([]), page = ref(1), lastPage = ref(1), optionsError = ref('');
let optionGeneration = 0, optionTimer, optionRequest;
async function loadOptions() {
  if (!props.remote) return;
  const current = ++optionGeneration; optionRequest?.abort(); optionRequest = new AbortController();
  try { const result = await api.issues(props.project.id, { page: page.value, perPage: 30, search: search.value }, { signal: optionRequest.signal }); if (current === optionGeneration) { remoteOptions.value = result.items; lastPage.value = result.lastPage; optionsError.value = ''; } }
  catch (failure) { if (current === optionGeneration && !['ABORTED','STALE_RESPONSE'].includes(failure.code)) optionsError.value = failure.message; }
}
watch(search, () => { page.value = 1; clearTimeout(optionTimer); optionTimer = setTimeout(loadOptions, 250); });
watch(page, loadOptions);
onMounted(loadOptions);
onBeforeUnmount(() => { optionGeneration++; optionRequest?.abort(); clearTimeout(optionTimer); });
const candidates = computed(() => props.remote ? remoteOptions.value : props.issues.filter(issue => [issue.title, issue.identifier, TYPES.find(type => type.id === issue.type)?.label].join(' ').toLowerCase().includes(search.value.trim().toLowerCase())));
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
        <p v-if="!candidates.length" class="subtle">沒有符合條件的項目</p>
      </div>
      <p v-if="optionsError" class="error-box" role="alert">{{ optionsError }}</p>
      <div v-if="remote && lastPage > 1" class="pagination"><button type="button" :disabled="page === 1" @click="page--">上一頁</button><span>{{ page }} / {{ lastPage }}</span><button type="button" :disabled="page === lastPage" @click="page++">下一頁</button></div>
      <p class="board-preview" role="status">已選 {{ form.selectedIssueIds.length }} 個項目<span v-if="!remote"> · 目前包含 {{ preview.length }} 張卡片（含 Epic）</span></p>

      <button class="button primary" :disabled="busy">{{ busy ? '儲存中…' : '儲存看板' }}</button>
    </form>
  </dialog>
</template>
