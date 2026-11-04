<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { api } from '../api/index.js';
import { dateInTaipei, epicSummary, hours } from '../domain/model.js';
import Icon from './Icon.vue';
const props = defineProps({ project: Object, issues: Array, entries: Array, members: Array, userId: String });
const emit = defineEmits(['updated', 'open']);
const month = ref(dateInTaipei().slice(0, 7)), member = ref(props.userId), selectedDate = ref(dateInTaipei());
const error = ref(''), busy = ref(false), editing = ref(null);
const durationError = ref(''), durationInput = ref(null), errorSummary = ref(null);
const panel = ref(null), panelOpen = ref(false);
const form = reactive({ issueId: '', workDate: dateInTaipei(), duration: '', note: '' });
const initialForm = ref(JSON.stringify(form));
let pending;
const leafIssues = computed(() => props.issues.filter((issue) => issue.type !== 'epic'));
const allMonth = computed(() => props.entries.filter((entry) => !entry.voidedAt && entry.projectId === props.project.id && entry.workDate.startsWith(month.value)));
const filtered = computed(() => allMonth.value.filter((entry) => !member.value || entry.userId === member.value));
const total = computed(() => filtered.value.reduce((sum, entry) => sum + entry.minutes, 0));
const selectedEntries = computed(() => filtered.value.filter((entry) => entry.workDate === selectedDate.value).sort((a, b) => b.createdAt.localeCompare(a.createdAt)));
const teamRows = computed(() => props.members.map((person) => ({ ...person, minutes: filtered.value.filter((entry) => entry.userId === person.id).reduce((sum, entry) => sum + entry.minutes, 0) })).filter((person) => !member.value || person.id === member.value));
const epicRows = computed(() => props.issues.filter((issue) => issue.type === 'epic').map((issue) => ({ ...issue, summary: epicSummary(issue, props.issues, filtered.value, props.project) })));
const issueRows = computed(() => leafIssues.value.map((issue) => ({ ...issue, spent: filtered.value.filter((entry) => entry.issueId === issue.id).reduce((sum, entry) => sum + entry.minutes, 0) })).filter((issue) => issue.spent > 0).sort((a, b) => b.spent - a.spent));
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
const dirty = computed(() => panelOpen.value && JSON.stringify(form) !== initialForm.value);
defineExpose({ dirty, saving: busy });
watch(month, () => { selectedDate.value = month.value === dateInTaipei().slice(0, 7) ? dateInTaipei() : `${month.value}-01`; }, { flush: 'sync' });
function moveMonth(direction) {
  const [year, current] = (month.value || dateInTaipei().slice(0, 7)).split('-').map(Number);
  month.value = new Date(Date.UTC(year, current - 1 + direction, 1)).toISOString().slice(0, 7);
}
async function openPanel() {
  panelOpen.value = true;
  await nextTick();
  if (!panel.value.open) panel.value.showModal();
  panel.value.querySelector(editing.value ? 'input[type="number"]' : 'select')?.focus();
}
function selectDay(day) {
  if (busy.value || (dirty.value && !window.confirm('捨棄尚未儲存的工時？'))) return;
  month.value = day.date.slice(0, 7); selectedDate.value = day.date;
  reset(day.date); openPanel();
}
function closePanel(force = false) {
  if (!force && (busy.value || (dirty.value && !window.confirm('捨棄尚未儲存的工時？')))) return;
  panel.value.close(); panelOpen.value = false; reset();
}
onBeforeUnmount(() => panel.value?.close());
function personName(id) { return props.members.find((person) => person.id === id)?.name || '已離開的成員'; }
function issueFor(id) { return props.issues.find((issue) => issue.id === id); }
function reset(workDate = selectedDate.value) {
  editing.value = null;
  Object.assign(form, { issueId: '', workDate, duration: '', note: '' });
  initialForm.value = JSON.stringify(form); error.value = ''; durationError.value = ''; pending = null;
}
function edit(entry) {
  if (busy.value || entry.userId !== props.userId || (dirty.value && !window.confirm('捨棄尚未儲存的工時？'))) return;
  editing.value = entry;
  Object.assign(form, { issueId: entry.issueId, workDate: entry.workDate, duration: Number((entry.minutes / 60).toFixed(6)), note: entry.note });
  initialForm.value = JSON.stringify(form);
  error.value = ''; durationError.value = ''; pending = null; openPanel();
}
async function save() {
  if (busy.value) return;
  error.value = ''; durationError.value = '';
  const rawMinutes = Number(form.duration) * 60;
  const minutes = Math.round(rawMinutes);
  if (!Number.isFinite(rawMinutes) || minutes < 1 || rawMinutes > 1440) {
    durationError.value = '請填入 1 分鐘至 24 小時的工時';
  } else if (Math.abs(rawMinutes - minutes) > 0.001) {
    durationError.value = '工時以整數分鐘計算；例如 0.25 小時為 15 分鐘、0.5 小時為 30 分鐘';
  }
  if (durationError.value) { await nextTick(); durationInput.value?.focus(); return; }
  const payload = { issueId: form.issueId, workDate: form.workDate, minutes, note: form.note, ...(editing.value ? { id: editing.value.id, expectedRevision: editing.value.revision } : {}) };
  const signature = JSON.stringify(payload);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  busy.value = true;
  try {
    await api[editing.value ? 'updateTime' : 'logTime']({ ...payload, requestId: pending.requestId });
    month.value = form.workDate.slice(0, 7); selectedDate.value = form.workDate;
    closePanel(true); emit('updated', '工時已儲存');
  } catch (failure) { error.value = failure.message; if (failure.code === 'REVISION_CONFLICT') emit('updated', '已重新載入工時, 請取消編輯後再選最新紀錄'); await nextTick(); errorSummary.value?.focus(); }
  finally { busy.value = false; }
}
const voidRequests = new Map();
async function voidEntry(entry) {
  if (busy.value || !window.confirm('確定作廢這筆工時？紀錄會保留, 但不再納入加總')) return;
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
    <header class="section-title"><div><div class="eyebrow">TIME, MADE VISIBLE</div><h2>每一天, 一起投入的時間</h2><p class="subtle">點選日期填報工時 · Asia/Taipei · 本月篩選合計 {{ hours(total) }}</p></div><label class="member-filter">查看<select v-model="member" aria-label="工時成員"><option value="">全團隊</option><option v-for="person in members" :key="person.id" :value="person.id">{{ person.name }}{{ person.id === userId ? '（我）' : '' }}</option></select></label></header>
    <div class="time-layout">
      <div class="calendar"><header class="calendar-toolbar"><div><button class="icon-button previous" aria-label="上個月" @click="moveMonth(-1)"><Icon name="chevron" /></button><input v-model="month" type="month" aria-label="工時月份" required /><button class="icon-button" aria-label="下個月" @click="moveMonth(1)"><Icon name="chevron" /></button></div><button class="text-button" @click="month = dateInTaipei().slice(0, 7); selectedDate = dateInTaipei()">今天</button></header>
        <div class="calendar-weekdays"><span v-for="day in ['一','二','三','四','五','六','日']" :key="day">{{ day }}</span></div>
        <div class="calendar-grid"><button v-for="day in days" :key="day.date" :class="{ muted: !day.inMonth, selected: day.date === selectedDate, today: day.date === dateInTaipei() }" :aria-label="`${day.date} 工時 ${hours(day.minutes)}`" :aria-pressed="day.date === selectedDate" aria-haspopup="dialog" @click="selectDay(day)"><span>{{ day.number }}</span><strong v-if="day.minutes">{{ hours(day.minutes) }}</strong><small v-if="day.count">{{ day.count }} 筆</small></button></div>
      </div>
      <div class="time-day-details"><header class="section-title"><h3>{{ selectedDate }} · 工時明細</h3><span>{{ hours(selectedEntries.reduce((sum, entry) => sum + entry.minutes, 0)) }}</span></header><p v-if="!selectedEntries.length" class="subtle">這天還沒有符合篩選條件的紀錄</p><article v-for="entry in selectedEntries" :key="entry.id" class="time-entry"><div><span class="identifier">{{ issueFor(entry.issueId)?.identifier }} · {{ personName(entry.userId) }}</span><button class="text-button time-issue-link" @click="$emit('open', issueFor(entry.issueId))">{{ issueFor(entry.issueId)?.title }}</button><p>{{ entry.note || '未填說明' }}</p></div><div class="time-entry-end"><strong>{{ hours(entry.minutes) }}</strong><div v-if="entry.userId === userId"><button class="text-button" :disabled="busy" :aria-label="`修改工時 ${entry.id}`" @click="edit(entry)">修改</button><button class="text-button danger-text" :disabled="busy" :aria-label="`作廢工時 ${entry.id}`" @click="voidEntry(entry)">作廢</button></div></div></article></div>
    </div>
    <dialog ref="panel" class="time-entry-drawer" aria-labelledby="time-entry-heading" @cancel.prevent="closePanel()" @click="event => { if (event.target === panel) closePanel(); }">
      <header class="time-drawer-header"><div><h3 id="time-entry-heading">{{ editing ? '修改你的工時' : '填報你的工時' }}</h3><p>{{ form.workDate }} · Asia/Taipei</p></div><button class="icon-button" aria-label="關閉工時面板" :disabled="busy" @click="closePanel()"><Icon name="close" /></button></header>
      <div class="time-form-card"><p class="subtle">記錄實際投入, 不限於指派給你的任務</p><div v-if="error" ref="errorSummary" class="error-box" role="alert" tabindex="-1">{{ error }}</div><form @submit.prevent="save"><label class="field">任務<select v-model="form.issueId" aria-label="工時任務" required :disabled="busy || Boolean(editing)"><option value="" disabled>選擇 Task 或 Bug</option><option v-for="issue in leafIssues" :key="issue.id" :value="issue.id">{{ issue.identifier }} · {{ issue.title }}</option></select></label><label class="field">工作日期<input v-model="form.workDate" type="date" :max="dateInTaipei()" required :disabled="busy" /></label><div class="field"><label for="time-duration">投入小時</label><input id="time-duration" ref="durationInput" v-model="form.duration" type="number" min="0.016666" max="24" step="any" placeholder="例如 1.5" required :disabled="busy" :aria-invalid="durationError ? 'true' : undefined" :aria-describedby="durationError ? 'time-duration-hint time-duration-error' : 'time-duration-hint'" @input="durationError = ''" /><small id="time-duration-hint">以整數分鐘計算；0.25 小時＝15 分鐘、0.5 小時＝30 分鐘</small><span v-if="durationError" id="time-duration-error" class="field-error" role="alert">{{ durationError }}</span></div><label class="field">工時說明<textarea v-model="form.note" rows="3" maxlength="1000" placeholder="今天完成了什麼？" :disabled="busy" /></label><button class="button primary" :disabled="busy || !leafIssues.length"><Icon name="check" :size="17" />{{ busy ? '儲存中…' : editing ? '儲存工時修改' : '登記工時' }}</button><button v-if="editing || dirty" type="button" class="text-button time-cancel" :disabled="busy" @click="closePanel()">取消填寫</button></form><p v-if="!leafIssues.length" class="subtle">先建立一個 Task 或 Bug, 再填工時</p><small class="time-form-note">Epic 工時由子任務加總<br />工時只可填報／修改自己的紀錄</small></div>
    </dialog>
    <div class="time-report-grid"><section class="report-card"><h3>本月成員投入</h3><p class="subtle">依上方成員篩選, 不代表出勤或績效</p><div v-for="person in teamRows" :key="person.id" class="report-row"><div><span>{{ person.name }}</span><strong>{{ hours(person.minutes) }}</strong></div><div class="progress-track"><span :style="{ width: `${total ? person.minutes / total * 100 : 0}%` }"></span></div></div></section><section class="report-card"><h3>Epic 工時</h3><p class="subtle">已投入為本月篩選, 預估為全部子任務</p><div v-for="epic in epicRows" :key="epic.id" class="report-epic"><button class="text-button" @click="$emit('open', epic)">{{ epic.title }}</button><span>{{ hours(epic.summary.spentMinutes) }} / 預估 {{ hours(epic.summary.estimateMinutes) }}</span></div><p v-if="!epicRows.length" class="subtle">建立 Epic 並掛上任務, 就能在這裡比較投入</p></section></div>
    <section class="report-card issue-time-report"><h3>本月任務投入</h3><p v-if="!issueRows.length" class="subtle">尚無符合條件的工時</p><div v-for="issue in issueRows" :key="issue.id" class="report-epic"><button class="text-button" @click="$emit('open', issue)">{{ issue.identifier }} · {{ issue.title }}</button><span>{{ hours(issue.spent) }} / 預估 {{ hours(issue.estimateMinutes || 0) }}</span></div></section>
  </section>
</template>
