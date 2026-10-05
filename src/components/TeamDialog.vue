<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api/index.js';
import Icon from './Icon.vue';
const props = defineProps({ members: Array, meta: Object });
const emit = defineEmits(['close']);
const dialog = ref(null), email = ref(''), busy = ref(false), error = ref(''), link = ref(''), copied = ref(false);
onMounted(() => dialog.value.showModal());
async function invite() {
  if (busy.value) return;
  busy.value = true; error.value = ''; copied.value = false;
  try {
    const invitation = await api.inviteMember({ email: email.value });
    link.value = `${location.origin}${location.pathname}#/invite?token=${encodeURIComponent(invitation.token)}`;
  } catch (failure) { error.value = failure.message; }
  finally { busy.value = false; }
}
async function copy() {
  try { await navigator.clipboard.writeText(link.value); copied.value = true; }
  catch { error.value = '請選取下方連結並複製'; }
}
</script>
<template>
  <dialog ref="dialog" class="team-dialog" aria-labelledby="team-heading" @cancel.prevent="!busy && emit('close')">
    <header class="section-title"><div><div class="eyebrow">YOUR TEAM</div><h2 id="team-heading">{{ meta.workspace?.name }} · 成員</h2></div><button class="icon-button" aria-label="關閉成員" :disabled="busy" @click="emit('close')"><Icon name="close" /></button></header>
    <p class="subtle">工作空間內的專案共享, 每位成員使用自己的帳號填報工時</p>
    <ul class="member-list"><li v-for="member in members" :key="member.id"><span class="avatar small">{{ member.name.slice(0, 1) }}</span><div><strong>{{ member.name }}</strong><small>{{ member.email }}</small></div><span class="type-tag">{{ member.role === 'admin' ? '管理員' : '成員' }}</span></li></ul>
    <template v-if="meta.role === 'admin'"><h3>邀請同伴</h3><form @submit.prevent="invite" class="invite-form"><label class="field">受邀者 Email<input v-model="email" type="email" required :disabled="busy" /></label><button class="button primary" :disabled="busy">{{ busy ? '建立中…' : '建立邀請連結' }}</button></form><div v-if="link" class="invite-result"><label class="field">邀請連結<input :value="link" readonly @focus="$event.target.select()" /></label><button class="button secondary" @click="copy">{{ copied ? '已複製' : '複製連結' }}</button></div><p class="subtle">連結 7 日有效, 限該 Email 的新帳號使用一次。先複製給同伴試用, 目前沒有寄出邀請信；既有帳號加入其他空間將在後續支援</p></template>
    <div v-if="error" class="error-box" role="alert">{{ error }}</div>
  </dialog>
</template>
