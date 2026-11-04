<script setup>
import { computed, nextTick, ref, useId, watch } from 'vue';
import { api } from '../api/index.js';
import { onTabKeydown } from '../ui/tabs.js';
const props = defineProps({ data: Object, userId: String, canWrite: Boolean, canAssign: Boolean, canConvert: Boolean, refresh: Function });
const emit = defineEmits(['convert', 'open-issue']);
const title = ref(''), note = ref(''), assigneeId = ref(props.userId), expanded = ref(false), tab = ref('mine');
const successMessage = ref('');
const tabId = useId(), showAllOpen = ref(false);
const previewLimit = 3;
const busy = ref(false), error = ref(''), conflict = ref(false), draft = ref(null), focused = ref(''), showClosed = ref(false);
const dirty = computed(() => Boolean(title.value.trim() || note.value.trim() || draft.value));
const mine = computed(() => props.data.todos.filter(item => item.assigneeId === props.userId));
const sent = computed(() => props.data.todos.filter(item => item.createdBy === props.userId && item.initialAssigneeId !== props.userId));
const items = computed(() => tab.value === 'mine' ? mine.value : sent.value);
const openItems = computed(() => items.value.filter(item => item.state === 'open'));
const displayedOpen = computed(() => {
  if (showAllOpen.value) return openItems.value;
  const preview = openItems.value.slice(0, previewLimit);
  const edited = openItems.value.find(item => item.id === draft.value?.id);
  // 背景更新重新排序時, 不讓正在編輯的項目掉出預覽。
  return edited && !preview.includes(edited) ? [...preview.slice(0, previewLimit - 1), edited] : preview;
});
watch(tab, () => { showAllOpen.value = false; });
const closedItems = computed(() => items.value.filter(item => item.state !== 'open'));
const stateLabels = { open: '待處理', done: '已完成', canceled: '已取消', converted: '已轉卡' };
const events = { created: '建立', edited: '修改內容', completed: '完成', reopened: '重新開啟', returned: '退回給交辦者', canceled: '取消', converted: '轉成正式卡片' };
let pending;
watch(() => props.data.todos, todos => { if (draft.value && !todos.some(item => item.id === draft.value.id)) { draft.value = null; error.value = '待辦已不可存取'; } });
function timestamp(value) { return new Date(value).toLocaleString('zh-TW', { timeZone: 'Asia/Taipei', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }); }
async function focus(id) {
  const item = props.data.todos.find(item => item.id === id);
  if (!item) return;
  tab.value = item.assigneeId === props.userId ? 'mine' : 'sent';
  showClosed.value = item.state !== 'open'; focused.value = id;
  await nextTick();
  if (openItems.value.findIndex(item => item.id === id) >= previewLimit) showAllOpen.value = true;
  await nextTick();
  const element = document.getElementById(`todo-${id}`); element?.scrollIntoView({ block: 'center', behavior: 'smooth' }); element?.focus({ preventScroll: true });
}
async function returnToDraft() {
  if (!draft.value) return;
  await focus(draft.value.id);
  document.getElementById(`todo-${draft.value.id}`)?.querySelector('input[aria-label="編輯待辦內容"]')?.focus();
}
defineExpose({ dirty, saving: busy, focus });
function edit(item) {
  if (busy.value || (draft.value && !window.confirm('捨棄尚未儲存的待辦修改？'))) return;
  draft.value = { id: item.id, title: item.title, note: item.note, expectedRevision: item.revision }; error.value = ''; conflict.value = false;
}
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
    await api[operation]({ ...payload, requestId: pending.requestId }); pending = null; success?.(); await props.refresh?.(); successMessage.value = ({ createTodo: '已加入待辦', updateTodo: '待辦已儲存', completeTodo: '待辦已完成', reopenTodo: '待辦已重新開啟', returnTodo: '已退回給交辦者', cancelTodo: '待辦已取消' })[operation]; return true;
  } catch (failure) { error.value = failure.message; conflict.value = failure.code === 'REVISION_CONFLICT'; await props.refresh?.(); return false; }
  finally { busy.value = false; }
}
async function create() { if (!title.value.trim()) return; await run('createTodo', { title: title.value, note: note.value, assigneeId: assigneeId.value }, () => { title.value = ''; note.value = ''; assigneeId.value = props.userId; expanded.value = false; }); }
async function saveDraft() { if (draft.value) await run('updateTodo', { ...draft.value }, () => { draft.value = null; }); }
async function action(item, operation) {
  if (operation === 'returnTodo' && !window.confirm(`退回給 ${item.creatorName}？這筆待辦會回到對方的清單`)) return;
  if (operation === 'cancelTodo' && !window.confirm('取消這筆待辦？會保留紀錄, 並通知承辦者')) return;
  return run(operation, { id: item.id, expectedRevision: item.revision });
}
</script>
<template>
  <section class="todo-home" aria-label="首頁小待辦">
    <header class="todo-heading"><div><h2>小待辦</h2><p>先記下來, 一件一件完成</p></div><span class="todo-private">只對交辦者與承辦者可見</span></header>
    <form v-if="canWrite" class="todo-create" @submit.prevent="create">
      <div class="todo-quick"><input v-model="title" aria-label="快速新增待辦" placeholder="記一件小事, Enter 加入…" maxlength="180" required :disabled="busy" @keydown.enter="$event.isComposing && $event.preventDefault()" /><select v-if="canAssign" v-model="assigneeId" aria-label="待辦交給誰" :disabled="busy"><option :value="userId">自己</option><option v-for="member in data.members.filter(member => member.id !== userId)" :key="member.id" :value="member.id">{{ member.name }} · {{ member.email }}</option></select><button class="button primary" :disabled="busy || !title.trim()">{{ assigneeId === userId ? '加入待辦' : '交辦' }}</button></div>
      <button type="button" class="text-button todo-note-toggle" :aria-expanded="expanded" @click="expanded = !expanded">{{ expanded ? '收起說明' : '補充說明' }}</button>
      <label v-show="expanded" class="field">待辦補充說明<textarea v-model="note" aria-label="待辦補充說明" rows="2" maxlength="2000" :disabled="busy" placeholder="背景、溝通重點或完成條件（選填）" /></label>
    </form>
    <div class="todo-tabs" role="tablist" aria-label="待辦範圍" @keydown="onTabKeydown"><button :id="`${tabId}-mine-tab`" role="tab" :aria-controls="`${tabId}-panel`" :tabindex="tab === 'mine' ? 0 : -1" :aria-selected="tab === 'mine'" :class="{ active: tab === 'mine' }" :disabled="busy" @click="tab = 'mine'">我的待辦 <span>{{ mine.filter(item => item.state === 'open').length }}</span></button><button :id="`${tabId}-sent-tab`" role="tab" :aria-controls="`${tabId}-panel`" :tabindex="tab === 'sent' ? 0 : -1" :aria-selected="tab === 'sent'" :class="{ active: tab === 'sent' }" :disabled="busy" @click="tab = 'sent'">我交辦的 <span>{{ sent.filter(item => item.state === 'open').length }}</span></button></div>
    <div v-if="draft" class="todo-draft-notice"><p role="status">有尚未儲存的待辦修改，切換清單會保留草稿</p><div><button type="button" class="text-button" :disabled="busy" @click="returnToDraft">返回草稿</button><button type="button" class="text-button" :disabled="busy" @click="discard">取消草稿</button></div></div>
    <div :id="`${tabId}-panel`" role="tabpanel" :aria-labelledby="`${tabId}-${tab}-tab`" tabindex="0">
    <div v-if="error" class="error-box" role="alert">{{ error }}<button v-if="conflict && draft" class="text-button" :disabled="busy" @click="reloadDraft">載入最新待辦</button></div>
    <p v-if="successMessage" class="todo-success" role="status">{{ successMessage }}</p>
    <p v-if="!openItems.length" class="todo-empty">{{ tab === 'mine' ? '目前沒有待處理的小事' : '目前沒有等待處理的交辦' }}</p>
    <div class="todo-list">
      <article v-for="item in [...displayedOpen, ...(showClosed ? closedItems : [])]" :id="`todo-${item.id}`" :key="item.id" class="todo-row" :class="{ 'todo-closed': item.state !== 'open', 'todo-focused': focused === item.id }" :data-todo-id="item.id" tabindex="-1">
        <label v-if="item.capabilities.complete || item.capabilities.reopen" class="todo-check" :title="draft?.id === item.id ? '請先儲存或取消此筆修改，再變更完成狀態' : undefined"><input type="checkbox" :checked="item.state === 'done'" :aria-label="`${item.state === 'done' ? '重新開啟' : '完成待辦'} ${item.title}`" :disabled="busy || draft?.id === item.id" @change="async event => { const saved = await action(item, item.state === 'done' ? 'reopenTodo' : 'completeTodo'); if (!saved && event.target.isConnected) event.target.checked = item.state === 'done'; }" /></label><span v-else class="todo-state">{{ stateLabels[item.state] }}</span>
        <div class="todo-body">
          <template v-if="draft?.id === item.id"><form class="todo-edit" @submit.prevent="saveDraft"><label class="field">待辦內容<input v-model="draft.title" aria-label="編輯待辦內容" maxlength="180" required :disabled="busy" /></label><label class="field">補充說明<textarea v-model="draft.note" aria-label="編輯待辦說明" maxlength="2000" rows="3" :disabled="busy" /></label><div><button class="button primary" :disabled="busy">儲存待辦修改</button><button type="button" class="text-button" :disabled="busy" @click="discard">取消修改</button></div></form></template>
          <template v-else><details class="todo-content"><summary><span :class="{ 'todo-done-title': item.state === 'done' }">{{ item.title }}</span></summary><p class="todo-note">{{ item.note || '沒有補充說明' }}</p><ol class="todo-history"><li v-for="(entry, index) in item.history" :key="index">{{ entry.actor }} · {{ events[entry.event] || entry.event }} <time>{{ timestamp(entry.at) }}</time></li></ol></details></template>
          <p class="todo-meta"><span v-if="item.returnedAt">已退回 · </span>{{ item.createdBy === userId ? item.initialAssigneeId === userId ? '自己記下的' : `交給 ${item.assigneeName}` : `來自 ${item.creatorName}` }}<span v-if="item.state !== 'open'"> · {{ stateLabels[item.state] }}</span><button v-if="item.convertedIssue" class="text-button" @click="emit('open-issue', item.convertedIssue)">{{ item.convertedIssue.identifier }} ↗</button><span v-else-if="item.state === 'converted'"> · 卡片依專案權限開放</span></p>
        </div>
        <details v-if="item.state === 'open' && draft?.id !== item.id" class="todo-actions"><summary aria-label="待辦操作">更多</summary><div><button v-if="item.capabilities.edit" :disabled="busy" @click="edit(item)">編輯</button><button v-if="item.capabilities.convert" :disabled="busy || !canConvert" :title="canConvert ? '補上專案與卡片欄位' : '需要至少一個專案的任務編輯權限'" @click="emit('convert', item)">轉成正式卡片</button><button v-if="item.capabilities.return" :disabled="busy" @click="action(item, 'returnTodo')">退回給交辦者</button><button v-if="item.capabilities.cancel" :disabled="busy" @click="action(item, 'cancelTodo')">取消待辦</button></div></details>
      </article>
    </div>
    <button v-if="openItems.length > previewLimit" class="text-button todo-expand" :aria-expanded="showAllOpen" :aria-controls="`${tabId}-panel`" :disabled="busy" @click="showAllOpen = !showAllOpen">{{ showAllOpen ? '收起待辦' : `顯示全部待辦（${openItems.length}）` }}</button>
    <button v-if="closedItems.length" class="text-button todo-show-closed" :aria-expanded="showClosed" @click="showClosed = !showClosed">{{ showClosed ? '收起' : '顯示' }}已完成／取消／轉卡（{{ closedItems.length }}）</button>
    </div>
  </section>
</template>
<style scoped>
.todo-home { min-width: 0; color: #242936; }
.todo-heading { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 18px; }
.todo-heading h2 { font-size: 22px; margin: 0; }.todo-heading p { font-size: 14px; color: #5f6673; margin-top: 6px; }.todo-private { font-size: 12px; color: #5f6673; }
.todo-quick { display: flex; gap: 8px; }.todo-quick input { flex: 1; min-width: 0; }.todo-quick select { max-width: 260px; }.todo-quick input, .todo-quick select { border: 1px solid #bfc6d2; padding: 10px 12px; border-radius: 7px; font-size: 14px; background: #fff; }
.todo-success { color: #286b55; font-size: 13px; margin-top: 12px; }
.todo-draft-notice { padding: 12px; margin-top: 12px; background: #f5f3fc; border: 1px solid #ddd6ef; border-radius: 6px; font-size: 14px; color: #424b5c; }
.todo-draft-notice > div { display: flex; flex-wrap: wrap; gap: 16px; }
.todo-draft-notice button { min-height: 44px; }
.todo-note-toggle { margin: 10px 0; font-size: 13px; }.todo-tabs { display: flex; gap: 24px; margin-top: 10px; border-bottom: 1px solid #daddE5; }.todo-tabs button { padding: 12px 0; color: #5f6673; border-bottom: 2px solid transparent; }.todo-tabs .active { color: #5844a0; border-color: #6653bd; font-weight: 600; }.todo-tabs span { font-size: 12px; margin-left: 5px; }
.todo-row { display: flex; gap: 12px; align-items: flex-start; padding: 16px 0; border-bottom: 1px solid #edf0f5; }.todo-row:focus, .todo-focused { outline: 2px solid #8d83ed; outline-offset: 3px; }.todo-check { display: grid; place-items: center; width: 44px; min-height: 44px; flex-shrink: 0; cursor: pointer; margin-top: -10px; }.todo-check input { width: 18px; height: 18px; accent-color: #6653bd; }.todo-body { flex: 1; min-width: 0; }.todo-content > summary { cursor: pointer; overflow-wrap: anywhere; font-size: 15px; font-weight: 550; }.todo-done-title { text-decoration: line-through; color: #5f6673; }.todo-note { white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.8; font-size: 14px; margin-top: 12px; }.todo-meta { font-size: 12px; color: #5f6673; margin-top: 7px; line-height: 1.8; }.todo-meta button { margin-left: 8px; }.todo-state { font-size: 12px; padding: 3px 6px; background: #f1f2f7; border-radius: 4px; white-space: nowrap; }.todo-history { padding-left: 18px; margin-top: 12px; font-size: 12px; color: #5f6673; line-height: 1.8; }.todo-history time { margin-left: 10px; }
.todo-actions { flex-shrink: 0; font-size: 13px; }.todo-actions summary { min-width: 44px; min-height: 44px; padding: 10px 0; margin-top: -10px; cursor: pointer; color: #5844a0; }.todo-actions > div { display: flex; flex-direction: column; align-items: flex-end; gap: 12px; margin-top: 12px; }.todo-actions button { min-height: 44px; text-align: right; }.todo-expand, .todo-show-closed { display: block; min-height: 44px; }.todo-quick { flex-wrap: wrap; }.todo-quick input { flex-basis: 100%; }.todo-quick select { min-width: 0; flex: 1; max-width: none; }.todo-edit .field { margin: 0 0 12px; }.todo-edit .text-button { margin-left: 16px; }.todo-empty { color: #5f6673; font-size: 14px; padding: 22px 0; }.todo-show-closed { margin-top: 14px; font-size: 13px; }
@media(max-width:700px) { .todo-heading { align-items: flex-start; flex-direction: column; }.todo-quick { flex-wrap: wrap; }.todo-quick input { flex-basis: 100%; }.todo-quick select { min-width: 0; flex: 1; max-width: none; }.todo-actions { max-width: 85px; }.todo-row { gap: 8px; }.todo-state { font-size: 11px; padding: 3px; } }
</style>
