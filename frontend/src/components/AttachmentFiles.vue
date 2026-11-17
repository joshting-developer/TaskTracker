<script setup>
import { t } from '../i18n/index.js';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { queryApi, requestAttachment, requestJson, requestUpload } from '../api/http.js';
import { releaseUploads, trackUploads } from '../composables/useUploadTray.js';

const props = defineProps({ issue: { type: Object, required: true }, canWrite: Boolean });
const emit = defineEmits(['changed']);
const rows = ref([]), page = ref(1), lastPage = ref(1), loading = ref(false), saving = ref(false), error = ref(''), progress = ref(0), draft = ref(null), fileInput = ref(null), limits = ref(null);
const root = computed(() => `projects/${encodeURIComponent(props.issue.projectId)}`);
let controller = new AbortController(), alive = true;
const owner = Symbol('issue-attachments');
// Per-row download/delete feedback stays next to the file instead of a page-wide toast.
const actions = reactive({}), deleting = new Map(), total = ref(0);
const MB = 1024 * 1024;
// perIssue is 20 and a page holds 20, so one page is the whole issue; past that only the total is known.
const quota = computed(() => {
  if (!limits.value || loading.value) return null;
  const general = lastPage.value > 1 ? null : rows.value.filter(row => !row.mime.startsWith('image/')).length;
  const count = total.value, full = count >= limits.value.perIssue ? t('任務附件已達 {0} 個上限', [limits.value.perIssue]) : general !== null && general >= limits.value.general.perIssue ? t('PDF/ZIP 已達 {0} 個上限', [limits.value.general.perIssue]) : '';
  return { total: count, general, full, remaining: Math.min(limits.value.perIssue - count, general === null ? Infinity : limits.value.general.perIssue - general) };
});
defineExpose({ dirty: computed(() => Boolean(draft.value)), saving });
async function load(next = 1) {
  if (loading.value) return;
  loading.value = true; error.value = '';
  try {
    const result = await queryApi(`${root.value}/issues/${encodeURIComponent(props.issue.id)}/attachments`, { page: next, perPage: 20 }, { signal: controller.signal });
    if (!alive) return;
    rows.value = result.items; total.value = result.total; page.value = result.page; lastPage.value = result.lastPage;
    if (props.canWrite && !limits.value) limits.value = await queryApi(`${root.value}/attachments/limits`, {}, { signal: controller.signal });
  } catch (failure) { if (alive) error.value = failure.message; }
  finally { if (alive) loading.value = false; }
}
function choose(event) {
  const file = event.target.files?.[0]; event.target.value = '';
  if (!file || saving.value || !props.canWrite || quota.value?.full) return;
  if (!limits.value?.general?.mimeTypes.includes(file.type)) { error.value = t('{0}：僅接受 PDF、ZIP', [file.name]); return; }
  if (file.size > limits.value.general.maxBytes) { error.value = t('{0}：超過 {1} MB 上限', [file.name, Math.floor(limits.value.general.maxBytes / MB)]); return; }
  const requestId = crypto.randomUUID();
  draft.value = { file, requestId, upload: null, bind: null, issueRevision: props.issue.revision, row: { id: requestId, file, kind: 'file', state: 'queued', progress: 0, error: '', upload: null } };
  trackUploads(owner, [draft.value.row], { retry: () => save(), cancel: discard, abortable: false });
  save();
}
function discard() {
  if (saving.value) return;
  if (draft.value) draft.value.row.state = 'canceled';
  draft.value = null; error.value = '';
}
async function save() {
  if (!draft.value || saving.value || !props.canWrite) return;
  const current = draft.value;
  saving.value = true; error.value = ''; progress.value = 0;
  const row = current.row; row.state = 'uploading'; row.error = '';
  try {
    if (!current.upload) {
      const form = new FormData(); form.set('requestId', current.requestId); form.set('file', current.file, current.file.name);
      current.upload = await requestUpload(`/api/v1/${root.value}/attachments`, form, { signal: controller.signal, onProgress: value => progress.value = row.progress = value });
      row.upload = current.upload;
    }
    row.state = 'binding';
    if (!current.bind) current.bind = { requestId: crypto.randomUUID(), expectedRevision: current.upload.revision, issueId: props.issue.id, issueRevision: current.issueRevision };
    await requestJson(`/api/v1/${root.value}/attachments/${current.upload.id}/bind`, { method: 'POST', input: current.bind, signal: controller.signal });
    if (!alive) return;
    row.state = 'done'; draft.value = null; emit('changed'); await load();
  } catch (failure) { if (alive) { error.value = failure.message; row.state = 'failed'; row.error = failure.message; } }
  finally { if (alive) saving.value = false; }
}
function rebase() {
  if (!draft.value || saving.value) return;
  draft.value.issueRevision = props.issue.revision; draft.value.bind = null; save();
}
function size(bytes) { return bytes >= MB ? `${(bytes / MB).toFixed(1)} MB` : `${(bytes / 1024).toFixed(1)} KB`; }
function typeOf(row) { return row.mime === 'application/pdf' ? 'PDF' : row.mime === 'application/zip' ? 'ZIP' : t('圖片'); }
async function download(row) {
  if (actions[row.id]?.state === 'busy') return;
  actions[row.id] = { state: 'busy', text: t('下載中…') };
  try {
    const result = await requestAttachment(`/api/v1/${root.value}/attachments/${row.id}/content?download=true`, { signal: controller.signal });
    if (!alive) return;
    const url = URL.createObjectURL(result.blob), link = document.createElement('a');
    link.href = url; link.download = row.name; link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
    actions[row.id] = { state: 'done', text: t('已開始下載') };
  } catch (failure) { if (alive) actions[row.id] = { state: 'failed', text: t('下載失敗：{0}', [failure.message]), retry: () => download(row) }; }
}
/** PDF/ZIP only: an image may still be referenced from the description or a comment. */
async function remove(row) {
  if (actions[row.id]?.state === 'busy' || !window.confirm(t('確定要刪除附件「{0}」嗎？', [row.name]))) return;
  const input = { expectedRevision: row.revision, issueId: props.issue.id, issueRevision: props.issue.revision }, signature = JSON.stringify(input);
  if (deleting.get(row.id)?.signature !== signature) deleting.set(row.id, { signature, requestId: crypto.randomUUID() });
  actions[row.id] = { state: 'busy', text: t('刪除中…') };
  try {
    await requestJson(`/api/v1/${root.value}/attachments/${row.id}`, { method: 'DELETE', input: { ...input, requestId: deleting.get(row.id).requestId }, signal: controller.signal });
    if (!alive) return;
    deleting.delete(row.id); delete actions[row.id]; emit('changed'); await load(page.value);
  } catch (failure) { if (alive) actions[row.id] = { state: 'failed', text: failure.code === 'REVISION_CONFLICT' ? t('任務已更新, 請重新載入後再刪除') : t('刪除失敗：{0}', [failure.message]) }; }
}
function accessChanged() { releaseUploads(owner); controller.abort(); rows.value = []; draft.value = null; error.value = t('附件存取權限已變更'); }
window.addEventListener('tasktracker:access-changed', accessChanged);
window.addEventListener('tasktracker:workspace-changed', accessChanged);
onBeforeUnmount(() => { releaseUploads(owner); alive = false; controller.abort(); window.removeEventListener('tasktracker:access-changed', accessChanged); window.removeEventListener('tasktracker:workspace-changed', accessChanged); });
load();
// Files attached from the description or comments change the issue count; refresh the open list without a page reload.
watch(() => props.issue.activityCounts?.attachments, (next, previous) => { if (next !== previous && next !== total.value && !saving.value) load(page.value); });
</script>

<template>
  <section class="attachment-files" :aria-label="t('任務附件')">
    <button v-if="canWrite" class="button secondary" type="button" :disabled="saving || loading || !limits || Boolean(draft) || Boolean(quota?.full)" @click="fileInput.click()">{{ t('加入檔案') }}</button>
    <p v-if="canWrite && quota" class="file-quota" :class="{ warn: quota.full || quota.remaining <= 2 }">
      <span>{{ quota.general === null ? t('{0}/{1} 個附件', [quota.total, limits.perIssue]) : t('{0}/{1} 個附件, 其中 PDF/ZIP {2}/{3}', [quota.total, limits.perIssue, quota.general, limits.general.perIssue]) }}</span>
      <span>{{ quota.full || (quota.remaining <= 2 ? t('還能再加 {0} 個', [quota.remaining]) : t('PDF/ZIP 單檔 {0} MB 以內', [Math.floor(limits.general.maxBytes / (1024 * 1024))])) }}</span>
    </p>
    <input ref="fileInput" type="file" accept=".pdf,.zip,application/pdf,application/zip" hidden :aria-label="t('上傳任務附件')" :disabled="!canWrite || saving" @change="choose" />
    <p v-if="error" class="error-box" role="alert">{{ t(error) }}</p>
    <div v-if="draft" class="file-draft">
      <span>{{ draft.file.name }}</span>
      <progress v-if="saving" :value="progress" max="100" :aria-label="t('附件上傳進度')"></progress>
      <template v-else>
        <button class="text-button" type="button" @click="save">{{ t('重試') }}</button>
        <button v-if="draft.upload" class="text-button" type="button" @click="emit('changed')">{{ t('更新任務版本') }}</button>
        <button v-if="draft.issueRevision !== issue.revision" class="text-button" type="button" @click="rebase">{{ t('依目前任務版本重試') }}</button>
        <button class="text-button" type="button" @click="discard">{{ t('取消') }}</button>
      </template>
    </div>
    <p v-if="loading" role="status">{{ t('載入中…') }}</p>
    <p v-else-if="!rows.length" class="subtle">{{ t('尚無附件') }}</p>
    <ul v-else class="file-list">
      <li v-for="row in rows" :key="row.id">
        <span class="file-info"><strong>{{ row.name }}</strong><span>{{ typeOf(row) }} · {{ size(row.bytes) }}</span>
          <span v-if="actions[row.id]" class="file-action-status" :class="actions[row.id].state" :role="actions[row.id].state === 'failed' ? 'alert' : 'status'">{{ actions[row.id].text }}</span></span>
        <span class="file-actions">
          <button class="text-button" type="button" :disabled="actions[row.id]?.state === 'busy'" :aria-label="actions[row.id]?.retry ? t('重試下載{0}', [row.name]) : t('下載{0}', [row.name])" @click="download(row)">{{ actions[row.id]?.retry ? t('重試') : t('下載') }}</button>
          <button v-if="canWrite && !row.mime.startsWith('image/')" class="text-button danger" type="button" :disabled="actions[row.id]?.state === 'busy' || saving" :aria-label="t('刪除{0}', [row.name])" @click="remove(row)">{{ t('刪除') }}</button>
        </span>
      </li>
    </ul>
    <nav v-if="lastPage > 1" class="file-pages" :aria-label="t('附件分頁')">
      <button class="text-button" type="button" :disabled="loading || page <= 1" @click="load(page - 1)">{{ t('上一頁') }}</button>
      <span>{{ page }} / {{ lastPage }}</span>
      <button class="text-button" type="button" :disabled="loading || page >= lastPage" @click="load(page + 1)">{{ t('下一頁') }}</button>
    </nav>
    <button v-if="error && !draft" class="text-button" type="button" :disabled="loading" @click="load(page)">{{ t('重新載入') }}</button>
  </section>
</template>

<style scoped>
.attachment-files { min-width: 0; }
.file-list { list-style: none; padding: 0; margin: 16px 0; }
.file-list li { display: flex; gap: 12px; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #daddE5; }
.file-info { display: grid; gap: 4px; min-width: 0; overflow-wrap: anywhere; }
.file-info > span { font-size: 12px; color: #5f6673; }
.file-draft { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 16px 0; overflow-wrap: anywhere; }
.file-quota { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 4px 12px; margin: 12px 0 0; font-size: 12px; color: #5f6673; }
.file-quota.warn { color: #9a5a00; }
.file-actions { display: flex; flex-shrink: 0; }
.file-action-status { font-size: 12px; }
.file-action-status.failed { color: #a52c3d; }
.file-action-status.done { color: #2f7a4f; }
.text-button.danger { color: #a52c3d; }
.file-pages { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.text-button { min-width: 44px; min-height: 44px; flex-shrink: 0; }
</style>
