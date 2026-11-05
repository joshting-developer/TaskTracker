<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useId } from 'vue';
import { api } from '../api/index.js';
import { onTabKeydown } from '../ui/tabs.js';
import Icon from './Icon.vue';
const props = defineProps({ members: Array, meta: Object, initialTab: { type: String, default: 'members' } });
const emit = defineEmits(['close', 'saved']);
const roleLabels = { owner: 'Owner', manager: 'Manager', admin: 'Manager', user: '成員', member: '成員', maintainer: '成員', developer: '成員' };
const dialog = ref(null), tab = ref(props.initialTab), data = ref(null), name = ref(''), loading = ref(true), busy = ref(false), error = ref(''), conflict = ref(false), success = ref('');
const email = ref(''), link = ref(''), copied = ref(false), memberDraft = ref(null), roleId = ref('');
const id = useId();
const permissions = computed(() => props.meta.permissions || []);
const canUpdate = computed(() => permissions.value.includes('workspace.update'));
const canManage = computed(() => permissions.value.includes('workspace.members.manage'));
const dirty = computed(() => Boolean(data.value && name.value !== data.value.workspace.name || memberDraft.value && roleId.value !== memberDraft.value.roleId || email.value.trim()));
const visibleMembers = computed(() => permissions.value.includes('workspace.members.read') ? data.value?.members || [] : props.members.map(member => ({ ...member, roleId: member.role, canChange: false })));
const roles = computed(() => (data.value?.roles || []).filter(role => !role.permissions.includes('workspace.ownership') && role.permissions.every(permission => permissions.value.includes(permission))));
defineExpose({ dirty, saving: busy });
let pending;
function close() { if (!busy.value && (!dirty.value || window.confirm('還有未儲存的工作空間設定，確定要關閉嗎？'))) emit('close'); }
function beforeUnload(event) { if (dirty.value) { event.preventDefault(); event.returnValue = ''; } }
function switchTab(next) {
  if (next === tab.value) return;
  if (dirty.value && !window.confirm('切換設定分區會捨棄尚未儲存的內容，要繼續嗎？')) return;
  if (data.value) name.value = data.value.workspace.name;
  memberDraft.value = null; roleId.value = ''; email.value = ''; pending = null;
  tab.value = next; success.value = '';
}
async function load(confirm = false) {
  if (confirm && dirty.value && !window.confirm('重新載入會捨棄目前修改，要繼續嗎？')) return;
  loading.value = true; error.value = ''; conflict.value = false;
  try { data.value = await api.getWorkspaceSettings(); name.value = data.value.workspace.name; memberDraft.value = null; roleId.value = ''; pending = null; if (confirm) email.value = ''; }
  catch (failure) { error.value = failure.message; }
  finally { loading.value = false; }
}
async function save(operation, payload) {
  if (busy.value || loading.value) return;
  if (operation === 'setWorkspaceMemberRole' && !window.confirm('變更工作空間角色會影響成員對所有專案的存取，確定儲存嗎？')) return;
  busy.value = true; error.value = ''; success.value = ''; conflict.value = false;
  const signature = JSON.stringify([operation, payload]);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  try { await api[operation]({ ...payload, requestId: pending.requestId }); pending = null; await load(); success.value = operation === 'updateWorkspace' ? '工作空間名稱已儲存' : '工作空間角色已儲存'; emit('saved'); }
  catch (failure) { error.value = failure.message; conflict.value = failure.code === 'REVISION_CONFLICT'; }
  finally { busy.value = false; }
}
function edit(member) {
  if (memberDraft.value && dirty.value && !window.confirm('捨棄尚未儲存的成員角色修改？')) return;
  memberDraft.value = { ...member }; roleId.value = member.roleId; error.value = ''; conflict.value = false; success.value = '';
}
async function invite() {
  if (busy.value) return;
  busy.value = true; error.value = ''; copied.value = false;
  try { const invitation = await api.inviteMember({ email: email.value }); link.value = `${location.origin}${location.pathname}#/invite?token=${encodeURIComponent(invitation.token)}`; email.value = ''; }
  catch (failure) { error.value = failure.message; }
  finally { busy.value = false; }
}
async function copy() { try { await navigator.clipboard.writeText(link.value); copied.value = true; } catch { error.value = '請選取下方連結並複製'; } }
onMounted(() => { dialog.value.showModal(); load(); window.addEventListener('beforeunload', beforeUnload); });
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
</script>
<template>
  <dialog ref="dialog" class="team-dialog" aria-labelledby="team-heading" @cancel.prevent="close">
    <header class="section-title"><h2 id="team-heading">{{ tab === 'general' ? '工作空間設定' : `${meta.workspace?.name} · 成員` }}</h2><button class="icon-button" aria-label="關閉工作空間設定" :disabled="busy" @click="close"><Icon name="close" /></button></header>
    <div class="settings-tabs" role="tablist" aria-label="工作空間設定分區" @keydown="onTabKeydown"><button :id="`${id}-general`" role="tab" :aria-selected="tab === 'general'" :tabindex="tab === 'general' ? 0 : -1" :aria-controls="`${id}-general-panel`" :disabled="busy" @click="switchTab('general')">一般設定</button><button :id="`${id}-members`" role="tab" :aria-selected="tab === 'members'" :tabindex="tab === 'members' ? 0 : -1" :aria-controls="`${id}-members-panel`" :disabled="busy" @click="switchTab('members')">成員與權限</button></div>
    <p v-if="loading" role="status">正在載入工作空間設定…</p>
    <div v-if="error" class="error-box" role="alert">{{ error }}<button v-if="!busy" class="text-button" @click="load(true)">{{ conflict ? '載入最新設定' : '重試載入' }}</button></div>
    <p v-if="success" class="settings-success" role="status">{{ success }}</p>
    <section v-show="tab === 'general'" :id="`${id}-general-panel`" role="tabpanel" :aria-labelledby="`${id}-general`" tabindex="0">
      <form v-if="data" @submit.prevent="save('updateWorkspace', { name, expectedRevision: data.workspace.revision })"><label class="field">工作空間名稱<input v-model="name" maxlength="60" required :readonly="!canUpdate" :disabled="busy || loading" /></label><p class="subtle">此名稱會顯示在工作空間及專案入口，改名不影響既有資料或授權。</p><button v-if="canUpdate" class="button primary" :disabled="busy || loading || !name.trim() || name === data.workspace.name">儲存工作空間名稱</button><p v-else class="subtle">你目前可查看設定；修改名稱需要工作空間管理權限。</p></form>
    </section>
    <section v-show="tab === 'members'" :id="`${id}-members-panel`" role="tabpanel" :aria-labelledby="`${id}-members`" tabindex="0">
      <p class="subtle">加入工作空間後仍需專案授權；此處顯示你有權查看的成員。Owner／Manager可操作所有專案，一般成員需要逐專案授權。</p>
      <ul class="member-list"><li v-for="member in visibleMembers" :key="member.id"><span class="avatar small">{{ member.name.slice(0, 1) }}</span><div><strong>{{ member.name }}</strong><small>{{ member.email }}</small></div><span class="type-tag">{{ roleLabels[member.roleId] || member.roleId }}</span><button v-if="canManage && member.canChange" class="text-button" :aria-label="`調整工作空間角色 ${member.name}`" :disabled="busy || loading" @click="edit(member)">調整角色</button></li></ul>
      <form v-if="memberDraft && canManage" class="role-form" @submit.prevent="save('setWorkspaceMemberRole', { userId: memberDraft.id, roleId, expectedRevision: memberDraft.revision })"><p>調整 {{ memberDraft.name }} 的工作空間角色</p><label class="field">工作空間角色<select v-model="roleId" aria-label="工作空間角色" :disabled="busy || loading" required><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.label }}</option></select></label><div><button class="button primary" :disabled="busy || loading || !roleId || roleId === memberDraft.roleId">儲存工作空間角色</button><button type="button" class="text-button" :disabled="busy" @click="memberDraft = null">取消角色修改</button></div></form>
      <p class="subtle">個別專案請進入「專案 → 成員與權限」調整負責人、專案管理者、開發者或讀者；工作空間角色不會改寫專案角色。擁有者資格保留，這裡不提供轉移。</p>
      <template v-if="permissions.includes('workspace.invite')"><h3>邀請同伴</h3><form @submit.prevent="invite" class="invite-form"><label class="field">受邀者 Email<input v-model="email" type="email" required :disabled="busy || loading" /></label><button class="button primary" :disabled="busy || loading">{{ busy ? '建立中…' : '建立邀請連結' }}</button></form><div v-if="link" class="invite-result"><label class="field">邀請連結<input :value="link" readonly @focus="$event.target.select()" /></label><button class="button secondary" @click="copy">{{ copied ? '已複製' : '複製連結' }}</button></div><p class="subtle">連結7日有效，限該Email新帳號使用一次。目前沒有寄出邀請信；既有帳號加入其他空間另行支援。</p></template>
    </section>
  </dialog>
</template>
<style scoped>
.team-dialog { width: min(720px, calc(100vw - 28px)); max-height: calc(100dvh - 28px); padding: 24px; overflow-y: auto; color: #242936; }
.section-title { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
h2 { font-size: 22px; } h3 { font-size: 18px; margin: 22px 0 12px; }
.settings-tabs { display: flex; gap: 18px; margin: 18px 0 22px; border-bottom: 1px solid #daddE5; }
.settings-tabs button { min-height: 44px; padding: 10px 4px; border-bottom: 2px solid transparent; font-size: 14px; }
.settings-tabs button[aria-selected="true"] { color: #5844a0; border-bottom-color: #6653bd; }
.subtle { color: #5f6673; font-size: 14px; line-height: 1.8; margin: 12px 0 20px; }
.field { font-size: 14px; }.field input, .field select { font-size: 14px; border-color: #bfc6d2; }
.member-list li { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.member-list li > div { flex: 1; min-width: 160px; overflow-wrap: anywhere; }
.member-list strong, .member-list small { display: block; }.member-list small { color: #5f6673; font-size: 13px; margin-top: 4px; }
.member-list .type-tag { font-size: 12px; color: #424b5c; border-color: #cbd0db; padding: 4px 6px; }
.member-list .text-button, .icon-button { min-width: 44px; min-height: 44px; }
.role-form { padding: 18px; background: #f7f6fb; margin: 18px 0; }.role-form > p { font-size: 14px; margin-bottom: 16px; }.role-form > div { display: flex; flex-wrap: wrap; gap: 16px; }
.settings-success { color: #286b55; font-size: 14px; margin-bottom: 16px; }
@media(max-width:700px) { .team-dialog { padding: 18px; }.member-list li > div { min-width: 140px; }.member-list li .text-button { margin-left: auto; } }
</style>
