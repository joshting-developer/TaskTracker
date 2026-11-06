<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { api } from '../api/index.js';
import { dateInTaipei, hours } from '../domain/model.js';
import Icon from './Icon.vue';
const props = defineProps({ mode: { type: String, default: 'index' }, workspaceVersion: Object });
const emit = defineEmits(['navigate']);
const analyses = [
  { id: 'members', title: '成員投入' },
  { id: 'projects', title: '專案投入' },
  { id: 'trend', title: '時間趨勢' },
];
const selected = computed(() => analyses.find(item => item.id === props.mode));
const today = dateInTaipei();
const startDate = ref(`${today.slice(0, 7)}-01`), endDate = ref(today), projectId = ref(''), userId = ref('');
const report = ref(null), options = ref({ members: [], projects: [] }), loading = ref(false), error = ref('');
let generation = 0, applied = null;
const rows = computed(() => report.value?.[props.mode] || []);
const total = computed(() => report.value?.summary.totalMinutes || 0);
const maxDay = computed(() => Math.max(1, ...(report.value?.days || []).map(day => day.minutes)));
const points = computed(() => (report.value?.days || []).map((day, index, days) => `${days.length === 1 ? 450 : 20 + index / (days.length - 1) * 860},${180 - day.minutes / maxDay.value * 150}`).join(' '));
const pendingFilters = computed(() => report.value && [startDate.value, endDate.value, projectId.value, userId.value].join('|') !== [report.value.range.startDate, report.value.range.endDate, report.value.range.projectId, report.value.range.userId].join('|'));
const share = minutes => total.value ? `${(minutes / total.value * 100).toFixed(1)}%` : '0%';
async function load(useDraft = true) {
  if (!selected.value || (!useDraft && loading.value)) return;
  const current = ++generation;
  loading.value = true; error.value = ''; report.value = null;
  try {
    const input = useDraft || !applied ? { startDate: startDate.value, endDate: endDate.value, projectId: projectId.value, userId: userId.value } : applied;
    const result = await api.getTimeReport(input);
    if (current !== generation) return;
    report.value = result; options.value = result.options;
    applied = { startDate: result.range.startDate, endDate: result.range.endDate, projectId: result.range.projectId, userId: result.range.userId };
  } catch (failure) { if (current === generation) { error.value = failure.message; if (['FORBIDDEN', 'UNAUTHENTICATED'].includes(failure.code)) options.value = { members: [], projects: [] }; } }
  finally { if (current === generation) loading.value = false; }
}
watch(() => [props.mode, props.workspaceVersion], () => {
  if (selected.value) load(false);
  else { generation++; report.value = null; loading.value = false; error.value = ''; }
}, { immediate: true });
onBeforeUnmount(() => { generation++; });
</script>
<template>
  <section class="time-analysis" aria-label="工時分析">
    <template v-if="!selected">
      <header class="analysis-heading"><h1>工時分析</h1></header>
      <div class="analysis-index">
        <button v-for="analysis in analyses" :key="analysis.id" class="analysis-choice" @click="emit('navigate', analysis.id)"><span><strong>{{ analysis.title }}</strong></span><Icon name="chevron" :size="20" /></button>
      </div>
    </template>
    <template v-else>
      <button class="text-button analysis-back" @click="emit('navigate', 'index')"><Icon name="chevron" :size="16" />返回工時分析</button>
      <header class="analysis-heading"><h1>{{ selected.title }}</h1></header>
      <form class="analysis-filters" @submit.prevent="load">
        <label class="field">開始日期<input v-model="startDate" aria-label="分析開始日期" type="date" min="1000-01-01" max="9999-12-31" required /></label>
        <label class="field">結束日期<input v-model="endDate" aria-label="分析結束日期" type="date" :min="startDate" max="9999-12-31" required /></label>
        <label class="field">專案<select v-model="projectId" aria-label="分析專案"><option value="">所有專案</option><option v-for="project in options.projects" :key="project.id" :value="project.id">{{ project.name }}</option></select></label>
        <label class="field">成員<select v-model="userId" aria-label="分析成員"><option value="">所有成員</option><option v-for="member in options.members" :key="member.id" :value="member.id">{{ member.name }}</option></select></label>
        <button class="button primary" :disabled="loading">{{ loading ? '分析中…' : '套用範圍' }}</button>

      </form>
      <p v-if="loading" role="status">正在整理工時…</p>
      <div v-else-if="error" class="error-box" role="alert">{{ error }}<button class="text-button" @click="load()">重試</button></div>
      <template v-else-if="report">
        <p v-if="pendingFilters" class="analysis-pending" role="status">篩選已調整，請按「套用範圍」更新報表。</p>
        <p class="analysis-period">{{ report.range.startDate }} — {{ report.range.endDate }}</p>
        <dl class="analysis-totals"><div><dt>實際投入</dt><dd>{{ hours(total) }}</dd></div><div><dt>工時紀錄</dt><dd>{{ report.summary.entryCount }} 筆</dd></div><div><dt>有填報的日期</dt><dd>{{ report.summary.activeDays }} 天</dd></div></dl>
        <p v-if="!report.summary.entryCount" class="analysis-empty" role="status">尚無符合條件的工時紀錄</p>
        <template v-else>
          <div v-if="mode === 'trend'" class="trend-chart"><h2>每日實際投入</h2><p class="trend-limit">{{ hours(maxDay) }}</p><svg viewBox="0 0 900 190" preserveAspectRatio="none" role="img" :aria-label="`${report.range.startDate}至${report.range.endDate}每日工時趨勢，最高${hours(maxDay)}，詳細數值見下表`"><line x1="20" y1="180" x2="880" y2="180" stroke="currentColor" opacity=".25" /><polyline v-if="report.days.length > 1" :points="points" fill="none" stroke="var(--accent)" stroke-width="3" vector-effect="non-scaling-stroke" /><circle v-else cx="450" :cy="180 - report.days[0].minutes / maxDay * 150" r="5" fill="var(--accent)" /></svg><div class="trend-labels"><span>{{ report.range.startDate }}</span><span>{{ report.range.endDate }}</span></div></div>
          <div class="analysis-table-scroll" tabindex="0" role="region" aria-label="工時明細，可左右捲動"><table class="analysis-table"><caption>{{ mode === 'trend' ? '每日工時明細（含未填報日期）' : `${selected.title}明細` }}</caption><thead><tr><th scope="col">{{ mode === 'members' ? '成員' : mode === 'projects' ? '專案' : '工作日期' }}</th><th scope="col">實際投入</th><th scope="col">紀錄數</th><th v-if="mode !== 'trend'" scope="col">{{ mode === 'members' ? '參與專案' : '投入成員' }}</th><th v-if="mode !== 'trend'" scope="col">投入占比</th></tr></thead><tbody><tr v-for="row in mode === 'trend' ? report.days : rows" :key="row.id || row.date"><th scope="row">{{ row.name || row.date }}<span v-if="row.code" class="analysis-code">{{ row.code }}</span></th><td>{{ hours(row.minutes) }}</td><td>{{ row.entryCount }}</td><td v-if="mode !== 'trend'">{{ mode === 'members' ? row.projectCount : row.memberCount }}</td><td v-if="mode !== 'trend'" class="analysis-share"><span>{{ share(row.minutes) }}</span><span class="analysis-bar"><i :style="{ width: `${total ? row.minutes / total * 100 : 0}%` }"></i></span></td></tr></tbody><tfoot><tr><th scope="row">總計</th><td>{{ hours(total) }}</td><td>{{ report.summary.entryCount }}</td><td v-if="mode !== 'trend'"></td><td v-if="mode !== 'trend'">100%</td></tr></tfoot></table></div>
        </template>

      </template>
    </template>
  </section>
</template>
<style scoped>
.time-analysis { color: var(--text-primary, #242936); }
.analysis-heading { margin-bottom: 24px; }
.analysis-heading h1 { font-size: 26px; line-height: 1.35; margin: 0; }
.analysis-period { color: var(--text-secondary, #5f6673); font-size: 14px; line-height: 1.7; }
.analysis-index { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
.analysis-choice { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; text-align: left; padding: 24px; min-height: 100px; border: 1px solid #daddE5; border-radius: 8px; background: #fff; }
.analysis-choice:hover { background: #f6f5fa; }
.analysis-choice strong { display: block; font-size: 20px; margin-bottom: 0; }
.analysis-choice svg { flex-shrink: 0; align-self: center; }
.analysis-back { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; margin-bottom: 12px; }
.analysis-back svg { transform: rotate(180deg); }
.analysis-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 14px; margin-bottom: 24px; }
.analysis-filters .field { flex: 1 1 160px; margin: 0; font-size: 14px; }
.analysis-filters input, .analysis-filters select { min-height: 44px; font-size: 14px; }
.analysis-filters button { min-height: 44px; }
.analysis-period { margin: 0 0 12px; }
.analysis-totals { display: flex; flex-wrap: wrap; gap: 36px; padding: 20px 0; border-top: 1px solid #daddE5; border-bottom: 1px solid #daddE5; margin: 0 0 28px; }
.analysis-totals dt { font-size: 13px; color: var(--text-secondary, #5f6673); }
.analysis-totals dd { margin: 8px 0 0; font-size: 24px; font-variant-numeric: tabular-nums; }
.analysis-table-scroll { overflow-x: auto; }
.analysis-table-scroll:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }
.analysis-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.analysis-table caption { text-align: left; font-size: 16px; font-weight: 600; padding: 0 0 16px; }
.analysis-table th, .analysis-table td { padding: 16px 12px; border-bottom: 1px solid #e5e7ed; text-align: right; font-variant-numeric: tabular-nums; }
.analysis-table th:first-child { text-align: left; overflow-wrap: anywhere; min-width: 100px; }
.analysis-table thead th { color: var(--text-secondary, #5f6673); font-size: 13px; font-weight: 500; white-space: nowrap; }
.analysis-table tfoot { font-weight: 600; }
.analysis-code { display: block; margin-top: 4px; font-size: 12px; font-weight: 400; color: var(--text-secondary, #5f6673); }
.analysis-share { min-width: 120px; }
.analysis-bar { display: block; height: 5px; background: #eeedf3; margin-top: 8px; }
.analysis-bar i { display: block; height: 100%; background: var(--accent); }
.analysis-empty { padding: 32px 0; font-size: 16px; line-height: 1.7; }
.analysis-pending { color: #805a13; font-size: 14px; }
.trend-chart { margin-bottom: 24px; }
.trend-chart h2 { font-size: 18px; }
.trend-chart svg { width: 100%; height: 200px; display: block; color: var(--text-secondary, #5f6673); }
.trend-limit, .trend-labels { font-size: 13px; color: var(--text-secondary, #5f6673); }.trend-limit { margin: 12px 0 0; }.trend-labels { display: flex; justify-content: space-between; gap: 12px; }
@media(max-width:900px) { .analysis-index { grid-template-columns: 1fr; }.analysis-choice { min-height: 110px; padding: 22px; } }
@media(max-width:600px) { .analysis-heading { margin-bottom: 24px; }
.analysis-heading h1 { font-size: 24px; }.analysis-totals { gap: 24px; }.analysis-totals dd { font-size: 22px; }.analysis-filters .field { flex-basis: calc(50% - 14px); min-width: 0; }.analysis-filters button { width: 100%; }.analysis-table th, .analysis-table td { padding: 14px 8px; }.analysis-table { min-width: 480px; } }
</style>
