import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { queryApi, requestUpload } from '../api/http.js';

/** One editor owns its draft files and stable retry IDs; no shared file/token cache. */
export function useAttachmentUpload({ projectId, source, insert, maxlength }) {
  const rows = ref([]), limits = ref(null), error = ref('');
  const busy = computed(() => rows.value.some(row => row.state === 'uploading'));
  const unsettled = computed(() => rows.value.some(row => row.state !== 'done' && row.state !== 'canceled'));
  let generation = 0, limitsRequest;
  const controllers = new Map();
  function clear() { generation++; limitsRequest?.abort(); for (const value of controllers.values()) value.abort(); controllers.clear(); rows.value = []; limits.value = null; error.value = ''; }
  watch(projectId, async project => {
    clear(); if (!project) return;
    limitsRequest = new AbortController(); const captured = generation;
    try { const result = await queryApi(`projects/${encodeURIComponent(project)}/attachments/limits`, {}, { signal: limitsRequest.signal }); if (generation === captured) limits.value = result; }
    catch (failure) { if (captured === generation) error.value = failure.message; }
  }, { immediate: true });
  // Move bookmarks with text edits so a slow upload does not overwrite newer typing.
  watch(source, (next, old) => {
    let start = 0, end = old.length, nextEnd = next.length;
    while (start < end && start < nextEnd && old[start] === next[start]) start++;
    while (end > start && nextEnd > start && old[end - 1] === next[nextEnd - 1]) { end--; nextEnd--; }
    for (const row of rows.value) if (row.state !== 'done') row.anchor = row.anchor >= end ? row.anchor + nextEnd - end : row.anchor > start ? nextEnd : row.anchor;
  }, { flush: 'sync' });
  function drain() { for (const row of rows.value.filter(row => row.state === 'queued')) { if (rows.value.filter(item => item.state === 'uploading').length >= 2) break; upload(row); } }
  function retry(row) { row.state = 'queued'; drain(); }
  function cancel(row) { controllers.get(row.id)?.abort(); row.state = 'canceled'; row.error = ''; }
  async function upload(row) {
    const captured = generation, project = projectId.value;
    const controller = new AbortController(); controllers.set(row.id, controller);
    row.state = 'uploading'; row.error = ''; row.progress = 0;
    try {
      const form = new FormData(); form.set('requestId', row.id); form.set('file', row.file, row.file.name || 'image.png');
      const result = await requestUpload(`/api/v1/projects/${encodeURIComponent(project)}/attachments`, form, { signal: controller.signal, onProgress: value => row.progress = value });
      if (captured !== generation || controller.signal.aborted) return;
      const reference = `![${(row.file.name || '圖片').replace(/[\[\]\\\r\n]/g, '_')}](attachment:${result.id})`;
      if (source.value.length + reference.length + 2 > maxlength.value) throw new Error('圖片參照超過文字上限, 請縮短內容後重試');
      if (!await insert(`\n${reference}\n`, row.anchor)) throw new Error('圖片尚未插入, 可重試');
      row.state = 'done'; row.progress = 100;
    } catch (failure) {
      if (captured !== generation) return;
      row.state = controller.signal.aborted ? 'canceled' : 'failed'; row.error = controller.signal.aborted ? '' : failure.message;
    } finally { controllers.delete(row.id); drain(); }
  }
  function add(files, anchor) {
    error.value = '';
    if (!limits.value) { error.value = '圖片設定尚未載入'; return; }
    for (const file of files) {
      if (rows.value.filter(row => row.state !== 'canceled').length >= Math.min(20, limits.value.perIssue)) { error.value = '圖片數量已達上限'; break; }
      if (!limits.value.mimeTypes.includes(file.type) || file.size > limits.value.maxBytes) { error.value = `${file.name || '圖片'}：僅接受限制大小內的 PNG、JPEG、WebP`; continue; }
      const row = { id: crypto.randomUUID(), file, anchor, state: 'queued', progress: 0, error: '' };
      rows.value.push(row); drain();
    }
  }
  onBeforeUnmount(clear);
  return { rows, limits, error, busy, unsettled, add, cancel, retry };
}
