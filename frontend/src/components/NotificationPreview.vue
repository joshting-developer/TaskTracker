<script setup>
import { computed, onMounted, ref } from 'vue';
import { api } from '../api/index.js';
const props = defineProps({ project: Object, notifications: Array, issues: Array, permissions: Array });
defineEmits(['open']);
const status = ref(null), message = ref(''), error = ref(''), busy = ref(false);
const entries = computed(() => props.notifications.filter((entry) => entry.projectId === props.project.id).sort((a, b) => b.createdAt.localeCompare(a.createdAt)));
onMounted(async () => { if (props.permissions.includes('smtp.verify')) { try { status.value = await api.smtpStatus(); } catch (failure) { error.value = failure.message; } } });
async function verify() {
  if (busy.value) return;
  busy.value = true; error.value = ''; message.value = '';
  try { message.value = (await api.verifySmtp()).message; }
  catch (failure) { error.value = failure.message; }
  finally { busy.value = false; }
}
function timestamp(value) { return new Intl.DateTimeFormat('zh-TW', { timeZone: 'Asia/Taipei', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(value)); }
</script>
<template>
  <section class="notification-workspace" aria-label="通知預覽"><header class="section-title"><div><h2>通知預覽</h2><p class="subtle">尚未寄送</p></div></header><div v-if="permissions.includes('smtp.verify')" class="smtp-card"><div><h3>SMTP 連線</h3><p>{{ status?.configured ? '已設定 SMTP' : '尚未設定 SMTP' }}</p></div><button class="button secondary" :disabled="busy || !status?.configured" @click="verify">{{ busy ? '檢查中…' : '檢查 SMTP 連線' }}</button></div><div v-if="message" class="success-box" role="status">{{ message }}</div><div v-if="error" class="error-box" role="alert">{{ error }}</div><div v-if="!entries.length" class="empty-state"><h2>目前還沒有通知</h2></div><article v-for="entry in entries" :key="entry.id" class="notification-card"><header><strong>{{ entry.subject }}</strong><span class="type-tag">待寄預覽</span></header><div class="notification-meta"><span>收件人 {{ entry.email }}</span><time>{{ timestamp(entry.createdAt) }}</time></div><p>{{ entry.body }}</p><button class="text-button" @click="$emit('open', issues.find(issue => issue.id === entry.issueId))">查看任務 →</button></article></section>
</template>
