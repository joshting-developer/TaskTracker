<script setup>
import { t } from '../i18n/index.js';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import { useAttachmentUpload } from '../composables/useAttachmentUpload.js';
import { onTabKeydown } from '../ui/tabs.js';
import MarkdownContent from './MarkdownContent.vue';

const props = defineProps({ modelValue: { type: String, default: '' }, label: { type: String, required: true }, placeholder: String, maxlength: { type: Number, default: 6000 }, rows: { type: Number, default: 4 }, disabled: Boolean, required: Boolean, name: String, spacious: Boolean, allowAttachments: Boolean, allowFiles: Boolean, fileNote: String, projectId: { type: String, default: '' } });
const emit = defineEmits(['update:modelValue']);
const fileInput = ref(null);
const id = useId(), input = ref(null), mode = ref('write'), composing = ref(false), message = ref('');
const model = computed({ get: () => props.modelValue, set: value => { message.value = ''; emit('update:modelValue', value); } });
const formats = [
  { id: 'bold', label: t('粗體'), before: '**', after: '**', sample: t('粗體文字') },
  { id: 'italic', label: t('斜體'), before: '*', after: '*', sample: t('斜體文字') },
  { id: 'list', label: t('清單'), prefix: '- ', sample: t('清單項目') },
  { id: 'checklist', label: t('待辦'), prefix: '- [ ] ', sample: t('待辦項目') },
  { id: 'link', label: t('連結'), before: '[', after: '](https://example.com)', sample: t('連結文字') },
  { id: 'code', label: t('程式碼'), before: '```\n', after: '\n```', sample: t('程式碼') },
];
let undoFormat = null, redoFormat = null;
let largestHeight = 0, resizeObserver;
function grow() {
  const element = input.value;
  if (!element || mode.value !== 'write' || !element.getClientRects().length) return;
  largestHeight = Math.max(largestHeight, element.getBoundingClientRect().height, element.scrollHeight + element.offsetHeight - element.clientHeight);
  element.style.height = `${Math.ceil(largestHeight)}px`;
}
watch([() => props.modelValue, mode], () => nextTick(grow), { flush: 'post' });
onMounted(() => {
  grow();
  let width = 0;
  resizeObserver = new ResizeObserver(entries => {
    const next = entries[0].contentRect.width;
    if (next !== width) { width = next; grow(); }
  });
  if (input.value) resizeObserver.observe(input.value);
});
onBeforeUnmount(() => resizeObserver?.disconnect());
watch(() => props.modelValue, value => { if (!value) mode.value = 'write'; });

async function focus() { mode.value = 'write'; await nextTick(); input.value?.scrollIntoView({ block: 'nearest' }); input.value?.focus({ preventScroll: true }); }
const uploads = useAttachmentUpload({ projectId: computed(() => props.allowAttachments ? props.projectId : ''), source: computed(() => props.modelValue), maxlength: computed(() => props.maxlength), allowFiles: computed(() => props.allowFiles), note: computed(() => props.fileNote), insert: insertAttachment });
const uploadRows = uploads.rows, uploadError = uploads.error, uploadLimits = uploads.limits;
const visibleRows = computed(() => uploadRows.value.filter(row => row.state !== 'canceled'));
defineExpose({ focus, uploadBusy: uploads.busy, attachmentDirty: uploads.unsettled, attachmentBlocked: uploads.blocked, filesPending: computed(() => uploads.pendingFiles.value.length), bindFiles: uploads.bind });
const accept = computed(() => `image/png,image/jpeg,image/webp${props.allowFiles ? ',application/pdf,application/zip,.pdf,.zip' : ''}`);
function rowType(row) { return row.kind === 'image' ? t('圖片') : row.kind === 'file' ? (/pdf/i.test(row.upload?.mime || row.file.type) ? 'PDF' : 'ZIP') : t('不支援'); }
function rowStatus(row) {
  if (row.state === 'queued') return t('等待上傳');
  if (row.state === 'uploading') return `${row.progress}%`;
  if (row.state === 'binding') return t('正在附加到任務');
  if (row.state === 'uploaded') return row.error ? t('尚未附到任務：{0}', [row.error]) : props.fileNote || t('儲存後附到任務');
  if (row.state === 'done') return row.kind === 'file' ? t('已附到任務') : t('已加入內容');
  return row.error;
}
async function insertAttachment(text, anchor) {
  if (composing.value) await new Promise(resolve => { const stop = watch(composing, value => { if (!value) { stop(); resolve(); } }); });
  if (!input.value || props.disabled) return false;
  mode.value = 'write'; await nextTick();
  const start = Math.max(0, Math.min(anchor, props.modelValue.length));
  input.value.focus(); input.value.setSelectionRange(start, start);
  if (!document.execCommand('insertText', false, text)) await replace(props.modelValue.slice(0, start) + text + props.modelValue.slice(start), start + text.length, start + text.length);
  return true;
}
function addFiles(files) {
  if (props.disabled || !props.allowAttachments) return;
  uploads.add([...files], input.value?.selectionStart ?? props.modelValue.length);
}
function pasteFiles(event) {
  const files = [...(event.clipboardData?.files || [])];
  if (!props.allowAttachments || !files.length) return;
  event.preventDefault(); addFiles(files);
}
// Count enter/leave pairs because dragging across the toolbar and textarea fires both.
const dragging = ref(false);
let dragDepth = 0;
function carriesFiles(event) { return props.allowAttachments && event.dataTransfer?.types.includes('Files'); }
function dragEnter(event) { if (!carriesFiles(event)) return; dragDepth++; dragging.value = !props.disabled; }
function dragLeave(event) { if (carriesFiles(event) && --dragDepth <= 0) { dragDepth = 0; dragging.value = false; } }
function dropFiles(event) {
  dragDepth = 0; dragging.value = false;
  if (!props.allowAttachments || !event.dataTransfer?.files.length) return;
  event.preventDefault(); addFiles(event.dataTransfer.files);
}
// A file dropped beside the editor must not replace the page and lose the draft.
function guardWindow(event) { if (event.dataTransfer?.types.includes('Files')) event.preventDefault(); }
onMounted(() => { if (props.allowAttachments) { window.addEventListener('dragover', guardWindow); window.addEventListener('drop', guardWindow); } });
onBeforeUnmount(() => { window.removeEventListener('dragover', guardWindow); window.removeEventListener('drop', guardWindow); });

async function replace(next, start, end) {
  emit('update:modelValue', next);
  await nextTick();
  input.value?.focus();
  input.value?.setSelectionRange(start, end);
}
function format(item) {
  if (props.disabled || composing.value) return;
  const element = input.value, source = props.modelValue;
  const start = element.selectionStart, end = element.selectionEnd;
  const selected = source.slice(start, end) || t(item.sample);
  let from = start, to = end, inserted, selectionStart, selectionEnd;
  if (item.prefix) {
    from = start === 0 ? 0 : source.lastIndexOf('\n', start - 1) + 1;
    const lastSelected = end > start && source[end - 1] === '\n' ? end - 1 : end;
    const lineEnd = source.indexOf('\n', lastSelected);
    to = lineEnd < 0 ? source.length : lineEnd;
    const lines = source.slice(from, to) || t(item.sample);
    inserted = lines.split('\n').map(line => `${item.prefix}${line}`).join('\n');
    selectionStart = from; selectionEnd = from + inserted.length;
  } else {
    inserted = `${item.before}${selected}${item.after}`;
    selectionStart = from + item.before.length; selectionEnd = selectionStart + selected.length;
  }
  const next = source.slice(0, from) + inserted + source.slice(to);
  if (next.length > props.maxlength) { message.value = t('加入格式後會超過 {0} 字，請先縮短內容', [props.maxlength]); return; }
  undoFormat = { before: source, after: next, beforeStart: start, beforeEnd: end, afterStart: selectionStart, afterEnd: selectionEnd };
  redoFormat = null;
  replace(next, selectionStart, selectionEnd);
}
function onKeydown(event) {
  if (event.isComposing || composing.value || props.disabled || !(event.ctrlKey || event.metaKey) || event.altKey) return;
  const key = event.key.toLowerCase();
  if (key === 'b' || key === 'i') { event.preventDefault(); format(formats.find(item => item.id === (key === 'b' ? 'bold' : 'italic'))); }
  if (key === 'z' && !event.shiftKey && undoFormat && props.modelValue === undoFormat.after) {
    event.preventDefault(); redoFormat = undoFormat; undoFormat = null;
    replace(redoFormat.before, redoFormat.beforeStart, redoFormat.beforeEnd);
  } else if ((key === 'y' || key === 'z' && event.shiftKey) && redoFormat && props.modelValue === redoFormat.before) {
    event.preventDefault(); undoFormat = redoFormat; redoFormat = null;
    replace(undoFormat.after, undoFormat.afterStart, undoFormat.afterEnd);
  }
}
</script>

<template>
  <div class="markdown-editor" :class="{ spacious }" role="group" :aria-label="t('{0}編輯器', [label])">
    <div class="editor-heading">
      <label :for="`${id}-input`">{{ label }}<span v-if="required" aria-hidden="true"> *</span></label>
      <div class="editor-modes" role="tablist" :aria-label="t('{0}模式', [label])" @keydown="onTabKeydown">
        <button :id="`${id}-write`" type="button" role="tab" :aria-selected="mode === 'write'" :aria-controls="`${id}-writing`" :tabindex="mode === 'write' ? 0 : -1" :disabled="disabled" @click="mode = 'write'">{{ t('編輯') }}</button>
        <button :id="`${id}-preview`" type="button" role="tab" :aria-selected="mode === 'preview'" :aria-controls="`${id}-reading`" :tabindex="mode === 'preview' ? 0 : -1" :disabled="disabled" @click="mode = 'preview'">{{ t('預覽') }}</button>
      </div>
    </div>
    <div v-show="mode === 'write'" class="editor-writing" :class="{ dragging }" @dragenter="dragEnter" @dragleave="dragLeave" @dragover="event => carriesFiles(event) && event.preventDefault()" @drop="dropFiles" :id="`${id}-writing`" role="tabpanel" :aria-labelledby="`${id}-write`">
      <div class="editor-tools" role="group" :aria-label="t('{0}格式', [label])">
        <button v-for="item in formats" :key="item.id" type="button" :disabled="disabled || composing" :aria-label="`${label}：${item.id === 'checklist' ? t('待辦清單') : t(item.label)}`" :title="t(item.label)" @mousedown.prevent @click="format(item)">{{ t(item.label) }}</button>
        <button v-if="allowAttachments" type="button" :disabled="disabled || !uploadLimits" :aria-label="allowFiles ? t('選擇{0}附件', [label]) : t('選擇{0}圖片', [label])" @click="fileInput.click()">{{ allowFiles ? t('附件') : t('圖片') }}</button>
        <input v-if="allowAttachments" ref="fileInput" type="file" :accept="accept" multiple hidden :aria-label="allowFiles ? t('上傳{0}附件', [label]) : t('上傳{0}圖片', [label])" @change="event => { addFiles(event.target.files); event.target.value = ''; }" />
      </div>
      <textarea :id="`${id}-input`" ref="input" v-model="model" :name="name" :aria-label="label" :aria-describedby="`${id}-help${allowAttachments && uploadError ? ` ${id}-upload-error` : ''}`" :placeholder="placeholder" :maxlength="maxlength" :rows="rows" :disabled="disabled" :required="required" @paste="pasteFiles" @keydown="onKeydown" @compositionstart="composing = true" @compositionend="composing = false"></textarea>
      <p v-if="dragging" class="drop-hint" aria-hidden="true">{{ allowFiles ? t('放開即可加入圖片或 PDF、ZIP 附件') : t('放開即可加入圖片') }}</p>
    </div>
    <div v-show="mode === 'preview'" :id="`${id}-reading`" class="editor-preview" role="tabpanel" tabindex="0" :aria-labelledby="`${id}-preview`">
      <MarkdownContent v-if="mode === 'preview' && modelValue.trim()" :source="modelValue" :project-id="projectId" />
      <p v-else class="editor-empty">{{ t('尚無內容') }}</p>
    </div>
    <ul v-if="allowAttachments && visibleRows.length" class="attachment-uploads" :aria-label="allowFiles ? t('附件上傳狀態') : t('圖片上傳狀態')">
      <li v-for="row in visibleRows" :key="row.id" :class="row.state">
        <span class="upload-name">{{ row.file.name || t('圖片') }}</span>
        <span class="upload-type">{{ rowType(row) }}</span>
        <progress v-if="row.state === 'uploading'" :value="row.progress" max="100" :aria-label="t('{0}上傳進度', [row.file.name])"></progress>
        <span :role="row.state === 'failed' || row.state === 'rejected' || row.error ? 'alert' : 'status'">{{ rowStatus(row) }}</span>
        <button v-if="row.state === 'failed'" type="button" @click="uploads.retry(row)">{{ t('重試') }}</button>
        <button v-if="row.state === 'rejected'" type="button" @click="uploads.cancel(row)">{{ t('移除') }}</button>
        <button v-else-if="row.state !== 'done' && row.state !== 'binding'" type="button" @click="uploads.cancel(row)">{{ t('取消') }}</button>
      </li>
    </ul>
    <p v-if="allowAttachments && uploadError" :id="`${id}-upload-error`" class="attachment-error" role="alert">{{ uploadError }}</p>
    <div :id="`${id}-help`" class="editor-footer"><span>{{ message }}</span><span>{{ modelValue.length }} / {{ maxlength }}</span></div>
    <p v-if="message" class="sr-only" role="status">{{ message }}</p>
  </div>
</template>

<style scoped>
.attachment-uploads { padding: 0; margin: 8px 0; list-style: none; font-size: 13px; }
.attachment-uploads li { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin: 4px 0; overflow-wrap: anywhere; }
.attachment-uploads .upload-name { flex: 1; min-width: 100px; }
.attachment-uploads .upload-type { padding: 1px 6px; border: 1px solid #d6d1ee; border-radius: 4px; color: #5844a0; font-size: 12px; }
.attachment-uploads li.rejected .upload-name, .attachment-uploads li.failed .upload-name { color: #a52c3d; }
.editor-writing { position: relative; }
.editor-writing.dragging textarea { border: 2px dashed #6653bd; background: #f6f4fd; }
.drop-hint { position: absolute; inset: auto 12px 12px; margin: 0; padding: 8px 12px; border-radius: 6px; background: #5844a0; color: #fff; font-size: 13px; text-align: center; pointer-events: none; }
.attachment-uploads progress { width: 100px; accent-color: #6653bd; }
.attachment-uploads button { min-height: 44px; padding: 8px; color: #5844a0; }
.attachment-error { color: #a52c3d; font-size: 13px; }
.markdown-editor { min-width: 0; width: 100%; margin: 0 0 16px; color: #424b5c; font-size: 14px; text-align: left; }
.editor-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 6px; }
.editor-heading > label { font-weight: 500; }
.editor-modes { display: flex; gap: 4px; flex-shrink: 0; }
.editor-modes button { min-height: 44px; min-width: 44px; padding: 8px; border-bottom: 2px solid transparent; font-size: 13px; color: #5f6673; }
.editor-modes button[aria-selected="true"] { color: #5844a0; border-bottom-color: #6653bd; }
.editor-tools { display: flex; flex-wrap: wrap; gap: 2px; padding: 4px 6px; border: 1px solid #bfc6d2; border-bottom: 0; border-radius: 6px 6px 0 0; background: #f8f9fb; }
.editor-tools button { min-height: 44px; min-width: 44px; padding: 7px; font-size: 13px; }
.editor-tools button:hover:not(:disabled) { background: #eeecf7; color: #5844a0; border-radius: 4px; }
.markdown-editor textarea { display: block; width: 100%; padding: 12px; border: 1px solid #bfc6d2; border-radius: 0 0 6px 6px; background: #fff; color: #242936; font-size: 14px; line-height: 1.8; resize: vertical; min-height: 88px; }
.markdown-editor.spacious textarea { min-height: max(240px, calc(100dvh - 390px)); }
.editor-preview { min-height: 110px; max-height: 480px; overflow: auto; border: 1px solid #bfc6d2; border-radius: 6px; padding: 12px; background: #fff; }
.editor-footer { display: flex; justify-content: space-between; gap: 12px; margin-top: 6px; font-size: 12px; line-height: 1.6; color: #5f6673; }
.editor-footer > :last-child { flex-shrink: 0; }
.editor-empty { color: #5f6673; font-size: 14px; }
.editor-preview:focus-visible, .markdown-editor button:focus-visible { outline: 2px solid #6653bd; outline-offset: 2px; }
@media(max-width:700px) { .editor-heading { gap: 6px; }.editor-footer { flex-wrap: wrap; }.editor-tools { padding-left: 2px; padding-right: 2px; }.editor-tools button { padding: 6px; } }
</style>
