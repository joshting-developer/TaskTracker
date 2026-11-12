<script setup>
import { computed, ref, watch } from 'vue';
import Icon from './Icon.vue';
import { boardClosedCount, boardCount } from '../domain/boards.js';
const props = defineProps({ remote: Boolean, boards: Array, issues: Array, canManage: Boolean, loading: Boolean });
const emit = defineEmits(['open', 'create', 'edit', 'search']);
const search = ref('');
watch(search, value => { if (props.remote) emit('search', value); });
const visible = computed(() => {
  const term = search.value.trim().toLowerCase();
  const boards = props.remote ? props.boards : props.boards.filter(board => board.name.toLowerCase().includes(term));
  return boards.map(board => {
    const total = props.remote ? board.issueCount : boardCount(board, props.issues);
    const closed = props.remote ? board.closedCount ?? 0 : boardClosedCount(board, props.issues);
    return { board, total, closed, percent: total ? Math.round(closed / total * 100) : 0 };
  });
});
</script>
<template>
  <section class="board-list-page" aria-label="看板列表">
    <header class="board-list-heading"><div><h2>看板列表</h2></div><button v-if="canManage" class="button primary" :disabled="loading" @click="$emit('create')"><Icon name="plus" :size="17" />新增看板</button></header>
    <label class="search-field"><Icon name="search" :size="18" /><input v-model="search" aria-label="搜尋看板" placeholder="搜尋看板名稱…" /></label>
    <div class="board-list-grid"><article v-for="{ board, total, closed, percent } in visible" :key="board.id" class="board-summary-card">
      <button class="board-summary-open" :aria-label="`開啟看板 ${board.name}`" :aria-describedby="`board-counts-${board.id}`" @click="$emit('open', board.id)">
        <Icon name="board" :size="24" /><h3>{{ board.name }}</h3>
        <span :id="`board-counts-${board.id}`" class="board-summary-counts"><span>{{ total }} 張卡片</span><span class="board-summary-closed">已完成 {{ closed }}</span></span>
        <span class="progress-track board-summary-progress" aria-hidden="true"><span :style="{ width: `${percent}%` }" /></span>
      </button>
      <footer v-if="!board.isDefault && canManage" class="board-summary-actions">
        <button class="button secondary board-summary-edit" :aria-label="`編輯收錄 ${board.name}`" @click="$emit('edit', board)"><Icon name="edit" :size="15" />編輯收錄</button>
      </footer>
    </article></div>
    <p v-if="!visible.length && !loading" class="subtle">沒有符合名稱的看板</p>
  </section>
</template>
