<script setup>
import { t } from '../i18n/index.js';
import { computed, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import { dateInTaipei, isClosed, statusesFor } from '../domain/model.js';
import { addDays, layoutBars, scheduleSpan, weekOf } from '../domain/schedule.js';
import { readContext, writeContext } from '../ui/workContext.js';
import Icon from './Icon.vue';
import PagedSelect from './PagedSelect.vue';
// TT-385：只讀目前Project當週與計畫日期重疊的卡片；改期仍在卡片編輯, 不另存排程。
const props = defineProps({ project: Object, contextKey: String });
const emit = defineEmits(['open', 'access']);
const saved = readContext(props.contextKey).schedule || {};
const validDay = value => typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(Date.parse(value)) && new Date(value).toISOString().slice(0, 10) === value;
const anchor = ref(validDay(saved.week) ? saved.week : dateInTaipei()), assignee = ref(typeof saved.assignee === 'string' && saved.assignee.length <= 100 ? saved.assignee : '');
const result = shallowRef(null), loading = ref(false), error = ref(''), page = ref(1);
let generation = 0, request;
const week = computed(() => weekOf(anchor.value));
const today = dateInTaipei();
const weekdays = [t('一'), t('二'), t('三'), t('四'), t('五'), t('六'), t('日')];
const bars = computed(() => layoutBars(result.value?.items || [], week.value.from, week.value.to));
const statuses = computed(() => new Map(statusesFor(props.project).map(status => [status.id, status])));
const lanes = computed(() => Math.max(1, ...bars.value.map(bar => bar.lane + 1)));
// 窄螢幕改逐日清單, 跨日卡片在每一天都列出。
const agenda = computed(() => week.value.days.map((date, index) => ({ date, index, bars: bars.value.filter(bar => bar.start <= index && bar.end >= index) })));
const assigneeOptions = computed(() => assignee.value && result.value?.items.find(item => item.assignee === assignee.value) ? [{ id: assignee.value, name: result.value.items.find(item => item.assignee === assignee.value).assigneeName }] : []);

async function load() {
  const current = ++generation;
  request?.abort(); request = new AbortController();
  loading.value = true;
  try {
    const data = await api.schedule(props.project.id, { from: week.value.from, to: week.value.to, assignee: assignee.value, page: page.value, perPage: 200 }, { signal: request.signal });
    if (current !== generation) return;
    result.value = data; error.value = '';
  } catch (failure) {
    if (current !== generation || ['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) return;
    error.value = failure.message;
    if (['NOT_FOUND', 'FORBIDDEN', 'WORKSPACE_CHANGED', 'UNAUTHENTICATED'].includes(failure.code)) { result.value = null; emit('access'); }
  } finally { if (current === generation) loading.value = false; }
}
watch([() => props.project.id, () => week.value.from, assignee], () => { result.value = null; if (page.value !== 1) page.value = 1; else load(); }, { immediate: true });
watch(page, load);
watch([anchor, assignee], () => writeContext(props.contextKey, { schedule: { week: anchor.value, assignee: assignee.value } }), { flush: 'post' });
onScopeDispose(() => { generation++; request?.abort(); });
defineExpose({ refresh: load });

function lookupMembers(filters, options) { return api.members(props.project.id, filters, options); }
function open(issue) { emit('open', { id: issue.id, projectId: props.project.id }); }
function label(date) { return `${Number(date.slice(5, 7))}/${Number(date.slice(8))}`; }
// 計畫結束日已過仍未關閉視為逾期, 和工具列「今天」同一個台北日期。
function overdue(issue) { return !isClosed(issue) && scheduleSpan(issue).end < today; }
function stateLabel(issue) { return issue.stateLabel || statuses.value.get(issue.state)?.label || issue.state; }
function barClass(bar, continued = true) { return { closed: isClosed(bar.issue), overdue: overdue(bar.issue), before: continued && bar.continuesBefore, after: continued && bar.continuesAfter }; }
function barStyle(issue) { return { '--status-color': statuses.value.get(issue.state)?.color || '#9a9da9' }; }
function initials(name) { return Array.from(name.trim())[0]?.toUpperCase() || ''; }
function barLabel(bar) {
  const { issue } = bar, range = issue.startDate && issue.endDate && issue.startDate !== issue.endDate ? `${issue.startDate} – ${issue.endDate}` : issue.startDate || issue.endDate;
  const state = overdue(issue) ? `${stateLabel(issue)}, ${t('已逾期')}` : stateLabel(issue);
  return t('{0} {1}, {2}, {3}, {4}', [issue.identifier, issue.title, state, range, issue.assigneeName || t('未指派')]);
}
</script>

<template>
  <section class="schedule-workspace" :aria-label="t('週曆')" :aria-busy="loading">
    <header class="section-title"><div><h2>{{ t('週曆') }}</h2><p class="subtle">{{ t('依計畫開始與結束日顯示, 到期日不列入') }}</p></div><div class="member-filter"><PagedSelect v-model="assignee" :label="t('承辦人')" :context="`${project.id}:schedule-assignee`" :fixed-options="assigneeOptions" :load="lookupMembers" :empty-label="t('全部成員')" /></div></header>
    <div v-if="error" class="error-box" role="alert">{{ t(error) }}<button class="text-button" @click="load">{{ t('重試') }}</button></div>
    <div class="calendar schedule-week">
      <header class="calendar-toolbar"><div><button class="icon-button previous" :aria-label="t('上一週')" @click="anchor = addDays(week.from, -7)"><Icon name="chevron" /></button><strong class="schedule-range">{{ week.from }} – {{ week.to }}</strong><button class="icon-button" :aria-label="t('下一週')" @click="anchor = addDays(week.from, 7)"><Icon name="chevron" /></button></div><button class="text-button" @click="anchor = dateInTaipei()">{{ t('本週') }}</button></header>
      <p v-if="loading && !result" class="schedule-status" role="status">{{ t('載入時程中…') }}</p>
      <div class="schedule-grid" :style="{ gridTemplateRows: `auto repeat(${lanes}, 38px) minmax(24px, 1fr)` }">
        <div v-for="(date, index) in week.days" :key="date" class="schedule-day" :class="{ today: date === today }" :style="{ gridColumn: index + 1, gridRow: 1 }"><span>{{ weekdays[index] }}</span><strong>{{ label(date) }}</strong></div>
        <div v-for="(date, index) in week.days" :key="`${date}:column`" class="schedule-column" :class="{ today: date === today }" :style="{ gridColumn: index + 1, gridRow: '2 / -1' }" aria-hidden="true"></div>
        <button v-for="bar in bars" :key="bar.issue.id" class="schedule-bar" :class="barClass(bar)" :style="{ ...barStyle(bar.issue), gridColumn: `${bar.start + 1} / ${bar.end + 2}`, gridRow: bar.lane + 2 }" :aria-label="barLabel(bar)" :title="barLabel(bar)" @click="open(bar.issue)"><span class="identifier">{{ bar.issue.identifier }}</span><span class="schedule-title">{{ bar.issue.title }}</span><span v-if="!assignee && bar.issue.assigneeName" class="avatar small" aria-hidden="true">{{ initials(bar.issue.assigneeName) }}</span></button>
      </div>
      <ol class="schedule-agenda" :aria-label="t('本週每日任務')">
        <li v-for="day in agenda" :key="day.date" :class="{ today: day.date === today }"><h3>{{ weekdays[day.index] }} {{ label(day.date) }}</h3><p v-if="!day.bars.length" class="subtle">{{ t('沒有排程') }}</p><button v-for="bar in day.bars" :key="bar.issue.id" class="schedule-bar" :class="barClass(bar, false)" :style="barStyle(bar.issue)" :aria-label="barLabel(bar)" @click="open(bar.issue)"><span class="identifier">{{ bar.issue.identifier }}</span><span class="schedule-title">{{ bar.issue.title }}</span><span v-if="!assignee && bar.issue.assigneeName" class="avatar small" aria-hidden="true">{{ initials(bar.issue.assigneeName) }}</span></button></li>
      </ol>
      <p v-if="result && !bars.length" class="schedule-status subtle">{{ t('這週沒有排程的任務') }}</p>
    </div>
    <footer class="schedule-footer"><span v-if="result?.unscheduled" class="subtle">{{ t('{0} 張任務尚未排程', [result.unscheduled]) }}</span><div v-if="result?.lastPage > 1" class="pagination" :aria-label="t('時程分頁')"><button class="text-button" :disabled="loading || page <= 1" @click="page--">{{ t('上一頁') }}</button><span>{{ page }} / {{ result.lastPage }}</span><button class="text-button" :disabled="loading || page >= result.lastPage" @click="page++">{{ t('下一頁') }}</button></div></footer>
  </section>
</template>
