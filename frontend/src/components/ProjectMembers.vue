<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { api } from '../api/index.js';
import Icon from './Icon.vue';
const props = defineProps({ project: Object });
const emit = defineEmits(['updated']);
const data = ref(null), loading = ref(false), busy = ref(false), error = ref(''), conflict = ref(false);
const userId = ref(''), roleId = ref('developer');
const dirty = computed(() => Boolean(userId.value));
defineExpose({ dirty });
let pending;
let generation = 0;
const roleLabel = id => data.value?.roles.find(role => role.id === id)?.label || id;
async function load() {
  const current = ++generation;
  loading.value = true;
  try {
    const result = await api.getProjectMembers({ projectId: props.project.id });
    if (current !== generation) return;
    data.value = result; error.value = ''; conflict.value = false;
    roleId.value = result.roles.some(role => role.id === 'developer') ? 'developer' : result.roles[0]?.id || '';
  } catch (failure) { if (current === generation) { error.value = failure.message; if (['FORBIDDEN', 'NOT_FOUND'].includes(failure.code)) data.value = null; } }
  finally { if (current === generation) loading.value = false; }
}
watch(() => props.project.id, () => { data.value = null; userId.value = ''; load(); }, { immediate: true });
onBeforeUnmount(() => { generation++; });
function edit(member) { if (busy.value) return; if (dirty.value && !window.confirm('捨棄尚未儲存的成員設定？')) return; userId.value = member.userId; roleId.value = member.roleId; }
async function reload() { if (dirty.value && !window.confirm('重新載入會捨棄成員設定, 要繼續嗎？')) return; userId.value = ''; pending = null; await load(); }
async function save(removeId = '') {
  if (busy.value || !data.value) return;
  if (removeId && !window.confirm(`${dirty.value ? '尚未儲存的成員設定會捨棄。' : ''}移除此成員的專案資格？既有任務、工時與歷史會保留, 空間管理權限不受影響`)) return;
  busy.value = true; error.value = ''; conflict.value = false;
  const operation = removeId ? 'removeProjectMember' : 'setProjectMember';
  const payload = { projectId: props.project.id, userId: removeId || userId.value, expectedRevision: data.value.revision, ...(removeId ? {} : { roleId: roleId.value }) };
  const signature = JSON.stringify([operation, payload]);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  try {
    await api[operation]({ ...payload, requestId: pending.requestId });
    pending = null; userId.value = ''; await load(); emit('updated', '專案成員已更新');
  } catch (failure) { error.value = failure.message; conflict.value = failure.code === 'REVISION_CONFLICT'; }
  finally { busy.value = false; }
}
</script>
<template>
  <section class="project-members" aria-label="專案成員與權限">
    <header class="section-title"><div><h2>專案成員與權限</h2></div><button class="button secondary" :disabled="busy || loading" @click="reload"><Icon name="refresh" :size="16" />重新載入成員</button></header>
    <p v-if="loading" role="status">正在載入成員…</p>
    <div v-if="error" class="error-box" role="alert">{{ error }}<button v-if="conflict" class="text-button" :disabled="busy" @click="reload">載入最新成員版本</button></div>
    <template v-if="data">
      <form class="project-member-form" @submit.prevent="save()">
        <label class="field">工作空間成員<select v-model="userId" aria-label="加入專案的成員" :disabled="busy" required><option value="" disabled>選擇成員</option><option v-for="member in data.candidates" :key="member.id" :value="member.id">{{ member.name }} · {{ member.email }}</option></select></label>
        <label class="field">專案角色<select v-model="roleId" aria-label="專案角色" :disabled="busy" required><option v-for="role in data.roles" :key="role.id" :value="role.id">{{ role.label }}</option></select></label>
        <button class="button primary" :disabled="busy || !userId">{{ busy ? '儲存中…' : '儲存專案成員' }}</button>
        <button v-if="dirty" class="text-button" type="button" :disabled="busy" @click="userId = ''; pending = null">取消修改</button>
      </form>

      <div class="project-member-list"><article v-for="member in data.members" :key="member.userId" class="project-member-row"><div><strong>{{ member.name }}</strong><span>{{ member.email }}</span></div><span>{{ member.source === 'workspace' ? '工作空間管理權限' : roleLabel(member.roleId) }}</span><div v-if="member.source === 'project'" class="member-actions"><button class="button secondary" :aria-label="`編輯成員 ${member.name}`" :disabled="busy" @click="edit(member)">編輯</button><button class="text-button danger-text" :aria-label="`移除成員 ${member.name}`" :disabled="busy" @click="save(member.userId)">移除</button></div></article></div>
      <details class="membership-history"><summary>授權異動紀錄（{{ data.history.length }}）</summary><p v-if="!data.history.length">尚無異動紀錄</p><article v-for="(entry, index) in [...data.history].reverse()" :key="index"><strong>{{ entry.actor }}</strong> · {{ data.candidates.find(member => member.id === entry.userId)?.name || entry.userId }}：{{ entry.before ? roleLabel(entry.before) : '未加入' }} → {{ entry.after ? roleLabel(entry.after) : '已移除' }}<time>{{ new Date(entry.at).toLocaleString('zh-TW', { timeZone: 'Asia/Taipei' }) }}</time></article></details>
      <p class="subtle">成員設定版本 {{ data.revision }}</p>
    </template>
  </section>
</template>
<style scoped>
.project-members { padding: 24px; background: #fff; color: #242936; }
.section-title { align-items: flex-start; gap: 16px; }

.project-member-form { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; margin: 24px 0 12px; }
.project-member-form .field { min-width: 180px; flex: 1; margin: 0; font-size: 14px; color: #5f6673; }
.project-member-form select { font-size: 14px; border-color: #bfc6d2; color: #242936; }
.project-member-form .button { margin-bottom: 1px; }
.project-member-row { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; padding: 18px 0; border-bottom: 1px solid #daddE5; font-size: 14px; }
.project-member-row > div:first-child { flex: 1; min-width: 180px; overflow-wrap: anywhere; }
.project-member-row strong, .project-member-row span { display: block; }
.project-member-row > div > span { color: #5f6673; margin-top: 5px; }
.member-actions { display: flex; gap: 8px; }
.membership-history { margin: 24px 0; font-size: 14px; }
.membership-history summary { cursor: pointer; }
.membership-history article { padding: 12px 0; line-height: 1.8; overflow-wrap: anywhere; }
time { display: block; color: #5f6673; font-size: 12px; }
@media(max-width:700px) { .project-members { padding: 18px; } .section-title { flex-wrap: wrap; } .project-member-form .field { flex-basis: 100%; } .project-member-row > div:first-child { flex-basis: 100%; } }
</style>
