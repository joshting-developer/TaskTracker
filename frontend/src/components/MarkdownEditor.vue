<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import { useAttachmentUpload } from '../composables/useAttachmentUpload.js';
import { onTabKeydown } from '../ui/tabs.js';
import MarkdownContent from './MarkdownContent.vue';

const props = defineProps({ modelValue: { type: String, default: '' }, label: { type: String, required: true }, placeholder: String, maxlength: { type: Number, default: 6000 }, rows: { type: Number, default: 4 }, disabled: Boolean, required: Boolean, name: String, spacious: Boolean, allowAttachments: Boolean, projectId: { type: String, default: '' } });
const emit = defineEmits(['update:modelValue']);
const fileInput = ref(null);
const id = useId(), input = ref(null), mode = ref('write'), composing = ref(false), message = ref('');
const model = computed({ get: () => props.modelValue, set: value => { message.value = ''; emit('update:modelValue', value); } });
const formats = [
  { id: 'bold', label: '粗體', before: '**', after: '**', sample: '粗體文字' },
  { id: 'italic', label: '斜體', before: '*', after: '*', sample: '斜體文字' },
  { id: 'list', label: '清單', prefix: '- ', sample: '清單項目' },
  { id: 'checklist', label: '待辦', prefix: '- [ ] ', sample: '待辦項目' },
  { id: 'link', label: '連結', before: '[', after: '](https://example.com)', sample: '連結文字' },
  { id: 'code', label: '程式碼', before: '```\n', after: '\n```', sample: '程式碼' },
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
const uploads = useAttachmentUpload({ projectId: computed(() => props.allowAttachments ? props.projectId : ''), source: computed(() => props.modelValue), maxlength: computed(() => props.maxlength), insert: insertAttachment });
const uploadRows = uploads.rows, uploadError = uploads.error, uploadLimits = uploads.limits;
const attachmentBlocked = uploads.unsettled;
defineExpose({ focus, uploadBusy: uploads.busy, attachmentDirty: uploads.unsettled, attachmentBlocked });
async function insertAttachment(text, anchor) {
  if (composing.value) await new Promise(resolve => { const stop = watch(composing, value => { if (!value) { stop(); resolve(); } }); });
  if (!input.value || props.disabled) return false;
  mode.value = 'write'; await nextTick();
  const start = Math.max(0, Math.min(anchor, props.modelValue.length));
  input.value.focus(); input.value.setSelectionRange(start, start);
  if (!document.execCommand('insertText', false, text)) await replace(props.modelValue.slice(0, start) + text + props.modelValue.slice(start), start + text.length, start + text.length);
  return true;
}
function addImages(files) {
  if (props.disabled || !props.allowAttachments) return;
  uploads.add([...files], input.value?.selectionStart ?? props.modelValue.length);
}
function pasteImage(event) {
  const images = [...(event.clipboardData?.files || [])];
  if (!props.allowAttachments || !images.length) return;
  event.preventDefault(); addImages(images);
}
function dropImage(event) {
  if (!props.allowAttachments || !event.dataTransfer?.files.length) return;
  event.preventDefault(); addImages(event.dataTransfer.files);
}

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
  const selected = source.slice(start, end) || item.sample;
  let from = start, to = end, inserted, selectionStart, selectionEnd;
  if (item.prefix) {
    from = start === 0 ? 0 : source.lastIndexOf('\n', start - 1) + 1;
    const lastSelected = end > start && source[end - 1] === '\n' ? end - 1 : end;
    const lineEnd = source.indexOf('\n', lastSelected);
    to = lineEnd < 0 ? source.length : lineEnd;
    const lines = source.slice(from, to) || item.sample;
    inserted = lines.split('\n').map(line => `${item.prefix}${line}`).join('\n');
    selectionStart = from; selectionEnd = from + inserted.length;
  } else {
    inserted = `${item.before}${selected}${item.after}`;
    selectionStart = from + item.before.length; selectionEnd = selectionStart + selected.length;
  }
  const next = source.slice(0, from) + inserted + source.slice(to);
  if (next.length > props.maxlength) { message.value = `加入格式後會超過 ${props.maxlength} 字，請先縮短內容`; return; }
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
  <div class="markdown-editor" :class="{ spacious }" role="group" :aria-label="`${label}編輯器`">
    <div class="editor-heading">
      <label :for="`${id}-input`">{{ label }}<span v-if="required" aria-hidden="true"> *</span></label>
      <div class="editor-modes" role="tablist" :aria-label="`${label}模式`" @keydown="onTabKeydown">
        <button :id="`${id}-write`" type="button" role="tab" :aria-selected="mode === 'write'" :aria-controls="`${id}-writing`" :tabindex="mode === 'write' ? 0 : -1" :disabled="disabled" @click="mode = 'write'">編輯</button>
        <button :id="`${id}-preview`" type="button" role="tab" :aria-selected="mode === 'preview'" :aria-controls="`${id}-reading`" :tabindex="mode === 'preview' ? 0 : -1" :disabled="disabled" @click="mode = 'preview'">預覽</button>
      </div>
    </div>
    <div v-show="mode === 'write'" @dragover="event => allowAttachments && event.dataTransfer?.types.includes('Files') && event.preventDefault()" @drop="dropImage" :id="`${id}-writing`" role="tabpanel" :aria-labelledby="`${id}-write`">
      <div class="editor-tools" role="group" :aria-label="`${label}格式`">
        <button v-for="item in formats" :key="item.id" type="button" :disabled="disabled || composing" :aria-label="`${label}：${item.id === 'checklist' ? '待辦清單' : item.label}`" :title="item.label" @mousedown.prevent @click="format(item)">{{ item.label }}</button>
        <button v-if="allowAttachments" type="button" :disabled="disabled || !uploadLimits" :aria-label="`選擇${label}圖片`" @click="fileInput.click()">圖片</button>
        <input v-if="allowAttachments" ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp" multiple hidden :aria-label="`上傳${label}圖片`" @change="event => { addImages(event.target.files); event.target.value = ''; }" />
      </div>
      <textarea :id="`${id}-input`" ref="input" v-model="model" :name="name" :aria-label="label" :aria-describedby="`${id}-help${allowAttachments && uploadError ? ` ${id}-upload-error` : ''}`" :placeholder="placeholder" :maxlength="maxlength" :rows="rows" :disabled="disabled" :required="required" @paste="pasteImage" @keydown="onKeydown" @compositionstart="composing = true" @compositionend="composing = false"></textarea>
    </div>
    <div v-show="mode === 'preview'" :id="`${id}-reading`" class="editor-preview" role="tabpanel" tabindex="0" :aria-labelledby="`${id}-preview`">
      <MarkdownContent v-if="mode === 'preview' && modelValue.trim()" :source="modelValue" :project-id="projectId" />
      <p v-else class="editor-empty">尚無內容</p>
    </div>
    <ul v-if="allowAttachments && uploadRows.some(row => row.state !== 'canceled')" class="attachment-uploads" aria-label="圖片上傳狀態">
      <li v-for="row in uploadRows.filter(item => item.state !== 'canceled')" :key="row.id">
        <span>{{ row.file.name || '圖片' }}</span>
        <progress v-if="row.state === 'uploading'" :value="row.progress" max="100" :aria-label="`${row.file.name}上傳進度`"></progress>
        <span :role="row.state === 'failed' ? 'alert' : 'status'">{{ row.state === 'done' ? '已加入內容' : row.state === 'queued' ? '等待上傳' : row.state === 'failed' ? row.error : `${row.progress}%` }}</span>
        <button v-if="row.state === 'failed'" type="button" @click="uploads.retry(row)">重試</button>
        <button v-if="row.state !== 'done'" type="button" @click="uploads.cancel(row)">取消</button>
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
.attachment-uploads li > :first-child { flex: 1; min-width: 100px; }
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
