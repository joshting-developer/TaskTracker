<script setup>
import { onBeforeUnmount, onMounted, ref, useId } from 'vue';
const root = ref(null), options = ref(null), position = ref({ visibility: 'hidden' }), id = useId();
function close(restoreFocus = false) {
  if (!root.value?.open) return;
  root.value.open = false;
  if (restoreFocus) root.value.querySelector('summary')?.focus();
}
function positionMenu() {
  if (!root.value.open) { position.value = { visibility: 'hidden' }; return; }
  const anchor = root.value.querySelector('summary').getBoundingClientRect();
  const menu = options.value.getBoundingClientRect();
  const top = anchor.bottom + menu.height + 4 <= innerHeight - 8 ? anchor.bottom + 4 : Math.max(8, anchor.top - menu.height - 4);
  const left = Math.max(8, Math.min(anchor.right - menu.width, innerWidth - menu.width - 8));
  position.value = { top: `${top}px`, left: `${left}px`, visibility: 'visible' };
}
function outside(event) { if (!root.value?.contains(event.target)) close(); }
function scroll(event) {
  if (!root.value?.open || options.value?.contains(event.target)) return;
  const anchor = root.value.querySelector('summary').getBoundingClientRect();
  if (anchor.bottom < 0 || anchor.top > innerHeight) close();
  else positionMenu();
}
function resize() { close(); }
function select(event) { if (event.target.closest('button')) close(true); }
function keydown(event) {
  if (event.key === 'Escape' && root.value.open) { event.preventDefault(); event.stopPropagation(); close(true); }
}
onMounted(() => {
  document.addEventListener('pointerdown', outside, true);
  window.addEventListener('scroll', scroll, true);
  window.addEventListener('resize', resize);
});
onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', outside, true);
  window.removeEventListener('scroll', scroll, true);
  window.removeEventListener('resize', resize);
});
</script>
<template>
  <details ref="root" class="todo-actions" @toggle="positionMenu" @keydown="keydown">
    <summary aria-label="待辦操作" :aria-controls="id">更多</summary>
    <div :id="id" ref="options" class="todo-options" :style="position" @click="select"><slot /></div>
  </details>
</template>
<style scoped>
.todo-actions { flex-shrink: 0; font-size: 13px; }
summary { min-width: 44px; min-height: 44px; padding: 10px 0; margin-top: -10px; cursor: pointer; color: #5844a0; }
.todo-options { position: fixed; z-index: 80; display: flex; flex-direction: column; min-width: 176px; max-width: calc(100vw - 16px); max-height: calc(100dvh - 16px); overflow-y: auto; padding: 4px; background: #fff; color: #242936; border: 1px solid #daddE5; border-radius: 7px; box-shadow: 0 4px 16px #24293614; }
.todo-options :deep(button) { min-height: 44px; width: 100%; padding: 10px 12px; text-align: left; white-space: nowrap; border-radius: 4px; }
.todo-options :deep(button:hover:not(:disabled)), .todo-options :deep(button:focus-visible) { background: #f3f1fa; }
</style>
