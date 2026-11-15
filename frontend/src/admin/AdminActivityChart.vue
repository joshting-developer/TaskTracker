<script setup>
import { computed } from 'vue';
import { t, formatNumber } from '../i18n/index.js';
const props = defineProps({ days: { type: Array, required: true } });
const total = computed(() => props.days.reduce((sum, day) => sum + day.requests, 0));
const maximum = computed(() => {
  const peak = Math.max(...props.days.map(day => day.requests), 1);
  const step = Math.max(1, 10 ** Math.floor(Math.log10(peak)));
  return Math.ceil((peak + 1) / (step * 2)) * step * 2;
});
const ticks = computed(() => [...new Set([maximum.value, Math.floor(maximum.value / 2), 0])]);
const bars = computed(() => props.days.map((day, index) => ({ ...day, x: index / props.days.length * 700 + 2, y: 160 - day.requests / maximum.value * 150, height: day.requests / maximum.value * 150 })));
</script>
<template>
  <div class="admin-chart">
    <p class="admin-chart-total"><strong>{{ formatNumber(total) }}</strong><span>{{ t('次成功請求') }}</span></p>
    <div class="admin-chart-frame"><div class="admin-chart-axis" aria-hidden="true"><span v-for="tick in ticks" :key="tick" :style="{ top: `${(160 - tick / maximum * 150) / 170 * 100}%` }">{{ formatNumber(tick) }}</span></div><div class="admin-chart-canvas"><svg viewBox="0 0 700 170" preserveAspectRatio="none" role="img" :aria-label="t('每日成功寫入次數, 精確數值見每日明細')"><line v-for="tick in ticks" :key="tick" x1="0" :y1="160 - tick / maximum * 150" x2="700" :y2="160 - tick / maximum * 150" class="admin-chart-grid" vector-effect="non-scaling-stroke" /><rect v-for="bar in bars" :key="bar.date" :x="bar.x" :y="bar.y" :width="Math.max(1,700 / days.length - 4)" :height="bar.height" class="admin-chart-bar" /></svg><div class="admin-chart-dates" aria-hidden="true"><span>{{ days[0]?.date.slice(5) }}</span><span>{{ days[Math.floor(days.length / 2)]?.date.slice(5) }}</span><span>{{ days.at(-1)?.date.slice(5) }}</span></div></div></div>
    <details class="admin-daily-details"><summary>{{ t('每日明細') }}</summary><div class="admin-table-frame"><table class="admin-table"><thead><tr><th>{{ t('日期') }}</th><th>{{ t('全部請求') }}</th><th>{{ t('建卡請求') }}</th><th>{{ t('留言請求') }}</th><th>{{ t('工時填報請求') }}</th></tr></thead><tbody><tr v-for="day in days" :key="day.date"><td>{{ day.date }}</td><td>{{ formatNumber(day.requests) }}</td><td>{{ formatNumber(day.issues) }}</td><td>{{ formatNumber(day.comments) }}</td><td>{{ formatNumber(day.timeEntries) }}</td></tr></tbody></table></div></details>
  </div>
</template>
<style scoped>
.admin-chart-total { display: flex; align-items: baseline; gap: 10px; margin: 20px 0; font-size: 13px; color: var(--admin-muted); }.admin-chart-total strong { color: var(--admin-ink); font-size: 30px; font-weight: 500; font-variant-numeric: tabular-nums; }
.admin-chart-frame { display: flex; gap: 12px; }.admin-chart-axis { width: 44px; height: 200px; position: relative; font-size: 12px; text-align: right; font-variant-numeric: tabular-nums; color: var(--admin-muted); flex-shrink: 0; }.admin-chart-axis span { position: absolute; right: 0; transform: translateY(-50%); }.admin-chart-canvas { min-width: 0; flex: 1; }.admin-chart-canvas svg { width: 100%; height: 200px; display: block; }.admin-chart-grid { stroke: var(--admin-border); stroke-width: 1; }.admin-chart-bar { fill: var(--admin-accent); }.admin-chart-dates { display: flex; justify-content: space-between; font-size: 12px; color: var(--admin-muted); }
.admin-daily-details { margin-top: 16px; font-size: 13px; }.admin-daily-details summary { cursor: pointer; min-height: 44px; padding: 12px 0; }.admin-table-frame { overflow-x: auto; }.admin-table { min-width: 450px; }
</style>
