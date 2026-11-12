<script setup>
import { computed, onScopeDispose, ref, shallowRef, useId, watch } from 'vue';
import { api } from '../api/index.js';
import { usePagedLookup } from '../composables/usePagedLookup.js';
import { onTabKeydown } from '../ui/tabs.js';
const props = defineProps({ meta: Object, activeTab: { type: String, default: 'general' } });
const emit = defineEmits(['navigate', 'saved', 'access']);
const roleLabels = { owner: 'Owner', manager: 'Manager', admin: 'Manager', user: '成員', member: '成員', maintainer: '成員', developer: '成員' };
const tab = ref(props.activeTab), data = shallowRef(null), roleChoices = shallowRef([]);
const name = ref(''), loading = ref(true), busy = ref(false), error = ref(''), conflict = ref(false), success = ref('');
const email = ref(''), link = ref(''), copied = ref(false);
const id = useId();
const context = computed(() => props.meta.workspace?.id || '');
const permissions = computed(() => props.meta.permissions || []);
const canUpdate = computed(() => permissions.value.includes('workspace.update'));
const canManage = computed(() => permissions.value.includes('workspace.members.manage'));
const canReadMembers = computed(() => permissions.value.includes('workspace.members.read'));
const dirty = computed(() => Boolean(data.value && name.value !== data.value.workspace.name || email.value.trim()));
const roles = computed(() => roleChoices.value);
const members = usePagedLookup({ context, load: async (filters, options) => {
  try { return await api.workspaceMembers({ ...filters, perPage: 20 }, options); }
  catch (failure) { handleFailure(failure); throw failure; }
} });
const visibleMembers = computed(() => data.value ? members.result.value?.items || [] : []);
let pending, generation = 0, controller;
function handleFailure(failure) {
  if (['FORBIDDEN', 'NOT_FOUND', 'WORKSPACE_CHANGED', 'UNAUTHENTICATED'].includes(failure.code)) {
    data.value = null;
    roleChoices.value = [];
    link.value = '';
    emit('access');
  }
}
function switchTab(next) {
  if (next === tab.value || busy.value) return;
  if (dirty.value && !window.confirm('切換設定分區會捨棄尚未儲存的內容，要繼續嗎？')) return;
  if (data.value) name.value = data.value.workspace.name;
  email.value = ''; pending = null;
  emit('navigate', next);
}
async function loadMembers() {
  if (tab.value !== 'members' || !canReadMembers.value) return;
  const current = generation;
  const options = { signal: controller?.signal };
  try {
    const [choices] = await Promise.all([canManage.value ? api.workspaceMemberRoles(options) : Promise.resolve({ roles: [] }), members.reload()]);
    if (current === generation) roleChoices.value = choices.roles;
  } catch (failure) {
    if (current === generation && !['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) { error.value = failure.message; handleFailure(failure); }
  }
}
async function load(confirm = false) {
  if (busy.value && confirm) return;
  if (confirm && dirty.value && !window.confirm('重新載入會捨棄目前修改，要繼續嗎？')) return;
  const current = ++generation;
  controller?.abort(); controller = new AbortController();
  loading.value = true; error.value = ''; conflict.value = false;
  try {
    const result = await api.workspaceSettings({ signal: controller.signal });
    if (current !== generation) return;
    data.value = result;
    name.value = result.workspace.name;
    pending = null;
    if (confirm) email.value = '';
    await loadMembers();
  } catch (failure) {
    if (current === generation && !['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) { error.value = failure.message; handleFailure(failure); }
  } finally { if (current === generation) loading.value = false; }
}
async function save(operation, payload) {
  if (busy.value || loading.value) return;
  if (operation === 'setWorkspaceMemberRole' && !window.confirm('變更工作空間角色會影響成員對所有專案的存取，確定儲存嗎？')) return;
  busy.value = true; error.value = ''; success.value = ''; conflict.value = false;
  const signature = JSON.stringify([operation, payload]);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  try {
    await api[operation]({ ...payload, requestId: pending.requestId });
    pending = null;
    if (operation === 'updateWorkspace') await load();
    else await members.reload();
    success.value = operation === 'updateWorkspace' ? '工作空間名稱已儲存' : '工作空間角色已儲存';
    emit('saved');
  } catch (failure) { error.value = failure.message; conflict.value = failure.code === 'REVISION_CONFLICT'; handleFailure(failure); }
  finally { busy.value = false; }
}
function changeRole(member, event) {
  const roleId = event.target.value;
  event.target.value = member.roleId;
  if (busy.value || loading.value || roleId === member.roleId) return;
  save('setWorkspaceMemberRole', { userId: member.id, roleId, expectedRevision: member.revision });
}
async function invite() {
  if (busy.value || loading.value) return;
  busy.value = true; error.value = ''; copied.value = false;
  try {
    const invitation = await api.inviteMember({ email: email.value });
    link.value = `${location.origin}${location.pathname}#/invite?token=${encodeURIComponent(invitation.token)}`;
    email.value = '';
    await members.reload();
  } catch (failure) { error.value = failure.message; handleFailure(failure); }
  finally { busy.value = false; }
}
async function copy() {
  try { await navigator.clipboard.writeText(link.value); copied.value = true; }
  catch { error.value = '請選取下方連結並複製'; }
}
watch(() => props.activeTab, next => {
  tab.value = next;
  if (data.value) name.value = data.value.workspace.name;
  email.value = ''; pending = null; success.value = '';
  if (next === 'members' && data.value) loadMembers();
});
watch(context, () => { data.value = null; roleChoices.value = []; link.value = ''; email.value = ''; load(); }, { immediate: true });
watch(() => members.result.value, result => { if (result && members.page.value > result.lastPage) members.page.value = result.lastPage; });
onScopeDispose(() => { generation++; controller?.abort(); });
defineExpose({ dirty, saving: busy, refresh: () => load(true) });
</script>
<template>
  <section class="workspace-settings-page" aria-labelledby="workspace-settings-heading">
    <header class="section-title"><h1 id="workspace-settings-heading">工作空間設定</h1><span class="workspace-name">{{ meta.workspace?.name }}</span></header>
    <div class="settings-tabs" role="tablist" aria-label="工作空間設定分區" @keydown="onTabKeydown"><button :id="`${id}-general`" role="tab" :aria-selected="tab === 'general'" :tabindex="tab === 'general' ? 0 : -1" :aria-controls="`${id}-general-panel`" :disabled="busy" @click="switchTab('general')">一般設定</button><button :id="`${id}-members`" role="tab" :aria-selected="tab === 'members'" :tabindex="tab === 'members' ? 0 : -1" :aria-controls="`${id}-members-panel`" :disabled="busy" @click="switchTab('members')">成員與權限</button></div>
    <p v-if="loading" role="status">正在載入工作空間設定…</p>
    <div v-if="error" class="error-box" role="alert">{{ error }}<button v-if="!busy" class="text-button" @click="load(true)">{{ conflict ? '載入最新設定' : '重試載入' }}</button></div>
    <p v-if="success" class="settings-success" role="status">{{ success }}</p>
    <section v-show="tab === 'general'" :id="`${id}-general-panel`" role="tabpanel" :aria-labelledby="`${id}-general`" tabindex="0">
      <form v-if="data" @submit.prevent="save('updateWorkspace', { name, expectedRevision: data.workspace.revision })"><label class="field">工作空間名稱<input v-model="name" maxlength="60" required :readonly="!canUpdate" :disabled="busy || loading" /></label><button v-if="canUpdate" class="button primary" :disabled="busy || loading || !name.trim() || name === data.workspace.name">儲存工作空間名稱</button><p v-else class="subtle">你目前可查看設定；修改名稱需要工作空間管理權限。</p></form>
    </section>
    <section v-show="tab === 'members'" :id="`${id}-members-panel`" role="tabpanel" :aria-labelledby="`${id}-members`" tabindex="0">

      <label v-if="canReadMembers" class="field">搜尋工作空間成員<input v-model="members.search.value" aria-label="搜尋工作空間成員" placeholder="姓名或信箱" /></label>
      <p v-if="members.loading.value" role="status">載入成員中…</p>
      <p v-if="members.error.value" class="error-box" role="alert">{{ members.error.value }}<button class="text-button" @click="members.reload">重試</button></p>
      <ul class="member-list"><li v-for="member in visibleMembers" :key="member.id"><span class="avatar small">{{ member.name.slice(0, 1) }}</span><div><strong>{{ member.name }}</strong><small>{{ member.email }}</small></div><select v-if="canManage && member.canChange" class="member-role" :aria-label="`工作空間身份 ${member.name}`" :value="member.roleId" :disabled="busy || loading || members.loading.value" @change="changeRole(member, $event)"><option v-if="!roles.some(role => role.id === member.roleId)" :value="member.roleId" disabled>{{ roleLabels[member.roleId] || member.roleId }}</option><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.label }}</option></select><span v-else class="type-tag">{{ roleLabels[member.roleId] || member.roleId }}</span></li></ul>
      <div v-if="members.result.value?.lastPage > 1" class="pagination" aria-label="工作空間成員分頁"><button class="text-button" :disabled="members.loading.value || busy || members.page.value <= 1" @click="members.page.value--">上一頁</button><span>{{ members.page.value }} / {{ members.result.value.lastPage }}</span><button class="text-button" :disabled="members.loading.value || busy || members.page.value >= members.result.value.lastPage" @click="members.page.value++">下一頁</button></div>

      <template v-if="permissions.includes('workspace.invite')"><h3>邀請成員</h3><form @submit.prevent="invite" class="invite-form"><label class="field">受邀者 Email<input v-model="email" type="email" required :disabled="busy || loading" /></label><button class="button primary" :disabled="busy || loading">{{ busy ? '建立中…' : '建立邀請連結' }}</button></form><div v-if="link" class="invite-result"><label class="field">邀請連結<input :value="link" readonly @focus="$event.target.select()" /></label><button class="button secondary" @click="copy">{{ copied ? '已複製' : '複製連結' }}</button></div></template>
    </section>
  </section>
</template>
<style scoped>
.workspace-settings-page { width: 100%; padding: 24px; color: #242936; }
.workspace-settings-page > [role=tabpanel] { max-width: 960px; }
.workspace-name { color: #5f6673; overflow-wrap: anywhere; }
.section-title { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
h1 { font-size: 24px; margin: 0; } h3 { font-size: 18px; margin: 22px 0 12px; }
.settings-tabs { display: flex; gap: 18px; margin: 18px 0 22px; border-bottom: 1px solid #daddE5; }
.settings-tabs button { min-height: 44px; padding: 10px 4px; border-bottom: 2px solid transparent; font-size: 14px; }
.settings-tabs button[aria-selected="true"] { color: #5844a0; border-bottom-color: #6653bd; }
.subtle { color: #5f6673; font-size: 14px; line-height: 1.8; margin: 12px 0 20px; }
.field { font-size: 14px; }.field input, .field select { font-size: 14px; border-color: #bfc6d2; }
.member-list li { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.member-list li > div { flex: 1; min-width: 160px; overflow-wrap: anywhere; }
.member-list strong, .member-list small { display: block; }.member-list small { color: #5f6673; font-size: 13px; margin-top: 4px; }
.member-list .type-tag { font-size: 12px; color: #424b5c; border-color: #cbd0db; padding: 4px 6px; }
.member-role, .icon-button { min-width: 44px; min-height: 44px; }
.member-role { width: 130px; max-width: 100%; padding: 8px 10px; border: 1px solid #bfc6d2; border-radius: 6px; background: #fff; color: #424b5c; font-size: 14px; }
.settings-success { color: #286b55; font-size: 14px; margin-bottom: 16px; }
@media(max-width:700px) { .workspace-settings-page { padding: 18px 15px; }.member-list li > div { min-width: 140px; }.member-role { margin-left: auto; } }
</style>
