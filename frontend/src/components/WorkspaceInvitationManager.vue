<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { api } from '../api/index.js';
import { usePagedLookup } from '../composables/usePagedLookup.js';
const props = defineProps({ workspaceId: { type: String, required: true }, disabled: Boolean });
const emit = defineEmits(['access']);
const context = computed(() => props.workspaceId);
const email = ref(''), link = ref(''), error = ref(''), success = ref(''), copied = ref(false), busy = ref(false);
const working = computed(() => busy.value || props.disabled);
const dirty = computed(() => Boolean(email.value.trim()));
const labels = { pending: '等待寄送', sending: '寄送中', sent: '已寄出', failed: '寄送失敗', uncertain: '寄送結果待確認', skipped: '未寄出', unavailable: '尚無寄送紀錄' };
let pending, disposed = false;
function fail(failure) {
  if (disposed || ['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) return;
  error.value = failure.message;
  if (['FORBIDDEN', 'NOT_FOUND', 'WORKSPACE_CHANGED', 'UNAUTHENTICATED'].includes(failure.code)) emit('access', failure);
}
const invitations = usePagedLookup({ context, load: async (filters, options) => {
  try { return await api.workspaceInvitations({ ...filters, perPage: 20 }, options); }
  catch (failure) { fail(failure); throw failure; }
} });
const items = computed(() => invitations.result.value?.items || []);
const date = value => new Date(value).toLocaleDateString('zh-TW', { timeZone: 'Asia/Taipei' });
async function invite() {
  if (working.value) return;
  const scope = context.value, address = email.value;
  if (pending?.email !== address) pending = { email: address, requestId: crypto.randomUUID() };
  busy.value = true; error.value = ''; success.value = ''; copied.value = false;
  try {
    const result = await api.inviteMember(pending);
    if (disposed || scope !== context.value) return;
    link.value = `${location.origin}/app/#/invite?token=${encodeURIComponent(result.token)}`;
    email.value = ''; pending = null;
    await invitations.reload();
    if (!disposed && scope === context.value) success.value = '邀請已建立';
  } catch (failure) { fail(failure); }
  finally { busy.value = false; }
}
async function copy() {
  try { await navigator.clipboard.writeText(link.value); copied.value = true; }
  catch { error.value = '請選取連結並複製'; }
}
function reset() { email.value = ''; pending = null; error.value = ''; success.value = ''; }
function refresh() { if (!busy.value) return invitations.reload(); }
watch(context, () => { email.value = ''; link.value = ''; pending = null; error.value = ''; success.value = ''; invitations.reload(); }, { immediate: true });
watch(() => invitations.result.value, result => { if (result && invitations.page.value > result.lastPage) invitations.page.value = result.lastPage; });
onBeforeUnmount(() => { disposed = true; });
defineExpose({ dirty, busy, refresh, reset });
</script>
<template>
  <section class="workspace-invitations" aria-label="工作空間邀請">
    <h3>邀請成員</h3>
    <p v-if="error" class="error-box" role="alert">{{ error }}</p>
    <p v-if="success" class="invitation-success" role="status">{{ success }}</p>
    <form class="invite-form" @submit.prevent="invite">
      <label class="field">受邀者 Email<input v-model="email" type="email" maxlength="254" required :disabled="working" /></label>
      <button class="button primary" :disabled="working || !email.trim()">{{ busy ? '建立中…' : '邀請成員' }}</button>
    </form>
    <div v-if="link" class="invite-result"><label class="field">邀請連結<input :value="link" readonly @focus="$event.target.select()" /></label><button class="button secondary" @click="copy">{{ copied ? '已複製' : '複製連結' }}</button></div>
    <section aria-label="待接受邀請">
      <h3>待接受邀請</h3>
      <p v-if="invitations.loading.value" role="status">載入邀請中…</p>
      <p v-if="invitations.error.value" class="error-box" role="alert">{{ invitations.error.value }} <button class="text-button" @click="refresh">重試</button></p>
      <p v-if="invitations.result.value && !items.length && !invitations.loading.value">沒有待接受邀請</p>
      <ul class="member-list"><li v-for="invitation in items" :key="invitation.id">
        <div><strong>{{ invitation.email }}</strong><small>到期 {{ date(invitation.expiresAt) }}</small></div>
        <span class="type-tag">成員</span>
        <span class="invitation-delivery" :class="{ 'delivery-failed': ['failed', 'uncertain', 'skipped'].includes(invitation.deliveryStatus) }">{{ labels[invitation.deliveryStatus] || '尚無寄送紀錄' }}</span>
      </li></ul>
      <div v-if="invitations.result.value?.lastPage > 1" class="pagination" aria-label="待接受邀請分頁"><button class="text-button" :disabled="invitations.loading.value || working || invitations.page.value <= 1" @click="invitations.page.value--">上一頁</button><span>{{ invitations.page.value }} / {{ invitations.result.value.lastPage }}</span><button class="text-button" :disabled="invitations.loading.value || working || invitations.page.value >= invitations.result.value.lastPage" @click="invitations.page.value++">下一頁</button></div>
    </section>
  </section>
</template>
<style scoped>
h3 { font-size: 18px; margin: 22px 0 12px; }
.field, .field input { font-size: 14px; }
.member-list li { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.member-list li > div { flex: 1; min-width: 180px; overflow-wrap: anywhere; }
.member-list strong, .member-list small { display: block; }
.member-list small { color: #5f6673; font-size: 13px; margin-top: 4px; }
.invitation-delivery { font-size: 14px; color: #5f6673; }
.delivery-failed { color: #a72d37; }
.invitation-success { color: #286b55; font-size: 14px; margin-bottom: 16px; }
</style>
