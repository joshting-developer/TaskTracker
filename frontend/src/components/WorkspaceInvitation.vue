<script setup>
import { t } from '../i18n/index.js';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { api } from '../api/index.js';
import { useLeaveGuard } from '../ui/leaveGuard.js';
import Icon from './Icon.vue';
const props = defineProps({ token: String, email: String, accept: Function });
const emit = defineEmits(['close', 'logout']);
const dialog = ref(null), invitation = ref(null), loading = ref(true), busy = ref(false), error = ref('');
useLeaveGuard({ saving: () => Boolean(busy.value) });
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
async function decline() {
  if (busy.value) return;
  busy.value = true; error.value = '';
  try { await api.declineWorkspaceInvite({ inviteToken: props.token }); emit('close'); }
  catch (failure) { error.value = failure.message; }
  finally { busy.value = false; }
}
onMounted(() => { dialog.value.showModal(); load(); });
onBeforeUnmount(() => dialog.value?.close());
</script>
<template>
  <dialog ref="dialog" class="workspace-invitation" aria-labelledby="workspace-invite-title" @cancel.prevent="close">
    <header><h2 id="workspace-invite-title">{{ t('工作空間邀請') }}</h2><button class="icon-button" :aria-label="t('關閉工作空間邀請')" :disabled="busy" @click="close"><Icon name="close" /></button></header>
    <p v-if="loading" role="status">{{ t('正在確認邀請…') }}</p><div v-if="error" class="error-box" role="alert">{{ t(error) }}<button v-if="!invitation" class="text-button" @click="load">{{ t('重試') }}</button></div>
    <template v-if="invitation"><h3>{{ invitation.workspaceName }}</h3><p>{{ t('受邀帳號：') }}{{ invitation.email }}</p>
      <p v-if="invitation.declined || invitation.consumed" role="status">{{ invitation.declined ? t('邀請已拒絕') : t('邀請已使用') }}</p>
      <template v-else-if="email === invitation.email"><div class="invitation-actions"><button class="button secondary" :disabled="busy" @click="decline">{{ t('拒絕邀請') }}</button><button class="button primary" :disabled="busy" @click="join">{{ busy ? t('處理中…') : t('接受邀請並進入') }}</button></div></template>
      <template v-else><p>{{ t('目前登入的是') }} {{ email }}{{ t(', 請切換成受邀帳號') }}</p><button class="button secondary" :disabled="busy" @click="emit('logout')">{{ t('改用受邀帳號登入') }}</button></template>
    </template>
  </dialog>
</template>
<style scoped>
.invitation-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 12px; }
.icon-button { min-width: 44px; min-height: 44px; }
.workspace-invitation { width: min(480px, calc(100vw - 28px)); padding: 24px; max-height: calc(100dvh - 28px); overflow-y: auto; border-radius: 8px; color: #242936; }.workspace-invitation header { display: flex; align-items: center; justify-content: space-between; gap: 12px; }.workspace-invitation h2 { font-size: 22px; }.workspace-invitation h3 { margin: 24px 0 14px; font-size: 20px; overflow-wrap: anywhere; }.workspace-invitation p { font-size: 14px; line-height: 1.7; color: #5f6673; overflow-wrap: anywhere; margin-bottom: 18px; }.workspace-invitation .button { min-height: 44px; }
</style>
