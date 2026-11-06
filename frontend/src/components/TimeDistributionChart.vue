<script setup>
import { computed } from 'vue';
import { hours } from '../domain/model.js';
const props = defineProps({ mode: String, rows: Array, allocations: Array, total: Number });
const emit = defineEmits(['select']);
const colors = ['#7563c7', '#348d82', '#c08a36', '#527fa9'];
const projects = computed(() => [...new Map(props.allocations.map(item => [item.projectId, item.projectName])).entries()].sort(([a], [b]) => a.localeCompare(b)));
const colorFor = id => colors[Math.max(0, projects.value.findIndex(([key]) => key === id)) % colors.length];
const topRows = computed(() => props.rows.slice(0, 12));
const maximum = computed(() => {
  const largest = Math.max(...props.rows.map(row => row.minutes), 0) / 60;
  const step = largest > 100 ? 50 : largest > 24 ? 10 : largest > 8 ? 5 : largest > 2 ? 1 : .5;
  return Math.max(step, Math.ceil(largest / step) * step) * 60;
});
const memberRows = computed(() => topRows.value.map(row => ({
  ...row,
  segments: props.allocations.filter(item => item.userId === row.id).sort((a, b) => a.projectId.localeCompare(b.projectId)),
})));
const percentage = minutes => props.total ? (minutes / props.total * 100).toFixed(1) : '0.0';
</script>
<template>
  <section class="distribution" :aria-label="mode === 'members' ? '成員跨專案工時分布' : '專案工時比較'">
    <header class="distribution-heading">
      <h2>{{ mode === 'members' ? '跨專案分配' : '專案投入比較' }}</h2>
      <span>{{ rows.length }} {{ mode === 'members' ? '位成員' : '個專案' }}</span>
    </header>
    <template v-if="mode === 'members'">
      <ul class="legend" aria-label="專案圖例">
        <li v-for="[id, name] in projects" :key="id"><i :style="{ background: colorFor(id) }"></i>{{ name }}</li>
      </ul>
      <div v-for="row in memberRows" :key="row.id" class="chart-row member-row">
        <button class="row-name" @click="emit('select', row.id)"><span class="member-initial" aria-hidden="true">{{ row.name.slice(0, 1).toUpperCase() }}</span><span>{{ row.name }}</span></button>
        <div class="bar-track">
          <button
            v-for="segment in row.segments" :key="segment.projectId" class="segment"
            :style="{ width: `${segment.minutes / maximum * 100}%`, '--segment-color': colorFor(segment.projectId) }"
            :aria-label="`${row.name}，${segment.projectName}，${hours(segment.minutes)}，查看工時明細`"
            @click="emit('select', row.id, segment.projectId)"
          ><i class="segment-mark" aria-hidden="true"></i><span class="segment-tip">{{ segment.projectName }} · {{ hours(segment.minutes) }}</span></button>
        </div>
        <span class="row-value">{{ hours(row.minutes) }}<small>{{ percentage(row.minutes) }}%</small></span>
      </div>
      <div class="member-axis" aria-hidden="true"><span>0 h</span><span>{{ hours(maximum / 2) }}</span><span>{{ hours(maximum) }}</span></div>
    </template>
    <div v-else class="project-plot" tabindex="0" role="region" aria-label="專案投入直條圖，可左右捲動">
      <div class="project-axis" aria-hidden="true"><span>{{ hours(maximum) }}</span><span>{{ hours(maximum / 2) }}</span><span>0 h</span></div>
      <div class="project-columns" :style="{ gridTemplateColumns: `repeat(${topRows.length}, minmax(110px, 1fr))` }">
        <button
          v-for="row in topRows" :key="row.id" class="chart-row project-column"
          :aria-label="`${row.name}，${hours(row.minutes)}，查看成員分配`" @click="emit('select', row.id)"
        >
          <span class="row-value">{{ hours(row.minutes) }}</span>
          <span class="project-bar-track"><i class="project-bar" :style="{ height: `${row.minutes / maximum * 100}%` }"></i></span>
          <span class="project-name">{{ row.name }}</span><small class="project-share">{{ percentage(row.minutes) }}%</small>
        </button>
      </div>
    </div>
    <p v-if="rows.length > 12" class="chart-note">顯示投入前 12 項，完整資料見下表</p>
  </section>
</template>
<style scoped>
.distribution { min-width: 0; }
.distribution-heading { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
.distribution-heading h2 { font-size: 16px; font-weight: 600; margin: 0; }
.distribution-heading > span { color: var(--text-secondary, #5f6673); font-size: 13px; white-space: nowrap; }
.legend { display: flex; flex-wrap: wrap; gap: 10px 20px; list-style: none; padding: 0; margin: 0 0 12px; font-size: 13px; color: var(--text-secondary, #5f6673); }
.legend li { display: flex; align-items: center; gap: 7px; }.legend i { width: 8px; height: 8px; border-radius: 2px; }
.member-row { display: grid; grid-template-columns: minmax(110px, 154px) minmax(0, 1fr) 100px; align-items: center; gap: 20px; min-height: 52px; }
.row-name { display: flex; gap: 10px; align-items: center; text-align: left; min-height: 44px; font-size: 14px; overflow-wrap: anywhere; color: var(--text-primary, #242936); }
.row-name:hover { color: var(--accent); }
.member-initial { display: grid; place-items: center; width: 30px; height: 30px; flex-shrink: 0; border-radius: 50%; background: #f0edf9; color: #62528e; font-size: 12px; font-weight: 600; }
.bar-track { display: flex; align-items: center; height: 24px; width: 100%; background: #f2f1f6; border-radius: 4px; min-height: 24px; }
.segment { display: flex; align-items: center; position: relative; height: 44px; padding: 0; min-width: 2px; }
.segment-mark { display: block; width: 100%; height: 24px; background: var(--segment-color); border-right: 2px solid #fff; }
.segment:first-child .segment-mark { border-radius: 4px 0 0 4px; }.segment:last-child .segment-mark { border-radius: 0 4px 4px 0; border-right: 0; }
.segment:only-child .segment-mark { border-radius: 4px; }
.segment-tip { display: none; position: absolute; bottom: calc(100% + 8px); left: 0; background: #242936; color: white; padding: 8px 10px; font-size: 13px; white-space: nowrap; z-index: 2; border-radius: 5px; pointer-events: none; }
.segment:hover .segment-tip, .segment:focus-visible .segment-tip { display: block; }
.segment:focus-visible, .project-column:focus-visible { outline: 2px solid var(--accent); outline-offset: 4px; z-index: 3; }
.row-value { text-align: right; font-size: 17px; font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
.row-value small { display: block; color: var(--text-secondary, #5f6673); font-size: 12px; font-weight: 400; margin-top: 3px; }
.member-axis { display: flex; justify-content: space-between; margin: 12px 120px 0 174px; padding-top: 8px; border-top: 1px solid #e9e7ef; color: var(--text-secondary, #5f6673); font-size: 12px; font-variant-numeric: tabular-nums; }
.project-plot { display: flex; gap: 16px; overflow-x: auto; padding-top: 8px; }
.project-axis { position: relative; flex-shrink: 0; width: 56px; height: 184px; margin-top: 28px; color: var(--text-secondary, #5f6673); font-size: 12px; text-align: right; font-variant-numeric: tabular-nums; }
.project-axis span { position: absolute; right: 0; transform: translateY(-50%); white-space: nowrap; }.project-axis span:first-child { top: 0; }.project-axis span:nth-child(2) { top: 50%; }.project-axis span:last-child { top: 100%; }
.project-columns { display: grid; flex: 1 0 0; min-width: min-content; gap: 20px; }
.project-column { display: grid; grid-template-rows: 28px 184px auto auto; justify-items: center; align-content: start; min-width: 0; padding: 0 10px; border-radius: 4px; }
.project-column .row-value { margin-bottom: 0; align-self: start; }
.project-bar-track { display: flex; align-items: flex-end; justify-content: center; width: 100%; height: 184px; border-bottom: 1px solid #dcd9e7; }
.project-bar { display: block; width: min(58%, 100px); min-height: 2px; background: #8271cd; border-radius: 5px 5px 0 0; }
.project-column:hover .project-bar { background: var(--accent); }
.project-name { font-size: 14px; font-weight: 500; margin-top: 14px; overflow-wrap: anywhere; }
.project-share { color: var(--text-secondary, #5f6673); font-size: 12px; margin-top: 4px; font-variant-numeric: tabular-nums; }
.chart-note { color: var(--text-secondary, #5f6673); font-size: 13px; margin-bottom: 0; }
@media(max-width:600px) {
  .member-row { grid-template-columns: minmax(82px, 100px) minmax(0, 1fr) 72px; gap: 10px; min-height: 62px; }
  .member-initial { display: none; }.row-name { font-size: 13px; }.row-value { font-size: 14px; }
  .member-axis { margin-left: 110px; margin-right: 82px; font-size: 11px; }
  .segment-mark { height: 28px; }.bar-track { height: 28px; }
  .segment-tip { white-space: normal; width: 130px; left: auto; right: 0; }
  .project-axis { width: 45px; }.project-columns { gap: 10px; }
}
</style>
