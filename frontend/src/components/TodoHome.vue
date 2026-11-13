<script setup>
import { computed, nextTick, ref, useId, watch } from 'vue';
import { api } from '../api/index.js';
import { onTabKeydown } from '../ui/tabs.js';
import MarkdownEditor from './MarkdownEditor.vue';
import TodoActions from './TodoActions.vue';
import TodoConversation from './TodoConversation.vue';
import MarkdownContent from './MarkdownContent.vue';
import PagedSelect from './PagedSelect.vue';
import { todoSource, todoDate } from '../domain/todoContent.js';
const props = defineProps({ remote: Boolean, counts: Object, moreHistory: Function, loadItem: Function, loadMembers: Function, workspaceId: String, data: Object, userId: String, canWrite: Boolean, canAssign: Boolean, canConvert: Boolean, refresh: Function });
const emit = defineEmits(['convert', 'open-issue', 'scope', 'closed']);
const quickInput = ref(null), draftEditor = ref(null);
const content = ref(''), assigneeId = ref(props.userId), tab = ref('mine');
const successMessage = ref('');
const tabId = useId(), showAllOpen = ref(false);
const previewLimit = 3;
const busy = ref(false), error = ref(''), conflict = ref(false), draft = ref(null), focused = ref(''), showClosed = ref(false);
const replies = ref({});
const dirty = computed(() => Boolean(content.value.trim() || draft.value || Object.values(replies.value).some(Boolean)));
function countsFor(scope, state) { return props.counts?.[`${scope}${state}`] ?? items.value.filter(item => (item.state === 'open') === (state === 'Open')).length; }
function setReply(id, value) { if (value) replies.value[id] = value; else delete replies.value[id]; }
const mine = computed(() => props.data.todos.filter(item => item.assigneeId === props.userId));
const sent = computed(() => props.data.todos.filter(item => item.createdBy === props.userId && item.initialAssigneeId !== props.userId));
const items = computed(() => tab.value === 'mine' ? mine.value : tab.value === 'sent' ? sent.value : props.data.todos);
const openItems = computed(() => items.value.filter(item => item.state === 'open'));
const displayedOpen = computed(() => {
  if (showAllOpen.value) return openItems.value;
  const preview = openItems.value.slice(0, previewLimit);
  const edited = openItems.value.find(item => item.id === draft.value?.id);
  // 背景更新重新排序時, 不讓正在編輯的項目掉出預覽。
  return edited && !preview.includes(edited) ? [...preview.slice(0, previewLimit - 1), edited] : preview;
});
watch(tab, () => { showAllOpen.value = false; emit('scope', tab.value); });
watch(showClosed, value => emit('closed', value));
const closedItems = computed(() => items.value.filter(item => item.state !== 'open'));
const stateLabels = { open: '待處理', done: '已完成', canceled: '已取消', converted: '已轉卡' };
const events = { created: '建立', edited: '修改內容', completed: '完成', reopened: '重新開啟', returned: '退回給交辦者', canceled: '取消', converted: '轉成正式卡片' };
let pending;
watch(() => props.data.todos, todos => { if (draft.value && !todos.some(item => item.id === draft.value.id && (!props.remote || item.capabilities.edit))) { draft.value = null; error.value = '待辦已不可存取'; } });
function timestamp(value) { return new Date(value).toLocaleString('zh-TW', { timeZone: 'Asia/Taipei', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }); }
async function focus(id) {
  const item = props.data.todos.find(item => item.id === id);
  if (!item) return;
  tab.value = item.assigneeId === props.userId ? 'mine' : item.createdBy === props.userId ? 'sent' : 'involved';
  showClosed.value = item.state !== 'open'; focused.value = id;
  await nextTick();
  if (openItems.value.findIndex(item => item.id === id) >= previewLimit) showAllOpen.value = true;
  await nextTick();
  const element = document.getElementById(`todo-${id}`); element?.scrollIntoView({ block: 'center', behavior: 'smooth' }); element?.focus({ preventScroll: true });
}
async function returnToDraft() {
  if (!draft.value) return;
  await focus(draft.value.id);
  draftEditor.value?.focus();
}
function setDraftEditor(editor) { draftEditor.value = editor; }
function focusQuickEntry() { quickInput.value?.focus(); }
defineExpose({ dirty, saving: busy, draftId: computed(() => draft.value?.id || Object.keys(replies.value)[0]), focus, focusQuickEntry });
async function edit(item) {
  if (busy.value || (draft.value && !window.confirm('捨棄尚未儲存的待辦修改？'))) return;
  if (props.loadItem) { try { item = await props.loadItem(item.id); } catch (failure) { error.value = failure.message; return; } }
  if (!item.capabilities.edit) { error.value = '待辦已無法編輯'; return; }
  draft.value = { id: item.id, content: todoSource(item), expectedRevision: item.revision }; error.value = ''; conflict.value = false;
}
async function convert(item) {
  try { emit('convert', props.loadItem ? await props.loadItem(item.id) : item); } catch (failure) { error.value = failure.message; }
}
async function expand(item) { if (props.loadItem) { try { await props.loadItem(item.id, {history:true}); } catch (failure) { error.value = failure.message; } } }
function discard() { if (!busy.value && (!draft.value || window.confirm('捨棄尚未儲存的待辦修改？'))) { draft.value = null; error.value = ''; conflict.value = false; } }
function reloadDraft() {
  const item = props.data.todos.find(item => item.id === draft.value?.id);
  if (item && window.confirm('載入最新待辦會捨棄目前修改, 要繼續嗎？')) { draft.value = null; edit(item); }
}
async function run(operation, payload, success) {
  if (busy.value) return false;
  busy.value = true; error.value = ''; successMessage.value = ''; conflict.value = false;
  const signature = JSON.stringify([operation, payload]);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  try {
    await api[operation]({ ...payload, requestId: pending.requestId }); pending = null; success?.(); await props.refresh?.(); successMessage.value = ({ createTodo: '已加入待辦', updateTodo: '待辦已儲存', completeTodo: '待辦已完成', reopenTodo: '待辦已重新開啟', returnTodo: '已退回上一手', commentTodo: '已送出回覆', forwardTodo: '已轉發', cancelTodo: '待辦已取消' })[operation]; return true;
  } catch (failure) { error.value = failure.message; conflict.value = failure.code === 'REVISION_CONFLICT'; await props.refresh?.(); return false; }
  finally { busy.value = false; }
}
async function create() { if (!content.value.trim()) return; await run('createTodo', { content: content.value, assigneeId: assigneeId.value }, () => { content.value = ''; assigneeId.value = props.userId; }); }
async function saveDraft() { if (draft.value) await run('updateTodo', { ...draft.value }, () => { draft.value = null; }); }
async function action(item, operation, fields = {}) {
  if (operation === 'returnTodo' && !window.confirm('退回上一手？')) return;
  if (operation === 'cancelTodo' && !window.confirm('取消這筆待辦？會保留紀錄, 並通知承辦者')) return;
  const reply = operation === 'returnTodo' ? replies.value[item.id] : null;
  return run(operation, { id: item.id, expectedRevision: reply?.revision ?? item.revision, ...(reply ? { reply: reply.text } : {}), ...fields }, () => { if (reply) setReply(item.id, null); });
}
</script>
<template>
  <section class="todo-home" aria-label="首頁小待辦">
    <header class="todo-heading"><div><h2>小待辦</h2></div></header>
    <form v-if="canWrite" class="todo-create" @submit.prevent="create">
      <MarkdownEditor ref="quickInput" v-model="content" label="快速新增待辦" placeholder="輸入待辦內容…" :maxlength="6000" :rows="3" :disabled="busy" required />
      <div class="todo-quick"><PagedSelect autoload v-if="canAssign && loadMembers" v-model="assigneeId" label="待辦交給誰" :options="[{id:userId,name:'自己'},...data.members]" :context="workspaceId" :load="loadMembers" :disabled="busy" empty-label="選擇承辦者" /><select v-else-if="canAssign" v-model="assigneeId" aria-label="待辦交給誰" :disabled="busy"><option :value="userId">自己</option><option v-for="member in data.members.filter(member => member.id !== userId)" :key="member.id" :value="member.id">{{ member.name }} · {{ member.email }}</option></select><button class="button primary" :disabled="busy || !content.trim()">{{ assigneeId === userId ? '加入待辦' : '交辦' }}</button></div>
    </form>
    <div class="todo-tabs" role="tablist" aria-label="待辦範圍" @keydown="onTabKeydown"><button :id="`${tabId}-mine-tab`" role="tab" :aria-controls="`${tabId}-panel`" :tabindex="tab === 'mine' ? 0 : -1" :aria-selected="tab === 'mine'" :class="{ active: tab === 'mine' }" :disabled="busy" @click="tab = 'mine'">我的待辦 <span>{{ counts?.mineOpen ?? mine.filter(item => item.state === 'open').length }}</span></button><button :id="`${tabId}-sent-tab`" role="tab" :aria-controls="`${tabId}-panel`" :tabindex="tab === 'sent' ? 0 : -1" :aria-selected="tab === 'sent'" :class="{ active: tab === 'sent' }" :disabled="busy" @click="tab = 'sent'">我交辦的 <span>{{ counts?.sentOpen ?? sent.filter(item => item.state === 'open').length }}</span></button><button :id="`${tabId}-involved-tab`" role="tab" :aria-controls="`${tabId}-panel`" :tabindex="tab === 'involved' ? 0 : -1" :aria-selected="tab === 'involved'" :class="{ active: tab === 'involved' }" :disabled="busy" @click="tab = 'involved'">我經手的 <span>{{ counts?.involvedOpen ?? data.todos.filter(item => item.state === 'open').length }}</span></button></div>
    <div v-if="draft" class="todo-draft-notice"><p role="status">有尚未儲存的待辦修改，切換清單會保留草稿</p><div><button type="button" class="text-button" :disabled="busy" @click="returnToDraft">返回草稿</button><button type="button" class="text-button" :disabled="busy" @click="discard">取消草稿</button></div></div>
    <div :id="`${tabId}-panel`" role="tabpanel" :aria-labelledby="`${tabId}-${tab}-tab`" tabindex="0">
    <div v-if="error" class="error-box" role="alert">{{ error }}<button v-if="conflict && draft" class="text-button" :disabled="busy" @click="reloadDraft">載入最新待辦</button></div>
    <p v-if="successMessage" class="todo-success" role="status">{{ successMessage }}</p>
    <p v-if="!openItems.length" class="todo-empty">{{ tab === 'mine' ? '尚無待辦' : tab === 'sent' ? '尚無交辦' : '尚無待處理的經手待辦' }}</p>
    <div class="todo-list">
      <article v-for="item in [...displayedOpen, ...(showClosed ? closedItems : [])]" :id="`todo-${item.id}`" :key="item.id" class="todo-row" :class="{ 'todo-closed': item.state !== 'open', 'todo-focused': focused === item.id }" :data-todo-id="item.id" tabindex="-1">
        <div class="todo-body">
          <template v-if="draft?.id === item.id"><form class="todo-edit" @submit.prevent="saveDraft"><MarkdownEditor :ref="setDraftEditor" v-model="draft.content" label="編輯待辦內容" :maxlength="6000" :rows="4" :disabled="busy" required /><div><button class="button primary" :disabled="busy || !draft.content.trim()">儲存待辦修改</button><button type="button" class="text-button" :disabled="busy" @click="discard">取消修改</button></div></form></template>
          <template v-else><div class="todo-title-actions"><button v-if="item.capabilities.convert" type="button" class="text-button todo-convert" :disabled="busy || !canConvert" @click="convert(item)">轉為卡片</button></div><p v-if="item.hasDetails === false" class="todo-plain" :class="{ 'todo-done-title': item.state === 'done' }">{{ item.title }}</p><details v-else class="todo-content" @toggle="event => event.target.open && expand(item)"><summary><span :class="{ 'todo-done-title': item.state === 'done' }">{{ item.title }}</span></summary><MarkdownContent class="todo-note" :source="todoSource(item)" /><ol class="todo-history"><li v-for="(entry, index) in item.history" :key="index">{{ entry.actor }} · {{ events[entry.event] || entry.event }} <time>{{ timestamp(entry.at) }}</time></li></ol><button v-if="moreHistory && item.historyPage?.page < item.historyPage?.lastPage" class="text-button" @click="moreHistory(item.id).catch(failure=>error=failure.message)">載入更多紀錄</button></details></template>
          <TodoConversation v-if="draft?.id !== item.id" :item="item" :draft="replies[item.id]" :busy="busy" :workspace-id="workspaceId" :load-members="loadMembers" :perform="action" @update:draft="setReply(item.id, $event)" />
          <p class="todo-meta"><span v-if="item.returnedAt">已退回 · </span><span v-if="item.createdBy !== userId || item.initialAssigneeId !== userId">{{ item.createdBy === userId ? `交給 ${item.assigneeName}` : `來自 ${item.creatorName}` }} · </span><span v-if="item.state !== 'open'">{{ stateLabels[item.state] }} · </span><time class="todo-date" :datetime="todoDate(item, userId).at" :title="todoDate(item, userId).full">{{ todoDate(item, userId).label }}</time><button v-if="item.convertedIssue" class="text-button" @click="emit('open-issue', item.convertedIssue)">{{ item.convertedIssue.identifier }} ↗</button><span v-else-if="item.state === 'converted'"> · 卡片依專案權限開放</span></p>
        </div>
        <TodoActions v-if="(item.state === 'open' || item.capabilities.reopen) && draft?.id !== item.id">
          <button v-if="item.capabilities.reopen" type="button" :disabled="busy" @click="action(item, 'reopenTodo')">重新開啟</button>
          <button v-if="item.capabilities.edit" :disabled="busy" @click="edit(item)">編輯</button>
          <button v-if="item.capabilities.return" :disabled="busy" @click="action(item, 'returnTodo')">退回上一手</button>
          <button v-if="item.capabilities.cancel" :disabled="busy" @click="action(item, 'cancelTodo')">取消待辦</button>
        </TodoActions>
      </article>
    </div>
    <button v-if="countsFor(tab, 'Open') > previewLimit" class="text-button todo-expand" :aria-expanded="showAllOpen" :aria-controls="`${tabId}-panel`" :disabled="busy" @click="showAllOpen = !showAllOpen">{{ showAllOpen ? '收起待辦' : `${remote ? '展開待辦清單' : '顯示全部待辦'}（${countsFor(tab, 'Open')}）` }}</button>
    <button v-if="countsFor(tab, 'Closed')" class="text-button todo-show-closed" :aria-expanded="showClosed" @click="showClosed = !showClosed">{{ showClosed ? '收起' : '顯示' }}已完成／取消／轉卡（{{ countsFor(tab, 'Closed') }}）</button>
    </div>
  </section>
</template>
<style scoped>
.todo-plain { margin: 0; font-size: 15px; font-weight: 550; overflow-wrap: anywhere; }
.todo-title-actions { float: right; margin-left: 12px; }
.todo-convert { min-height: 44px; margin-top: -10px; }
.todo-home { min-width: 0; color: #242936; }
.todo-heading { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 18px; }
.todo-heading h2 { font-size: 22px; margin: 0; }
.todo-quick { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: start; gap: 12px; }
.todo-quick > .paged-select { min-width: 0; margin: 0; }
.todo-quick :deep(.paged-select > label) { display: block; line-height: 20px; }
.todo-quick :deep(.paged-select-control) { margin-top: 8px; align-items: start; }
.todo-quick :deep(.paged-select-control select), .todo-quick > select { min-width: 0; width: 100%; height: 44px; margin: 0; border: 1px solid #bfc6d2; padding: 10px 12px; border-radius: 7px; font-size: 14px; background: #fff; }
.todo-quick :deep(.paged-select-control .icon-button) { width: 44px; height: 44px; flex: none; }
.todo-quick > .button { height: 44px; align-self: start; }
.todo-quick > .paged-select ~ .button { margin-top: 28px; }
.todo-success { color: #286b55; font-size: 13px; margin-top: 12px; }
.todo-draft-notice { padding: 12px; margin-top: 12px; background: #f5f3fc; border: 1px solid #ddd6ef; border-radius: 6px; font-size: 14px; color: #424b5c; }
.todo-draft-notice > div { display: flex; flex-wrap: wrap; gap: 16px; }
.todo-draft-notice button { min-height: 44px; }
.todo-note-toggle { margin: 10px 0; font-size: 13px; }.todo-tabs { display: flex; gap: 24px; margin-top: 10px; border-bottom: 1px solid #daddE5; }.todo-tabs button { padding: 12px 0; color: #5f6673; border-bottom: 2px solid transparent; }.todo-tabs .active { color: #5844a0; border-color: #6653bd; font-weight: 600; }.todo-tabs span { font-size: 12px; margin-left: 5px; }
.todo-row { display: flex; gap: 12px; align-items: flex-start; padding: 16px 0; border-bottom: 1px solid #edf0f5; }.todo-row:focus, .todo-focused { outline: 2px solid #8d83ed; outline-offset: 3px; }.todo-body { flex: 1; min-width: 0; }.todo-content > summary { cursor: pointer; overflow-wrap: anywhere; font-size: 15px; font-weight: 550; }.todo-done-title { text-decoration: line-through; color: #5f6673; }.todo-note { overflow-wrap: anywhere; line-height: 1.8; font-size: 14px; margin-top: 12px; }.todo-meta { font-size: 12px; color: #5f6673; margin-top: 7px; line-height: 1.8; }.todo-meta button { margin-left: 8px; }.todo-history { padding-left: 18px; margin-top: 12px; font-size: 12px; color: #5f6673; line-height: 1.8; }.todo-history time { margin-left: 10px; }
.todo-expand, .todo-show-closed { display: block; min-height: 44px; }.todo-edit .field { margin: 0 0 12px; }.todo-edit .text-button { margin-left: 16px; }.todo-empty { color: #5f6673; font-size: 14px; padding: 22px 0; }.todo-show-closed { margin-top: 14px; font-size: 13px; }
@media(max-width:700px) { .todo-heading { align-items: flex-start; flex-direction: column; }.todo-row { gap: 8px; } }
@media(max-width:479px) {
  .todo-quick { grid-template-columns: minmax(0, 1fr); }
  .todo-quick > .button { width: 100%; }
  .todo-quick > .paged-select ~ .button { margin-top: 0; }
}
</style>
