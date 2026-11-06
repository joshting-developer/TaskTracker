<script setup>
import { computed, ref, watch } from 'vue';
import { api } from '../api/index.js';
import { dateInTaipei, epicSummary, hours } from '../domain/model.js';
import Icon from './Icon.vue';
import TimeEntryDrawer from './TimeEntryDrawer.vue';
import { timeSelection, writeContext } from '../ui/workContext.js';
const props = defineProps({ project: Object, issues: Array, entries: Array, members: Array, userId: String, contextKey: String, canWrite: { type: Boolean, default: false } });
const emit = defineEmits(['updated', 'open']);
const initialSelection = timeSelection(props.contextKey, props.members, props.userId, dateInTaipei());
const month = ref(initialSelection.month), member = ref(initialSelection.member), selectedDate = ref(initialSelection.date);
const contextNotice = ref(initialSelection.resetMember ? '先前查看的成員已不在此專案，已重設查看範圍' : '');
const error = ref(''), busy = ref(false), editing = ref(null);
const panel = ref(null), panelOpen = ref(false), formKey = ref(0);
const timeIssues = computed(() => props.issues);
const allMonth = computed(() => props.entries.filter((entry) => !entry.voidedAt && entry.projectId === props.project.id && entry.workDate.startsWith(month.value)));
const filtered = computed(() => allMonth.value.filter((entry) => !member.value || entry.userId === member.value));
const total = computed(() => filtered.value.reduce((sum, entry) => sum + entry.minutes, 0));
const selectedEntries = computed(() => filtered.value.filter((entry) => entry.workDate === selectedDate.value).sort((a, b) => b.createdAt.localeCompare(a.createdAt)));
const teamRows = computed(() => props.members.map((person) => ({ ...person, minutes: filtered.value.filter((entry) => entry.userId === person.id).reduce((sum, entry) => sum + entry.minutes, 0) })).filter((person) => !member.value || person.id === member.value));
const epicRows = computed(() => props.issues.filter((issue) => issue.type === 'epic').map((issue) => ({ ...issue, summary: epicSummary(issue, props.issues, filtered.value, props.project) })));
const issueRows = computed(() => timeIssues.value.map((issue) => ({ ...issue, spent: filtered.value.filter((entry) => entry.issueId === issue.id).reduce((sum, entry) => sum + entry.minutes, 0) })).filter((issue) => issue.spent > 0).sort((a, b) => b.spent - a.spent));
const days = computed(() => {
  if (!/^\d{4}-\d{2}$/.test(month.value)) return [];
  const [year, monthNumber] = month.value.split('-').map(Number);
  const first = new Date(Date.UTC(year, monthNumber - 1, 1));
  const offset = (first.getUTCDay() + 6) % 7;
  return Array.from({ length: 42 }, (_, index) => {
    const date = new Date(Date.UTC(year, monthNumber - 1, 1 - offset + index)).toISOString().slice(0, 10);
    const entries = filtered.value.filter((entry) => entry.workDate === date);
    return { date, number: Number(date.slice(8)), inMonth: date.startsWith(month.value), minutes: entries.reduce((sum, entry) => sum + entry.minutes, 0), count: entries.length };
  });
});
const dirty = computed(() => Boolean(panelOpen.value && panel.value?.dirty));
const saving = computed(() => busy.value || Boolean(panel.value?.saving));
defineExpose({ dirty, saving });
watch(() => props.canWrite, allowed => { if (!allowed) { panelOpen.value = false; editing.value = null; } });
watch(month, () => { selectedDate.value = month.value === dateInTaipei().slice(0, 7) ? dateInTaipei() : `${month.value}-01`; }, { flush: 'sync' });
watch(() => props.contextKey, () => {
  const saved = timeSelection(props.contextKey, props.members, props.userId, dateInTaipei());
  month.value = saved.month; member.value = saved.member; selectedDate.value = saved.date;
  contextNotice.value = saved.resetMember ? '先前查看的成員已不在此專案，已重設查看範圍' : '';
}, { flush: 'sync' });
watch(() => props.members.map(person => person.id).join(','), () => {
  if (member.value && !props.members.some(person => person.id === member.value)) {
    member.value = props.members.some(person => person.id === props.userId) ? props.userId : '';
    contextNotice.value = '先前查看的成員已不在此專案，已重設查看範圍';
  }
});
watch([month, member, selectedDate], () => writeContext(props.contextKey, { time: { month: month.value, member: member.value, date: selectedDate.value } }), { flush: 'post' });
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
function personName(id) { return props.members.find(person => person.id === id)?.name || '已離開的成員'; }
function issueFor(id) { return props.issues.find(issue => issue.id === id); }
function edit(entry) {
  if (!props.canWrite || saving.value || entry.userId !== props.userId || (dirty.value && !window.confirm('捨棄尚未儲存的工時？'))) return;
  editing.value = { ...entry }; error.value = ''; formKey.value++; panelOpen.value = true;
}
function closePanel() { panelOpen.value = false; editing.value = null; }
function saved(entry) {
  month.value = entry.workDate.slice(0, 7); selectedDate.value = entry.workDate;
  closePanel(); emit('updated', '工時已儲存');
}
const voidRequests = new Map();
async function voidEntry(entry) {
  if (!props.canWrite || saving.value || !window.confirm('確定作廢這筆工時？紀錄會保留, 但不再納入加總')) return;
  busy.value = true; error.value = '';
  const key = `${entry.id}:${entry.revision}`;
  if (!voidRequests.has(key)) voidRequests.set(key, crypto.randomUUID());
  try { await api.voidTime({ id: entry.id, expectedRevision: entry.revision, requestId: voidRequests.get(key) }); voidRequests.delete(key); emit('updated', '工時已作廢'); }
  catch (failure) { error.value = failure.message; if (failure.code === 'REVISION_CONFLICT') emit('updated', '工時已更新, 請再確認'); }
  finally { busy.value = false; }
}
</script>

<template>
  <section class="time-workspace" aria-label="工時月曆">
    <header class="section-title"><div><h2>工時月曆</h2><p class="subtle">本月合計 {{ hours(total) }}</p></div><label class="member-filter">查看<select v-model="member" aria-label="工時成員"><option value="">全團隊</option><option v-for="person in members" :key="person.id" :value="person.id">{{ person.name }}{{ person.id === userId ? '（我）' : '' }}</option></select></label></header>
    <p v-if="contextNotice" class="subtle" role="status">{{ contextNotice }}</p>
    <div class="time-layout">
      <div class="calendar"><header class="calendar-toolbar"><div><button class="icon-button previous" aria-label="上個月" @click="moveMonth(-1)"><Icon name="chevron" /></button><input v-model="month" type="month" aria-label="工時月份" required /><button class="icon-button" aria-label="下個月" @click="moveMonth(1)"><Icon name="chevron" /></button></div><button class="text-button" @click="month = dateInTaipei().slice(0, 7); selectedDate = dateInTaipei()">今天</button></header>
        <div class="calendar-weekdays"><span v-for="day in ['一','二','三','四','五','六','日']" :key="day">{{ day }}</span></div>
        <div class="calendar-grid"><button v-for="day in days" :key="day.date" :class="{ muted: !day.inMonth, selected: day.date === selectedDate, today: day.date === dateInTaipei() }" :aria-label="`${day.date} 工時 ${hours(day.minutes)}`" :aria-pressed="day.date === selectedDate" :aria-haspopup="canWrite ? 'dialog' : undefined" @click="selectDay(day)"><span>{{ day.number }}</span><strong v-if="day.minutes">{{ hours(day.minutes) }}</strong><small v-if="day.count">{{ day.count }} 筆</small></button></div>
      </div>
      <div class="time-day-details"><header class="section-title"><h3>{{ selectedDate }} · 工時明細</h3><span>{{ hours(selectedEntries.reduce((sum, entry) => sum + entry.minutes, 0)) }}</span></header><p v-if="!selectedEntries.length" class="subtle">這天還沒有符合篩選條件的紀錄</p><article v-for="entry in selectedEntries" :key="entry.id" class="time-entry"><div><span class="identifier">{{ issueFor(entry.issueId)?.identifier }} · {{ personName(entry.userId) }}</span><button class="text-button time-issue-link" @click="$emit('open', issueFor(entry.issueId))">{{ issueFor(entry.issueId)?.title }}</button><p>{{ entry.note || '未填說明' }}</p></div><div class="time-entry-end"><strong>{{ hours(entry.minutes) }}</strong><div v-if="canWrite && entry.userId === userId"><button class="text-button" :disabled="busy" :aria-label="`修改工時 ${entry.id}`" @click="edit(entry)">修改</button><button class="text-button danger-text" :disabled="busy" :aria-label="`作廢工時 ${entry.id}`" @click="voidEntry(entry)">作廢</button></div></div></article></div>
    </div>
    <TimeEntryDrawer v-if="panelOpen && canWrite" ref="panel" :key="formKey" :issues="timeIssues" :default-date="selectedDate" :entry="editing" @close="closePanel" @saved="saved" @conflict="emit('updated', '已重新載入工時, 請取消編輯後再選最新紀錄')" />
    <div class="time-report-grid"><section class="report-card"><h3>本月成員投入</h3><div v-for="person in teamRows" :key="person.id" class="report-row"><div><span>{{ person.name }}</span><strong>{{ hours(person.minutes) }}</strong></div><div class="progress-track"><span :style="{ width: `${total ? person.minutes / total * 100 : 0}%` }"></span></div></div></section><section class="report-card"><h3>Epic 工時</h3><div v-for="epic in epicRows" :key="epic.id" class="report-epic"><button class="text-button" @click="$emit('open', epic)">{{ epic.title }}</button><span>{{ hours(epic.summary.spentMinutes) }} / 預估 {{ hours(epic.summary.estimateMinutes) }}</span></div><p v-if="!epicRows.length" class="subtle">尚無 Epic 工時</p></section></div>
    <section class="report-card issue-time-report"><h3>本月任務投入</h3><p v-if="!issueRows.length" class="subtle">尚無符合條件的工時</p><div v-for="issue in issueRows" :key="issue.id" class="report-epic"><button class="text-button" @click="$emit('open', issue)">{{ issue.identifier }} · {{ issue.title }}</button><span>{{ hours(issue.spent) }} / 預估 {{ hours(issue.estimateMinutes || 0) }}</span></div></section>
  </section>
</template>
