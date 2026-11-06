<script setup>
defineProps({ pagination: { type: Object, required: true }, label: { type: String, required: true }, disabled: Boolean });
const emit = defineEmits(['change']);
</script>

<template>
  <nav v-if="pagination.lastPage > 1" class="report-pagination" :aria-label="label">
    <span>第 {{ pagination.page }} / {{ pagination.lastPage }} 頁 · 共 {{ pagination.total }} 筆</span>
    <button class="button secondary" :disabled="disabled || pagination.page <= 1" @click="emit('change', pagination.page - 1)">上一頁</button>
    <button class="button secondary" :disabled="disabled || pagination.page >= pagination.lastPage" @click="emit('change', pagination.page + 1)">下一頁</button>
  </nav>
</template>

<style scoped>
.report-pagination { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: 10px; margin-top: 16px; font-size: 14px; }
.report-pagination .button { min-height: 44px; }
</style>
