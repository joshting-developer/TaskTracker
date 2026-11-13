<script setup>
import { computed, onBeforeUnmount, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import PagedSelect from './PagedSelect.vue';
import MarkdownContent from './MarkdownContent.vue';
const props = defineProps({ item: Object, draft: Object, busy: Boolean, workspaceId: String, loadMembers: Function, perform: Function });
const emit = defineEmits(['update:draft']);
const forwarding = shallowRef(false), recipient = shallowRef(''), expanded = shallowRef(false), loading = shallowRef(false), error = shallowRef('');
const comments = shallowRef(null), handoffs = shallowRef(null);
let controller, generation = 0;
const text = computed({ get: () => props.draft?.text || '', set: value => emit('update:draft', value ? { text: value, revision: props.draft?.revision ?? props.item.revision } : null) });
const stale = computed(() => Boolean(props.draft && props.draft.revision !== props.item.revision));
const stamp = value => new Date(value).toLocaleString('zh-TW', { timeZone: 'Asia/Taipei', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' });
async function load(kind, next = false) {
  controller?.abort(); controller = new AbortController(); const current = ++generation;
  loading.value = true; error.value = '';
  try {
    const target = kind === 'comments' ? comments : handoffs;
    const page = next ? target.value.page + 1 : 1;
    const result = await api[kind === 'comments' ? 'todoComments' : 'todoHandoffs'](props.item.id, { page, perPage: 20 }, { signal: controller.signal });
    if (current !== generation) return;
    target.value = { ...result, items: next ? [...target.value.items, ...result.items] : result.items };
    return true;
  } catch (failure) { if (current === generation && failure.code !== 'ABORTED') error.value = failure.message; }
  finally { if (current === generation) loading.value = false; }
}
async function history() { if (expanded.value && await load('comments')) await load('handoffs'); }
watch(expanded, history);
watch(() => props.item.revision, () => { if (expanded.value) history(); });
async function submit(operation) {
  if (props.busy || stale.value) return;
  if (operation === 'forwardTodo' && !recipient.value) return;
  const ok = await props.perform(props.item, operation, { expectedRevision: props.draft?.revision ?? props.item.revision, reply: text.value, ...(operation === 'forwardTodo' ? { assigneeId: recipient.value } : {}) });
  if (ok) { emit('update:draft', null); forwarding.value = false; recipient.value = ''; }
}
function useLatest() { if (props.draft) emit('update:draft', { ...props.draft, revision: props.item.revision }); }
onBeforeUnmount(() => { generation++; controller?.abort(); });
</script>
<template>
  <div class="todo-conversation">
    <div v-if="item.capabilities.comment" class="todo-reply">
      <label :for="`reply-${item.id}`">回覆</label>
      <textarea :id="`reply-${item.id}`" v-model="text" rows="2" maxlength="2000" placeholder="補充進度或回覆…" :disabled="busy" />
      <p v-if="stale" class="reply-conflict" role="status">待辦已更新, 回覆草稿已保留 <button class="text-button" :disabled="busy" @click="useLatest">使用最新版本繼續</button></p>
      <div class="reply-actions">
        <button v-if="text.trim()" type="button" class="button" :disabled="busy || stale" @click="submit('commentTodo')">送出回覆</button>
        <button v-if="item.capabilities.complete" type="button" class="button primary" :disabled="busy || stale" @click="submit('completeTodo')">完成</button>
        <button v-if="item.capabilities.forward" type="button" class="button" :aria-expanded="forwarding" :disabled="busy" @click="forwarding = !forwarding">轉發</button>
      </div>
      <form v-if="forwarding" class="todo-forward" @submit.prevent="submit('forwardTodo')">
        <PagedSelect v-model="recipient" label="轉發給誰" :context="workspaceId" :load="loadMembers" :options="[]" :disabled="busy" autoload empty-label="選擇承辦者" />
        <button class="button primary" :disabled="busy || stale || !recipient || recipient === item.assigneeId">確認轉發</button>
      </form>
    </div>
    <details class="conversation-history" @toggle="expanded = $event.target.open">
      <summary>留言與交接</summary>
      <p v-if="loading" role="status">載入中…</p>
      <div v-if="error" role="alert">{{ error }} <button class="text-button" :disabled="loading" @click="history">重試</button></div>
      <section v-if="comments"><h4>留言</h4><p v-if="!comments.total">尚無留言</p><ol><li v-for="entry in comments.items" :key="entry.id"><div>{{ entry.author }} <time>{{ stamp(entry.createdAt) }}</time></div><MarkdownContent :source="entry.content" /></li></ol><button v-if="comments.page < comments.lastPage" class="text-button" :disabled="loading" @click="load('comments', true)">載入更多留言</button></section>
      <section v-if="handoffs"><h4>交接紀錄</h4><p class="initial-handoff">{{ item.creatorName }} → {{ item.initialAssigneeName || item.assigneeName }} · 建立交辦</p><ol><li v-for="entry in handoffs.items" :key="entry.id">{{ entry.from }} → {{ entry.to }} · {{ entry.kind === 'returned' ? '退回' : '轉發' }} <time>{{ stamp(entry.createdAt) }}</time></li></ol><button v-if="handoffs.page < handoffs.lastPage" class="text-button" :disabled="loading" @click="load('handoffs', true)">載入更多交接</button></section>
    </details>
  </div>
</template>
<style scoped>
.todo-conversation { margin-top: 12px; min-width: 0; }
.todo-reply > label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; }
textarea { display: block; width: 100%; min-height: 72px; padding: 10px 12px; border: 1px solid #bfc6d2; border-radius: 6px; font: inherit; font-size: 14px; line-height: 1.5; resize: vertical; background: white; }
.reply-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; margin-top: 8px; }
.reply-actions .button, .todo-forward > .button { min-height: 44px; }
.todo-forward { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: end; gap: 12px; margin-top: 12px; }
.todo-forward :deep(.paged-select) { min-width: 0; margin: 0; }
.conversation-history { margin-top: 12px; font-size: 13px; color: #424b5c; }
.conversation-history > summary { min-height: 44px; padding: 12px 0; cursor: pointer; }
.conversation-history h4 { margin: 12px 0 8px; font-size: 13px; }
.conversation-history ol { list-style: none; padding: 0; margin: 0; }
.conversation-history li { padding: 10px 0; border-bottom: 1px solid #edf0f5; overflow-wrap: anywhere; }
.conversation-history time { color: #5f6673; margin-left: 8px; font-size: 12px; }
.reply-conflict { font-size: 13px; color: #8a4a13; }
@media(max-width:479px) { .todo-forward { grid-template-columns: minmax(0, 1fr); } }
</style>
