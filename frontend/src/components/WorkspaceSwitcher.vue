<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { api } from '../api/index.js';
import Icon from './Icon.vue';
const props = defineProps({ workspace: Object, switching: Boolean, change: Function, create: Function });
const dialog = ref(null), trigger = ref(null), choices = ref([]), loading = ref(false), error = ref('');
const creating = ref(false), name = ref(''), nameInput = ref(null);
let creationRequestId = crypto.randomUUID(), opener;
function restoreFocus() { const target = opener?.isConnected && opener.getBoundingClientRect().width ? opener : trigger.value; target?.focus(); }
watch(name, () => { creationRequestId = crypto.randomUUID(); });
async function beginCreate() { creating.value = true; error.value = ''; name.value = ''; await nextTick(); nameInput.value?.focus(); }
async function submitCreation() {
  if (props.switching || !name.value.trim()) return;
  error.value = '';
  try {
    if (await props.create({ workspaceName: name.value.trim(), requestId: creationRequestId })) { dialog.value?.close(); creating.value = false; await nextTick(); restoreFocus(); }
  } catch (failure) { error.value = failure.message; }
}
function close() { if (!props.switching) { dialog.value.close(); restoreFocus(); } }
async function open() {
  opener = document.activeElement; dialog.value.showModal(); creating.value = false; loading.value = true; error.value = ''; choices.value = [];
  try { choices.value = (await api.listWorkspaces()).workspaces; }
  catch (failure) { error.value = failure.message; }
  finally { loading.value = false; }
}
async function select(workspace) {
  if (workspace.id === props.workspace?.id) { close(); return; }
  error.value = '';
  try { if (await props.change(workspace.id)) { dialog.value?.close(); await nextTick(); restoreFocus(); } }
  catch (failure) { error.value = failure.message; }
}
defineExpose({ open });
onBeforeUnmount(() => dialog.value?.close());
</script>
<template>
  <button ref="trigger" class="workspace-switch" aria-label="切換工作空間" aria-haspopup="dialog" :disabled="switching" @click="open"><span class="workspace-avatar">{{ workspace?.name?.slice(0, 1) || '—' }}</span><span class="switcher-current"><strong>{{ workspace?.name || '無隸屬工作區' }}</strong><span>切換工作空間</span></span><span class="workspace-chevron">⌄</span></button>
  <dialog ref="dialog" class="workspace-picker" aria-labelledby="workspace-picker-title" @cancel.prevent="close">
    <header><h2 id="workspace-picker-title">選擇工作空間</h2><button class="icon-button" aria-label="關閉工作空間選擇" :disabled="switching" @click="close"><Icon name="close" /></button></header>
    <p v-if="loading || switching" role="status">{{ switching ? '正在切換工作空間…' : '正在載入工作空間…' }}</p>
    <form v-if="creating" class="workspace-create" @submit.prevent="submitCreation">
      <label for="new-workspace-name">工作空間名稱</label>
      <input id="new-workspace-name" ref="nameInput" v-model="name" name="workspaceName" maxlength="60" required :disabled="switching" autocomplete="organization" />
      <p v-if="error" class="error-box" role="alert">{{ error }}</p>
      <div class="workspace-create-actions"><button type="button" class="button secondary" :disabled="switching" @click="creating = false; error = ''">返回工作空間列表</button><button type="submit" class="button primary" :disabled="switching || !name.trim()">{{ switching ? '建立中…' : '建立工作空間' }}</button></div>
    </form>
    <template v-else>
      <div v-if="error" class="error-box" role="alert">{{ error }}<button class="text-button" @click="open">重新載入</button></div>
      <ul v-else-if="!loading && !switching" class="workspace-options"><li v-for="choice in choices" :key="choice.id"><button :aria-label="`選擇工作空間 ${choice.name}`" :disabled="switching" @click="select(choice)"><span>{{ choice.name }}</span><span v-if="choice.id === workspace?.id" class="current-badge">目前工作空間</span><Icon v-else name="chevron" :size="18" /></button></li></ul>
      <button v-if="create" type="button" class="button secondary workspace-create-entry" :disabled="loading || switching" @click="beginCreate"><Icon name="plus" :size="18" />建立工作空間</button>
    </template>

  </dialog>
</template>
<style scoped>
.icon-button { min-width: 44px; min-height: 44px; }
.switcher-current { flex: 1; min-width: 0; text-align: left; }.switcher-current strong,.switcher-current > span { display: block; overflow-wrap: anywhere; }.switcher-current > span { font-size: 13px; color: #bdc0cd; margin-top: 6px; }
.workspace-picker { width: min(480px, calc(100vw - 28px)); max-height: calc(100dvh - 28px); overflow-y: auto; padding: 24px; border-radius: 8px; color: #242936; }.workspace-picker header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 20px; }.workspace-picker h2 { font-size: 22px; }.workspace-picker p { font-size: 14px; line-height: 1.7; }.workspace-options { list-style: none; padding: 0; margin: 0; }.workspace-options button { display: flex; align-items: center; justify-content: space-between; gap: 16px; width: 100%; min-height: 60px; padding: 14px 10px; text-align: left; border-bottom: 1px solid #daddE5; font-size: 15px; overflow-wrap: anywhere; }.workspace-options button:hover { background: #f3f1f8; }.current-badge { color: #65538d; font-size: 12px; flex-shrink: 0; }
.workspace-create { display: grid; gap: 12px; }.workspace-create label { font-size: 15px; font-weight: 600; }.workspace-create input { width: 100%; min-height: 44px; border: 1px solid #b9becd; border-radius: 4px; padding: 10px 12px; }.workspace-create-entry { width: 100%; margin-top: 20px; }.workspace-create-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; margin-top: 8px; }.workspace-create-actions .button { min-height: 44px; }
</style>
