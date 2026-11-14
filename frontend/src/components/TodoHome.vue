<script setup>
import { computed, nextTick, ref, shallowRef, useId, watch } from 'vue';
import { api } from '../api/index.js';
import { onTabKeydown } from '../ui/tabs.js';
import MarkdownEditor from './MarkdownEditor.vue';
import TodoDetailDrawer from './TodoDetailDrawer.vue';
import PagedSelect from './PagedSelect.vue';
import { todoDate } from '../domain/todoContent.js';
const props = defineProps({ remote: Boolean, scope: String, counts: Object, moreHistory: Function, loadItem: Function, loadMembers: Function, workspaceId: String, data: Object, userId: String, canWrite: Boolean, canAssign: Boolean, canConvert: Boolean, refresh: Function });
const emit = defineEmits(['convert', 'open-issue', 'scope', 'tab']);
const content = shallowRef(''), assigneeId = shallowRef(props.userId), tab = shallowRef('mine');
const busy = shallowRef(false), error = shallowRef(''), success = shallowRef(''), selected = shallowRef(null);
const quickInput = ref(null), drawer = ref(null), tabId = useId();
const tabs = [{ id: 'create', label: '建立' }, { id: 'mine', label: '待處理' }, { id: 'sent', label: '我交辦的' }, { id: 'involved', label: '我經手的' }];
const dirty = computed(() => Boolean(content.value.trim() || drawer.value?.dirty));
const items = computed(() => props.data.todos.filter(item => tab.value === 'mine' ? item.assigneeId === props.userId && item.state === 'open' : tab.value === 'sent' ? item.createdBy === props.userId : true));
const labels = { open: '待處理', done: '已完成', canceled: '已取消', converted: '已轉卡' };
let pending;
watch(() => props.data.todos, rows => {
  if (!selected.value) return;
  const current = rows.find(item => item.id === selected.value.id);
  if (current && current.revision >= selected.value.revision && typeof current.content === 'string') selected.value = current;
});
function choose(id) { success.value = ''; error.value = ''; tab.value = id; emit('tab', id); if (id !== 'create') emit('scope', id); }
watch(() => props.scope, value => { if (value) { tab.value = value; emit('tab', value); } });
async function focus(id, { loaded = false } = {}) {
  success.value = ''; error.value = '';
  if (props.scope) { tab.value = props.scope; emit('tab', props.scope); }
  const cached = props.data.todos.find(row => row.id === id);
  const item = loaded && typeof cached?.content === 'string' ? cached : props.loadItem ? await props.loadItem(id) : cached;
  if (item) selected.value = item;
}
async function focusQuickEntry() { choose('create'); await nextTick(); quickInput.value?.focus(); }
defineExpose({ dirty, saving: computed(() => busy.value), draftId: computed(() => selected.value?.id), focus, focusQuickEntry });
async function run(operation, payload) {
  if (busy.value) return false;
  busy.value = true; error.value = ''; success.value = '';
  const signature = JSON.stringify([operation, payload]);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  try {
    await api[operation]({ ...payload, requestId: pending.requestId }); pending = null;
    await props.refresh?.();
    if (selected.value && props.loadItem) selected.value = await props.loadItem(selected.value.id);
    success.value = ({ createTodo: '紙條已建立', updateTodo: '紙條已儲存', completeTodo: '紙條已完成', reopenTodo: '紙條已重新開啟', forwardTodo: '紙條已傳遞', cancelTodo: '紙條已取消' })[operation];
    return true;
  } catch (failure) {
    error.value = failure.message;
    if (['FORBIDDEN', 'NOT_FOUND', 'WORKSPACE_CHANGED', 'UNAUTHENTICATED'].includes(failure.code)) selected.value = null;
    await props.refresh?.();
    if (failure.code === 'REVISION_CONFLICT' && selected.value && props.loadItem) selected.value = await props.loadItem(selected.value.id);
    return false;
  } finally { busy.value = false; }
}
async function create() { if (content.value.trim() && await run('createTodo', { content: content.value, assigneeId: assigneeId.value })) { content.value = ''; assigneeId.value = props.userId; choose('mine'); success.value = '紙條已建立'; } }
async function convert(item) { emit('convert', item); selected.value = null; }
</script>
<template>
  <section class="notes-page" aria-label="紙條">
    <h1>紙條</h1>
    <div class="notes-tabs" role="tablist" aria-label="紙條分頁" @keydown="onTabKeydown">
      <button v-for="option in tabs" :id="`${tabId}-${option.id}`" :key="option.id" role="tab" :aria-selected="tab === option.id" :tabindex="tab === option.id ? 0 : -1" :aria-controls="`${tabId}-panel`" :disabled="busy" @click="choose(option.id)">{{ option.label }}</button>
    </div>
    <p v-if="error" class="error-box" role="alert">{{ error }}</p><p v-if="success" class="notes-success" role="status">{{ success }}</p>
    <div :id="`${tabId}-panel`" role="tabpanel" :aria-labelledby="`${tabId}-${tab}`">
      <form v-if="canWrite && tab === 'create'" class="notes-create" @submit.prevent="create">
        <MarkdownEditor ref="quickInput" v-model="content" label="紙條內容" placeholder="輸入紙條內容…" :maxlength="6000" :rows="5" :disabled="busy" required />
        <div class="notes-create-actions"><PagedSelect v-if="canAssign && loadMembers" v-model="assigneeId" label="交給誰" :fixed-options="[{ id: userId, name: '自己' }]" :context="workspaceId" :load="loadMembers" :disabled="busy" autoload required empty-label="選擇承辦人" /><button class="button primary" :disabled="busy || !content.trim() || !assigneeId">建立紙條</button></div>
      </form>
      <template v-if="tab !== 'create'">
        <p v-if="!items.length" class="notes-empty">尚無紙條</p>
        <ol class="notes-list"><li v-for="item in items" :key="item.id" :data-todo-id="item.id">
          <div class="notes-row-content"><strong>{{ item.title }}</strong><p>來自 {{ item.creatorName }} <span v-if="item.state !== 'open'">· {{ labels[item.state] }}</span><time :datetime="todoDate(item, userId).at" :title="todoDate(item, userId).full">{{ todoDate(item, userId).label }}</time></p></div>
          <button type="button" class="text-button notes-more" :aria-label="`更多：${item.title}`" :disabled="busy" @click="focus(item.id)">更多 <span aria-hidden="true">›</span></button>
        </li></ol>
      </template>
    </div>
    <TodoDetailDrawer v-if="selected" ref="drawer" :key="selected.id" :item="selected" :busy="busy" :error="error" :workspace-id="workspaceId" :load-members="loadMembers" :perform="run" :can-convert="canConvert" @close="selected = null" @convert="convert" @open-issue="emit('open-issue', $event)" />
  </section>
</template>
<style scoped>
.notes-page { max-width: 1100px; min-width: 0; }
.notes-page h1 { margin: 0 0 20px; font-size: 24px; }
.notes-tabs { display: flex; gap: 28px; overflow-x: auto; border-bottom: 1px solid #daddE5; }
.notes-tabs button { min-height: 48px; flex: none; border-bottom: 2px solid transparent; color: #5f6673; }
.notes-tabs [aria-selected="true"] { color: #5844a0; border-bottom-color: #6653bd; font-weight: 600; }
.notes-list { list-style: none; margin: 0; padding: 0; }
.notes-list li { display: flex; align-items: center; gap: 16px; padding: 18px 0; border-bottom: 1px solid #e8eaf0; }
.notes-row-content { flex: 1; min-width: 0; }
.notes-row-content strong { display: block; font-size: 15px; font-weight: 550; overflow-wrap: anywhere; }
.notes-row-content p { color: #5f6673; font-size: 13px; margin: 7px 0 0; line-height: 1.7; }
.notes-row-content time { margin-left: 14px; white-space: nowrap; }
.notes-more { min-height: 44px; white-space: nowrap; }
.notes-more span { margin-left: 6px; }
.notes-create { max-width: 760px; padding-top: 24px; }
.notes-create-actions { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 16px; align-items: start; }
.notes-create-actions :deep(.field) { margin: 0; min-width: 0; }
.notes-create-actions .button { height: 44px; }
.notes-empty { padding: 32px 0; color: #5f6673; }
.notes-success { font-size: 14px; color: #286b55; padding-top: 12px; }
@media(max-width:479px) { .notes-tabs { gap: 20px; }.notes-create-actions { grid-template-columns: minmax(0, 1fr); }.notes-create-actions .button { width: 100%; } }
.notes-create-actions :deep(.paged-select-control select), .notes-create-actions :deep(.paged-select-control .icon-button) { height: 44px; min-height: 44px; box-sizing: border-box; }
.notes-create-actions :deep(.paged-select > label) { display: block; line-height: 20px; }
.notes-create-actions :deep(.paged-select-control) { margin-top: 8px; align-items: start; }
.notes-create-actions :deep(.paged-select-control select) { margin: 0; }
@media(min-width:480px) { .notes-create-actions > .paged-select ~ .button { margin-top: 28px; } }
</style>
