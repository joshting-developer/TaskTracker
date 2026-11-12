<script setup>
import { nextTick, onBeforeUnmount, shallowRef, useTemplateRef, watch } from 'vue';
import { api } from '../api/index.js';
import { hours } from '../domain/model.js';
import ReportPagination from './ReportPagination.vue';

const props = defineProps({ member: { type: Object, required: true }, workspaceVersion: { type: String, default: '' } });
const emit = defineEmits(['back']);
const group = shallowRef('dates'), page = shallowRef(1), expandedKey = shallowRef(''), entryPage = shallowRef(1);
const report = shallowRef(null), loading = shallowRef(false), error = shallowRef('');
const heading = useTemplateRef('heading');
let generation = 0;

async function load(focusHeading = false) {
  const current = ++generation;
  loading.value = true;
  error.value = '';
  try {
    const result = await api.getTimeReport({
      ...props.member.range, userId: props.member.id,
      detailGroup: group.value, detailPage: page.value,
      entryGroupKey: expandedKey.value, entryPage: entryPage.value,
    });
    if (current !== generation) return;
    report.value = result;
    page.value = result.details.page;
    entryPage.value = result.details.expanded?.page || 1;
    if (focusHeading) { await nextTick(); heading.value?.focus(); }
  } catch (failure) {
    if (current === generation) { report.value = null; error.value = failure.message; }
  } finally {
    if (current === generation) loading.value = false;
  }
}
function changeGroup(value) {
  group.value = value; page.value = 1; expandedKey.value = ''; entryPage.value = 1;
  report.value = null;
  load();
}
function changePage(value) {
  page.value = value; expandedKey.value = ''; entryPage.value = 1;
  load(true);
}
function expand(key) {
  expandedKey.value = expandedKey.value === key ? '' : key;
  entryPage.value = 1;
  load();
}
function changeEntryPage(value) { entryPage.value = value; load(); }
watch(() => [props.member, props.workspaceVersion], () => {
  report.value = null;
  // 背景更新重查資格；群組消失時不保留舊的填報說明。
  load();
}, { immediate: true });
onBeforeUnmount(() => { generation++; });
</script>

<template>
  <section class="member-details" aria-label="成員工時明細" :aria-busy="loading">
    <button class="text-button member-back" @click="emit('back')">返回成員分析</button>
    <h1 ref="heading" class="member-heading" tabindex="-1">{{ member.name }} · 工時明細</h1>
    <p class="member-period">{{ member.range.startDate }} — {{ member.range.endDate }}<template v-if="member.projectName"> · {{ member.projectName }}</template></p>
    <div class="member-grouping" role="group" aria-label="明細整理方式">
      <button class="button secondary" :aria-pressed="group === 'dates'" :disabled="loading" @click="changeGroup('dates')">依日期</button>
      <button class="button secondary" :aria-pressed="group === 'issues'" :disabled="loading" @click="changeGroup('issues')">依卡片</button>
    </div>
    <p v-if="loading" role="status">正在載入工時明細…</p>
    <div v-if="error" class="error-box" role="alert">{{ error }} <button class="text-button" @click="load()">重試</button></div>
    <template v-else-if="report">
      <p class="member-summary"><strong>{{ hours(report.summary.totalMinutes) }}</strong><span>{{ report.summary.entryCount }} 筆</span><span>{{ report.summary.activeDays }} 天</span></p>
      <p v-if="!report.details.total" role="status">尚無符合條件的工時紀錄</p>
      <div v-else class="member-groups">
        <article v-for="(row, index) in report.details.rows" :key="row.key" class="member-row">
          <div v-if="group === 'dates' && (index === 0 || report.details.rows[index - 1].date !== row.date)" class="member-day"><strong>{{ row.date }}</strong><span>當日合計 {{ hours(row.dayMinutes) }}</span></div>
          <div class="member-card">
            <div class="member-card-name"><span class="member-code">{{ row.identifier }} · {{ row.projectName }}</span><h2 class="member-card-title">{{ row.title }}</h2></div>
            <strong class="member-hours">{{ hours(row.minutes) }}</strong>
            <button class="text-button member-expand" :disabled="loading" :aria-expanded="report.details.expanded?.key === row.key" :aria-label="`${report.details.expanded?.key === row.key ? '收合' : '展開'} ${row.identifier} 工時紀錄`" @click="expand(row.key)">{{ report.details.expanded?.key === row.key ? '收合紀錄' : '查看紀錄' }}（{{ row.entryCount }}）</button>
          </div>
          <div v-if="report.details.expanded?.key === row.key" class="member-entries" :aria-label="`${row.identifier} 填報紀錄`" role="region">
            <div v-for="entry in report.details.expanded.rows" :key="entry.id" class="member-entry">
              <div class="member-entry-meta"><time>{{ entry.workDate }}</time><strong>{{ hours(entry.minutes) }}</strong></div>
              <p class="member-note">{{ entry.note || '未填寫說明' }}</p>
            </div>
            <ReportPagination :pagination="report.details.expanded" label="填報紀錄分頁" :disabled="loading" @change="changeEntryPage" />
          </div>
        </article>
      </div>
      <ReportPagination :pagination="report.details" label="成員明細分頁" :disabled="loading" @change="changePage" />
    </template>
  </section>
</template>

<style scoped>
.member-details { min-width: 0; color: var(--text-primary, #242936); }
.member-back { min-height: 44px; margin-bottom: 12px; }
.member-heading { font-size: 26px; line-height: 1.4; margin: 0; overflow-wrap: anywhere; }
.member-period, .member-code, .member-day span { color: var(--text-secondary, #5f6673); font-size: 14px; }
.member-period { line-height: 1.7; }
.member-grouping { display: inline-flex; gap: 3px; margin: 12px 0 20px; background: #f5f3f9; border: 1px solid #e5e2ed; border-radius: 8px; padding: 3px; }
.member-grouping .button { min-height: 40px; background: transparent; border: 1px solid transparent; border-radius: 5px; }
.member-grouping [aria-pressed="true"] { border-color: #e2ddec; background: #fff; color: var(--accent); font-weight: 600; }
.member-summary { display: flex; align-items: baseline; flex-wrap: wrap; gap: 24px; padding: 18px 0; border-block: 1px solid #e5e2ed; font-variant-numeric: tabular-nums; }
.member-summary strong { font-size: 26px; font-weight: 600; letter-spacing: -.4px; }.member-summary span { color: var(--text-secondary, #5f6673); font-size: 14px; }
.member-row { border-bottom: 1px solid #e5e2ed; padding: 20px 0; }
.member-day { display: flex; flex-wrap: wrap; gap: 16px; align-items: baseline; margin-bottom: 14px; }
.member-card { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 20px; align-items: center; }
.member-card-name { min-width: 0; overflow-wrap: anywhere; }
.member-card-title { margin: 6px 0 0; font-size: 16px; line-height: 1.5; }
.member-hours { font-variant-numeric: tabular-nums; white-space: nowrap; }
.member-expand { min-height: 44px; }
.member-entries { margin-top: 16px; border-left: 2px solid #c4b9e5; padding: 4px 18px; background: #faf9fd; border-radius: 0 6px 6px 0; }
.member-entry { padding: 14px 0; border-bottom: 1px solid #eceef2; }
.member-entry-meta { display: flex; justify-content: space-between; gap: 16px; font-size: 14px; font-variant-numeric: tabular-nums; }
.member-note { white-space: pre-wrap; overflow-wrap: anywhere; margin: 8px 0 0; line-height: 1.7; }
@media(max-width:600px) { .member-grouping .button { min-height: 44px; } .member-heading { font-size: 24px; }.member-card { grid-template-columns: minmax(0, 1fr) auto; gap: 8px 12px; }.member-expand { grid-column: 1 / -1; justify-self: start; }.member-entries { padding-left: 12px; } }
</style>
