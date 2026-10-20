<script setup>
import { computed, ref } from 'vue';
import Icon from './Icon.vue';
import { boardCount } from '../domain/boards.js';
const props = defineProps({ boards: Array, issues: Array, canManage: Boolean, loading: Boolean });
defineEmits(['open', 'create', 'edit']);
const search = ref('');
const visible = computed(() => props.boards.filter(board => board.name.toLowerCase().includes(search.value.trim().toLowerCase())));
</script>
<template>
  <section class="board-list-page" aria-label="看板列表">
    <header class="board-list-heading"><div><h2>看板列表</h2><p class="subtle">Default 查看全部卡片, 自訂看板整理這個專案的工作</p></div><button v-if="canManage" class="button primary" :disabled="loading" @click="$emit('create')"><Icon name="plus" :size="17" />新增看板</button></header>
    <label class="search-field"><Icon name="search" :size="18" /><input v-model="search" aria-label="搜尋看板" placeholder="搜尋看板名稱…" /></label>
    <div class="board-list-grid"><article v-for="board in visible" :key="board.id" class="board-summary-card">
      <button class="board-summary-open" :aria-label="`開啟看板 ${board.name}`" @click="$emit('open', board.id)"><Icon name="board" :size="24" /><h3>{{ board.name }}</h3><p>{{ board.isDefault ? '目前專案的所有卡片, 自動更新' : `明確收錄 ${board.selectedIssueIds.length} 個項目, Epic 包含所屬卡片` }}</p><span>{{ boardCount(board, issues) }} 張卡片</span></button>
      <button v-if="!board.isDefault && canManage" class="text-button board-summary-edit" :aria-label="`編輯看板 ${board.name}`" @click="$emit('edit', board)">編輯收錄</button>
    </article></div>
    <p v-if="!visible.length && !loading" class="subtle">沒有符合名稱的看板</p>
  </section>
</template>
