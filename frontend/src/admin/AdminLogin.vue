<script setup>
import { shallowRef } from 'vue';
import { t } from '../i18n/index.js';
const props = defineProps({ busy: Boolean, error: { type: String, default: '' } });
const emit = defineEmits(['submit']);
const username = shallowRef('');
const password = shallowRef('');
function submit() {
  if (props.busy) return;
  emit('submit', { username: username.value, password: password.value });
  password.value = '';
}
</script>
<template>
  <section class="admin-login">
    <h1 class="admin-title">{{ t('管理後台') }}</h1>
    <form class="admin-login-form" @submit.prevent="submit">
      <label class="admin-field">{{ t('管理帳號') }}<input v-model="username" name="username" autocomplete="username" required maxlength="100" :disabled="busy" /></label>
      <label class="admin-field">{{ t('密碼') }}<input v-model="password" name="password" type="password" autocomplete="current-password" required maxlength="1024" :disabled="busy" /></label>
      <p v-if="error" class="admin-error" role="alert">{{ error }}</p>
      <v-btn type="submit" color="primary" :loading="busy" :disabled="!username || !password" block>{{ t('登入') }}</v-btn>
    </form>
  </section>
</template>
<style scoped>
.admin-login { max-width: 400px; margin: 64px auto; padding: 28px; border: 1px solid var(--admin-border); border-radius: 8px; background: var(--admin-surface); }
.admin-login-form { display: grid; gap: 20px; margin-top: 24px; }
@media(max-width:600px) { .admin-login { margin-top: 32px; padding: 24px; } }
</style>
