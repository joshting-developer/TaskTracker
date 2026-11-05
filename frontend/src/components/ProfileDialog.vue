<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { api } from '../api/index.js';
import Icon from './Icon.vue';
const emit = defineEmits(['close', 'saved']);
const dialog = ref(null), profile = ref(null), name = ref(''), loading = ref(true), saving = ref(false), error = ref(''), conflict = ref(false);
const dirty = computed(() => Boolean(profile.value && name.value !== profile.value.name));
defineExpose({ dirty, saving });
let pending;
function close() { if (!saving.value && (!dirty.value || window.confirm('還有未儲存的個人資料，確定要關閉嗎？'))) emit('close'); }
function beforeUnload(event) { if (dirty.value) { event.preventDefault(); event.returnValue = ''; } }
async function load(confirm = false) {
  if (confirm && dirty.value && !window.confirm('重新載入會捨棄目前修改，要繼續嗎？')) return;
  loading.value = true; error.value = ''; conflict.value = false;
  try { profile.value = await api.getProfile(); name.value = profile.value.name; pending = null; }
  catch (failure) { error.value = failure.message; }
  finally { loading.value = false; }
}
async function save() {
  if (saving.value || !dirty.value) return;
  saving.value = true; error.value = ''; conflict.value = false;
  const payload = { name: name.value, expectedRevision: profile.value.revision };
  const signature = JSON.stringify(payload);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  try { await api.updateProfile({ ...payload, requestId: pending.requestId }); profile.value = { ...profile.value, name: name.value }; emit('saved'); }
  catch (failure) { error.value = failure.message; conflict.value = failure.code === 'REVISION_CONFLICT'; }
  finally { saving.value = false; }
}
onMounted(() => { dialog.value.showModal(); load(); window.addEventListener('beforeunload', beforeUnload); });
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
</script>
<template>
  <dialog ref="dialog" class="profile-dialog" aria-labelledby="profile-heading" @cancel.prevent="close">
    <header class="section-title"><h2 id="profile-heading">個人資料</h2><button class="icon-button" aria-label="關閉個人資料" :disabled="saving" @click="close"><Icon name="close" /></button></header>
    <p v-if="loading" role="status">正在載入個人資料…</p>
    <div v-if="error" class="error-box" role="alert">{{ error }}<button v-if="!saving" class="text-button" @click="load(true)">{{ conflict ? '重新載入個人資料' : '重試載入' }}</button></div>
    <form v-if="profile" @submit.prevent="save">
      <label class="field">顯示名稱<input v-model="name" maxlength="60" required :disabled="saving || loading" autocomplete="name" /></label>
      <label class="field">Email<input :value="profile.email" type="email" readonly aria-describedby="profile-email-note" /></label>
      <p id="profile-email-note" class="subtle">Email目前作為登入帳號，這裡先提供顯示名稱修改。</p>
      <button class="button primary" :disabled="saving || loading || !dirty || !name.trim()">{{ saving ? '儲存中…' : '儲存個人資料' }}</button>
    </form>
  </dialog>
</template>
<style scoped>
.profile-dialog { width: min(480px, calc(100vw - 28px)); max-height: calc(100dvh - 28px); overflow-y: auto; margin: auto; padding: 24px; border: 1px solid #daddE5; border-radius: 10px; background: #fff; color: #242936; }
.profile-dialog::backdrop { background: #20202b65; }
.section-title { display: flex; justify-content: space-between; gap: 16px; align-items: center; margin-bottom: 22px; }
h2 { font-size: 22px; }
.field { font-size: 14px; color: #424b5c; }
.field input { font-size: 14px; border-color: #bfc6d2; }
.subtle { font-size: 13px; color: #5f6673; line-height: 1.8; margin: 12px 0 22px; }
.icon-button { min-width: 44px; min-height: 44px; }
</style>
