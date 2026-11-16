<script setup>
import { onMounted, onUnmounted, shallowRef } from 'vue';
import { adminSession, adminLogin, adminLogout } from '../api/admin.js';
import { t } from '../i18n/index.js';
import BrandMark from '../components/BrandMark.vue';
import LocalePicker from '../components/LocalePicker.vue';
import AdminLogin from './AdminLogin.vue';
import AdminDashboard from './AdminDashboard.vue';

const username = shallowRef('');
const restoring = shallowRef(true);
const busy = shallowRef(false);
const error = shallowRef('');
const initial = new AbortController();
onUnmounted(() => initial.abort());
onMounted(async () => {
  try { username.value = (await adminSession(initial.signal)).username; }
  catch (cause) { if (cause.status !== 401 && cause.code !== 'ABORTED') error.value = cause.message; }
  finally { restoring.value = false; }
});
async function login(input) {
  if (busy.value) return;
  busy.value = true; error.value = '';
  try { username.value = (await adminLogin(input)).username; }
  catch (cause) { error.value = cause.message; }
  finally { busy.value = false; }
}
async function logout() {
  if (busy.value) return;
  busy.value = true; error.value = '';
  try { await adminLogout(); username.value = ''; }
  catch (cause) { error.value = cause.message; }
  finally { busy.value = false; }
}
function expired() {
  username.value = '';
  error.value = t('管理登入已過期, 請重新登入');
}
</script>
<template>
  <v-app>
    <div class="admin-shell">
      <header class="admin-topbar">
        <a class="admin-brand" href="/" aria-label="Pavenro"><BrandMark class="admin-mark" /><span>Pavenro</span></a>
        <div class="admin-account"><LocalePicker /><template v-if="username"><span class="admin-username">{{ username }}</span><v-btn variant="text" :loading="busy" @click="logout">{{ t('登出') }}</v-btn></template></div>
      </header>
      <main class="admin-content">
        <p v-if="restoring" role="status">{{ t('載入中…') }}</p>
        <template v-else>
          <AdminLogin v-if="!username" :busy="busy" :error="error" @submit="login" />
          <template v-else><p v-if="error" class="admin-error" role="alert">{{ error }}</p><AdminDashboard @expired="expired" /></template>
        </template>
      </main>
    </div>
  </v-app>
</template>
<style scoped>
.admin-shell { min-height: 100dvh; }
.admin-topbar { min-height: 72px; padding: 12px 32px; display: flex; gap: 16px; align-items: center; justify-content: space-between; background: var(--admin-surface); border-bottom: 1px solid var(--admin-border); }
.admin-brand { display: flex; align-items: center; gap: 10px; font-size: 20px; font-weight: 650; text-decoration: none; color: var(--admin-ink); }
.admin-mark { width: 25px; height: 28px; }
.admin-account { display: flex; align-items: center; gap: 12px; }
.admin-account :deep(.locale-picker) { margin: 0; }
.admin-username { font-size: 14px; color: var(--admin-muted); }
.admin-content { max-width: 1248px; margin: 0 auto; padding: 32px; }
@media(max-width:600px) { .admin-topbar { padding: 12px 16px; flex-wrap: wrap; }.admin-content { padding: 24px 16px; }.admin-username { display: none; }.admin-account { gap: 4px; }.admin-account :deep(.locale-picker>span) { display: none; } }
</style>
