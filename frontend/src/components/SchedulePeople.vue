<script setup>
import { t } from '../i18n/index.js';
import { computed, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import { dateInTaipei, isClosed } from '../domain/model.js';
import { addDays, layoutBars, weekOf } from '../domain/schedule.js';
import { useScheduleBars } from '../composables/useScheduleBars.js';
import { readContext, writeContext } from '../ui/workContext.js';
import Icon from './Icon.vue';
import PagedSelect from './PagedSelect.vue';
// TT-384：每位成員一列, 沒有任務的成員也列出, 才看得出誰有空；同一天同時進行 HEAVY 張以上標為負荷高。
const HEAVY = 3, SPANS = [14, 28];
const props = defineProps({ project: Object, contextKey: String });
const emit = defineEmits(['open', 'access']);
const saved = readContext(props.contextKey).people || {};
const validDay = value => typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(Date.parse(value)) && new Date(value).toISOString().slice(0, 10) === value;
const anchor = ref(validDay(saved.week) ? saved.week : dateInTaipei()), span = ref(SPANS.includes(saved.span) ? saved.span : SPANS[0]);
const assignee = ref(typeof saved.assignee === 'string' && saved.assignee.length <= 100 ? saved.assignee : '');
const includeClosed = ref(saved.includeClosed === true);
const result = shallowRef(null), members = shallowRef(null), loading = ref(false), error = ref(''), memberPage = ref(1);
const { today, barClass, barStyle, barLabel, initials } = useScheduleBars(() => props.project);
let generation = 0, request;
const range = computed(() => { const from = weekOf(anchor.value).from, to = addDays(from, span.value - 1); return { from, to, days: Array.from({ length: span.value }, (_, index) => addDays(from, index)) }; });
const weekdays = [t('一'), t('二'), t('三'), t('四'), t('五'), t('六'), t('日')];
// 列：目前成員分頁＋出現在結果但不在分頁的承辦人（例如已停用）＋未指派；篩選承辦人時只留該列。
const rows = computed(() => {
  const items = result.value?.items || [], list = new Map();
  if (!assignee.value) for (const member of members.value?.items || []) list.set(member.id, { id: member.id, name: member.name, disabled: false });
  for (const item of items) if (item.assignee && !list.has(item.assignee) && (!assignee.value || item.assignee === assignee.value)) list.set(item.assignee, { id: item.assignee, name: item.assigneeName, disabled: item.assigneeDisabled });
  if (assignee.value && !list.has(assignee.value)) list.set(assignee.value, { id: assignee.value, name: assigneeName.value, disabled: false });
  if (!assignee.value && items.some(item => !item.assignee)) list.set('', { id: '', name: t('未指派'), disabled: false });
  return [...list.values()].map(row => {
    const bars = layoutBars(items.filter(item => (item.assignee || '') === row.id), range.value.from, range.value.to);
    const load = range.value.days.map((_, index) => bars.filter(bar => !isClosed(bar.issue) && bar.start <= index && bar.end >= index).length);
    return { ...row, bars, load, lanes: Math.max(1, ...bars.map(bar => bar.lane + 1)), heavy: load.some(count => count >= HEAVY) };
  });
});
const assigneeName = computed(() => (members.value?.items || []).find(member => member.id === assignee.value)?.name || result.value?.items.find(item => item.assignee === assignee.value)?.assigneeName || '');
const assigneeOptions = computed(() => assignee.value && assigneeName.value ? [{ id: assignee.value, name: assigneeName.value }] : []);
const hasBars = computed(() => rows.value.some(row => row.bars.length));

async function load() {
  const current = ++generation;
  request?.abort(); request = new AbortController();
  loading.value = true;
  try {
    const [data, people] = await Promise.all([
      api.schedule(props.project.id, { from: range.value.from, to: range.value.to, assignee: assignee.value, includeClosed: includeClosed.value ? 1 : 0, perPage: 1000 }, { signal: request.signal }),
      // 篩選承辦人時仍讀成員分頁, 沒有排程的成員才有名稱可顯示。
      api.members(props.project.id, { page: memberPage.value, perPage: 100 }, { signal: request.signal }),
    ]);
    if (current !== generation) return;
    result.value = data.total > 1000 ? { ...data, items: [], epics: [] } : data; members.value = people; error.value = '';
  } catch (failure) {
    if (current !== generation || ['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) return;
    error.value = failure.message;
    if (['NOT_FOUND', 'FORBIDDEN', 'WORKSPACE_CHANGED', 'UNAUTHENTICATED'].includes(failure.code)) { result.value = null; emit('access'); }
  } finally { if (current === generation) loading.value = false; }
}
watch([() => props.project.id, () => range.value.from, span, assignee, includeClosed], () => { result.value = null; load(); }, { immediate: true });
watch(memberPage, load);
watch([anchor, span, assignee, includeClosed], () => writeContext(props.contextKey, { people: { includeClosed: includeClosed.value, week: anchor.value, span: span.value, assignee: assignee.value } }), { flush: 'post' });
onScopeDispose(() => { generation++; request?.abort(); });
defineExpose({ refresh: load });

function lookupMembers(filters, options) { return api.members(props.project.id, filters, options); }
function open(issue) { emit('open', { id: issue.id, projectId: props.project.id }); }
function label(date) { return `${Number(date.slice(5, 7))}/${Number(date.slice(8))}`; }
function weekday(index) { return weekdays[index % 7]; }
function shift(direction) { anchor.value = addDays(range.value.from, direction * 7); }
// 4 週時兩天以內的條狀太窄, 只留狀態色塊, 完整資訊在 title 與讀屏文字。
function compact(bar) { return span.value > 14 && bar.end - bar.start < 2; }
function rangeText(issue) { const start = issue.startDate || issue.endDate, end = issue.endDate || issue.startDate; return start === end ? label(start) : `${label(start)} – ${label(end)}`; }
</script>

<template>
  <section class="schedule-workspace people-schedule" :aria-label="t('人員排程')" :aria-busy="loading">
    <header class="section-title"><div><h2>{{ t('人員排程') }}</h2><p class="subtle">{{ t('每位成員一列, 同一天同時進行 {0} 張以上標為負荷高', [HEAVY]) }}</p></div><div class="member-filter"><PagedSelect v-model="assignee" :label="t('承辦人')" :context="`${project.id}:people-assignee`" :fixed-options="assigneeOptions" :load="lookupMembers" :empty-label="t('全部成員')" /></div></header>
    <div v-if="error" class="error-box" role="alert">{{ t(error) }}<button class="text-button" @click="load">{{ t('重試') }}</button></div>
    <div class="calendar schedule-week">
      <header class="calendar-toolbar"><label class="schedule-completed"><input v-model="includeClosed" type="checkbox"> {{ t('顯示已完成') }}</label><div><button class="icon-button previous" :aria-label="t('上一週')" @click="shift(-1)"><Icon name="chevron" /></button><strong class="schedule-range">{{ range.from }} – {{ range.to }}</strong><button class="icon-button" :aria-label="t('下一週')" @click="shift(1)"><Icon name="chevron" /></button></div><div class="people-tools"><div class="segmented" role="group" :aria-label="t('顯示期間')"><button v-for="option in SPANS" :key="option" type="button" :aria-pressed="span === option" :class="{ active: span === option }" @click="span = option">{{ t('{0} 週', [option / 7]) }}</button></div><button class="text-button" @click="anchor = dateInTaipei()">{{ t('本週') }}</button></div></header>
      <p v-if="loading && !result" class="schedule-status" role="status">{{ t('載入時程中…') }}</p>
      <div class="people-scroll">
        <div class="people-grid" role="table" :aria-label="t('人員排程')" :style="{ '--days': span }">
          <div class="people-row people-head" role="row"><span class="people-name" role="columnheader">{{ t('成員') }}</span><span v-for="(date, index) in range.days" :key="date" role="columnheader" class="people-date" :class="{ today: date === today, weekend: index % 7 > 4 }"><small>{{ weekday(index) }}</small>{{ label(date) }}</span></div>
          <div v-for="row in rows" :key="row.id || 'unassigned'" class="people-row" role="row" :class="{ heavy: row.heavy }">
            <span class="people-name" role="rowheader"><span class="avatar small" aria-hidden="true">{{ row.id ? initials(row.name) : '—' }}</span><span class="people-label">{{ row.name }}<small v-if="row.disabled" class="subtle">{{ t('已停用') }}</small></span><small v-if="row.heavy" class="people-heavy">{{ t('負荷高') }}</small></span>
            <div class="people-lanes" role="cell" :style="{ gridTemplateRows: `repeat(${row.lanes}, 34px)` }">
              <span v-for="(count, index) in row.load" :key="index" class="people-cell" :class="{ today: range.days[index] === today, weekend: index % 7 > 4, busy: count >= HEAVY }" :style="{ gridColumn: index + 1 }" aria-hidden="true"></span>
              <button v-for="bar in row.bars" :key="bar.issue.id" class="schedule-bar" :class="{ ...barClass(bar), compact: compact(bar) }" :style="{ ...barStyle(bar.issue), gridColumn: `${bar.start + 1} / ${bar.end + 2}`, gridRow: bar.lane + 1 }" :aria-label="barLabel(bar)" :title="barLabel(bar)" @click="open(bar.issue)"><span class="identifier">{{ bar.issue.identifier }}</span><span class="schedule-title">{{ bar.issue.title }}</span></button>
            </div>
          </div>
        </div>
      </div>
      <ol class="schedule-agenda people-agenda" :aria-label="t('成員排程清單')">
        <li v-for="row in rows" :key="row.id || 'unassigned'"><h3>{{ row.name }}<small v-if="row.heavy" class="people-heavy">{{ t('負荷高') }}</small></h3><p v-if="!row.bars.length" class="subtle">{{ t('這段期間沒有排程') }}</p><button v-for="bar in row.bars" :key="bar.issue.id" class="schedule-bar" :class="barClass(bar, false)" :style="barStyle(bar.issue)" :aria-label="barLabel(bar)" @click="open(bar.issue)"><span class="identifier">{{ bar.issue.identifier }}</span><span class="schedule-title">{{ bar.issue.title }}</span><small class="people-range">{{ rangeText(bar.issue) }}</small></button></li>
      </ol>
      <p v-if="result && !hasBars" class="schedule-status subtle">{{ t('這段期間沒有排程') }}</p>
    </div>
    <p v-if="result?.total > 1000" role="alert" class="schedule-status">{{ t('任務超過 1000 張, 請縮短期間或加篩選') }}</p>
    <footer class="schedule-footer"><span v-if="result?.unscheduled" class="subtle">{{ t('{0} 張任務尚未排程', [result.unscheduled]) }}</span>
      <div v-if="!assignee && members?.lastPage > 1" class="pagination" :aria-label="t('成員分頁')"><button class="text-button" :disabled="loading || memberPage <= 1" @click="memberPage--">{{ t('上一頁') }}</button><span>{{ memberPage }} / {{ members.lastPage }}</span><button class="text-button" :disabled="loading || memberPage >= members.lastPage" @click="memberPage++">{{ t('下一頁') }}</button></div>

    </footer>
  </section>
</template>
