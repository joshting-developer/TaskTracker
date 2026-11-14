<script setup>
import { computed, ref, watch } from 'vue';
import Icon from './Icon.vue';
import { VMenu, VList, VListItem } from 'vuetify/components';
import { boardClosedCount, boardCount } from '../domain/boards.js';
const props = defineProps({ remote: Boolean, boards: Array, issues: Array, canManage: Boolean, loading: Boolean, archived: Boolean });
const emit = defineEmits(['open', 'create', 'edit', 'search', 'archived', 'restore', 'archive']);
// TT-283：已封存看板顯示封存日期；日期用本地時間的年月日。
const archivedDate = value => value ? new Date(value).toLocaleDateString('zh-TW', { year: 'numeric', month: 'numeric', day: 'numeric' }) : '';
const search = ref(''), menuBoard = ref(null);
function choose(board, action) { menuBoard.value = null; emit(action, board); }
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
    <header class="board-list-heading"><div><h2>看板列表</h2></div><button v-if="canManage && !archived" class="button primary" :disabled="loading" @click="$emit('create')"><Icon name="plus" :size="17" />新增看板</button></header>
    <div v-if="remote" class="board-list-tabs" role="group" aria-label="看板狀態">
      <button type="button" class="board-list-tab" :aria-pressed="!archived" @click="$emit('archived', false)">使用中</button>
      <button type="button" class="board-list-tab" :aria-pressed="archived" @click="$emit('archived', true)">已封存</button>
    </div>
    <label class="search-field"><Icon name="search" :size="18" /><input v-model="search" aria-label="搜尋看板" placeholder="搜尋看板名稱…" /></label>
    <div class="board-list-grid"><article v-for="{ board, total, closed, percent } in visible" :key="board.id" class="board-summary-card">
      <button class="board-summary-open" :aria-label="`開啟看板 ${board.name}`" :aria-describedby="`board-counts-${board.id}`" @click="$emit('open', board.id)">
        <Icon name="board" :size="24" /><h3>{{ board.name }}</h3><span v-if="board.description" class="board-summary-description" :title="board.description">{{ board.description }}</span>
        <span :id="`board-counts-${board.id}`" class="board-summary-counts"><span>{{ total }} 張卡片</span><span class="board-summary-closed">已完成 {{ closed }}</span><span v-if="board.archivedAt" class="board-summary-archived">封存於 {{ archivedDate(board.archivedAt) }}</span></span>
        <span class="progress-track board-summary-progress" aria-hidden="true"><span :style="{ width: `${percent}%` }" /></span>
      </button>
      <footer v-if="!board.isDefault && canManage" class="board-summary-actions">
        <VMenu location="bottom end" :transition="false" :model-value="menuBoard === board.id" :disabled="loading" @update:model-value="value => { menuBoard = value ? board.id : null; }">
          <template #activator="{ props: menuProps }"><button v-bind="menuProps" type="button" class="button secondary board-action-trigger" :aria-label="`看板操作 ${board.name}`" :data-board-actions="board.id" :disabled="loading">更多<span aria-hidden="true">⋯</span></button></template>
          <VList role="menu" class="board-action-menu" aria-label="看板操作選項" density="comfortable">
            <VListItem tag="button" type="button" role="menuitem" v-if="board.archivedAt" :aria-label="`還原看板 ${board.name}`" @click="choose(board, 'restore')">還原</VListItem>
            <template v-else><VListItem tag="button" type="button" role="menuitem" :aria-label="`編輯看板 ${board.name}`" @click="choose(board, 'edit')">編輯</VListItem><VListItem tag="button" type="button" role="menuitem" :aria-label="`封存看板 ${board.name}`" @click="choose(board, 'archive')">封存</VListItem></template>
          </VList>
        </VMenu>
      </footer>
    </article></div>
    <p v-if="!visible.length && !loading" class="subtle">{{ archived ? (search.trim() ? '沒有符合名稱的封存看板' : '目前沒有封存的看板') : '沒有符合名稱的看板' }}</p>
  </section>
</template>

<style scoped>
.board-action-trigger { min-height: 44px; gap: 10px; padding: 8px 12px; font-size: 14px; }
.board-action-menu { min-width: 180px; border: 1px solid var(--border); border-radius: 6px; }
.board-action-menu :deep(.v-list-item) { min-height: 44px; font-size: 14px; width: 100%; display: flex; justify-content: flex-start; text-align: left; }
</style>
