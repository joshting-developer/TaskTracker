<script setup>
import { onUnmounted, shallowRef, watch } from 'vue';
import { adminStatistics } from '../api/admin.js';
import { t, formatNumber } from '../i18n/index.js';
import AdminActivityChart from './AdminActivityChart.vue';
import AdminWorkspaces from './AdminWorkspaces.vue';
const emit = defineEmits(['expired']);
const days = shallowRef(7);
const refresh = shallowRef(0);
const data = shallowRef(null);
const loading = shallowRef(false);
const error = shallowRef('');
let active;
onUnmounted(() => active?.abort());
watch([days, refresh], async ([period], _previous, onCleanup) => {
  const controller = new AbortController(); active = controller;
  onCleanup(() => controller.abort());
  loading.value = true; error.value = ''; data.value = null;
  try { const result = await adminStatistics(period, controller.signal); if (!controller.signal.aborted) data.value = result; }
  catch (cause) {
    if (controller.signal.aborted) return;
    if (cause.status === 401) emit('expired'); else error.value = cause.message;
  } finally { if (!controller.signal.aborted) loading.value = false; }
}, { immediate: true });
</script>
<template>
  <div class="admin-dashboard">
    <div class="admin-heading"><h1 class="admin-title">{{ t('使用量總覽') }}</h1><div class="admin-controls"><label class="admin-period">{{ t('期間') }}<select v-model.number="days" :aria-label="t('期間')"><option :value="7">{{ t('近7日') }}</option><option :value="30">{{ t('近30日') }}</option></select></label><v-btn variant="outlined" :disabled="loading" @click="refresh++">{{ t('重新整理') }}</v-btn></div></div>
    <section class="admin-summary" :aria-label="t('平台使用量')" :aria-busy="loading">
      <div><span>{{ t('帳號總數') }}</span><strong>{{ data ? formatNumber(data.totals.users) : '—' }}</strong></div>
      <div><span>{{ t('工作區總數') }}</span><strong>{{ data ? formatNumber(data.totals.workspaces) : '—' }}</strong></div>
      <div><span>{{ t('期間活躍帳號') }}</span><strong>{{ data ? formatNumber(data.active.users) : '—' }}</strong></div>
      <div><span>{{ t('期間活躍工作區') }}</span><strong>{{ data ? formatNumber(data.active.workspaces) : '—' }}</strong></div>
    </section>
    <p v-if="error" class="admin-error" role="alert">{{ error }} <button type="button" class="admin-retry" @click="refresh++">{{ t('重試') }}</button></p>
    <p v-else-if="loading" role="status">{{ t('載入中…') }}</p>
    <!-- 1184px canvas = (2fr + 1fr) + 24px gap; both panels keep the same outer edges. -->
    <div class="admin-grid">
      <section class="admin-panel admin-activity">
        <div class="admin-panel-heading"><h2>{{ t('每日成功寫入') }}</h2><span v-if="data" class="admin-period-stamp">{{ data.period.from }} – {{ data.period.through }} · UTC</span></div>
        <template v-if="data"><AdminActivityChart :days="data.daily" /><details class="admin-definitions"><summary>{{ t('統計定義') }}</summary><p>{{ t('活躍指期間內有成功寫入操作, 不包含只登入或瀏覽的帳號') }}</p><p>{{ t('次數依操作紀錄計算, 包含成功重送；不代表實際卡片數或工時時數') }}</p><p>{{ t('紀錄只涵蓋啟用後的最近90日, 無紀錄的日期顯示0') }}</p></details></template>
      </section>
      <AdminWorkspaces @expired="emit('expired')" />
    </div>
  </div>
</template>
<style scoped>
.admin-heading, .admin-controls { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.admin-period { display: flex; align-items: center; gap: 10px; font-size: 14px; }
.admin-summary { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); margin: 24px 0; padding: 24px 0; border-block: 1px solid var(--admin-border); background: var(--admin-surface); }
.admin-summary>div { padding: 0 24px; display: flex; flex-direction: column; gap: 8px; border-right: 1px solid var(--admin-border); }
.admin-summary>div:last-child { border: 0; }.admin-summary span { color: var(--admin-muted); font-size: 14px; }.admin-summary strong { font-size: 28px; font-weight: 500; font-variant-numeric: tabular-nums; }
.admin-grid { display: grid; grid-template-columns: minmax(0,2fr) minmax(290px,1fr); align-items: start; gap: 24px; }
.admin-period-stamp { font-size: 12px; color: var(--admin-muted); }.admin-definitions { font-size: 13px; color: var(--admin-muted); margin-top: 20px; }.admin-definitions summary { cursor: pointer; min-height: 44px; display: list-item; padding: 12px 0; }.admin-definitions p { margin-top: 8px; line-height: 1.7; }
@media(max-width:1000px) { .admin-grid { grid-template-columns: 1fr; } }
@media(max-width:600px) { .admin-summary { grid-template-columns: 1fr 1fr; gap: 24px 0; padding: 20px 0; }.admin-summary>div { padding: 0 16px; }.admin-summary>div:nth-child(2) { border: 0; }.admin-summary strong { font-size: 24px; }.admin-controls { gap: 12px; }.admin-heading { align-items: flex-start; } }
</style>
