<script setup>
import { reactive, ref } from 'vue';
import { api } from '../api/index.js';
const props = defineProps({ token: { type: String, default: '' }, email: { type: String, default: '' } });
const emit = defineEmits(['back']);
const form = reactive({ email: props.email, password: '', password_confirmation: '' });
const busy = ref(false), sent = ref(false), complete = ref(false), error = ref('');
async function submit() {
  if (busy.value) return;
  if (props.token && form.password !== form.password_confirmation) { error.value = '兩次密碼不一致'; return; }
  busy.value = true; error.value = '';
  try {
    if (props.token) {
      await api.resetPassword({ ...form, token: props.token });
      form.password = ''; form.password_confirmation = ''; complete.value = true;
      history.replaceState(null, '', '/login');
      try { localStorage.removeItem('tasktracker:signed-in'); localStorage.setItem('tasktracker:auth-updated', String(Date.now())); } catch { /* Session epoch still rejects stale sessions. */ }
    } else { await api.forgotPassword({ email: form.email }); sent.value = true; }
  } catch (failure) { error.value = failure.message; }
  finally { busy.value = false; }
}
function back() { if (!busy.value) { history.replaceState(null, '', '/login'); emit('back'); } }
</script>

<template>
  <section aria-label="密碼恢復">
    <h2>{{ complete ? '密碼已重設' : token ? '設定新密碼' : '忘記密碼' }}</h2>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-5" role="alert">{{ error }}</v-alert>
    <p v-if="complete" role="status">請用新密碼登入, 原有登入與個人 API Token 已撤銷</p>
    <p v-else-if="sent" role="status">如果這個信箱已註冊, 你會收到重設連結。請檢查收件匣或垃圾郵件, 需要重寄時請稍後再申請</p>
    <form v-else @submit.prevent="submit">
      <v-text-field v-model="form.email" label="Email" type="email" autocomplete="email" maxlength="254" required :disabled="busy" />
      <template v-if="token">
        <v-text-field v-model="form.password" label="新密碼" type="password" autocomplete="new-password" minlength="10" maxlength="128" hint="至少 10 字" persistent-hint required :disabled="busy" />
        <v-text-field v-model="form.password_confirmation" label="確認新密碼" type="password" autocomplete="new-password" minlength="10" maxlength="128" required :disabled="busy" />
      </template>
      <v-btn type="submit" color="primary" block size="large" :loading="busy" :disabled="busy">{{ token ? '重設密碼' : '寄送重設連結' }}</v-btn>
    </form>
    <button class="text-button auth-toggle" :disabled="busy" @click="back">返回登入</button>
  </section>
</template>
