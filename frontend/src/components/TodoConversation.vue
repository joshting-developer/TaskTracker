<script setup>
import { onBeforeUnmount, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import MarkdownContent from './MarkdownContent.vue';
const props = defineProps({ item: Object });
const loading = shallowRef(false), error = shallowRef('');
const comments = shallowRef(null), handoffs = shallowRef(null);
let controller, generation = 0;
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
async function history() { if (await load('comments')) await load('handoffs'); }
watch(() => props.item.revision, history, { immediate: true });
onBeforeUnmount(() => { generation++; controller?.abort(); });
</script>
<template>
  <div class="todo-conversation">
    <section class="conversation-history" aria-label="紙條歷程">
      <h3>歷程</h3>
      <p v-if="loading" role="status">載入中…</p>
      <div v-if="error" role="alert">{{ error }} <button class="text-button" :disabled="loading" @click="history">重試</button></div>
      <section v-if="comments"><h4>留言</h4><p v-if="!comments.total">尚無留言</p><ol><li v-for="entry in comments.items" :key="entry.id"><div>{{ entry.author }} <time>{{ stamp(entry.createdAt) }}</time></div><MarkdownContent :source="entry.content" /></li></ol><button v-if="comments.page < comments.lastPage" class="text-button" :disabled="loading" @click="load('comments', true)">載入更多留言</button></section>
      <section v-if="handoffs"><h4>交接紀錄</h4><p class="initial-handoff">{{ item.creatorName }} → {{ item.initialAssigneeName || item.assigneeName }} · 建立交辦</p><ol><li v-for="entry in handoffs.items" :key="entry.id">{{ entry.from }} → {{ entry.to }} · {{ entry.kind === 'returned' ? '退回' : '傳遞' }} <time>{{ stamp(entry.createdAt) }}</time></li></ol><button v-if="handoffs.page < handoffs.lastPage" class="text-button" :disabled="loading" @click="load('handoffs', true)">載入更多交接</button></section>
    </section>
  </div>
</template>
<style scoped>
.conversation-history { margin-top: 24px; border-top: 1px solid #e8eaf0; padding-top: 20px; font-size: 14px; color: #424b5c; }
.conversation-history h3 { font-size: 16px; margin-bottom: 16px; }
.conversation-history h4 { margin: 16px 0 8px; font-size: 14px; }
.conversation-history ol { list-style: none; padding: 0; margin: 0; }
.conversation-history li { padding: 12px 0; border-bottom: 1px solid #edf0f5; overflow-wrap: anywhere; }
.conversation-history time { color: #5f6673; margin-left: 8px; font-size: 13px; }
</style>
