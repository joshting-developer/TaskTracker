<script setup>
import { computed, shallowRef } from 'vue';
import { hours } from '../domain/model.js';
const props = defineProps({ days: { type: Array, required: true } });
const selectedDate = shallowRef(props.days.at(-1)?.date || '');
const maximum = computed(() => Math.max(60, Math.ceil(Math.max(...props.days.map(day => day.minutes), 0) / 60) * 60));
const points = computed(() => props.days.map((day, index) => ({
  ...day, x: props.days.length === 1 ? 400 : index / (props.days.length - 1) * 800,
  y: 180 - day.minutes / maximum.value * 160,
})));
const selected = computed(() => points.value.find(day => day.date === selectedDate.value) || points.value.at(-1));
const line = computed(() => points.value.map(day => `${day.x},${day.y}`).join(' '));
const area = computed(() => `0,180 ${line.value} 800,180`);
const peak = computed(() => props.days.reduce((best, day) => day.minutes > best.minutes ? day : best, props.days[0]));
const middleDate = computed(() => props.days[Math.floor((props.days.length - 1) / 2)]?.date);
const shortDate = date => date ? `${Number(date.slice(5, 7))}/${Number(date.slice(8))}` : '';
function selectPoint(event) {
  if (!props.days.length) return;
  const bounds = event.currentTarget.getBoundingClientRect();
  const ratio = Math.max(0, Math.min(1, (event.clientX - bounds.left) / bounds.width));
  selectedDate.value = props.days[Math.round(ratio * (props.days.length - 1))].date;
}
</script>
<template>
  <section class="trend-chart">
    <div class="chart-heading"><h2>每日實際投入</h2><span>最高 {{ peak?.date }} · {{ hours(peak?.minutes || 0) }}</span></div>
    <div class="trend-selection">
      <output aria-live="polite"><strong>{{ hours(selected?.minutes || 0) }}</strong><span>{{ selected?.date }} · {{ selected?.entryCount || 0 }} 筆</span></output>
      <label class="point-picker">工作日期<select v-model="selectedDate" aria-label="圖表工作日期"><option v-for="day in days" :key="day.date" :value="day.date">{{ day.date }}</option></select></label>
    </div>
    <div class="trend-frame">
      <div class="trend-axis" aria-hidden="true"><span>{{ hours(maximum) }}</span><span>{{ hours(maximum / 2) }}</span><span>0 h</span></div>
      <div class="trend-canvas">
        <div class="trend-plot" tabindex="0" role="region" aria-label="每日趨勢圖">
          <svg viewBox="0 0 800 200" preserveAspectRatio="none" role="img" aria-label="每日工時趨勢；含零日期，詳細數值見下表" @pointermove="selectPoint" @click="selectPoint">
            <line v-for="y in [20, 100, 180]" :key="y" x1="0" :y1="y" x2="800" :y2="y" class="plot-grid" vector-effect="non-scaling-stroke" />
            <polygon v-if="points.length > 1" :points="area" class="plot-area" />
            <polyline v-if="points.length > 1" :points="line" class="plot-line" vector-effect="non-scaling-stroke" />
            <line v-if="selected" :x1="selected.x" :y1="20" :x2="selected.x" y2="180" class="plot-guide" vector-effect="non-scaling-stroke" />
          </svg>
          <i v-if="selected" class="plot-marker" :style="{ left: `${selected.x / 8}%`, top: `${selected.y / 2}%` }" aria-hidden="true"></i>
        </div>
        <div class="trend-dates" aria-hidden="true"><span>{{ shortDate(days[0]?.date) }}</span><span v-if="days.length > 2">{{ shortDate(middleDate) }}</span><span v-if="days.length > 1">{{ shortDate(days.at(-1)?.date) }}</span></div>
      </div>
    </div>
  </section>
</template>
<style scoped>
.trend-chart { min-width: 0; }
.chart-heading { display: flex; justify-content: space-between; flex-wrap: wrap; align-items: baseline; gap: 8px; }
.chart-heading h2 { font-size: 16px; font-weight: 600; margin: 0; }.chart-heading span { color: var(--text-secondary, #5f6673); font-size: 12px; }
.trend-selection { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; margin: 20px 0 4px; }
.trend-selection output { display: flex; align-items: baseline; flex-wrap: wrap; gap: 12px; font-variant-numeric: tabular-nums; }
.trend-selection output strong { font-size: 26px; font-weight: 600; letter-spacing: -.5px; }.trend-selection output span { color: var(--text-secondary, #5f6673); font-size: 13px; }
.point-picker { display: flex; gap: 8px; align-items: center; color: var(--text-secondary, #5f6673); font-size: 13px; }
.point-picker select { min-height: 44px; padding: 8px 10px; border: 1px solid #ddd9e8; border-radius: 6px; color: var(--text-primary, #242936); background: #fff; }
.trend-frame { display: flex; gap: 16px; }
.trend-axis { display: flex; flex-direction: column; justify-content: space-between; padding: 14px 0; width: 56px; height: 220px; flex-shrink: 0; font-size: 12px; text-align: right; color: var(--text-secondary, #5f6673); font-variant-numeric: tabular-nums; }
.trend-canvas { min-width: 0; flex: 1; }
.trend-plot { position: relative; height: 220px; }
.trend-plot svg { display: block; width: 100%; height: 100%; overflow: visible; }
.plot-grid { stroke: #e9e6ef; stroke-width: 1; }.plot-area { fill: var(--accent); fill-opacity: .07; }
.plot-line { fill: none; stroke: var(--accent); stroke-width: 2.5; stroke-linejoin: round; }
.plot-guide { stroke: #9a90bd; stroke-width: 1; stroke-dasharray: 4 4; }
.plot-marker { position: absolute; width: 9px; height: 9px; border: 2px solid #fff; background: var(--accent); border-radius: 50%; transform: translate(-50%, -50%); outline: 1px solid var(--accent); pointer-events: none; }
.trend-dates { display: flex; justify-content: space-between; font-size: 12px; color: var(--text-secondary, #5f6673); font-variant-numeric: tabular-nums; }
.trend-plot:focus-visible { outline: 2px solid var(--accent); outline-offset: 4px; }
@media(max-width:600px) {
  .trend-axis { width: 42px; }.trend-frame { gap: 10px; }.trend-selection { margin-top: 16px; gap: 12px; }
  .point-picker { width: 100%; justify-content: space-between; }.point-picker select { flex: 1; max-width: 190px; }
  .trend-selection output strong { font-size: 24px; }.chart-heading span { line-height: 1.6; }
}
</style>
