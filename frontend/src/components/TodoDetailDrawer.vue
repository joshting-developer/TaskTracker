<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, useId } from 'vue';
import Icon from './Icon.vue';
import PagedSelect from './PagedSelect.vue';
import MarkdownContent from './MarkdownContent.vue';
import MarkdownEditor from './MarkdownEditor.vue';
import TodoConversation from './TodoConversation.vue';
import TodoActions from './TodoActions.vue';
import { todoSource } from '../domain/todoContent.js';
const props = defineProps({ item: Object, busy: Boolean, error: String, workspaceId: String, loadMembers: Function, perform: Function, canConvert: Boolean });
const emit = defineEmits(['close', 'convert', 'open-issue']);
const dialog = ref(null), id = useId();
const defaultRecipient = () => props.item.capabilities.return ? '__previous__' : '';
const reply = shallowRef(''), recipient = shallowRef(defaultRecipient()), revision = shallowRef(props.item.revision), editing = shallowRef(false), content = shallowRef('');
const dirty = computed(() => Boolean(reply.value.trim() || editing.value));
const stale = computed(() => revision.value !== props.item.revision);
const labels = { open: '待處理', done: '已完成', canceled: '已取消', converted: '已轉卡' };
let opener;
defineExpose({ dirty });
function close() {
  if (props.busy || (dirty.value && !window.confirm('捨棄尚未送出的紙條內容？'))) return;
  emit('close');
}
function latest() { revision.value = props.item.revision; }
async function send(operation) {
  if (props.busy || stale.value) return;
  const fields = { id: props.item.id, expectedRevision: revision.value };
  if (operation === 'forwardTodo') {
    if (recipient.value === '__previous__') { if (!props.item.capabilities.return || !reply.value.trim()) return; operation = 'returnTodo'; }
    else { if (!recipient.value || recipient.value === props.item.assigneeId) return; fields.assigneeId = recipient.value; }
  }
  if (operation === 'updateTodo') fields.content = content.value;
  else if (reply.value.trim()) fields.reply = reply.value;
  if (await props.perform(operation, fields)) { reply.value = ''; editing.value = false; await nextTick(); recipient.value = defaultRecipient(); latest(); }
}
function edit() { content.value = todoSource(props.item); editing.value = true; latest(); }
async function cancel() { if (window.confirm('取消這張紙條？')) await send('cancelTodo'); }
function convert() { if (!dirty.value || window.confirm('捨棄尚未送出的紙條內容並轉成卡片？')) { reply.value = ''; editing.value = false; emit('convert', props.item); } }
onMounted(() => { opener = document.activeElement; dialog.value.showModal(); });
onBeforeUnmount(() => { dialog.value?.close(); if (opener?.isConnected) opener.focus({ preventScroll: true }); });
</script>
<template>
  <dialog ref="dialog" class="note-drawer" :aria-labelledby="`${id}-heading`" @cancel.prevent="close" @click="event => { if (event.target === dialog) close(); }">
    <header class="note-drawer-header"><div><h2 :id="`${id}-heading`">紙條</h2><span>{{ labels[item.state] }} · {{ item.assigneeName }}</span></div><div class="note-header-actions">
      <button v-if="item.capabilities.complete && !editing" type="button" class="button secondary" :disabled="busy || stale" @click="send('completeTodo')">完成</button>
      <button v-if="item.capabilities.convert && canConvert && !editing" type="button" class="text-button" :disabled="busy" @click="convert">轉為卡片</button>
      <TodoActions v-if="item.capabilities.edit || item.capabilities.cancel || item.capabilities.reopen"><button v-if="item.capabilities.edit" :disabled="busy" @click="edit">編輯</button><button v-if="item.capabilities.reopen" :disabled="busy || stale" @click="send('reopenTodo')">重新開啟</button><button v-if="item.capabilities.cancel" :disabled="busy || stale" @click="cancel">取消紙條</button></TodoActions>
      <button class="icon-button" aria-label="關閉紙條" :disabled="busy" @click="close"><Icon name="close" /></button>
    </div></header>
    <div class="note-drawer-body">
      <p v-if="error" class="error-box" role="alert">{{ error }}</p>
      <p v-if="stale" class="note-conflict" role="status">紙條已更新, 草稿已保留 <button class="text-button" :disabled="busy" @click="latest">使用最新版本繼續</button></p>
      <form v-if="editing" @submit.prevent="send('updateTodo')"><MarkdownEditor v-model="content" label="編輯紙條內容" :maxlength="6000" :rows="5" :disabled="busy" required /><button class="button primary" :disabled="busy || stale || !content.trim()">儲存修改</button><button type="button" class="text-button note-cancel-edit" :disabled="busy" @click="editing = false">取消修改</button></form>
      <template v-else><MarkdownContent issue-references class="note-content" :source="todoSource(item)" /></template>
      <button v-if="item.convertedIssue" class="text-button" @click="emit('open-issue', item.convertedIssue)">{{ item.convertedIssue.identifier }} ↗</button>
      <TodoConversation :item="item" />
    </div>
    <form v-if="item.capabilities.forward && !editing" class="note-transfer" @submit.prevent="send('forwardTodo')">
      <label :for="`${id}-reply`">回覆內容</label><textarea :id="`${id}-reply`" v-model="reply" rows="3" maxlength="2000" :disabled="busy" placeholder="輸入回覆…" />
      <div class="note-transfer-actions"><PagedSelect v-model="recipient" label="要傳給誰" :context="workspaceId" :load="loadMembers" :fixed-options="[...(item.capabilities.return ? [{ id: '__previous__', name: '回覆上一手' }] : []), { id: item.createdBy, name: item.creatorName }, { id: item.initialAssigneeId, name: item.initialAssigneeName || item.assigneeName }]" :disabled="busy" autoload required empty-label="選擇承辦人" /><button class="button primary" :disabled="busy || stale || !recipient || recipient === item.assigneeId || (recipient === '__previous__' && (!item.capabilities.return || !reply.trim()))">傳遞</button></div>
    </form>
  </dialog>
</template>
<style scoped>
.note-drawer { position: fixed; inset: 0 0 0 auto; margin: 0; width: min(680px, 100vw); height: 100dvh; max-height: 100dvh; max-width: 100vw; border: 0; border-radius: 0; padding: 0; background: white; color: #242936; display: flex; flex-direction: column; }
.note-drawer::backdrop { background: #171b2b66; }
.note-drawer-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 20px 24px; border-bottom: 1px solid #daddE5; flex: none; }
.note-drawer-header h2 { font-size: 20px; margin: 0 0 4px; }
.note-drawer-header span { font-size: 13px; color: #5f6673; }
.note-header-actions { display: flex; align-items: center; flex-wrap: wrap; justify-content: flex-end; gap: 12px; }
.note-header-actions :deep(.todo-actions summary) { margin-top: 0; }
.note-drawer-body { overflow-y: auto; padding: 24px; flex: 1; min-height: 0; }
.note-drawer-body h3 { font-size: 18px; margin: 0 0 16px; overflow-wrap: anywhere; }
.note-content { font-size: 14px; line-height: 1.8; overflow-wrap: anywhere; }
.note-transfer { flex: none; border-top: 1px solid #daddE5; padding: 16px 24px 24px; max-height: 48dvh; overflow-y: auto; }
.note-transfer > label { display: block; font-size: 13px; margin-bottom: 6px; }
.note-transfer textarea { width: 100%; border: 1px solid #bfc6d2; border-radius: 6px; padding: 10px 12px; font: inherit; font-size: 14px; resize: vertical; max-height: 18dvh; }
.note-transfer-actions { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; align-items: start; margin-top: 12px; }
.note-transfer-actions :deep(.field) { margin: 0; min-width: 0; }
.note-transfer-actions .button { height: 44px; }
.note-conflict { color: #8a4a13; font-size: 14px; }
.note-cancel-edit { margin-left: 16px; }
@media(max-width:479px) { .note-drawer-header, .note-drawer-body { padding: 16px; }.note-header-actions { gap: 8px; }.note-transfer { padding: 12px 16px 16px; }.note-transfer-actions { grid-template-columns: minmax(0, 1fr); }.note-transfer-actions .button { width: 100%; } }
.note-transfer-actions :deep(.paged-select-control select), .note-transfer-actions :deep(.paged-select-control .icon-button) { height: 44px; min-height: 44px; box-sizing: border-box; }
.note-transfer-actions :deep(.paged-select > label) { display: block; line-height: 20px; }
.note-transfer-actions :deep(.paged-select-control) { margin-top: 8px; align-items: start; }
.note-transfer-actions :deep(.paged-select-control select) { margin: 0; }
@media(min-width:480px) { .note-transfer-actions > .paged-select ~ .button { margin-top: 28px; } }
</style>
