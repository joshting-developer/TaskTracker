<script setup>
import { ref } from 'vue';
import { t } from '../i18n/index.js';
import PersonalDashboardPanel from './PersonalDashboardPanel.vue';
defineProps({ permissions: Array });
const emit = defineEmits(['access']);
const refreshKey = ref(0);
defineExpose({ refresh: () => { refreshKey.value++; } });
const panels = [{ kind: 'mine', title: '我的未結案任務' }, { kind: 'due', title: '七天內到期與逾期' }, { kind: 'starting', title: '七天內開始' }, { kind: 'recent', title: '最近更新' }];
</script>
<template>
  <section class="personal-dashboard" :aria-label="t('個人工作摘要')">
    <header class="dashboard-heading"><h1>{{ t('我的工作') }}</h1><button class="text-button" @click="refreshKey++">{{ t('重新整理') }}</button></header>
    <div class="dashboard-grid"><PersonalDashboardPanel v-for="panel in panels" :key="panel.kind" v-bind="panel" :refresh-key="refreshKey" @access="emit('access')" /><PersonalDashboardPanel v-if="permissions.includes('todo.read')" kind="notes" title="待處理紙條" :refresh-key="refreshKey" @access="emit('access')" /></div>
  </section>
</template>
<style scoped>
.personal-dashboard { max-width: 1440px; min-width: 0; }
.dashboard-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
h1 { margin: 0; font-size: 24px; }
.dashboard-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; align-items: start; }
@media (max-width: 1200px) { .dashboard-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 700px) { .dashboard-grid { grid-template-columns: minmax(0, 1fr); gap: 16px; } }
</style>
