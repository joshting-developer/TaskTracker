<script setup>
import { computed, h } from 'vue';
import { renderMarkdownDocument } from '../ui/markdown.js';
import AttachmentImage from './AttachmentImage.vue';
const props = defineProps({ source: { type: String, default: '' }, projectId: { type: String, default: '' } });
// Only sanitized DOM becomes Vue nodes. User HTML stays escaped by the Markdown renderer.
const nodes = computed(() => {
  const document = renderMarkdownDocument(props.source, Boolean(props.projectId));
  const template = window.document.createElement('template');
  template.innerHTML = document.html;
  function node(element) {
    if (element.nodeType === Node.TEXT_NODE) return element.textContent;
    if (element.nodeType !== Node.ELEMENT_NODE) return null;
    const attachment = document.attachments[Number(element.getAttribute('data-attachment-slot'))];
    if (element.hasAttribute('data-attachment-slot') && attachment) return h(AttachmentImage, { key: `${props.projectId}:${attachment.id}:${element.getAttribute('data-attachment-slot')}`, projectId: props.projectId, attachmentId: attachment.id, label: attachment.label });
    const attrs = Object.fromEntries([...element.attributes].map(attr => [attr.name, ['checked', 'disabled'].includes(attr.name) ? true : attr.value]));
    return h(element.tagName.toLowerCase(), attrs, [...element.childNodes].map(node));
  }
  return [...template.content.childNodes].map(node);
});
const Content = () => nodes.value;
</script>

<template>
  <div class="markdown-body"><Content /></div>
</template>

<style scoped>
.markdown-body { min-width: 0; font-size: 14px; line-height: 1.8; color: #343b49; white-space: normal; overflow-wrap: anywhere; }
.markdown-body :deep(p) { margin: 0 0 10px; }
.markdown-body :deep(> :last-child) { margin-bottom: 0; }
.markdown-body :deep(h2), .markdown-body :deep(h3), .markdown-body :deep(h4), .markdown-body :deep(h5), .markdown-body :deep(h6) { line-height: 1.6; font-weight: 650; margin: 16px 0 8px; }
.markdown-body :deep(h2) { font-size: 19px; }
.markdown-body :deep(h3) { font-size: 17px; }
.markdown-body :deep(h4), .markdown-body :deep(h5), .markdown-body :deep(h6) { font-size: 15px; }
.markdown-body :deep(> :first-child) { margin-top: 0; }
.markdown-body :deep(ul), .markdown-body :deep(ol) { padding-left: 24px; margin: 8px 0 12px; }
.markdown-body :deep(ul) { list-style: disc; }
.markdown-body :deep(ol) { list-style: decimal; }
.markdown-body :deep(li) { padding-left: 2px; }
.markdown-body :deep(li:has(> input[type="checkbox"])) { list-style: none; }
.markdown-body :deep(input[type="checkbox"]) { width: 16px; height: 16px; margin-right: 7px; vertical-align: -2px; accent-color: #6653bd; }
.markdown-body :deep(a[href]) { color: #5844a0; text-decoration: underline; text-underline-offset: 3px; }
.markdown-body :deep(a[href]:focus-visible) { outline: 2px solid #6653bd; outline-offset: 2px; }
.markdown-body :deep(blockquote) { margin: 12px 0; padding: 4px 14px; border-left: 3px solid #bfc6d2; color: #5f6673; }
.markdown-body :deep(code) { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; background: #f1f2f6; padding: 1px 4px; border-radius: 3px; font-size: 0.92em; }
.markdown-body :deep(pre) { max-width: 100%; overflow-x: auto; padding: 12px; margin: 12px 0; background: #f4f5f8; border-radius: 5px; white-space: pre; }
.markdown-body :deep(pre code) { padding: 0; background: none; white-space: pre; overflow-wrap: normal; }
.markdown-body :deep(table) { display: block; max-width: 100%; overflow-x: auto; border-collapse: collapse; margin: 12px 0; }
.markdown-body :deep(th), .markdown-body :deep(td) { padding: 6px 10px; border: 1px solid #daddE5; text-align: left; }
.markdown-body :deep(hr) { margin: 16px 0; border: 0; border-top: 1px solid #daddE5; }
</style>
