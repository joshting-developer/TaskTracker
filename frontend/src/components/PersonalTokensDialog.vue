<script setup>
import { computed, onBeforeUnmount, onMounted, shallowRef } from 'vue';
import { api } from '../api/index.js';
import { useLeaveGuard } from '../ui/leaveGuard.js';
const props = defineProps({ workspaceName: String });
const emit = defineEmits(['close']);
const root = shallowRef(null), name = shallowRef(''), scope = shallowRef('read'), expiresAt = shallowRef(''), rows = shallowRef([]), page = shallowRef(1), last = shallowRef(1), busy = shallowRef(false), error = shallowRef(''), secret = shallowRef('');
const opener = document.activeElement;
function trap(event) {
  if (event.key !== 'Tab') return;
  const items = [...root.value.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea')];
  const first = items[0], last = items.at(-1);
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
}
onBeforeUnmount(() => { secret.value = ''; opener?.focus?.(); });
let requestId;
const dirty = computed(() => Boolean(name.value || expiresAt.value));
useLeaveGuard({ dirty: () => Boolean(dirty.value), saving: () => Boolean(busy.value) });
function close() { if (!busy.value && (!dirty.value || window.confirm('還有未送出的Token設定，確定要關閉嗎？'))) { secret.value = ''; emit('close'); } }
async function load(next = 1) {
  busy.value = true; error.value = '';
  try { const data = await api.personalTokens({ page: next }); rows.value = data.items; page.value = data.page; last.value = data.lastPage; }
  catch (failure) { error.value = failure.message; }
  finally { busy.value = false; }
}
async function create() {
  if (busy.value || secret.value) return;
  busy.value = true; error.value = ''; requestId ||= crypto.randomUUID();
  try {
    const result = await api.createPersonalToken({ name: name.value, abilities: [scope.value], ...(expiresAt.value ? { expiresAt: expiresAt.value } : {}), requestId });
    secret.value = result.plainTextToken; name.value = ''; expiresAt.value = ''; requestId = undefined; await load();
  } catch (failure) { error.value = failure.message; if (failure.code === 'REQUEST_CONFLICT') requestId = undefined; }
  finally { busy.value = false; }
}
async function revoke(row) {
  if (busy.value || !window.confirm(`撤銷「${row.name}」？`)) return;
  busy.value = true; error.value = '';
  try { await api.revokePersonalToken(row.id); secret.value = ''; await load(page.value); }
  catch (failure) { error.value = failure.message; }
  finally { busy.value = false; }
}
const status = value => ({ active: '有效', expired: '已過期', revoked: '已撤銷' }[value]);
onMounted(() => { root.value.focus(); load(); });
</script>
<template>
  <div class="modal-backdrop" @click.self="close">
    <section ref="root" class="token-dialog" role="dialog" aria-modal="true" aria-labelledby="token-title" tabindex="-1" @keydown.esc.stop="close" @keydown="trap">
      <header><h2 id="token-title">個人 API Token</h2><button type="button" class="button secondary" :disabled="busy" @click="close">關閉</button></header>
      <p>工作空間：{{ props.workspaceName }}</p>
      <p v-if="error" role="alert">{{ error }}</p>
      <section v-if="secret" class="token-secret" aria-label="新建Token">
        <strong>原文僅顯示這一次，關閉後無法再次取得。</strong>
        <label>Token<textarea readonly :value="secret" rows="3" spellcheck="false" /></label>
        <button type="button" class="button secondary" @click="secret = ''">我已保存，隱藏原文</button>
      </section>
      <form v-else @submit.prevent="create">
        <label>名稱<input v-model="name" maxlength="60" required :disabled="busy" /></label>
        <label>Scope<select aria-label="Scope" v-model="scope" :disabled="busy"><option value="read">read — 讀取</option><option value="write">write — 讀取與寫入</option></select></label>
        <label>到期日<input aria-label="到期日" v-model="expiresAt" type="date" :disabled="busy" /><span>預設一個月，最長兩年；到期日台北時間 00:00 失效。</span></label>
        <button type="submit" class="button primary" :disabled="busy">建立 Token</button>
      </form>
      <ul aria-label="Token清單"><li v-for="row in rows" :key="row.id"><div><strong>{{ row.name }}</strong><span>{{ row.abilities.join(', ') }} · {{ status(row.status) }} · {{ new Date(row.expiresAt).toLocaleDateString('zh-TW', { timeZone: 'Asia/Taipei' }) }}</span></div><button v-if="row.status !== 'revoked'" class="button secondary" :disabled="busy" @click="revoke(row)">撤銷</button></li></ul>
      <p v-if="!rows.length && !busy">尚無 Token</p>
      <footer><button class="button secondary" :disabled="busy || page <= 1" @click="load(page - 1)">上一頁</button><span>{{ page }} / {{ last }}</span><button class="button secondary" :disabled="busy || page >= last" @click="load(page + 1)">下一頁</button></footer>
    </section>
  </div>
</template>
<style scoped>
.modal-backdrop { position: fixed; inset: 0; z-index: 100; display: grid; place-items: center; background: #20253266; }
.token-dialog { width: min(680px, calc(100vw - 32px)); max-height: calc(100dvh - 40px); overflow: auto; background: #fff; color: #242936; padding: 24px; border-radius: 8px; }
header, footer, li { display: flex; align-items: center; justify-content: space-between; gap: 12px; } h2 { margin: 0; font-size: 22px; } form { display: grid; gap: 16px; margin-block: 24px; } label { display: grid; gap: 6px; } input, select, textarea { width: 100%; border: 1px solid #aeb6c6; padding: 10px; border-radius: 4px; min-height: 44px; } label span, li span { font-size: 13px; color: #5f6673; } ul { padding: 0; list-style: none; } li { border-top: 1px solid #daddE5; padding: 16px 0; } li div { min-width: 0; overflow-wrap: anywhere; } li strong, li span { display: block; } .token-secret { padding: 16px; border: 1px solid #6653bd; margin-block: 20px; display: grid; gap: 12px; } textarea { font-family: monospace; overflow-wrap: anywhere; } [role='alert'] { color: #a12c42; } footer { justify-content: flex-end; } @media(max-width: 480px) { .token-dialog { padding: 16px; } }
</style>
