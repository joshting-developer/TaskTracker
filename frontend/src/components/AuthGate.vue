<script setup>
import { t } from '../i18n/index.js';
import { computed, onBeforeUnmount, onMounted, reactive, ref, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import GuestTopBar from './GuestTopBar.vue';
import BrandMark from './BrandMark.vue';
import PasswordRecovery from './PasswordRecovery.vue';
const resetMatch = /^#\/reset-password\/([a-f0-9]{64})(?:\?(.*))?$/.exec(location.hash);
const recovery = ref(Boolean(resetMatch));
const resetToken = ref(resetMatch?.[1] || '');
const resetEmail = resetMatch ? new URLSearchParams(resetMatch[2] || '').get('email') || '' : '';


const registration = shallowRef(null), policyError = shallowRef('');
const policyRequest = new AbortController();
async function loadRegistrationPolicy() {
  policyError.value = '';
  try {
    registration.value = await api.registrationPolicy({ signal: policyRequest.signal });
    if (!registration.value.publicRegistration && !inviteToken) mode.value = 'login';
  } catch (failure) { if (failure.code !== 'ABORTED') { policyError.value = t('無法讀取註冊設定, 請重試'); if (!inviteToken) mode.value = 'login'; } }
}
const props = defineProps({ pending: Object });
const verification = ref(props.pending), changingEmail = ref(false), newEmail = ref(''), now = ref(Date.now());
const activationToken = /^#\/activate\/([a-f0-9]{64})$/.exec(location.hash)?.[1] || '';
const resendDeadline = ref(0);
watch(verification, value => { resendDeadline.value = Date.now() + (value?.resendAfterSeconds || 0) * 1000; }, { immediate: true });
const remaining = computed(() => Math.max(0, Math.ceil((resendDeadline.value - now.value) / 1000)));
let clock;
watch(() => props.pending, value => { verification.value = value; });
let checking = false;
async function checkActivation() {
  if (!verification.value || activationToken || busy.value || checking || document.hidden) return;
  checking = true;
  try {
    const current = await api.current();
    if (!current.verificationRequired && current.user) {
      verification.value = null;
      history.replaceState(null, '', `/app/${location.hash.startsWith('#/') ? location.hash : '#/'}`);
      emit('authenticated');
    }
  } catch { /* A failed recheck never grants access; keep the pending screen and resend option. */ }
  finally { checking = false; }
}
function activationSignal(event) { if (event.key === 'tasktracker:auth-updated') checkActivation(); }
onMounted(() => {
  clock = setInterval(() => { now.value = Date.now(); }, 1000);
  window.addEventListener('focus', checkActivation);
  window.addEventListener('storage', activationSignal);
  document.addEventListener('visibilitychange', checkActivation);
});
onBeforeUnmount(() => { clearInterval(clock); policyRequest.abort(); window.removeEventListener('focus', checkActivation); window.removeEventListener('storage', activationSignal); document.removeEventListener('visibilitychange', checkActivation); });
const emit = defineEmits(['authenticated']);
const inviteToken = new URLSearchParams(location.hash.startsWith('#/invite?') ? location.hash.slice(9) : '').get('token') || '';
// TT-298：首頁與TopBar的「建立工作空間」帶?mode=register, 直接打開建立表單
const mode = ref(inviteToken || new URLSearchParams(location.search).get('mode') === 'register' ? 'register' : 'login');
const form = reactive({ name: '', email: '', password: '', workspaceName: '', inviteToken });
const busy = ref(false);
const error = ref('');
const invitedWorkspace = ref(''), invitationLoaded = ref(false), passwordConfirmation = ref('');
onMounted(async () => {
  if (verification.value || activationToken) return;
  if (!inviteToken) { await loadRegistrationPolicy(); return; }
  try { const invitation = await api.invitation({ inviteToken }); if (invitation.consumed || invitation.declined) throw Error(invitation.declined ? t('邀請已拒絕, 請向邀請者索取新連結') : t('邀請已使用')); invitedWorkspace.value = invitation.workspaceName; form.email = invitation.email; mode.value = invitation.authMode; invitationLoaded.value = true; }
  catch (failure) { error.value = failure.message; }
});
async function submit() {
  if (busy.value || (inviteToken && !invitationLoaded.value)) return;
  if (mode.value === 'register' && form.password !== passwordConfirmation.value) { error.value = t('兩次密碼不一致'); return; }
  busy.value = true; error.value = '';
  try {
    const result = await api[mode.value]({ ...form });
    if (result.verificationRequired) { verification.value = result; return; }
    if (inviteToken) history.replaceState(null, '', '/app/#/invite?token=' + encodeURIComponent(inviteToken));
    emit('authenticated');
  } catch (failure) {
    error.value = failure.message;
    if (inviteToken && failure.code === 'CONFLICT') {
      try {
        const invitation = await api.invitation({ inviteToken });
        if (invitation.authMode === 'login' && !invitation.consumed && !invitation.declined) {
          mode.value = 'login'; form.password = ''; passwordConfirmation.value = '';
        }
      } catch (refreshFailure) { invitationLoaded.value = false; error.value = refreshFailure.message; }
    }
  }
  finally { busy.value = false; }
}
async function verify() {
  if (busy.value) return;
  busy.value = true; error.value = '';
  try {
    if (!activationToken) return;
    await api.verifyEmail({ token: activationToken });
    try { localStorage.setItem('tasktracker:auth-updated', String(Date.now())); } catch { /* Focus also rechecks when storage is unavailable. */ }
    verification.value = null;
    const destination = !inviteToken && !activationToken && location.hash.startsWith('#/') ? location.hash : '#/';
    history.replaceState(null, '', `/app/${destination}`); emit('authenticated');
  } catch (failure) { error.value = failure.message; }
  finally { busy.value = false; }
}
async function resend() {
  if (busy.value || remaining.value) return;
  busy.value = true; error.value = '';
  try { verification.value = await api.resendVerification(changingEmail.value ? { email: newEmail.value } : {}); changingEmail.value = false; now.value = Date.now(); }
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
  <GuestTopBar :current="mode" :signup-available="Boolean(registration?.publicRegistration && !verification && !activationToken)" />
  <main class="auth-page">
    <div class="auth-story"><BrandMark class="auth-story-mark" /><h1>Pavenro</h1></div>
    <v-card class="auth-card" elevation="0" rounded="0">

      <PasswordRecovery v-if="recovery" :token="resetToken" :email="resetEmail" @back="recovery = false; resetToken = ''; form.password = ''" />
      <template v-else-if="verification || activationToken">
        <h2>{{ t('驗證信箱') }}</h2>
        <p v-if="verification" class="verification-recipient">{{ verification.deliveryStatus === 'sent' ? t('啟用連結已寄到') : t('驗證信箱') }} {{ verification.email }}</p>
        <v-alert v-if="verification && ['failed', 'uncertain', 'skipped'].includes(verification.deliveryStatus)" type="error" variant="tonal" role="alert">{{ verification.deliveryStatus === 'uncertain' ? t('無法確認寄送結果, 請先查看信箱；未收到再重寄') : t('驗證信未寄出, 請稍後按重寄啟用信') }}</v-alert>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-5" role="alert">{{ t(error) }}</v-alert>
        <form v-if="activationToken" @submit.prevent="verify">
          <v-btn type="submit" color="primary" block size="large" :loading="busy" :disabled="busy">{{ t('啟用帳號') }}</v-btn>
        </form>
        <p v-else class="verification-recipient">{{ t('請點擊信件中的啟用連結') }}</p>
        <template v-if="verification && !activationToken">
          <div class="verification-actions"><button class="text-button" :disabled="busy || remaining > 0" @click="resend">{{ remaining ? t('重寄啟用信（{0}秒）', [remaining]) : t('重寄啟用信') }}</button><button class="text-button" :disabled="busy" @click="changingEmail = !changingEmail; newEmail = verification.email">{{ t('修改Email') }}</button></div>
          <form v-if="changingEmail" @submit.prevent="resend"><v-text-field v-model="newEmail" :label="t('新的Email')" type="email" autocomplete="email" maxlength="254" required :disabled="busy" /><v-btn type="submit" variant="outlined" :disabled="busy || remaining > 0">{{ t('修改並重寄') }}</v-btn></form>
        </template>
        <button class="text-button auth-toggle" :disabled="busy" @click="backToLogin">{{ t('返回登入') }}</button>
      </template>
      <template v-else>
      <p v-if="!inviteToken && registration?.publicRegistration === false" class="registration-notice">{{ t('目前僅接受邀請註冊') }}</p>
      <p v-if="policyError" role="alert">{{ t(policyError) }} <button class="text-button" @click="loadRegistrationPolicy">{{ t('重試') }}</button></p>
      <h2>{{ inviteToken ? t('工作空間邀請') : mode === 'login' ? t('登入工作空間') : t('建立工作空間') }}</h2>
      <p v-if="inviteToken && invitedWorkspace">{{ invitedWorkspace }}</p>
      <v-alert v-if="error" type="error" variant="tonal" class="mb-5" role="alert">{{ t(error) }}</v-alert>
      <form v-if="(!inviteToken && (mode === 'login' || registration?.publicRegistration)) || (inviteToken && invitationLoaded)" @submit.prevent="submit">
        <v-text-field v-if="mode === 'register'" v-model="form.name" :label="t('你的名字')" autocomplete="name" maxlength="60" required :disabled="busy" />
        <v-text-field v-model="form.email" label="Email" type="email" autocomplete="email" maxlength="254" required :disabled="busy" :readonly="Boolean(inviteToken)" />
        <v-text-field v-model="form.password" :label="t('密碼')" :aria-label="t('密碼')" type="password" :autocomplete="mode === 'login' ? 'current-password' : 'new-password'" :minlength="mode === 'register' ? 10 : 1" maxlength="128" required :disabled="busy" :hint="mode === 'register' ? t('至少 10 字') : ''" persistent-hint />
        <v-text-field v-if="mode === 'register'" v-model="passwordConfirmation" :label="t('確認密碼')" type="password" autocomplete="new-password" minlength="10" maxlength="128" required :disabled="busy" />
        <v-text-field v-if="mode === 'register' && !inviteToken" v-model="form.workspaceName" :label="t('工作空間名稱')" :placeholder="t('例如：產品團隊')" maxlength="60" required :disabled="busy" />
        <v-btn type="submit" color="primary" block size="large" :loading="busy" :disabled="busy || (Boolean(inviteToken) && !invitationLoaded)">{{ mode === 'login' ? t('登入') : inviteToken ? t('建立帳號') : t('建立工作空間') }}</v-btn>
      </form>
      <button v-if="mode === 'login'" class="text-button auth-toggle" :disabled="busy" @click="recovery = true">{{ t('忘記密碼') }}</button>
      <button v-if="!inviteToken && registration?.publicRegistration" class="text-button auth-toggle" :disabled="busy" @click="mode = mode === 'login' ? 'register' : 'login'; error = ''">{{ mode === 'login' ? (inviteToken ? t('尚未有帳號？註冊加入') : t('第一次使用？建立工作空間')) : t('已有帳號？登入') }}</button>

      </template>
    </v-card>
  </main>
</template>

<style scoped>
.auth-story-mark { width: clamp(112px, 12vw, 176px); height: auto; aspect-ratio: 1; }
@media (max-width: 700px) {
  .auth-story { flex-direction: row; align-items: center; justify-content: flex-start; gap: 16px; }
  .auth-story-mark { width: 48px; }
}
.registration-notice { margin: 0 0 16px; font-size: 14px; color: #424b5c; }
.verification-recipient { margin: 16px 0 24px; overflow-wrap: anywhere; line-height: 1.7; }
.verification-actions { display: flex; flex-wrap: wrap; gap: 8px 20px; margin: 16px 0; }
.verification-actions button { min-height: 44px; }
</style>
