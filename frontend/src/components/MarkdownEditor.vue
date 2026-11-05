<script setup>
import { computed, nextTick, ref, useId, watch } from 'vue';
import { onTabKeydown } from '../ui/tabs.js';
import MarkdownContent from './MarkdownContent.vue';

const props = defineProps({ modelValue: { type: String, default: '' }, label: { type: String, required: true }, placeholder: String, maxlength: { type: Number, default: 6000 }, rows: { type: Number, default: 4 }, disabled: Boolean, required: Boolean, name: String });
const emit = defineEmits(['update:modelValue']);
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
watch(() => props.modelValue, value => { if (!value) mode.value = 'write'; });

async function focus() { mode.value = 'write'; await nextTick(); input.value?.scrollIntoView({ block: 'nearest' }); input.value?.focus({ preventScroll: true }); }
defineExpose({ focus });

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
  <div class="markdown-editor" role="group" :aria-label="`${label}編輯器`">
    <div class="editor-heading">
      <label :for="`${id}-input`">{{ label }}<span v-if="required" aria-hidden="true"> *</span></label>
      <div class="editor-modes" role="tablist" :aria-label="`${label}模式`" @keydown="onTabKeydown">
        <button :id="`${id}-write`" type="button" role="tab" :aria-selected="mode === 'write'" :aria-controls="`${id}-writing`" :tabindex="mode === 'write' ? 0 : -1" :disabled="disabled" @click="mode = 'write'">編輯</button>
        <button :id="`${id}-preview`" type="button" role="tab" :aria-selected="mode === 'preview'" :aria-controls="`${id}-reading`" :tabindex="mode === 'preview' ? 0 : -1" :disabled="disabled" @click="mode = 'preview'">預覽</button>
      </div>
    </div>
    <div v-show="mode === 'write'" :id="`${id}-writing`" role="tabpanel" :aria-labelledby="`${id}-write`">
      <div class="editor-tools" role="group" :aria-label="`${label}格式`">
        <button v-for="item in formats" :key="item.id" type="button" :disabled="disabled || composing" :aria-label="`${label}：${item.id === 'checklist' ? '待辦清單' : item.label}`" :title="item.label" @mousedown.prevent @click="format(item)">{{ item.label }}</button>
      </div>
      <textarea :id="`${id}-input`" ref="input" v-model="model" :name="name" :aria-label="label" :aria-describedby="`${id}-help`" :placeholder="placeholder" :maxlength="maxlength" :rows="rows" :disabled="disabled" :required="required" @keydown="onKeydown" @compositionstart="composing = true" @compositionend="composing = false"></textarea>
    </div>
    <div v-show="mode === 'preview'" :id="`${id}-reading`" class="editor-preview" role="tabpanel" tabindex="0" :aria-labelledby="`${id}-preview`">
      <MarkdownContent v-if="mode === 'preview' && modelValue.trim()" :source="modelValue" />
      <p v-else class="editor-empty">尚無內容</p>
    </div>
    <div :id="`${id}-help`" class="editor-footer"><span>{{ message || '支援 Markdown · Enter 換行' }}</span><span>{{ modelValue.length }} / {{ maxlength }}</span></div>
    <p v-if="message" class="sr-only" role="status">{{ message }}</p>
  </div>
</template>

<style scoped>
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
.editor-preview { min-height: 110px; max-height: 480px; overflow: auto; border: 1px solid #bfc6d2; border-radius: 6px; padding: 12px; background: #fff; }
.editor-footer { display: flex; justify-content: space-between; gap: 12px; margin-top: 6px; font-size: 12px; line-height: 1.6; color: #5f6673; }
.editor-footer > :last-child { flex-shrink: 0; }
.editor-empty { color: #5f6673; font-size: 14px; }
.editor-preview:focus-visible, .markdown-editor button:focus-visible { outline: 2px solid #6653bd; outline-offset: 2px; }
@media(max-width:700px) { .editor-heading { gap: 6px; }.editor-footer { flex-wrap: wrap; }.editor-tools { padding-left: 2px; padding-right: 2px; }.editor-tools button { padding: 6px; } }
</style>
