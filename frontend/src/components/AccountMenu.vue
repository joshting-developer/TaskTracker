<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';
const props = defineProps({ name: String, email: String, workspaceName: String });
const emit = defineEmits(['profile', 'logout']);
const root = ref(null), trigger = ref(null), menu = ref(null), open = ref(false), id = useId();
function focus() { trigger.value?.focus(); }
defineExpose({ focus });
function close(restore = false) { open.value = false; if (restore) focus(); }
async function show(last = false) { open.value = true; await nextTick(); const items = menu.value?.querySelectorAll('[role="menuitem"]'); items?.[last ? items.length - 1 : 0]?.focus(); }
function choose(action) { close(true); emit(action); }
function outside(event) { if (!root.value?.contains(event.target)) close(); }
function keydown(event) {
  if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(true); return; }
  if (event.key === 'Tab') { close(true); return; }
  if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
  event.preventDefault(); const items = [...menu.value.querySelectorAll('[role="menuitem"]')];
  const index = items.indexOf(document.activeElement);
  const next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
  items[next].focus();
}
onMounted(() => document.addEventListener('pointerdown', outside));
onBeforeUnmount(() => document.removeEventListener('pointerdown', outside));
</script>
<template>
  <div ref="root" class="account-menu">
    <button ref="trigger" type="button" class="account-trigger" aria-label="帳號選單" aria-haspopup="menu" :aria-expanded="open" :aria-controls="id" @click="open ? close() : show()" @keydown.down.prevent="show()" @keydown.up.prevent="show(true)"><span class="avatar tiny" aria-hidden="true">{{ name?.slice(0, 1) }}</span></button>
    <div v-if="open" :id="id" ref="menu" class="account-dropdown" role="menu" aria-label="帳號選項" @keydown="keydown">
      <div class="account-summary" role="presentation"><strong>{{ name }}</strong><span>{{ email }}</span><span>{{ workspaceName }}</span></div>
      <button type="button" role="menuitem" tabindex="-1" @click="choose('profile')">個人資料</button>
      <button type="button" role="menuitem" tabindex="-1" class="account-logout" @click="choose('logout')">登出</button>
    </div>
  </div>
</template>
<style scoped>
.account-menu { position: relative; flex-shrink: 0; }
.account-trigger { min-width: 44px; min-height: 44px; display: grid; place-items: center; border-radius: 6px; }
.account-trigger:hover, .account-trigger[aria-expanded="true"] { background: #eeecf7; }
.account-dropdown { position: absolute; top: calc(100% + 8px); right: 0; z-index: 70; width: 280px; max-width: calc(100vw - 28px); max-height: calc(100dvh - 80px); overflow-y: auto; padding: 8px; border: 1px solid #cbd0db; border-radius: 8px; background: #fff; box-shadow: 0 8px 24px #24293620; color: #242936; }
.account-summary { padding: 10px 12px 14px; border-bottom: 1px solid #daddE5; margin-bottom: 4px; overflow-wrap: anywhere; }
.account-summary strong, .account-summary span { display: block; font-size: 14px; line-height: 1.7; }
.account-summary span { color: #5f6673; font-size: 13px; }
.account-dropdown > button { display: block; width: 100%; min-height: 44px; padding: 10px 12px; text-align: left; font-size: 14px; border-radius: 4px; }
.account-dropdown > button:hover, .account-dropdown > button:focus-visible { background: #f1eff8; }
.account-trigger:focus-visible, .account-dropdown > button:focus-visible { outline: 2px solid #6653bd; outline-offset: 2px; }
.account-dropdown .account-logout { color: #a12c42; border-top: 1px solid #daddE5; margin-top: 4px; }
</style>
