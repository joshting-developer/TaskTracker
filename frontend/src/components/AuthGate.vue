<script setup>
import { onMounted, reactive, ref } from 'vue';
import { api } from '../api/index.js';

const emit = defineEmits(['authenticated']);
const inviteToken = new URLSearchParams(location.hash.startsWith('#/invite?') ? location.hash.slice(9) : '').get('token') || '';
const mode = ref(inviteToken ? 'register' : 'login');
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
    await api[mode.value]({ ...form });
    if (inviteToken) history.replaceState(null, '', '#/');
    emit('authenticated');
  } catch (failure) { error.value = failure.message; }
  finally { busy.value = false; }
}
</script>

<template>
  <main class="auth-page">
    <div class="auth-story"><span class="eyebrow">TASKTRACKER / TEAM WORKSPACE</span><h1>一個目標<br />一起完成<span>.</span></h1><p>從 Epic 到每個小任務,<br />讓進度、討論與投入的時間有地方可去</p><div class="auth-mini-board"><span>計畫</span><span>一起推進</span><span>交付</span><i></i><i></i><i></i></div><small>本機協作試用 · 資料保存在這台電腦</small></div>
    <v-card class="auth-card" elevation="0" rounded="0">
      <div class="eyebrow">WELCOME TO YOUR WORKSPACE</div>
      <h2>{{ mode === 'login' ? '登入工作空間' : inviteToken ? '加入團隊' : '建立你的工作空間' }}</h2>
      <p>{{ inviteToken ? (invitedWorkspace ? `加入 ${invitedWorkspace}，請使用受邀Email登入或註冊` : '使用受邀Email登入或註冊，加入工作空間') : '帳號與工作空間不限定 Google 身分' }}</p>
      <v-alert v-if="error" type="error" variant="tonal" class="mb-5" role="alert">{{ error }}</v-alert>
      <form @submit.prevent="submit">
        <v-text-field v-if="mode === 'register'" v-model="form.name" label="你的名字" autocomplete="name" maxlength="60" required :disabled="busy" />
        <v-text-field v-model="form.email" label="Email" type="email" autocomplete="email" maxlength="254" required :disabled="busy" />
        <v-text-field v-model="form.password" label="密碼" aria-label="密碼" type="password" :autocomplete="mode === 'login' ? 'current-password' : 'new-password'" :minlength="mode === 'register' ? 10 : 1" maxlength="128" required :disabled="busy" :hint="mode === 'register' ? '至少 10 字' : ''" persistent-hint />
        <v-text-field v-if="mode === 'register' && !inviteToken" v-model="form.workspaceName" label="工作空間名稱" placeholder="例如：產品團隊" maxlength="60" required :disabled="busy" />
        <v-btn type="submit" color="primary" block size="large" :loading="busy" :disabled="busy">{{ mode === 'login' ? (inviteToken ? '登入並接受邀請' : '登入') : inviteToken ? '加入工作空間' : '建立工作空間' }}</v-btn>
      </form>
      <button class="text-button auth-toggle" :disabled="busy" @click="mode = mode === 'login' ? 'register' : 'login'; error = ''">{{ mode === 'login' ? (inviteToken ? '尚未有帳號？註冊加入' : '第一次使用？建立工作空間') : '已有帳號？登入' }}</button>
      <p class="auth-hint">協作者請使用管理員提供的邀請連結加入<br />自行建立工作空間會有各自獨立的資料</p>
    </v-card>
  </main>
</template>
