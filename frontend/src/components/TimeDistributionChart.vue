<script setup>
import { computed } from 'vue';
import { hours } from '../domain/model.js';
const props = defineProps({ mode: String, rows: Array, allocations: Array, total: Number });
const emit = defineEmits(['select']);
const colors = ['#6653bd', '#258579', '#b57a24', '#3c78b4', '#ae5681', '#65723e'];
const projects = computed(() => [...new Map(props.allocations.map(item => [item.projectId, item.projectName])).entries()].sort(([a], [b]) => a.localeCompare(b)));
const colorFor = id => colors[Math.max(0, projects.value.findIndex(([key]) => key === id)) % colors.length];
const topRows = computed(() => props.rows.slice(0, 12));
const maximum = computed(() => Math.max(1, ...props.rows.map(row => row.minutes)));
const segments = row => props.allocations.filter(item => item.userId === row.id).sort((a, b) => a.projectId.localeCompare(b.projectId));
</script>
<template>
  <section class="distribution" :aria-label="mode === 'members' ? '成員跨專案工時分布' : '專案工時比較'">
    <h2>{{ mode === 'members' ? '成員的專案分配' : '專案投入比較' }}</h2>
    <ul v-if="mode === 'members'" class="legend" aria-label="專案圖例"><li v-for="[id, name] in projects" :key="id"><i :style="{ background: colorFor(id) }"></i>{{ name }}</li></ul>
    <div v-for="row in topRows" :key="row.id" class="chart-row">
      <button class="row-name" @click="emit('select', row.id)">{{ row.name }}</button>
      <div class="bar-track">
        <template v-if="mode === 'members'">
          <button v-for="segment in segments(row)" :key="segment.projectId" class="segment" :style="{ width: `${segment.minutes / maximum * 100}%`, background: colorFor(segment.projectId) }" :aria-label="`${row.name}，${segment.projectName}，${hours(segment.minutes)}，查看工時明細`" @click="emit('select', row.id, segment.projectId)"><span class="segment-tip">{{ segment.projectName }} · {{ hours(segment.minutes) }}</span></button>
        </template>
        <button v-else class="segment" :style="{ width: `${row.minutes / maximum * 100}%`, background: colorFor(row.id) }" :aria-label="`${row.name}，${hours(row.minutes)}，查看成員分配`" @click="emit('select', row.id)"><span class="segment-tip">{{ row.name }} · {{ hours(row.minutes) }}</span></button>
      </div>
      <span class="row-value">{{ hours(row.minutes) }}<small>{{ total ? (row.minutes / total * 100).toFixed(1) : 0 }}%</small></span>
    </div>
    <p v-if="rows.length > 12" class="chart-note">顯示投入前 12 項，完整資料見下表</p>
  </section>
</template>
<style scoped>
.distribution { margin-bottom: 32px; }
.distribution h2 { font-size: 18px; margin: 0 0 18px; }
.legend { display: flex; flex-wrap: wrap; gap: 12px 20px; list-style: none; padding: 0; margin: 0 0 20px; font-size: 13px; }
.legend li { display: flex; align-items: center; gap: 7px; }.legend i { width: 10px; height: 10px; border-radius: 2px; }
.chart-row { display: grid; grid-template-columns: minmax(100px, 180px) minmax(0, 1fr) 90px; align-items: center; gap: 16px; margin: 10px 0; }
.row-name { text-align: left; min-height: 44px; font-size: 14px; overflow-wrap: anywhere; color: #473594; text-decoration: underline; text-underline-offset: 3px; }
.bar-track { display: flex; width: 100%; background: #f4f4f7; border-radius: 3px; }
.segment { display: block; position: relative; height: 32px; padding: 0; min-width: 2px; border-right: 1px solid white; }
.segment-tip { display: none; position: absolute; bottom: calc(100% + 5px); left: 0; background: #242936; color: white; padding: 7px 10px; font-size: 13px; white-space: nowrap; z-index: 2; border-radius: 4px; pointer-events: none; }
.segment:hover .segment-tip, .segment:focus-visible .segment-tip { display: block; }
.segment:focus-visible { outline: 2px solid #242936; outline-offset: 3px; z-index: 3; }
.row-value { text-align: right; font-size: 14px; font-variant-numeric: tabular-nums; }.row-value small { display: block; color: #5f6673; font-size: 12px; margin-top: 3px; }.chart-note { color: #5f6673; font-size: 13px; }
@media(max-width:600px) { .chart-row { grid-template-columns: minmax(72px, 95px) minmax(0, 1fr) 66px; gap: 8px; }.segment-tip { white-space: normal; width: 140px; left: auto; right: 0; }.segment { height: 36px; } }
</style>
