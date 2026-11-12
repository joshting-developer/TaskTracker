<script setup>
import { computed, onBeforeUnmount, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import { dateInTaipei, hours } from '../domain/model.js';
import Icon from './Icon.vue';
import TimeDistributionChart from './TimeDistributionChart.vue';
import TimeTrendChart from './TimeTrendChart.vue';
import TimeMemberDetails from './TimeMemberDetails.vue';
import ReportPagination from './ReportPagination.vue';
const props = defineProps({ mode: { type: String, default: 'index' }, workspaceVersion: { type: String, default: '' } });
const emit = defineEmits(['navigate']);
const analyses = [
  { id: 'members', title: '成員投入' },
  { id: 'projects', title: '專案投入' },
  { id: 'trend', title: '時間趨勢' },
];
const selected = computed(() => analyses.find(item => item.id === props.mode));
const today = dateInTaipei();
const startDate = shallowRef(`${today.slice(0, 7)}-01`), endDate = shallowRef(today), projectId = shallowRef(''), userId = shallowRef('');
const report = shallowRef(null), options = shallowRef({ members: [], projects: [] }), loading = shallowRef(false), error = shallowRef('');
const memberDetail = shallowRef(null);
const tablePage = shallowRef(1);
let generation = 0, applied = null;
const rows = computed(() => report.value?.[props.mode] || []);
const allTableRows = computed(() => props.mode === 'trend' ? report.value?.days || [] : rows.value);
const tablePagination = computed(() => {
  const total = allTableRows.value.length;
  const lastPage = Math.max(1, Math.ceil(total / 20));
  return { total, lastPage, page: Math.min(tablePage.value, lastPage), perPage: 20 };
});
const tableRows = computed(() => allTableRows.value.slice((tablePagination.value.page - 1) * 20, tablePagination.value.page * 20));
const total = computed(() => report.value?.summary.totalMinutes || 0);
const pendingFilters = computed(() => report.value && [startDate.value, endDate.value, projectId.value, userId.value].join('|') !== [report.value.range.startDate, report.value.range.endDate, report.value.range.projectId, report.value.range.userId].join('|'));
const share = minutes => total.value ? `${(minutes / total.value * 100).toFixed(1)}%` : '0%';
async function load(useDraft = true) {
  if (!selected.value || (!useDraft && loading.value)) return;
  const current = ++generation;
  if (useDraft) tablePage.value = 1;
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
async function drill(id, selectedProjectId = '') {
  if (!report.value || loading.value) return;
  if (props.mode === 'members') {
    const range = { ...report.value.range, projectId: selectedProjectId || report.value.range.projectId };
    memberDetail.value = {
      id, range,
      name: options.value.members.find(member => member.id === id)?.name || '歷史成員',
      projectName: options.value.projects.find(project => project.id === range.projectId)?.name || '',
    };
    return;
  } else {
    startDate.value = report.value.range.startDate; endDate.value = report.value.range.endDate;
    projectId.value = id; userId.value = report.value.range.userId;
  }
  await load();
  if (report.value) emit('navigate', props.mode === 'members' ? 'projects' : 'members');
}
function backFromMember() {
  memberDetail.value = null;
  load(false);
}
function preset(recent = false) {
  const date = new Date(`${today}T12:00:00Z`);
  if (recent) date.setUTCDate(date.getUTCDate() - 27);
  startDate.value = recent ? date.toISOString().slice(0, 10) : `${today.slice(0, 7)}-01`;
  endDate.value = today;
  load();
}
watch(() => props.mode, () => { memberDetail.value = null; tablePage.value = 1; });
watch(() => [props.mode, props.workspaceVersion], () => {
  if (selected.value && !memberDetail.value) load(false);
  else if (memberDetail.value) return;
  else { generation++; report.value = null; loading.value = false; error.value = ''; }
}, { immediate: true });
onBeforeUnmount(() => { generation++; });
</script>
<template>
  <section class="time-analysis" aria-label="工時分析">
    <template v-if="!selected">
      <header class="analysis-heading index-heading"><h1>工時分析</h1></header>
      <div class="analysis-index">
        <button v-for="(analysis, index) in analyses" :key="analysis.id" class="analysis-choice" @click="emit('navigate', analysis.id)">
          <span class="choice-top" aria-hidden="true"><span class="choice-number">0{{ index + 1 }}</span><Icon name="arrow" :size="18" /></span>
          <svg class="choice-preview" viewBox="0 0 240 90" aria-hidden="true">
            <template v-if="analysis.id === 'members'">
              <rect x="8" y="12" width="150" height="12" rx="3" class="preview-primary" /><rect x="158" y="12" width="65" height="12" rx="3" class="preview-secondary" />
              <rect x="8" y="40" width="92" height="12" rx="3" class="preview-primary" /><rect x="100" y="40" width="80" height="12" rx="3" class="preview-secondary" />
              <rect x="8" y="68" width="60" height="12" rx="3" class="preview-primary" /><rect x="68" y="68" width="60" height="12" rx="3" class="preview-secondary" />
            </template>
            <template v-else-if="analysis.id === 'projects'">
              <line x1="8" y1="84" x2="232" y2="84" class="preview-rule" /><rect x="34" y="12" width="38" height="72" rx="3" class="preview-primary" /><rect x="103" y="36" width="38" height="48" rx="3" class="preview-muted" /><rect x="172" y="52" width="38" height="32" rx="3" class="preview-primary" />
            </template>
            <template v-else>
              <line x1="8" y1="28" x2="232" y2="28" class="preview-rule" /><line x1="8" y1="66" x2="232" y2="66" class="preview-rule" />
              <polyline points="8,66 42,48 74,58 108,24 140,40 172,16 202,32 232,12" class="preview-line" />
            </template>
          </svg>
          <span class="choice-title">{{ analysis.title }}<Icon name="chevron" :size="17" /></span>
        </button>
      </div>
    </template>
    <TimeMemberDetails v-else-if="memberDetail" :member="memberDetail" :workspace-version="workspaceVersion" @back="backFromMember" />
    <template v-else>
      <button class="navigation-back analysis-back" @click="emit('navigate', 'index')"><Icon name="chevron" :size="15" />返回工時分析</button>
      <header class="analysis-heading">
        <h1>{{ selected.title }}</h1>
        <div class="analysis-presets"><button class="button secondary" :disabled="loading" @click="preset()">本月</button><button class="button secondary" :disabled="loading" @click="preset(true)">近 28 天</button></div>
      </header>
      <nav class="analysis-modes" aria-label="分析方式"><button v-for="analysis in analyses" :key="analysis.id" :aria-current="mode === analysis.id ? 'page' : undefined" :disabled="loading" @click="analysis.id !== mode && emit('navigate', analysis.id)">{{ analysis.title }}</button></nav>
      <form class="analysis-filters" @submit.prevent="load">
        <label class="field">開始日期<input v-model="startDate" aria-label="分析開始日期" type="date" min="1000-01-01" max="9999-12-31" required /></label>
        <span class="range-divider" aria-hidden="true">—</span>
        <label class="field">結束日期<input v-model="endDate" aria-label="分析結束日期" type="date" :min="startDate" max="9999-12-31" required /></label>
        <label class="field">專案<select v-model="projectId" aria-label="分析專案"><option value="">所有專案</option><option v-for="project in options.projects" :key="project.id" :value="project.id">{{ project.name }}</option></select></label>
        <label class="field">成員<select v-model="userId" aria-label="分析成員"><option value="">所有成員</option><option v-for="member in options.members" :key="member.id" :value="member.id">{{ member.name }}</option></select></label>
        <button class="button primary" :disabled="loading">{{ loading ? '分析中…' : '套用範圍' }}</button>
      </form>
      <p v-if="loading" class="analysis-loading" role="status">正在整理工時…</p>
      <div v-else-if="error" class="error-box" role="alert">{{ error }}<button class="text-button" @click="load()">重試</button></div>
      <template v-else-if="report">
        <p v-if="pendingFilters" class="analysis-pending" role="status">篩選已調整，請按「套用範圍」更新報表。</p>
        <div class="analysis-report-heading"><p class="analysis-period">{{ report.range.startDate }} — {{ report.range.endDate }}</p></div>
        <dl class="analysis-totals"><div><dt>實際投入</dt><dd>{{ hours(total) }}</dd></div><div><dt>工時紀錄</dt><dd>{{ report.summary.entryCount }} <small>筆</small></dd></div><div><dt>填報天數</dt><dd>{{ report.summary.activeDays }} <small>天</small></dd></div></dl>
        <p v-if="!report.summary.entryCount" class="analysis-empty" role="status">尚無符合條件的工時紀錄</p>
        <template v-else>
          <div class="analysis-chart-panel">
            <TimeTrendChart v-if="mode === 'trend'" :days="report.days" />
            <TimeDistributionChart v-else :mode="mode" :rows="rows" :allocations="report.allocations || []" :total="total" @select="drill" />
          </div>
          <div class="analysis-table-scroll" tabindex="0" role="region" aria-label="工時明細，可左右捲動">
            <table class="analysis-table">
              <caption>{{ mode === 'trend' ? '每日工時明細' : `${selected.title}明細` }}</caption>
              <thead><tr><th scope="col">{{ mode === 'members' ? '成員' : mode === 'projects' ? '專案' : '工作日期' }}</th><th scope="col">實際投入</th><th scope="col">紀錄數</th><th v-if="mode !== 'trend'" scope="col">{{ mode === 'members' ? '參與專案' : '投入成員' }}</th><th v-if="mode !== 'trend'" scope="col">投入占比</th></tr></thead>
              <tbody>
                <tr v-for="row in tableRows" :key="row.id || row.date">
                  <th scope="row"><button v-if="mode !== 'trend'" class="text-button" @click="drill(row.id)">{{ row.name }}</button><template v-else>{{ row.date }}</template><span v-if="row.code" class="analysis-code">{{ row.code }}</span></th>
                  <td>{{ hours(row.minutes) }}</td><td>{{ row.entryCount }}</td><td v-if="mode !== 'trend'">{{ mode === 'members' ? row.projectCount : row.memberCount }}</td>
                  <td v-if="mode !== 'trend'" class="analysis-share"><span>{{ share(row.minutes) }}</span><span class="analysis-bar"><i :style="{ width: `${total ? row.minutes / total * 100 : 0}%` }"></i></span></td>
                </tr>
              </tbody>
              <tfoot><tr><th scope="row">總計</th><td>{{ hours(total) }}</td><td>{{ report.summary.entryCount }}</td><td v-if="mode !== 'trend'"></td><td v-if="mode !== 'trend'">100%</td></tr></tfoot>
            </table>
          </div>
          <ReportPagination :pagination="tablePagination" label="分析明細分頁" :disabled="loading" @change="tablePage = $event" />
        </template>
      </template>
    </template>
  </section>
</template>
<style scoped>
.time-analysis { --analysis-line: #e5e2ed; --analysis-muted: #646879; color: var(--text-primary, #242936); }
.analysis-heading { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; }
.analysis-heading h1 { font-size: 26px; line-height: 1.35; letter-spacing: -.5px; margin: 0; font-weight: 650; }
.index-heading { padding: 12px 0 8px; }
.analysis-index { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }
.analysis-choice { display: flex; flex-direction: column; gap: 32px; text-align: left; padding: 22px; border: 1px solid var(--analysis-line); border-radius: 10px; background: #fff; transition: border-color .15s, background .15s; min-width: 0; }
.analysis-choice:hover { background: #faf9fd; border-color: #a89bcc; }.analysis-choice:focus-visible { outline: 2px solid var(--accent); outline-offset: 4px; }
.choice-top, .choice-title { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.choice-top { color: #7e788f; }.choice-number { font-size: 12px; font-variant-numeric: tabular-nums; }
.choice-title { font-size: 19px; font-weight: 600; }.choice-title svg { color: #898297; }
.choice-preview { width: 100%; height: 90px; }.preview-primary { fill: #8c7cd2; }.preview-secondary { fill: #76b2a9; }.preview-muted { fill: #b6a9e3; }.preview-rule { stroke: #ebe8f2; stroke-width: 1; }.preview-line { fill: none; stroke: #8c7cd2; stroke-width: 3; }
.analysis-back { margin-bottom: 4px; }
.analysis-presets { display: flex; border: 1px solid var(--analysis-line); border-radius: 7px; padding: 3px; gap: 2px; background: #faf9fc; }
.analysis-presets .button { border: 0; background: transparent; min-height: 36px; padding: 6px 12px; font-size: 13px; }
.analysis-presets .button:hover { background: #efecf7; }
.analysis-modes { display: flex; gap: 26px; border-bottom: 1px solid var(--analysis-line); margin-bottom: 20px; }
.analysis-modes button { min-height: 44px; padding: 0 2px 12px; border-bottom: 2px solid transparent; margin-bottom: -1px; font-size: 14px; color: var(--analysis-muted); }
.analysis-modes button[aria-current="page"] { color: var(--accent); font-weight: 600; border-bottom-color: var(--accent); }
.analysis-filters { display: grid; grid-template-columns: minmax(140px, 1fr) 12px minmax(140px, 1fr) minmax(130px, 1fr) minmax(130px, 1fr) auto; align-items: end; gap: 12px; padding: 16px; margin-bottom: 24px; border: 1px solid var(--analysis-line); border-radius: 8px; background: #faf9fc; }
.analysis-filters .field { margin: 0; min-width: 0; font-size: 12px; color: var(--analysis-muted); gap: 6px; }
.analysis-filters input, .analysis-filters select { min-height: 44px; font-size: 14px; background: #fff; padding: 8px 10px; border-color: #d8d4e2; border-radius: 6px; color: var(--text-primary, #242936); min-width: 0; }
.analysis-filters button { box-shadow: none; /* taste-skill-ignore: 明確移除既有按鈕陰影 */ min-height: 44px; font-size: 13px; padding-inline: 16px; }
.range-divider { padding-bottom: 12px; color: #928b9f; }
.analysis-report-heading { display: flex; align-items: center; justify-content: space-between; }
.analysis-period { margin: 0 0 12px; color: var(--analysis-muted); font-size: 12px; font-variant-numeric: tabular-nums; }
.analysis-totals { display: grid; grid-template-columns: 1.2fr 1fr 1fr; padding: 4px 0 24px; margin: 0; }
.analysis-totals > div { padding: 0 24px; border-left: 1px solid var(--analysis-line); }.analysis-totals > div:first-child { padding-left: 0; border-left: 0; }
.analysis-totals dt { font-size: 12px; color: var(--analysis-muted); }
.analysis-totals dd { margin: 8px 0 0; font-size: 24px; line-height: 1.2; font-weight: 500; font-variant-numeric: tabular-nums; letter-spacing: -.4px; }
.analysis-totals > div:first-child dd { font-size: 30px; font-weight: 600; }.analysis-totals dd small { font-size: 14px; font-weight: 400; color: var(--analysis-muted); }
.analysis-chart-panel { border: 1px solid var(--analysis-line); border-radius: 10px; background: #fff; padding: 20px 24px; margin-bottom: 24px; }
.analysis-table-scroll { overflow-x: auto; }.analysis-table-scroll:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }
.analysis-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.analysis-table caption { text-align: left; font-size: 15px; font-weight: 600; padding: 0 0 16px; }
.analysis-table th, .analysis-table td { padding: 14px 12px; border-bottom: 1px solid #eeecf3; text-align: right; font-variant-numeric: tabular-nums; }
.analysis-table th:first-child { text-align: left; overflow-wrap: anywhere; min-width: 100px; padding-left: 0; }
.analysis-table td:last-child, .analysis-table th:last-child { padding-right: 0; }
.analysis-table th .text-button { color: var(--text-primary, #242936); text-decoration: none; min-height: 32px; font-weight: 500; }.analysis-table th .text-button:hover { color: var(--accent); text-decoration: underline; }
.analysis-table thead th { color: var(--analysis-muted); font-size: 12px; font-weight: 400; white-space: nowrap; border-bottom-color: var(--analysis-line); }
.analysis-table tfoot { font-weight: 600; }.analysis-table tfoot th, .analysis-table tfoot td { border-bottom: 0; padding-top: 18px; }
.analysis-code { display: block; margin-top: 3px; font-size: 11px; font-weight: 400; color: var(--analysis-muted); }
.analysis-share { min-width: 100px; }.analysis-bar { display: block; height: 4px; background: #f0edf7; margin-top: 7px; width: 88px; margin-left: auto; border-radius: 2px; }.analysis-bar i { display: block; height: 100%; background: #9e90d7; border-radius: 2px; }
.analysis-empty { padding: 40px 20px; border: 1px dashed var(--analysis-line); border-radius: 8px; text-align: center; font-size: 14px; color: var(--analysis-muted); }
.analysis-loading { padding: 28px 0; color: var(--analysis-muted); font-size: 14px; }.analysis-pending { color: #805c1b; font-size: 13px; padding: 10px 12px; background: #fcf8ee; border-radius: 6px; }
@media(max-width:1000px) { .analysis-filters .field:nth-of-type(1) { grid-column: 1; grid-row: 1; }.analysis-filters .field:nth-of-type(2) { grid-column: 2; grid-row: 1; }.analysis-filters .field:nth-of-type(3) { grid-column: 1; grid-row: 2; }.analysis-filters .field:nth-of-type(4) { grid-column: 2; grid-row: 2; } .analysis-filters { grid-template-columns: repeat(2, minmax(0, 1fr)) auto; }.range-divider { display: none; }.analysis-filters button { grid-column: 3; grid-row: 2; }.analysis-index { gap: 12px; }.analysis-choice { padding: 18px; gap: 24px; } }
@media(max-width:600px) {
  .time-analysis { margin-inline: 2px; }
  .analysis-index { grid-template-columns: 1fr; gap: 12px; }.analysis-choice { display: grid; grid-template-columns: 88px minmax(0, 1fr); column-gap: 20px; row-gap: 6px; padding: 16px; }.choice-top { display: none; }.choice-preview { grid-row: 1; width: 88px; height: 60px; }.choice-title { font-size: 17px; }
  .analysis-back { min-height: 44px; }.analysis-presets .button { min-height: 44px; }
  .analysis-heading h1 { font-size: 24px; }.analysis-heading { gap: 12px; margin-bottom: 16px; }.analysis-presets .button { padding-inline: 10px; }.analysis-modes { gap: 22px; margin-bottom: 16px; }
  .analysis-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; padding: 14px; margin-bottom: 20px; }.analysis-filters button { grid-column: 1 / -1; grid-row: auto; }
  .analysis-totals > div { padding-inline: 14px; }.analysis-totals dd { font-size: 22px; }.analysis-totals > div:first-child dd { font-size: 26px; }.analysis-totals dt { font-size: 11px; }
  .analysis-chart-panel { padding: 18px 14px; margin-bottom: 24px; }.analysis-table { min-width: 460px; }.analysis-table th, .analysis-table td { padding: 14px 8px; }
}
@media(prefers-reduced-motion:reduce) { .analysis-choice { transition: none; } }
</style>
