<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { api } from '../api/index.js';
import Icon from './Icon.vue';
const props = defineProps({ token: String, email: String, accept: Function });
const emit = defineEmits(['close', 'logout']);
const dialog = ref(null), invitation = ref(null), loading = ref(true), busy = ref(false), error = ref('');
defineExpose({ saving: busy });
function close() { if (!busy.value) emit('close'); }
async function load() {
  loading.value = true; error.value = '';
  try { invitation.value = await api.invitation({ inviteToken: props.token }); }
  catch (failure) { error.value = failure.message; }
  finally { loading.value = false; }
}
async function join() {
  if (busy.value) return;
  busy.value = true; error.value = '';
  try { await props.accept(props.token); }
  catch (failure) { error.value = failure.message; }
  finally { busy.value = false; }
}
onMounted(() => { dialog.value.showModal(); load(); });
onBeforeUnmount(() => dialog.value?.close());
</script>
<template>
  <dialog ref="dialog" class="workspace-invitation" aria-labelledby="workspace-invite-title" @cancel.prevent="close">
    <header><h2 id="workspace-invite-title">工作空間邀請</h2><button class="icon-button" aria-label="關閉工作空間邀請" :disabled="busy" @click="close"><Icon name="close" /></button></header>
    <p v-if="loading" role="status">正在確認邀請…</p><div v-if="error" class="error-box" role="alert">{{ error }}<button v-if="!invitation" class="text-button" @click="load">重試</button></div>
    <template v-if="invitation"><h3>{{ invitation.workspaceName }}</h3><p>受邀帳號：{{ invitation.email }}</p><p v-if="email !== invitation.email">目前登入的是 {{ email }}，請切換成受邀帳號。</p><p v-else>接受後會進入這個工作空間。各專案的存取由團隊另行授權。</p><button v-if="email === invitation.email" class="button primary" :disabled="busy" @click="join">{{ busy ? '正在加入…' : '接受邀請並進入' }}</button><button v-else class="button secondary" :disabled="busy" @click="emit('logout')">改用受邀帳號登入</button></template>
  </dialog>
</template>
<style scoped>
.icon-button { min-width: 44px; min-height: 44px; }
.workspace-invitation { width: min(480px, calc(100vw - 28px)); padding: 24px; max-height: calc(100dvh - 28px); overflow-y: auto; border-radius: 8px; color: #242936; }.workspace-invitation header { display: flex; align-items: center; justify-content: space-between; gap: 12px; }.workspace-invitation h2 { font-size: 22px; }.workspace-invitation h3 { margin: 24px 0 14px; font-size: 20px; overflow-wrap: anywhere; }.workspace-invitation p { font-size: 14px; line-height: 1.7; color: #5f6673; overflow-wrap: anywhere; margin-bottom: 18px; }.workspace-invitation .button { min-height: 44px; }
</style>
