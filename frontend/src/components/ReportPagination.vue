<script setup>
import { t } from '../i18n/index.js';
defineProps({ pagination: { type: Object, required: true }, label: { type: String, required: true }, disabled: Boolean });
const emit = defineEmits(['change']);
</script>

<template>
  <nav v-if="pagination.lastPage > 1" class="report-pagination" :aria-label="label">
    <span>{{ t('第') }} {{ pagination.page }} / {{ pagination.lastPage }} {{ t('頁 · 共') }} {{ pagination.total }} {{ t('筆') }}</span>
    <button class="button secondary" :disabled="disabled || pagination.page <= 1" @click="emit('change', pagination.page - 1)">{{ t('上一頁') }}</button>
    <button class="button secondary" :disabled="disabled || pagination.page >= pagination.lastPage" @click="emit('change', pagination.page + 1)">{{ t('下一頁') }}</button>
  </nav>
</template>

<style scoped>
.report-pagination { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: 10px; margin-top: 16px; font-size: 14px; }
.report-pagination .button { min-height: 44px; }
</style>
