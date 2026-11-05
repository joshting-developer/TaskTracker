<script setup>
import { nextTick, onBeforeUnmount, ref } from 'vue';
import { api } from '../api/index.js';
import Icon from './Icon.vue';
const props = defineProps({ workspace: Object, switching: Boolean, change: Function });
const dialog = ref(null), trigger = ref(null), choices = ref([]), loading = ref(false), error = ref('');
function close() { if (!props.switching) { dialog.value.close(); trigger.value?.focus(); } }
async function open() {
  dialog.value.showModal(); loading.value = true; error.value = ''; choices.value = [];
  try { choices.value = (await api.listWorkspaces()).workspaces; }
  catch (failure) { error.value = failure.message; }
  finally { loading.value = false; }
}
async function select(workspace) {
  if (workspace.id === props.workspace?.id) { close(); return; }
  error.value = '';
  try { if (await props.change(workspace.id)) { dialog.value?.close(); await nextTick(); trigger.value?.focus(); } }
  catch (failure) { error.value = failure.message; }
}
onBeforeUnmount(() => dialog.value?.close());
</script>
<template>
  <button ref="trigger" class="workspace-switch" aria-label="切換工作空間" aria-haspopup="dialog" :disabled="switching" @click="open"><span class="workspace-avatar">{{ workspace?.name.slice(0, 1) }}</span><span class="switcher-current"><strong>{{ workspace?.name }}</strong><span>切換工作空間</span></span><span class="workspace-chevron">⌄</span></button>
  <dialog ref="dialog" class="workspace-picker" aria-labelledby="workspace-picker-title" @cancel.prevent="close">
    <header><h2 id="workspace-picker-title">選擇工作空間</h2><button class="icon-button" aria-label="關閉工作空間選擇" :disabled="switching" @click="close"><Icon name="close" /></button></header>
    <p v-if="loading || switching" role="status">{{ switching ? '正在切換工作空間…' : '正在載入工作空間…' }}</p>
    <div v-else-if="error" class="error-box" role="alert">{{ error }}<button class="text-button" @click="open">重新載入</button></div>
    <ul v-else class="workspace-options"><li v-for="choice in choices" :key="choice.id"><button :aria-label="`選擇工作空間 ${choice.name}`" :disabled="switching" @click="select(choice)"><span>{{ choice.name }}</span><span v-if="choice.id === workspace?.id" class="current-badge">目前工作空間</span><Icon v-else name="chevron" :size="18" /></button></li></ul>
    <p class="picker-note">這裡只顯示你已加入的工作空間。加入其他團隊請使用邀請連結。</p>
  </dialog>
</template>
<style scoped>
.icon-button { min-width: 44px; min-height: 44px; }
.switcher-current { flex: 1; min-width: 0; text-align: left; }.switcher-current strong,.switcher-current > span { display: block; overflow-wrap: anywhere; }.switcher-current > span { font-size: 13px; color: #bdc0cd; margin-top: 6px; }
.workspace-picker { width: min(480px, calc(100vw - 28px)); max-height: calc(100dvh - 28px); overflow-y: auto; padding: 24px; border-radius: 8px; color: #242936; }.workspace-picker header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 20px; }.workspace-picker h2 { font-size: 22px; }.workspace-picker p { font-size: 14px; line-height: 1.7; }.workspace-options { list-style: none; padding: 0; margin: 0; }.workspace-options button { display: flex; align-items: center; justify-content: space-between; gap: 16px; width: 100%; min-height: 60px; padding: 14px 10px; text-align: left; border-bottom: 1px solid #daddE5; font-size: 15px; overflow-wrap: anywhere; }.workspace-options button:hover { background: #f3f1f8; }.current-badge { color: #65538d; font-size: 12px; flex-shrink: 0; }.picker-note { color: #5f6673; margin-top: 20px; }
</style>
