<script setup>
import { computed, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import { dateInTaipei, hours } from '../domain/model.js';
import Icon from './Icon.vue';
import PagedSelect from './PagedSelect.vue';
import TimeEntryDrawer from './TimeEntryDrawer.vue';
import { timeSelection, writeContext } from '../ui/workContext.js';
// 月曆只讀目前Project當月SQL摘要；點日期才讀當日分頁明細，不再依賴整包Workspace工時。
const props = defineProps({ project: Object, userId: String, contextKey: String, canWrite: { type: Boolean, default: false }, canReadTeam: { type: Boolean, default: false } });
const emit = defineEmits(['updated', 'open', 'access']);
const initialSelection = timeSelection(props.contextKey, null, props.userId, dateInTaipei());
const month = ref(initialSelection.month), member = ref(props.canReadTeam ? initialSelection.member : props.userId), selectedDate = ref(initialSelection.date);
const error = ref(''), busy = ref(false), editing = ref(null);
const panel = ref(null), panelOpen = ref(false), formKey = ref(0);
const summary = shallowRef(null), summaryLoading = ref(false);
const dayResult = shallowRef(null), dayPage = ref(1), dayLoading = ref(false);
let summaryGeneration = 0, dayGeneration = 0, summaryRequest, dayRequest, timer, failures = 0, disposed = false;

const total = computed(() => summary.value?.total || 0);
const selectedEntries = computed(() => dayResult.value?.items || []);
const selectedTotal = computed(() => selectedEntries.value.reduce((sum, entry) => sum + entry.minutes, 0));
const teamRows = computed(() => summary.value?.members || []);
const epicRows = computed(() => summary.value?.epics || []);
const issueRows = computed(() => summary.value?.issues || []);
const memberOptions = computed(() => teamRows.value.filter(person => person.id === member.value));
const days = computed(() => {
  if (!/^\d{4}-\d{2}$/.test(month.value)) return [];
  const byDate = new Map((summary.value?.month === month.value ? summary.value.days : []).map(day => [day.date, day]));
  const [year, monthNumber] = month.value.split('-').map(Number);
  const first = new Date(Date.UTC(year, monthNumber - 1, 1));
  const offset = (first.getUTCDay() + 6) % 7;
  return Array.from({ length: 42 }, (_, index) => {
    const date = new Date(Date.UTC(year, monthNumber - 1, 1 - offset + index)).toISOString().slice(0, 10);
    const day = byDate.get(date);
    return { date, number: Number(date.slice(8)), inMonth: date.startsWith(month.value), minutes: day?.minutes || 0, count: day?.count || 0 };
  });
});
const dirty = computed(() => Boolean(panelOpen.value && panel.value?.dirty));
const saving = computed(() => busy.value || Boolean(panel.value?.saving));

function accessFailure(failure) {
  if (['NOT_FOUND', 'FORBIDDEN', 'WORKSPACE_CHANGED', 'UNAUTHENTICATED'].includes(failure.code)) {
    summary.value = null; dayResult.value = null; panelOpen.value = false; editing.value = null;
    emit('access');
  }
}
async function loadSummary() {
  const current = ++summaryGeneration;
  summaryRequest?.abort(); summaryRequest = new AbortController();
  summaryLoading.value = true;
  try {
    const data = await api.timeCalendar(props.project.id, { month: month.value, member: member.value }, { signal: summaryRequest.signal });
    if (current !== summaryGeneration) return;
    summary.value = data; failures = 0; error.value = '';
  } catch (failure) {
    if (current !== summaryGeneration || ['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) return;
    failures++; error.value = failure.message; accessFailure(failure);
  } finally { if (current === summaryGeneration) summaryLoading.value = false; }
}
async function loadDay() {
  const current = ++dayGeneration;
  dayRequest?.abort(); dayRequest = new AbortController();
  dayLoading.value = true;
  try {
    const data = await api.timeEntries(props.project.id, { date: selectedDate.value, member: member.value, page: dayPage.value, perPage: 20 }, { signal: dayRequest.signal });
    if (current !== dayGeneration) return;
    dayResult.value = data;
    if (dayPage.value > data.lastPage) dayPage.value = data.lastPage;
  } catch (failure) {
    if (current !== dayGeneration || ['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) return;
    error.value = failure.message; accessFailure(failure);
  } finally { if (current === dayGeneration) dayLoading.value = false; }
}
async function refresh() { await Promise.all([loadSummary(), loadDay()]); }
// 背景更新：隱藏分頁停止、失敗退避；填報面板開啟時不打擾。
function schedule() {
  clearTimeout(timer);
  if (disposed || document.hidden) return;
  timer = setTimeout(async () => { if (!panelOpen.value) await refresh(); schedule(); }, Math.min(120000, 30000 * 2 ** Math.min(failures, 2)) * (0.9 + Math.random() * 0.2));
}
function visibility() {
  clearTimeout(timer);
  if (document.hidden) { summaryGeneration++; dayGeneration++; summaryRequest?.abort(); dayRequest?.abort(); }
  else { refresh(); schedule(); }
}

watch(() => props.canWrite, allowed => { if (!allowed) { panelOpen.value = false; editing.value = null; } });
watch(() => props.canReadTeam, allowed => { if (!allowed) member.value = props.userId; });
watch(month, () => { selectedDate.value = month.value === dateInTaipei().slice(0, 7) ? dateInTaipei() : `${month.value}-01`; }, { flush: 'sync' });
watch(() => props.contextKey, () => {
  const saved = timeSelection(props.contextKey, null, props.userId, dateInTaipei());
  month.value = saved.month; member.value = props.canReadTeam ? saved.member : props.userId; selectedDate.value = saved.date;
}, { flush: 'sync' });
watch([() => props.project.id, month, member], () => { summary.value = null; loadSummary(); }, { immediate: true });
watch([() => props.project.id, selectedDate, member], () => { dayResult.value = null; if (dayPage.value !== 1) dayPage.value = 1; else loadDay(); }, { immediate: true });
watch(dayPage, loadDay);
watch([month, member, selectedDate], () => writeContext(props.contextKey, { time: { month: month.value, member: member.value, date: selectedDate.value } }), { flush: 'post' });
document.addEventListener('visibilitychange', visibility);
schedule();
onScopeDispose(() => { disposed = true; summaryGeneration++; dayGeneration++; summaryRequest?.abort(); dayRequest?.abort(); clearTimeout(timer); document.removeEventListener('visibilitychange', visibility); });
defineExpose({ dirty, saving, refresh });

function moveMonth(direction) {
  const [year, current] = (month.value || dateInTaipei().slice(0, 7)).split('-').map(Number);
  month.value = new Date(Date.UTC(year, current - 1 + direction, 1)).toISOString().slice(0, 7);
}
function selectDay(day) {
  if (saving.value || (dirty.value && !window.confirm('捨棄尚未儲存的工時？'))) return;
  month.value = day.date.slice(0, 7); selectedDate.value = day.date;
  editing.value = null; error.value = '';
  if (props.canWrite) { formKey.value++; panelOpen.value = true; }
}
function openIssue(issue) { if (issue?.id) emit('open', { ...issue, projectId: issue.projectId || props.project.id }); }
function lookupIssues(filters, options) { return api.issueOptions(props.project.id, { ...filters, kind: 'issue' }, options); }
function lookupMembers(filters, options) { return api.members(props.project.id, filters, options); }
function edit(entry) {
  if (!props.canWrite || saving.value || entry.userId !== props.userId || (dirty.value && !window.confirm('捨棄尚未儲存的工時？'))) return;
  editing.value = { ...entry }; error.value = ''; formKey.value++; panelOpen.value = true;
}
function closePanel() { panelOpen.value = false; editing.value = null; }
async function saved(entry) {
  const sameScope = month.value === entry.workDate.slice(0, 7) && selectedDate.value === entry.workDate;
  month.value = entry.workDate.slice(0, 7); selectedDate.value = entry.workDate;
  closePanel();
  if (sameScope) await refresh();
  emit('updated', '工時已儲存');
}
const voidRequests = new Map();
async function voidEntry(entry) {
  if (!props.canWrite || saving.value || !window.confirm('確定作廢這筆工時？紀錄會保留, 但不再納入加總')) return;
  busy.value = true; error.value = '';
  const key = `${entry.id}:${entry.revision}`;
  if (!voidRequests.has(key)) voidRequests.set(key, crypto.randomUUID());
  try { await api.voidTime({ id: entry.id, expectedRevision: entry.revision, requestId: voidRequests.get(key) }); voidRequests.delete(key); await refresh(); emit('updated', '工時已作廢'); }
  catch (failure) { error.value = failure.message; if (failure.code === 'REVISION_CONFLICT') { await refresh(); emit('updated', '工時已更新, 請再確認'); } }
  finally { busy.value = false; }
}
</script>

<template>
  <section class="time-workspace" aria-label="工時月曆">
    <header class="section-title"><div><h2>工時月曆</h2><p class="subtle">本月合計 {{ hours(total) }}</p></div><div v-if="canReadTeam" class="member-filter"><PagedSelect v-model="member" label="工時成員" :context="`${project.id}:time-member`" :fixed-options="memberOptions" :load="lookupMembers" empty-label="全團隊" /></div><p v-else class="subtle">只顯示你的工時</p></header>
    <div v-if="error" class="error-box" role="alert">{{ error }}<button class="text-button" @click="refresh">重試</button></div>
    <div class="time-layout">
      <div class="calendar"><header class="calendar-toolbar"><div><button class="icon-button previous" aria-label="上個月" @click="moveMonth(-1)"><Icon name="chevron" /></button><input v-model="month" type="month" aria-label="工時月份" required /><button class="icon-button" aria-label="下個月" @click="moveMonth(1)"><Icon name="chevron" /></button></div><button class="text-button" @click="month = dateInTaipei().slice(0, 7); selectedDate = dateInTaipei()">今天</button></header>
        <p v-if="summaryLoading && !summary" role="status">載入工時中…</p>
        <div class="calendar-weekdays"><span v-for="day in ['一','二','三','四','五','六','日']" :key="day">{{ day }}</span></div>
        <div class="calendar-grid"><button v-for="day in days" :key="day.date" :class="{ muted: !day.inMonth, selected: day.date === selectedDate, today: day.date === dateInTaipei() }" :aria-label="`${day.date} 工時 ${hours(day.minutes)}`" :aria-pressed="day.date === selectedDate" :aria-haspopup="canWrite ? 'dialog' : undefined" @click="selectDay(day)"><span>{{ day.number }}</span><strong v-if="day.minutes">{{ hours(day.minutes) }}</strong><small v-if="day.count">{{ day.count }} 筆</small></button></div>
      </div>
      <div class="time-day-details"><header class="section-title"><h3>{{ selectedDate }} · 工時明細</h3><span>{{ hours(selectedTotal) }}</span></header><p v-if="dayLoading && !dayResult" role="status">載入明細中…</p><p v-else-if="!selectedEntries.length" class="subtle">這天還沒有符合篩選條件的紀錄</p><article v-for="entry in selectedEntries" :key="entry.id" class="time-entry"><div><span class="identifier">{{ entry.issue.identifier }} · {{ entry.userName }}</span><button class="text-button time-issue-link" @click="openIssue(entry.issue)">{{ entry.issue.title }}</button><p>{{ entry.note || '未填說明' }}</p></div><div class="time-entry-end"><strong>{{ hours(entry.minutes) }}</strong><div v-if="canWrite && entry.userId === userId"><button class="text-button" :disabled="busy" :aria-label="`修改工時 ${entry.id}`" @click="edit(entry)">修改</button><button class="text-button danger-text" :disabled="busy" :aria-label="`作廢工時 ${entry.id}`" @click="voidEntry(entry)">作廢</button></div></div></article><div v-if="dayResult?.lastPage > 1" class="pagination" aria-label="工時明細分頁"><button class="text-button" :disabled="dayLoading || dayPage <= 1" @click="dayPage--">上一頁</button><span>{{ dayPage }} / {{ dayResult.lastPage }}</span><button class="text-button" :disabled="dayLoading || dayPage >= dayResult.lastPage" @click="dayPage++">下一頁</button></div></div>
    </div>
    <TimeEntryDrawer v-if="panelOpen && canWrite" ref="panel" :key="formKey" :lookup="lookupIssues" :fixed-issues="editing ? [editing.issue] : []" :default-date="selectedDate" :entry="editing" @close="closePanel" @saved="saved" @conflict="refresh(); emit('updated', '已重新載入工時, 請取消編輯後再選最新紀錄')" />
    <div class="time-report-grid"><section class="report-card"><h3>本月成員投入</h3><p v-if="!teamRows.length" class="subtle">尚無符合條件的工時</p><div v-for="person in teamRows" :key="person.id" class="report-row"><div><span>{{ person.name }}{{ person.id === userId ? '（我）' : '' }}</span><strong>{{ hours(person.minutes) }}</strong></div><div class="progress-track"><span :style="{ width: `${total ? person.minutes / total * 100 : 0}%` }"></span></div></div></section><section class="report-card"><h3>Epic 工時</h3><div v-for="epic in epicRows" :key="epic.id" class="report-epic"><button class="text-button" @click="openIssue(epic)">{{ epic.title }}</button><span>{{ hours(epic.spentMinutes) }} / 預估 {{ hours(epic.estimateMinutes) }}</span></div><p v-if="!epicRows.length" class="subtle">尚無 Epic 工時</p></section></div>
    <section class="report-card issue-time-report"><h3>本月任務投入</h3><p v-if="!issueRows.length" class="subtle">尚無符合條件的工時</p><div v-for="issue in issueRows" :key="issue.id" class="report-epic"><button class="text-button" @click="openIssue(issue)">{{ issue.identifier }} · {{ issue.title }}</button><span>{{ hours(issue.spent) }} / 預估 {{ hours(issue.estimateMinutes || 0) }}</span></div><p v-if="summary && issueRows.length >= summary.limit" class="subtle">僅顯示投入最多的 {{ summary.limit }} 項</p></section>
  </section>
</template>
