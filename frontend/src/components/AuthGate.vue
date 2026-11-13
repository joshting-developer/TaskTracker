<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { api } from '../api/index.js';
import GuestTopBar from './GuestTopBar.vue';

const props = defineProps({ pending: Object });
const verification = ref(props.pending), code = ref(''), changingEmail = ref(false), newEmail = ref(''), now = ref(Date.now());
const activationToken = /^#\/activate\/([a-f0-9]{64})$/.exec(location.hash)?.[1] || '';
const resendDeadline = ref(0);
watch(verification, value => { resendDeadline.value = Date.now() + (value?.resendAfterSeconds || 0) * 1000; }, { immediate: true });
const remaining = computed(() => Math.max(0, Math.ceil((resendDeadline.value - now.value) / 1000)));
let clock;
watch(() => props.pending, value => { verification.value = value; });
onMounted(() => { clock = setInterval(() => { now.value = Date.now(); }, 1000); });
onBeforeUnmount(() => clearInterval(clock));
const emit = defineEmits(['authenticated']);
const inviteToken = new URLSearchParams(location.hash.startsWith('#/invite?') ? location.hash.slice(9) : '').get('token') || '';
// TT-298：首頁與TopBar的「建立工作空間」帶?mode=register, 直接打開建立表單
const mode = ref(inviteToken || new URLSearchParams(location.search).get('mode') === 'register' ? 'register' : 'login');
const form = reactive({ name: '', email: '', password: '', workspaceName: '', inviteToken });
const busy = ref(false);
const error = ref('');
const invitedWorkspace = ref('');
onMounted(async () => {
  if (!inviteToken) return;
  try { const invitation = await api.invitation({ inviteToken }); invitedWorkspace.value = invitation.workspaceName; if (!form.email) form.email = invitation.email; }
  catch (failure) { error.value = failure.message; }
});
async function submit() {
  if (busy.value) return;
  busy.value = true; error.value = '';
  try {
    const result = await api[mode.value]({ ...form });
    if (result.verificationRequired) { verification.value = result; return; }
    if (inviteToken) history.replaceState(null, '', '#/');
    emit('authenticated');
  } catch (failure) { error.value = failure.message; }
  finally { busy.value = false; }
}
async function verify() {
  if (busy.value) return;
  busy.value = true; error.value = '';
  try {
    await api.verifyEmail(activationToken ? { token: activationToken } : { code: code.value });
    verification.value = null; history.replaceState(null, '', '/app/#/'); emit('authenticated');
  } catch (failure) { error.value = failure.message; }
  finally { busy.value = false; }
}
async function resend() {
  if (busy.value || remaining.value) return;
  busy.value = true; error.value = '';
  try { verification.value = await api.resendVerification(changingEmail.value ? { email: newEmail.value } : {}); code.value = ''; changingEmail.value = false; now.value = Date.now(); }
  catch (failure) { error.value = failure.message; }
  finally { busy.value = false; }
}
async function backToLogin() {
  if (busy.value) return;
  busy.value = true;
  try { await api.logout(); history.replaceState(null, '', '/login'); location.reload(); }
  catch (failure) { error.value = failure.message; busy.value = false; }
}
</script>

<template>
  <GuestTopBar current="login" />
  <main class="auth-page">
    <div class="auth-story"><h1>TaskTracker</h1></div>
    <v-card class="auth-card" elevation="0" rounded="0">

      <template v-if="verification || activationToken">
        <h2>驗證信箱</h2>
        <p v-if="verification" class="verification-recipient">驗證碼已寄到 {{ verification.email }}, 請到信箱查看</p>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-5" role="alert">{{ error }}</v-alert>
        <form @submit.prevent="verify">
          <v-text-field v-if="!activationToken" v-model="code" label="驗證碼" autocomplete="one-time-code" autocapitalize="characters" :spellcheck="false" maxlength="8" required :disabled="busy" />
          <v-btn type="submit" color="primary" block size="large" :loading="busy" :disabled="busy">{{ activationToken ? '啟用帳號' : '驗證並登入' }}</v-btn>
        </form>
        <template v-if="verification && !activationToken">
          <div class="verification-actions"><button class="text-button" :disabled="busy || remaining > 0" @click="resend">{{ remaining ? `重寄驗證碼（${remaining}秒）` : '重寄驗證碼' }}</button><button class="text-button" :disabled="busy" @click="changingEmail = !changingEmail; newEmail = verification.email">修改Email</button></div>
          <form v-if="changingEmail" @submit.prevent="resend"><v-text-field v-model="newEmail" label="新的Email" type="email" autocomplete="email" maxlength="254" required :disabled="busy" /><v-btn type="submit" variant="outlined" :disabled="busy || remaining > 0">修改並重寄</v-btn></form>
        </template>
        <button class="text-button auth-toggle" :disabled="busy" @click="backToLogin">返回登入</button>
      </template>
      <template v-else>
      <h2>{{ mode === 'login' ? '登入工作空間' : inviteToken ? '工作空間邀請' : '建立工作空間' }}</h2>
      <p v-if="inviteToken && invitedWorkspace">{{ invitedWorkspace }}</p>
      <v-alert v-if="error" type="error" variant="tonal" class="mb-5" role="alert">{{ error }}</v-alert>
      <form @submit.prevent="submit">
        <v-text-field v-if="mode === 'register'" v-model="form.name" label="你的名字" autocomplete="name" maxlength="60" required :disabled="busy" />
        <v-text-field v-model="form.email" label="Email" type="email" autocomplete="email" maxlength="254" required :disabled="busy" />
        <v-text-field v-model="form.password" label="密碼" aria-label="密碼" type="password" :autocomplete="mode === 'login' ? 'current-password' : 'new-password'" :minlength="mode === 'register' ? 10 : 1" maxlength="128" required :disabled="busy" :hint="mode === 'register' ? '至少 10 字' : ''" persistent-hint />
        <v-text-field v-if="mode === 'register' && !inviteToken" v-model="form.workspaceName" label="工作空間名稱" placeholder="例如：產品團隊" maxlength="60" required :disabled="busy" />
        <v-btn type="submit" color="primary" block size="large" :loading="busy" :disabled="busy">{{ mode === 'login' ? (inviteToken ? '登入並接受邀請' : '登入') : inviteToken ? '加入工作空間' : '建立工作空間' }}</v-btn>
      </form>
      <button class="text-button auth-toggle" :disabled="busy" @click="mode = mode === 'login' ? 'register' : 'login'; error = ''">{{ mode === 'login' ? (inviteToken ? '尚未有帳號？註冊加入' : '第一次使用？建立工作空間') : '已有帳號？登入' }}</button>

      </template>
    </v-card>
  </main>
</template>

<style scoped>
.verification-recipient { margin: 16px 0 24px; overflow-wrap: anywhere; line-height: 1.7; }
.verification-actions { display: flex; flex-wrap: wrap; gap: 8px 20px; margin: 16px 0; }
.verification-actions button { min-height: 44px; }
</style>
