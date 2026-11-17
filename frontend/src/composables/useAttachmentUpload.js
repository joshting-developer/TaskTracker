import { t } from '../i18n/index.js';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { queryApi, requestJson, requestUpload } from '../api/http.js';
import { releaseUploads, trackUploads } from './useUploadTray.js';

// Windows reports ZIP under older names; the server still sniffs the real content.
const ZIP_ALIASES = ['application/x-zip-compressed', 'application/x-zip'];
const MB = 1024 * 1024;

/** One editor owns its draft files and stable retry IDs; no shared file/token cache.
 * Images go into the text; PDF/ZIP stay private until the owner form binds them after its save. */
export function useAttachmentUpload({ projectId, source, insert, maxlength, allowFiles, note, existing }) {
  const rows = ref([]), limits = ref(null), error = ref('');
  const live = computed(() => rows.value.filter(row => row.state !== 'canceled' && row.state !== 'rejected'));
  const busy = computed(() => rows.value.some(row => row.state === 'uploading' || row.state === 'binding'));
  // Uploaded files wait for the owner's save, so only unfinished transfers block saving.
  const blocked = computed(() => rows.value.some(row => ['queued', 'uploading', 'binding', 'failed'].includes(row.state)));
  const unsettled = computed(() => live.value.some(row => row.state !== 'done'));
  const pendingFiles = computed(() => rows.value.filter(row => row.kind === 'file' && row.state === 'uploaded'));
  let generation = 0, limitsRequest, binding = false;
  const controllers = new Map(), owner = Symbol('attachment-upload');
  const root = () => `/api/v1/projects/${encodeURIComponent(projectId.value)}/attachments`;
  function clear() { releaseUploads(owner); generation++; limitsRequest?.abort(); for (const value of controllers.values()) value.abort(); controllers.clear(); rows.value = []; limits.value = null; error.value = ''; }
  watch(projectId, async project => {
    clear(); if (!project) return;
    limitsRequest = new AbortController(); const captured = generation;
    try { const result = await queryApi(`projects/${encodeURIComponent(project)}/attachments/limits`, {}, { signal: limitsRequest.signal }); if (generation === captured) limits.value = result; }
    catch (failure) { if (captured === generation) error.value = failure.message; }
  }, { immediate: true });
  // A reloaded issue count already includes what this editor finished, so stop counting those rows twice.
  if (existing) watch(existing, () => { for (const row of rows.value) if (row.state === 'done') row.counted = true; });
  // Move bookmarks with text edits so a slow upload does not overwrite newer typing.
  watch(source, (next, old) => {
    let start = 0, end = old.length, nextEnd = next.length;
    while (start < end && start < nextEnd && old[start] === next[start]) start++;
    while (end > start && nextEnd > start && old[end - 1] === next[nextEnd - 1]) { end--; nextEnd--; }
    for (const row of rows.value) if (row.state !== 'done') row.anchor = row.anchor >= end ? row.anchor + nextEnd - end : row.anchor > start ? nextEnd : row.anchor;
  }, { flush: 'sync' });
  function drain() { for (const row of rows.value.filter(row => row.state === 'queued')) { if (rows.value.filter(item => item.state === 'uploading').length >= 2) break; upload(row); } }
  function retry(row) { row.state = 'queued'; drain(); }
  function cancel(row) {
    controllers.get(row.id)?.abort();
    // shortcut: best-effort cleanup, an unbound upload also expires after limits.pendingHours.
    if (row.state === 'uploaded') requestJson(`${root()}/${encodeURIComponent(row.upload.id)}`, { method: 'DELETE', input: { requestId: crypto.randomUUID(), expectedRevision: row.upload.revision } }).catch(() => {});
    row.state = 'canceled'; row.error = '';
  }
  async function upload(row) {
    const captured = generation;
    const controller = new AbortController(); controllers.set(row.id, controller);
    row.state = 'uploading'; row.error = ''; row.progress = 0;
    try {
      const form = new FormData(); form.set('requestId', row.id); form.set('file', row.file, row.file.name || 'image.png');
      const result = await requestUpload(root(), form, { signal: controller.signal, onProgress: value => row.progress = value });
      if (captured !== generation || controller.signal.aborted) return;
      row.progress = 100;
      // Only the server's sniffed type decides what may be embedded as an image.
      if (row.kind === 'file' || !String(result.mime || '').startsWith('image/')) { row.kind = 'file'; row.upload = result; row.state = 'uploaded'; return; }
      const reference = `![${(row.file.name || t('圖片')).replace(/[\[\]\\\r\n]/g, '_')}](attachment:${result.id})`;
      if (source.value.length + reference.length + 2 > maxlength.value) throw new Error(t('圖片參照超過文字上限, 請縮短內容後重試'));
      if (!await insert(`\n${reference}\n`, row.anchor)) throw new Error(t('圖片尚未插入, 可重試'));
      row.state = 'done';
    } catch (failure) {
      if (captured !== generation) return;
      row.state = controller.signal.aborted ? 'canceled' : 'failed'; row.error = controller.signal.aborted ? '' : failure.message;
    } finally { controllers.delete(row.id); drain(); }
  }
  /** Attach uploaded files to a saved issue one by one; each bind returns the next issue revision. */
  async function bind(issueId, issueRevision) {
    let revision = issueRevision, failed = 0;
    if (binding) return { revision, failed: pendingFiles.value.length };
    binding = true;
    try {
      for (const row of [...pendingFiles.value]) {
        const input = { expectedRevision: row.upload.revision, issueId, issueRevision: revision };
        const signature = JSON.stringify(input);
        if (row.bindRequest?.signature !== signature) row.bindRequest = { signature, requestId: crypto.randomUUID() };
        row.state = 'binding'; row.error = '';
        try {
          const result = await requestJson(`${root()}/${encodeURIComponent(row.upload.id)}/bind`, { method: 'POST', input: { ...input, requestId: row.bindRequest.requestId } });
          revision = result.issueRevision; row.state = 'done'; row.bindRequest = null;
        } catch (failure) { row.state = 'uploaded'; row.error = failure.message; failed++; }
      }
    } finally { binding = false; }
    return { revision, failed };
  }
  function kindOf(file) {
    if (limits.value.mimeTypes.includes(file.type)) return 'image';
    if (allowFiles.value && (limits.value.general?.mimeTypes.includes(file.type) || ZIP_ALIASES.includes(file.type))) return 'file';
    return '';
  }
  function add(files, anchor) {
    error.value = '';
    if (!limits.value) { error.value = t('附件設定尚未載入'); return; }
    for (const file of files) {
      const kind = kindOf(file), maxBytes = kind === 'file' ? limits.value.general.maxBytes : limits.value.maxBytes;
      let reason = '';
      if (!kind) reason = allowFiles.value ? t('僅接受 PNG、JPEG、WebP、PDF、ZIP') : t('僅接受 PNG、JPEG、WebP');
      else if (file.size > maxBytes) reason = t('超過 {0} MB 上限', [Math.floor(maxBytes / MB)]);
      // Files already on the issue count too, so the 20th slot is refused here instead of after upload.
      else if ((existing?.value || 0) + live.value.filter(row => !row.counted).length >= limits.value.perIssue) reason = t('任務附件數量已達上限');
      else if (kind === 'file' && live.value.filter(row => row.kind === 'file').length >= limits.value.general.perIssue) reason = t('一般附件數量已達上限');
      rows.value.push({ id: crypto.randomUUID(), file, kind, anchor, state: reason ? 'rejected' : 'queued', progress: 0, error: reason, upload: null, bindRequest: null });
    }
    trackUploads(owner, rows.value.slice(rows.value.length - files.length), { retry, cancel, note: note?.value });
    drain();
  }
  onBeforeUnmount(clear);
  return { rows, limits, error, busy, blocked, unsettled, pendingFiles, add, cancel, retry, bind };
}
