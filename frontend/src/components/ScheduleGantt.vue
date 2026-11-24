<script setup>
import { t } from '../i18n/index.js';
import { computed, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import { dateInTaipei } from '../domain/model.js';
import { addDays, daysBetween, layoutBars, scheduleSpan, weekOf } from '../domain/schedule.js';
import { useScheduleBars } from '../composables/useScheduleBars.js';
import { readContext, writeContext } from '../ui/workContext.js';
import Icon from './Icon.vue';
// TT-386：Project → Epic → 卡片三層, 一張卡一列；到期日用菱形標記與計畫條分開。依賴箭頭與拖曳改期未授權, 不提供。
const SPANS = [28, 84];
const props = defineProps({ project: Object, contextKey: String });
const emit = defineEmits(['open', 'access']);
const saved = readContext(props.contextKey).gantt || {};
const validDay = value => typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(Date.parse(value)) && new Date(value).toISOString().slice(0, 10) === value;
const anchor = ref(validDay(saved.week) ? saved.week : dateInTaipei()), span = ref(SPANS.includes(saved.span) ? saved.span : SPANS[0]);
const collapsed = ref(Array.isArray(saved.collapsed) ? saved.collapsed.filter(id => typeof id === 'string').slice(0, 200) : []);
const result = shallowRef(null), loading = ref(false), error = ref(''), page = ref(1);
const { today, barClass, barStyle, barLabel } = useScheduleBars(() => props.project);
let generation = 0, request;
const range = computed(() => { const from = weekOf(anchor.value).from, to = addDays(from, span.value - 1); return { from, to, days: Array.from({ length: span.value }, (_, index) => addDays(from, index)) }; });
// 表頭：4 週逐日, 12 週只標每週一；月份另起一列。
const months = computed(() => {
  const list = [];
  range.value.days.forEach((date, index) => { const key = date.slice(0, 7); if (!list.length || list.at(-1).key !== key) list.push({ key, label: t('{0}年{1}月', [date.slice(0, 4), Number(date.slice(5, 7))]), start: index, length: 0 }); list.at(-1).length++; });
  return list;
});
const ticks = computed(() => range.value.days.map((date, index) => ({ date, index, label: span.value <= 28 || index % 7 === 0 ? String(Number(date.slice(8))) : '' })));
const todayIndex = computed(() => { const index = daysBetween(range.value.from, today); return index >= 0 && index < span.value ? index : -1; });

function place(issue) {
  const [bar] = layoutBars([issue], range.value.from, range.value.to);
  return bar || null;
}
function dueMark(issue) {
  if (!issue.dueDate || issue.dueDate < range.value.from || issue.dueDate > range.value.to) return null;
  return { index: daysBetween(range.value.from, issue.dueDate), late: Boolean(scheduleSpan(issue)?.end > issue.dueDate) };
}
function derived(children) {
  const spans = children.map(scheduleSpan).filter(Boolean);
  if (!spans.length) return null;
  const startDate = spans.reduce((min, item) => item.start < min ? item.start : min, spans[0].start), endDate = spans.reduce((max, item) => item.end > max ? item.end : max, spans[0].end);
  return place({ id: '', startDate, endDate, state: '' });
}
// 群組：Epic 本身有日期時用自己的條狀, 沒有時依子卡推算（虛線）；沒有 Epic 的卡片放最後, 不像部分競品直接隱藏。
const groups = computed(() => {
  const items = result.value?.items || [], epics = new Map((result.value?.epics || []).map(epic => [epic.id, { ...epic, issue: null }]));
  for (const item of items) if (item.type === 'epic') epics.set(item.id, { id: item.id, identifier: item.identifier, title: item.title, issue: item });
  const list = [...epics.values()].sort((a, b) => a.identifier.localeCompare(b.identifier, undefined, { numeric: true })).map(epic => {
    const children = items.filter(item => item.epicId === epic.id && item.type !== 'epic');
    return { id: epic.id, epic, children, bar: epic.issue ? place(epic.issue) : derived(children), derived: !epic.issue };
  });
  const loose = items.filter(item => item.type !== 'epic' && !epics.has(item.epicId));
  if (loose.length) list.push({ id: '', epic: null, children: loose, bar: null, derived: false });
  return list;
});
const summary = computed(() => derived(result.value?.items || []));
const empty = computed(() => result.value && !result.value.items.length);

async function load() {
  const current = ++generation;
  request?.abort(); request = new AbortController();
  loading.value = true;
  try {
    const data = await api.schedule(props.project.id, { from: range.value.from, to: range.value.to, page: page.value, perPage: 200 }, { signal: request.signal });
    if (current !== generation) return;
    result.value = data; error.value = '';
  } catch (failure) {
    if (current !== generation || ['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) return;
    error.value = failure.message;
    if (['NOT_FOUND', 'FORBIDDEN', 'WORKSPACE_CHANGED', 'UNAUTHENTICATED'].includes(failure.code)) { result.value = null; emit('access'); }
  } finally { if (current === generation) loading.value = false; }
}
watch([() => props.project.id, () => range.value.from, span], () => { result.value = null; if (page.value !== 1) page.value = 1; else load(); }, { immediate: true });
watch(page, load);
watch([anchor, span, collapsed], () => writeContext(props.contextKey, { gantt: { week: anchor.value, span: span.value, collapsed: collapsed.value } }), { flush: 'post', deep: true });
onScopeDispose(() => { generation++; request?.abort(); });
defineExpose({ refresh: load });

function open(issue) { emit('open', { id: issue.id, projectId: props.project.id }); }
function toggle(id) { collapsed.value = collapsed.value.includes(id) ? collapsed.value.filter(item => item !== id) : [...collapsed.value, id]; }
function isCollapsed(id) { return collapsed.value.includes(id || 'loose'); }
function groupKey(group) { return group.id || 'loose'; }
function shift(direction) { anchor.value = addDays(range.value.from, direction * (span.value > 28 ? 28 : 7)); }
function label(date) { return `${Number(date.slice(5, 7))}/${Number(date.slice(8))}`; }
function rangeText(issue) { const value = scheduleSpan(issue); return !value ? '' : value.start === value.end ? label(value.start) : `${label(value.start)} – ${label(value.end)}`; }
function dueLabel(issue, mark) { return mark.late ? t('到期日 {0}, 計畫晚於到期日', [issue.dueDate]) : t('到期日 {0}', [issue.dueDate]); }
function isLate(issue) { return Boolean(issue.dueDate && scheduleSpan(issue)?.end > issue.dueDate); }
function barColumns(bar) { return `${bar.start + 1} / ${bar.end + 2}`; }
</script>

<template>
  <section class="schedule-workspace gantt-schedule" :aria-label="t('專案甘特')" :aria-busy="loading">
    <header class="section-title"><div><h2>{{ t('專案甘特') }}</h2><p class="subtle">{{ t('依 Epic 分組顯示計畫期間, 菱形是到期日') }}</p></div></header>
    <div v-if="error" class="error-box" role="alert">{{ t(error) }}<button class="text-button" @click="load">{{ t('重試') }}</button></div>
    <div class="calendar schedule-week">
      <header class="calendar-toolbar"><div><button class="icon-button previous" :aria-label="t('往前')" @click="shift(-1)"><Icon name="chevron" /></button><strong class="schedule-range">{{ range.from }} – {{ range.to }}</strong><button class="icon-button" :aria-label="t('往後')" @click="shift(1)"><Icon name="chevron" /></button></div><div class="people-tools"><div class="segmented" role="group" :aria-label="t('顯示期間')"><button v-for="option in SPANS" :key="option" type="button" :aria-pressed="span === option" :class="{ active: span === option }" @click="span = option">{{ t('{0} 週', [option / 7]) }}</button></div><button class="text-button" @click="anchor = dateInTaipei()">{{ t('本週') }}</button></div></header>
      <p v-if="loading && !result" class="schedule-status" role="status">{{ t('載入時程中…') }}</p>
      <div class="people-scroll">
        <div class="gantt-grid" :style="{ '--days': span }">
          <div class="gantt-row gantt-months" aria-hidden="true"><span class="gantt-name"></span><div class="gantt-track"><span v-for="month in months" :key="month.key" class="gantt-month" :style="{ gridColumn: `${month.start + 1} / span ${month.length}` }">{{ month.label }}</span></div></div>
          <div class="gantt-row gantt-head" aria-hidden="true"><span class="gantt-name">{{ t('項目') }}</span><div class="gantt-track"><span v-for="tick in ticks" :key="tick.date" class="gantt-tick" :class="{ today: tick.index === todayIndex, weekend: tick.index % 7 > 4 }" :style="{ gridColumn: tick.index + 1 }">{{ tick.label }}</span></div></div>
          <ul class="gantt-body" :aria-label="t('甘特項目')">
            <li class="gantt-row gantt-project"><span class="gantt-name"><Icon name="folder" :size="15" /><strong>{{ project.name }}</strong><small class="subtle">{{ t('依本頁任務推算') }}</small></span><div class="gantt-track"><span v-if="todayIndex >= 0" class="gantt-today" :style="{ gridColumn: todayIndex + 1 }" aria-hidden="true"></span><span v-if="summary" class="gantt-summary" :style="{ gridColumn: barColumns(summary) }" aria-hidden="true"></span></div></li>
            <template v-for="group in groups" :key="groupKey(group)">
              <li class="gantt-row gantt-epic">
                <span class="gantt-name"><button type="button" class="icon-button gantt-toggle" :aria-expanded="!isCollapsed(group.id)" :aria-label="t('{0}：{1}張卡片', [group.epic ? `${group.epic.identifier} ${group.epic.title}` : t('未歸入 Epic'), group.children.length])" @click="toggle(groupKey(group))"><Icon name="chevron" :size="14" /></button><template v-if="group.epic"><button v-if="group.epic.issue" type="button" class="gantt-link" @click="open(group.epic.issue)"><span class="identifier">{{ group.epic.identifier }}</span>{{ group.epic.title }}</button><span v-else class="gantt-link static"><span class="identifier">{{ group.epic.identifier }}</span>{{ group.epic.title }}</span></template><span v-else class="gantt-link static">{{ t('未歸入 Epic') }}</span><small class="gantt-count">{{ group.children.length }}</small></span>
                <div class="gantt-track"><span v-if="todayIndex >= 0" class="gantt-today" :style="{ gridColumn: todayIndex + 1 }" aria-hidden="true"></span>
                  <button v-if="group.bar && group.epic?.issue" type="button" class="schedule-bar gantt-epic-bar" :class="barClass(group.bar)" :style="{ ...barStyle(group.epic.issue), gridColumn: barColumns(group.bar) }" :aria-label="barLabel(group.bar)" :title="barLabel(group.bar)" @click="open(group.epic.issue)"></button>
                  <span v-else-if="group.bar" class="gantt-derived" :class="{ before: group.bar.continuesBefore, after: group.bar.continuesAfter }" :style="{ gridColumn: barColumns(group.bar) }" :title="t('依子卡片推算')"></span>
                </div>
              </li>
              <template v-if="!isCollapsed(group.id)">
                <li v-for="issue in group.children" :key="issue.id" class="gantt-row gantt-item">
                  <span class="gantt-name"><button type="button" class="gantt-link" @click="open(issue)"><span class="identifier">{{ issue.identifier }}</span><span class="schedule-title">{{ issue.title }}</span></button></span>
                  <div class="gantt-track"><span v-if="todayIndex >= 0" class="gantt-today" :style="{ gridColumn: todayIndex + 1 }" aria-hidden="true"></span>
                    <template v-if="place(issue)"><button type="button" class="schedule-bar" :class="barClass(place(issue))" :style="{ ...barStyle(issue), gridColumn: barColumns(place(issue)) }" :aria-label="barLabel(place(issue))" :title="barLabel(place(issue))" @click="open(issue)"></button></template>
                    <span v-if="dueMark(issue)" class="gantt-due" :class="{ late: dueMark(issue).late }" :style="{ gridColumn: dueMark(issue).index + 1 }" role="img" :aria-label="dueLabel(issue, dueMark(issue))" :title="dueLabel(issue, dueMark(issue))"></span>
                  </div>
                </li>
              </template>
            </template>
          </ul>
        </div>
      </div>
      <ol class="schedule-agenda people-agenda gantt-agenda" :aria-label="t('甘特清單')">
        <li v-for="group in groups" :key="groupKey(group)"><h3>{{ group.epic ? `${group.epic.identifier} ${group.epic.title}` : t('未歸入 Epic') }}<small v-if="group.bar" class="people-range">{{ group.derived ? t('推算') : '' }} {{ label(addDays(range.from, group.bar.start)) }} – {{ label(addDays(range.from, group.bar.end)) }}</small></h3><button v-for="issue in group.children" :key="issue.id" class="schedule-bar" :class="place(issue) ? barClass(place(issue), false) : {}" :style="barStyle(issue)" :aria-label="place(issue) ? barLabel(place(issue)) : issue.title" @click="open(issue)"><span class="identifier">{{ issue.identifier }}</span><span class="schedule-title">{{ issue.title }}</span><small class="people-range">{{ rangeText(issue) }}<template v-if="issue.dueDate"> · <span :class="{ late: isLate(issue) }">{{ isLate(issue) ? t('到期 {0}, 計畫較晚', [label(issue.dueDate)]) : t('到期 {0}', [label(issue.dueDate)]) }}</span></template></small></button></li>
      </ol>
      <p v-if="empty" class="schedule-status subtle">{{ t('這段期間沒有排程') }}</p>
    </div>
    <footer class="schedule-footer"><span v-if="result?.unscheduled" class="subtle">{{ t('{0} 張任務尚未排程', [result.unscheduled]) }}</span><div v-if="result?.lastPage > 1" class="pagination" :aria-label="t('時程分頁')"><button class="text-button" :disabled="loading || page <= 1" @click="page--">{{ t('上一頁') }}</button><span>{{ page }} / {{ result.lastPage }}</span><button class="text-button" :disabled="loading || page >= result.lastPage" @click="page++">{{ t('下一頁') }}</button></div></footer>
  </section>
</template>
