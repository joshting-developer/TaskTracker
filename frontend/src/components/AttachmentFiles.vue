<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { queryApi, requestAttachment, requestJson, requestUpload } from '../api/http.js';

const props = defineProps({ issue: { type: Object, required: true }, canWrite: Boolean });
const emit = defineEmits(['changed']);
const rows = ref([]), page = ref(1), lastPage = ref(1), loading = ref(false), saving = ref(false), error = ref(''), progress = ref(0), draft = ref(null), fileInput = ref(null), limits = ref(null);
const root = computed(() => `projects/${encodeURIComponent(props.issue.projectId)}`);
let controller = new AbortController(), alive = true;
defineExpose({ dirty: computed(() => Boolean(draft.value)), saving });
async function load(next = 1) {
  if (loading.value) return;
  loading.value = true; error.value = '';
  try {
    const result = await queryApi(`${root.value}/issues/${encodeURIComponent(props.issue.id)}/attachments`, { page: next, perPage: 20 }, { signal: controller.signal });
    if (!alive) return;
    rows.value = result.items; page.value = result.page; lastPage.value = result.lastPage;
    if (props.canWrite && !limits.value) limits.value = await queryApi(`${root.value}/attachments/limits`, {}, { signal: controller.signal });
  } catch (failure) { if (alive) error.value = failure.message; }
  finally { if (alive) loading.value = false; }
}
function choose(event) {
  const file = event.target.files?.[0]; event.target.value = '';
  if (!file || saving.value || !props.canWrite) return;
  if (!limits.value?.general?.mimeTypes.includes(file.type) || file.size > limits.value.general.maxBytes) { error.value = '請選擇限制大小內的PDF或ZIP'; return; }
  draft.value = { file, requestId: crypto.randomUUID(), upload: null, bind: null, issueRevision: props.issue.revision };
  save();
}
async function save() {
  if (!draft.value || saving.value || !props.canWrite) return;
  const current = draft.value;
  saving.value = true; error.value = ''; progress.value = 0;
  try {
    if (!current.upload) {
      const form = new FormData(); form.set('requestId', current.requestId); form.set('file', current.file, current.file.name);
      current.upload = await requestUpload(`/api/v1/${root.value}/attachments`, form, { signal: controller.signal, onProgress: value => progress.value = value });
    }
    if (!current.bind) current.bind = { requestId: crypto.randomUUID(), expectedRevision: current.upload.revision, issueId: props.issue.id, issueRevision: current.issueRevision };
    await requestJson(`/api/v1/${root.value}/attachments/${current.upload.id}/bind`, { method: 'POST', input: current.bind, signal: controller.signal });
    if (!alive) return;
    draft.value = null; emit('changed'); await load();
  } catch (failure) { if (alive) error.value = failure.message; }
  finally { if (alive) saving.value = false; }
}
function rebase() {
  if (!draft.value || saving.value) return;
  draft.value.issueRevision = props.issue.revision; draft.value.bind = null; save();
}
async function download(row) {
  error.value = '';
  try {
    const result = await requestAttachment(`/api/v1/${root.value}/attachments/${row.id}/content?download=true`, { signal: controller.signal });
    if (!alive) return;
    const url = URL.createObjectURL(result.blob), link = document.createElement('a');
    link.href = url; link.download = row.name; link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (failure) { if (alive) error.value = failure.message; }
}
function accessChanged() { controller.abort(); rows.value = []; draft.value = null; error.value = '附件存取權限已變更'; }
window.addEventListener('tasktracker:access-changed', accessChanged);
window.addEventListener('tasktracker:workspace-changed', accessChanged);
onBeforeUnmount(() => { alive = false; controller.abort(); window.removeEventListener('tasktracker:access-changed', accessChanged); window.removeEventListener('tasktracker:workspace-changed', accessChanged); });
load();
</script>

<template>
  <section class="attachment-files" aria-label="任務附件">
    <button v-if="canWrite" class="button secondary" type="button" :disabled="saving || loading || !limits || Boolean(draft)" @click="fileInput.click()">加入檔案</button>
    <input ref="fileInput" type="file" accept=".pdf,.zip,application/pdf,application/zip" hidden aria-label="上傳任務附件" :disabled="!canWrite || saving" @change="choose" />
    <p v-if="error" class="error-box" role="alert">{{ error }}</p>
    <div v-if="draft" class="file-draft">
      <span>{{ draft.file.name }}</span>
      <progress v-if="saving" :value="progress" max="100" aria-label="附件上傳進度"></progress>
      <template v-else>
        <button class="text-button" type="button" @click="save">重試</button>
        <button v-if="draft.upload" class="text-button" type="button" @click="emit('changed')">更新任務版本</button>
        <button v-if="draft.issueRevision !== issue.revision" class="text-button" type="button" @click="rebase">依目前任務版本重試</button>
        <button class="text-button" type="button" @click="draft = null; error = ''">取消</button>
      </template>
    </div>
    <p v-if="loading" role="status">載入中…</p>
    <p v-else-if="!rows.length" class="subtle">尚無附件</p>
    <ul v-else class="file-list">
      <li v-for="row in rows" :key="row.id">
        <span class="file-info"><strong>{{ row.name }}</strong><span>{{ row.mime === 'application/pdf' ? 'PDF' : row.mime === 'application/zip' ? 'ZIP' : '圖片' }} · {{ (row.bytes / 1024).toFixed(1) }} KB</span></span>
        <button class="text-button" type="button" :aria-label="`下載${row.name}`" @click="download(row)">下載</button>
      </li>
    </ul>
    <nav v-if="lastPage > 1" class="file-pages" aria-label="附件分頁">
      <button class="text-button" type="button" :disabled="loading || page <= 1" @click="load(page - 1)">上一頁</button>
      <span>{{ page }} / {{ lastPage }}</span>
      <button class="text-button" type="button" :disabled="loading || page >= lastPage" @click="load(page + 1)">下一頁</button>
    </nav>
    <button v-if="error && !draft" class="text-button" type="button" :disabled="loading" @click="load(page)">重新載入</button>
  </section>
</template>

<style scoped>
.attachment-files { min-width: 0; }
.file-list { list-style: none; padding: 0; margin: 16px 0; }
.file-list li { display: flex; gap: 12px; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #daddE5; }
.file-info { display: grid; gap: 4px; min-width: 0; overflow-wrap: anywhere; }
.file-info > span { font-size: 12px; color: #5f6673; }
.file-draft { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 16px 0; overflow-wrap: anywhere; }
.file-pages { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.text-button { min-width: 44px; min-height: 44px; flex-shrink: 0; }
</style>
