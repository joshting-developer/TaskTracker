<script setup>
import { computed, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import { usePolling } from '../composables/usePolling.js';
import { pollIntervals } from '../ui/pollingConfig.js';
const props = defineProps({ project: Object, permissions: Array });
const emit = defineEmits(['open', 'access']);
const result = shallowRef(null), page = ref(1), loading = ref(false);
const status = shallowRef(null), message = ref(''), error = ref(''), busy = ref(false);
const expanded = shallowRef(null), bodyLoading = ref(false);
const entries = computed(() => result.value?.items || []);
let generation = 0, controller, bodyController, disposed = false;

function accessFailure(failure) {
  if (['NOT_FOUND', 'FORBIDDEN', 'WORKSPACE_CHANGED', 'UNAUTHENTICATED'].includes(failure.code)) {
    result.value = null;
    expanded.value = null;
    emit('access');
  }
}
async function refresh() {
  const current = ++generation;
  controller?.abort();
  bodyController?.abort();
  if (bodyLoading.value) expanded.value = null;
  bodyLoading.value = false;
  controller = new AbortController();
  loading.value = true;
  error.value = '';
  try {
    const data = await api.projectNotifications(props.project.id, { page: page.value, perPage: 20 }, { signal: controller.signal });
    if (current !== generation) return;
    result.value = data;
    if (expanded.value && !data.items.some(item => item.id === expanded.value.id)) expanded.value = null;
    poller.report(null);
    if (page.value > data.lastPage) page.value = data.lastPage;
  } catch (failure) {
    if (current !== generation || ['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) return;
    poller.report(failure);
    error.value = failure.message;
    accessFailure(failure);
  } finally {
    if (current === generation) loading.value = false;
  }
}
async function openBody(entry) {
  bodyController?.abort();
  if (expanded.value?.id === entry.id) { expanded.value = null; bodyLoading.value = false; return; }
  const request = new AbortController();
  bodyController = request;
  expanded.value = { id: entry.id };
  bodyLoading.value = true;
  error.value = '';
  try {
    const data = await api.projectNotification(props.project.id, entry.id, { signal: request.signal });
    if (!request.signal.aborted && !disposed) expanded.value = data;
  } catch (failure) {
    if (!request.signal.aborted && !disposed) { error.value = failure.message; accessFailure(failure); }
  } finally {
    if (bodyController === request) bodyLoading.value = false;
  }
}
async function loadSmtp() {
  if (!props.permissions.includes('smtp.verify')) return;
  try { status.value = await api.smtpStatus(); }
  catch (failure) { if (!disposed) error.value = failure.message; }
}
async function verify() {
  if (busy.value) return;
  busy.value = true; error.value = ''; message.value = '';
  try { message.value = (await api.verifySmtp()).message; }
  catch (failure) { error.value = failure.message; }
  finally { busy.value = false; }
}
function timestamp(value) {
  return new Intl.DateTimeFormat('zh-TW', { timeZone: 'Asia/Taipei', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(value));
}
// 背景更新交由共用輪詢生命週期；隱藏／離線時只中止請求，讓finally收回loading。
const poller = usePolling({
  task: refresh,
  interval: pollIntervals.notifications,
  onPause: () => { controller?.abort(); bodyController?.abort(); },
});
watch(() => props.project.id, () => { result.value = null; page.value = 1; status.value = null; refresh(); loadSmtp(); }, { immediate: true });
watch(page, refresh);
poller.start();
onScopeDispose(() => { disposed = true; generation++; controller?.abort(); bodyController?.abort(); });
defineExpose({ refresh });
</script>
<template>
  <section class="notification-workspace" aria-label="通知預覽">
    <header class="section-title"><h2>通知預覽</h2><button class="button secondary" :disabled="loading" @click="refresh">重新整理</button></header>
    <div v-if="permissions.includes('smtp.verify')" class="smtp-card"><div><h3>SMTP 連線</h3><p>{{ status?.configured ? '已設定 SMTP' : '尚未設定 SMTP' }}</p></div><button class="button secondary" :disabled="busy || !status?.configured" @click="verify">{{ busy ? '檢查中…' : '檢查 SMTP 連線' }}</button></div>
    <div v-if="message" class="success-box" role="status">{{ message }}</div>
    <div v-if="error" class="error-box" role="alert">{{ error }}</div>
    <p v-if="loading" role="status">載入通知中…</p>
    <div v-if="result && !entries.length" class="empty-state"><h2>目前還沒有通知</h2></div>
    <article v-for="entry in entries" :key="entry.id" class="notification-card">
      <header><strong>{{ entry.subject }}</strong><span class="type-tag">待寄預覽</span></header>
      <div class="notification-meta"><span>收件人 {{ entry.email }}</span><time>{{ timestamp(entry.createdAt) }}</time></div>
      <button class="text-button" :aria-expanded="expanded?.id === entry.id" @click="openBody(entry)">{{ expanded?.id === entry.id ? '收起內容' : '查看內容' }}</button>
      <div v-if="expanded?.id === entry.id"><p v-if="bodyLoading" role="status">載入內容中…</p><p v-else>{{ expanded.body }}</p></div>
      <button class="text-button" @click="emit('open', { id: entry.issueId, projectId: project.id }, true)">{{ entry.identifier }} →</button>
    </article>
    <div v-if="result?.lastPage > 1" class="pagination" aria-label="通知預覽分頁"><button class="text-button" :disabled="loading || page <= 1" @click="page--">上一頁</button><span>{{ page }} / {{ result.lastPage }}</span><button class="text-button" :disabled="loading || page >= result.lastPage" @click="page++">下一頁</button></div>
  </section>
</template>
