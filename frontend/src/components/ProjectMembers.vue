<script setup>
import { computed, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import { usePagedLookup } from '../composables/usePagedLookup.js';
import PagedSelect from './PagedSelect.vue';
import Icon from './Icon.vue';
const props = defineProps({ project: Object });
const emit = defineEmits(['updated', 'access']);
const data = shallowRef(null), loading = ref(false), busy = ref(false), error = ref(''), conflict = ref(false);
const userId = ref(''), roleId = ref('developer'), chosenMember = shallowRef(null), historyOpen = ref(false);
const context = computed(() => props.project.id);
const dirty = computed(() => Boolean(userId.value));
let pending, generation = 0, controller;

function handleFailure(failure) {
  if (['NOT_FOUND', 'FORBIDDEN', 'UNAUTHENTICATED', 'WORKSPACE_CHANGED'].includes(failure.code)) {
    data.value = null;
    userId.value = '';
    emit('access');
  }
}
async function guardedLoad(loader, filters, options) {
  try { return await loader(props.project.id, { ...filters, perPage: 20 }, options); }
  catch (failure) { handleFailure(failure); throw failure; }
}
const members = usePagedLookup({ context, load: (...args) => guardedLoad(api.projectAclMembers, ...args) });
const history = usePagedLookup({ context, load: (...args) => guardedLoad(api.projectAclHistory, ...args) });
const candidates = (filters, options) => guardedLoad(api.projectAclCandidates, filters, options);
const selectedOptions = computed(() => chosenMember.value ? [chosenMember.value] : []);
const roleLabel = id => data.value?.roles.find(role => role.id === id)?.label || id;

async function load() {
  const current = ++generation;
  controller?.abort();
  controller = new AbortController();
  loading.value = true;
  error.value = '';
  try {
    const result = await api.projectAcl(props.project.id, { signal: controller.signal });
    if (current !== generation) return;
    data.value = result;
    conflict.value = false;
    roleId.value = result.roles.some(role => role.id === 'developer') ? 'developer' : result.roles[0]?.id || '';
    await members.reload();
    if (historyOpen.value) await history.reload();
  } catch (failure) {
    if (current === generation && !['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) {
      error.value = failure.message;
      handleFailure(failure);
    }
  } finally { if (current === generation) loading.value = false; }
}
function cancelEdit() { userId.value = ''; chosenMember.value = null; pending = null; }
function edit(member) {
  if (busy.value || loading.value || !member.canChange) return;
  if (dirty.value && !window.confirm('捨棄尚未儲存的成員設定？')) return;
  chosenMember.value = { id: member.userId, name: member.name, email: member.email };
  userId.value = member.userId;
  roleId.value = member.roleId;
}
async function reload() {
  if (busy.value) return;
  if (dirty.value && !window.confirm('重新載入會捨棄成員設定, 要繼續嗎？')) return;
  cancelEdit();
  await load();
}
async function save(removeId = '') {
  if (busy.value || !data.value || loading.value) return;
  if (removeId && !window.confirm(`${dirty.value ? '尚未儲存的成員設定會捨棄。' : ''}移除此成員的專案資格？既有任務、工時與歷史會保留, 空間管理權限不受影響`)) return;
  busy.value = true;
  error.value = '';
  conflict.value = false;
  const operation = removeId ? 'removeProjectMember' : 'setProjectMember';
  const payload = { projectId: props.project.id, userId: removeId || userId.value, expectedRevision: data.value.revision, ...(removeId ? {} : { roleId: roleId.value }) };
  const signature = JSON.stringify([operation, payload]);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  try {
    await api[operation]({ ...payload, requestId: pending.requestId });
    cancelEdit();
    await load();
    emit('updated', '專案成員已更新');
  } catch (failure) {
    error.value = failure.message;
    conflict.value = failure.code === 'REVISION_CONFLICT';
    handleFailure(failure);
  } finally { busy.value = false; }
}
function toggleHistory(event) {
  historyOpen.value = event.target.open;
  if (historyOpen.value) history.open();
}
watch(() => props.project.id, () => { data.value = null; cancelEdit(); historyOpen.value = false; load(); }, { immediate: true });
watch(() => members.result.value, result => { if (result && members.page.value > result.lastPage) members.page.value = result.lastPage; });
onScopeDispose(() => { generation++; controller?.abort(); });
defineExpose({ dirty, saving: busy, refresh: reload });
</script>
<template>
  <section class="project-members" aria-label="專案成員與權限">
    <header class="section-title"><h2>專案成員與權限</h2><button class="button secondary" :disabled="busy || loading" @click="reload"><Icon name="refresh" :size="16" />重新載入成員</button></header>
    <p v-if="loading" role="status">正在載入成員…</p>
    <div v-if="error" class="error-box" role="alert">{{ error }}<button v-if="conflict" class="text-button" :disabled="busy" @click="reload">載入最新成員版本</button></div>
    <template v-if="data">
      <form class="project-member-form" @submit.prevent="save()">
        <PagedSelect v-model="userId" label="加入專案的成員" :context="project.id" :load="candidates" :options="selectedOptions" :disabled="busy || loading" required empty-label="選擇成員" @choose="chosenMember = $event" />
        <label class="field">專案角色<select v-model="roleId" aria-label="專案角色" :disabled="busy || loading" required><option v-for="role in data.roles" :key="role.id" :value="role.id">{{ role.label }}</option></select></label>
        <button class="button primary" :disabled="busy || loading || !userId">{{ busy ? '儲存中…' : '儲存專案成員' }}</button>
        <button v-if="dirty" class="text-button" type="button" :disabled="busy" @click="cancelEdit">取消修改</button>
      </form>
      <label class="field">搜尋專案成員<input v-model="members.search.value" aria-label="搜尋專案成員" placeholder="姓名或信箱" /></label>
      <p v-if="members.loading.value" role="status">載入成員中…</p>
      <p v-if="members.error.value" class="error-box" role="alert">{{ members.error.value }}<button class="text-button" @click="members.reload">重試</button></p>
      <div class="project-member-list"><article v-for="member in members.result.value?.items || []" :key="member.userId" class="project-member-row"><div><strong>{{ member.name }}</strong><span>{{ member.email }}</span></div><span>{{ member.source === 'workspace' ? '工作空間管理權限' : roleLabel(member.roleId) }}</span><div v-if="member.canChange" class="member-actions"><button class="button secondary" :aria-label="`編輯成員 ${member.name}`" :disabled="busy || loading" @click="edit(member)">編輯</button><button class="text-button danger-text" :aria-label="`移除成員 ${member.name}`" :disabled="busy || loading" @click="save(member.userId)">移除</button></div></article></div>
      <div v-if="members.result.value?.lastPage > 1" class="pagination" aria-label="專案成員分頁"><button class="text-button" :disabled="members.loading.value || members.page.value <= 1" @click="members.page.value--">上一頁</button><span>{{ members.page.value }} / {{ members.result.value.lastPage }}</span><button class="text-button" :disabled="members.loading.value || members.page.value >= members.result.value.lastPage" @click="members.page.value++">下一頁</button></div>
      <details class="membership-history" @toggle="toggleHistory"><summary>授權異動紀錄</summary><p v-if="history.loading.value" role="status">載入紀錄中…</p><p v-if="history.error.value" role="alert">{{ history.error.value }}<button class="text-button" @click="history.reload">重試</button></p><p v-if="history.result.value && !history.result.value.items.length">尚無異動紀錄</p><article v-for="entry in history.result.value?.items || []" :key="entry.id"><strong>{{ entry.actor }}</strong> · {{ entry.targetName || entry.userId }}：{{ entry.before ? roleLabel(entry.before) : '未加入' }} → {{ entry.after ? roleLabel(entry.after) : '已移除' }}<time>{{ new Date(entry.at).toLocaleString('zh-TW', { timeZone: 'Asia/Taipei' }) }}</time></article><div v-if="history.result.value?.lastPage > 1" class="pagination" aria-label="授權歷史分頁"><button class="text-button" :disabled="history.loading.value || history.page.value <= 1" @click="history.page.value--">上一頁</button><span>{{ history.page.value }} / {{ history.result.value.lastPage }}</span><button class="text-button" :disabled="history.loading.value || history.page.value >= history.result.value.lastPage" @click="history.page.value++">下一頁</button></div></details>
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
