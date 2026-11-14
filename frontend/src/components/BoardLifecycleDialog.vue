<script setup>
import { onBeforeUnmount, onMounted, ref, useId } from 'vue';
const props = defineProps({ board: Object, action: String, busy: Boolean, error: String });
const emit = defineEmits(['close', 'confirm', 'reload']);
const dialog = ref(null), id = useId();
function close() { if (!props.busy) emit('close'); }
function keepFocus(event) {
  if (event.key !== 'Tab') return;
  const buttons = [...dialog.value.querySelectorAll('button:not(:disabled)')];
  if (!buttons.length) { event.preventDefault(); return; }
  const first = buttons[0], last = buttons.at(-1);
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
}
onMounted(() => dialog.value.showModal());
onBeforeUnmount(() => dialog.value?.close());
</script>
<template>
  <dialog ref="dialog" class="board-lifecycle-dialog" :aria-labelledby="id" :aria-describedby="`${id}-description`" :aria-busy="busy" @cancel.prevent="close" @keydown="keepFocus">
    <form @submit.prevent="$emit('confirm')">
      <h2 :id="id">{{ action === 'archive' ? '封存' : '還原' }}看板「{{ board.name }}」？</h2>
      <p :id="`${id}-description`">{{ action === 'archive' ? '卡片、留言和工時都會保留, 之後可從已封存看板還原' : '還原後會回到使用中列表, 保留原本的卡片收錄' }}</p>
      <div v-if="error" class="error-box" role="alert">{{ error }}<button type="button" class="text-button" :disabled="busy" @click="$emit('reload')">重新載入</button></div>
      <footer><button type="button" class="button secondary" autofocus :disabled="busy" @click="close">取消</button><button class="button primary" :disabled="busy || Boolean(error)">{{ busy ? '處理中…' : action === 'archive' ? '封存看板' : '還原看板' }}</button></footer>
    </form>
  </dialog>
</template>
<style scoped>
.board-lifecycle-dialog { width: min(460px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); margin: auto; border: 1px solid var(--border); border-radius: 10px; background: #fff; overflow-y: auto; }
form { padding: 24px; }
h2 { font-size: 20px; line-height: 1.5; overflow-wrap: anywhere; }
p { margin: 14px 0 24px; line-height: 1.7; color: var(--text-secondary, #5f6673); font-size: 14px; }
footer { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 12px; }
button { min-height: 44px; }
</style>
