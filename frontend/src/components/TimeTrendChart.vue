<script setup>
import { computed, shallowRef } from 'vue';
import { hours } from '../domain/model.js';
const props = defineProps({ days: Array });
const selectedDate = shallowRef('');
const max = computed(() => Math.max(60, Math.ceil(Math.max(...props.days.map(day => day.minutes), 0) / 60) * 60));
const points = computed(() => props.days.map((day, index) => ({ ...day, x: props.days.length === 1 ? 470 : 60 + index / (props.days.length - 1) * 800, y: 200 - day.minutes / max.value * 160 })));
const selected = computed(() => points.value.find(day => day.date === selectedDate.value) || points.value.at(-1));
const line = computed(() => points.value.map(day => `${day.x},${day.y}`).join(' '));
const peak = computed(() => props.days.reduce((best, day) => day.minutes > best.minutes ? day : best, props.days[0]));
</script>
<template>
  <section class="trend-chart">
    <div class="chart-heading"><h2>每日實際投入</h2><span>最高 {{ peak?.date }} · {{ hours(peak?.minutes || 0) }}</span></div>
    <div class="trend-plot" tabindex="0" role="region" aria-label="每日趨勢圖，可左右捲動"><svg viewBox="0 0 900 240" role="img" aria-label="每日工時趨勢；含零日期，詳細數值見下表">
      <g v-for="ratio in [0, .5, 1]" :key="ratio"><line x1="60" :y1="200 - ratio * 160" x2="860" :y2="200 - ratio * 160" stroke="#daddE5" /><text x="48" :y="205 - ratio * 160" text-anchor="end">{{ hours(max * ratio) }}</text></g>
      <polyline v-if="points.length > 1" :points="line" fill="none" stroke="var(--accent)" stroke-width="3" vector-effect="non-scaling-stroke" />
      <circle v-if="selected" :cx="selected.x" :cy="selected.y" r="5" fill="var(--accent)" />
      <text x="60" y="230">{{ days[0]?.date }}</text><text v-if="days.length > 1" x="860" y="230" text-anchor="end">{{ days.at(-1)?.date }}</text>
    </svg></div>
    <label class="point-picker">工作日期<select v-model="selectedDate" aria-label="圖表工作日期"><option value="">{{ days.at(-1)?.date }}</option><option v-for="day in days" :key="day.date" :value="day.date">{{ day.date }}</option></select><output aria-live="polite">{{ selected?.date }} · {{ hours(selected?.minutes || 0) }} · {{ selected?.entryCount || 0 }} 筆</output></label>
  </section>
</template>
<style scoped>
.trend-chart { margin-bottom: 32px; }.chart-heading { display: flex; justify-content: space-between; flex-wrap: wrap; align-items: center; gap: 8px; }.chart-heading h2 { font-size: 18px; margin: 0; }.chart-heading span { color: #5f6673; font-size: 13px; }
.trend-plot { overflow-x: auto; }
.trend-chart svg { min-width: 600px; width: 100%; min-height: 180px; display: block; margin: 16px 0; }.trend-chart text { fill: #454b5c; font-size: 13px; }
.point-picker { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; font-size: 14px; }.point-picker select { min-height: 44px; padding: 8px; border: 1px solid #bfc5d4; border-radius: 5px; }.point-picker output { font-variant-numeric: tabular-nums; }
@media(max-width:600px) { .trend-chart svg { min-height: 160px; }.point-picker output { flex-basis: 100%; } }
</style>
