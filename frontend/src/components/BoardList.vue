<script setup>
import { computed, ref, watch } from 'vue';
import Icon from './Icon.vue';
import { boardCount } from '../domain/boards.js';
const props = defineProps({ remote: Boolean, boards: Array, issues: Array, canManage: Boolean, loading: Boolean });
const emit = defineEmits(['open', 'create', 'edit', 'search']);
const search = ref('');
watch(search, value => { if (props.remote) emit('search', value); });
const visible = computed(() => props.remote ? props.boards : props.boards.filter(board => board.name.toLowerCase().includes(search.value.trim().toLowerCase())));
</script>
<template>
  <section class="board-list-page" aria-label="看板列表">
    <header class="board-list-heading"><div><h2>看板列表</h2></div><button v-if="canManage" class="button primary" :disabled="loading" @click="$emit('create')"><Icon name="plus" :size="17" />新增看板</button></header>
    <label class="search-field"><Icon name="search" :size="18" /><input v-model="search" aria-label="搜尋看板" placeholder="搜尋看板名稱…" /></label>
    <div class="board-list-grid"><article v-for="board in visible" :key="board.id" class="board-summary-card">
      <button class="board-summary-open" :aria-label="`開啟看板 ${board.name}`" @click="$emit('open', board.id)"><Icon name="board" :size="24" /><h3>{{ board.name }}</h3><span>{{ remote ? board.issueCount : boardCount(board, issues) }} 張卡片</span></button>
      <button v-if="!board.isDefault && canManage" class="text-button board-summary-edit" :aria-label="`編輯看板 ${board.name}`" @click="$emit('edit', board)">編輯收錄</button>
    </article></div>
    <p v-if="!visible.length && !loading" class="subtle">沒有符合名稱的看板</p>
  </section>
</template>
