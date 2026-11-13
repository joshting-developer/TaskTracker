<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { loadAttachmentImage } from '../api/attachments.js';

const props = defineProps({ projectId: { type: String, required: true }, attachmentId: { type: String, required: true }, label: { type: String, default: '圖片' } });
const root = ref(null), dialog = ref(null), trigger = ref(null), image = ref(null), error = ref(''), busy = ref(false), dimensions = ref('');
let observer, controller, url = '', alive = true;
function release() {
  controller?.abort();
  if (url) URL.revokeObjectURL(url);
  url = ''; image.value = null;
  dialog.value?.close();
}
async function load() {
  if (busy.value) return false;
  controller?.abort(); controller = new AbortController();
  const current = controller;
  busy.value = true; error.value = '';
  try {
    const result = await loadAttachmentImage(props.projectId, props.attachmentId, current.signal);
    if (!alive || current.signal.aborted) return false;
    if (url) URL.revokeObjectURL(url);
    url = URL.createObjectURL(result.blob);
    image.value = { ...result, url };
    return true;
  } catch (failure) {
    if (!alive || current.signal.aborted) return false;
    release(); error.value = '圖片無法載入或已無存取權限';
    return false;
  } finally { if (current === controller) busy.value = false; }
}
async function expand() {
  // Reauthorize each new viewing/download action, never reuse a signed URL.
  if (await load()) { await nextTick(); dialog.value?.showModal(); }
}
function close() { dialog.value?.close(); trigger.value?.focus({ preventScroll: true }); }
async function download() {
  if (!await load()) return;
  const link = document.createElement('a');
  link.href = url; link.download = image.value.name; link.click();
}
function accessChanged() { release(); error.value = '圖片存取權限已變更'; busy.value = false; }
function decoded(event) { dimensions.value = `${event.target.naturalWidth} × ${event.target.naturalHeight}`; }
onMounted(() => {
  observer = new IntersectionObserver(entries => { if (entries.some(entry => entry.isIntersecting)) { observer.disconnect(); load(); } }, { rootMargin: '160px' });
  observer.observe(root.value);
  window.addEventListener('tasktracker:access-changed', accessChanged);
  window.addEventListener('tasktracker:workspace-changed', accessChanged);
});
onBeforeUnmount(() => {
  alive = false; observer?.disconnect(); release();
  window.removeEventListener('tasktracker:access-changed', accessChanged);
  window.removeEventListener('tasktracker:workspace-changed', accessChanged);
});
</script>

<template>
  <span ref="root" class="attachment-image">
    <button v-if="image" ref="trigger" type="button" class="image-preview" :aria-label="`放大圖片：${label}`" :disabled="busy" @click="expand">
      <img :src="image.url" :alt="label" loading="lazy" decoding="async" @load="decoded" @error="accessChanged" />
    </button>
    <span v-else class="image-placeholder" role="status">{{ error || (busy ? '圖片載入中…' : label) }}</span>
    <span class="image-caption"><span>{{ image?.name || label }}</span><span v-if="image">{{ (image.bytes / 1024).toFixed(1) }} KB<span v-if="dimensions"> · {{ dimensions }}</span></span><button v-if="error" type="button" :disabled="busy" @click="load">重試</button></span>
    <Teleport to="body">
      <dialog ref="dialog" class="attachment-lightbox" :aria-label="`圖片：${label}`" @cancel.prevent="close" @click="event => event.target === dialog && close()">
        <header><strong>{{ image?.name || label }}</strong><span><button type="button" :disabled="busy" @click="download">下載圖片</button><button type="button" aria-label="關閉圖片" @click="close">關閉</button></span></header>
        <img v-if="image" :src="image.url" :alt="label" />
        <p v-if="error" role="alert">{{ error }}</p>
      </dialog>
    </Teleport>
  </span>
</template>

<style scoped>
.attachment-image { display: inline-flex; vertical-align: top; flex-direction: column; max-width: 100%; width: min(100%, 640px); margin: 8px 0 12px; gap: 6px; }
.image-preview { display: block; width: 100%; min-height: 80px; padding: 0; overflow: hidden; border: 1px solid #daddE5; border-radius: 5px; background: #f8f9fb; cursor: zoom-in; }
.image-preview img { display: block; width: 100%; max-height: 360px; object-fit: contain; }
.image-placeholder { display: grid; place-content: center; min-height: 100px; border: 1px solid #daddE5; background: #f8f9fb; color: #5f6673; padding: 16px; }
.image-caption { display: flex; flex-wrap: wrap; gap: 4px 12px; align-items: center; color: #5f6673; font-size: 12px; line-height: 1.6; overflow-wrap: anywhere; }
.image-caption > :first-child { flex: 1; }
.image-caption button, .attachment-lightbox button { min-height: 44px; padding: 8px 12px; color: #5844a0; border: 1px solid #bfc6d2; border-radius: 5px; background: #fff; cursor: pointer; }
.attachment-image button:focus-visible, .attachment-lightbox button:focus-visible { outline: 2px solid #6653bd; outline-offset: 3px; }
.attachment-lightbox { margin: auto; width: min(1100px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); padding: 16px; border: 1px solid #bfc6d2; border-radius: 6px; color: #242936; background: #fff; overflow: auto; }
.attachment-lightbox::backdrop { background: rgb(0 0 0 / .65); }
.attachment-lightbox header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; overflow-wrap: anywhere; }
.attachment-lightbox header strong { min-width: 0; }
.attachment-lightbox header span { display: flex; flex-shrink: 0; gap: 8px; }
.attachment-lightbox > img { display: block; max-width: 100%; max-height: calc(100dvh - 140px); margin: auto; object-fit: contain; }
@media(max-width: 600px) { .attachment-lightbox header { flex-wrap: wrap; }.image-preview img { max-height: 260px; } }
</style>
