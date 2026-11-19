<script setup>
import { t } from '../i18n/index.js';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Icon from './Icon.vue';
import TagLabels from './TagLabels.vue';
import IssueContext from './IssueContext.vue';
import { buildIssueIndex } from '../domain/issueIndex.js';
import { isClosed, PRIORITIES, statusesFor, TYPES } from '../domain/model.js';

const props = defineProps({ syncState: { type: String, default: 'polling' }, project: Object, issues: Array, visibleIssues: Array, entries: Array, members: Array, userId: String, moving: String, today: String, boardKey: String, includedEpicIds: Array, canWrite: { type: Boolean, default: true }, canCreate: { type: Boolean, default: true }, manual: Boolean });
const emit = defineEmits(['open', 'move', 'create', 'drag', 'reorder']);
const group = ref('type');
const density = ref('comfortable');
const collapsed = ref([]);
const dragOver = ref('');
// TT-428：自訂順序時拖曳要放到欄位內的哪個位置；index 是目標格放下後的位置（不含被拖的卡）
const dropAt = ref({ cell: '', index: -1 });
const announcement = ref('');
const canReorder = computed(() => props.manual && props.canWrite && !props.moving);
let draggedId = '';
const states = computed(() => statusesFor(props.project));
const preferenceKey = computed(() => `tasktracker-board:${props.userId}:${props.project.id}${props.boardKey && props.boardKey !== 'default' ? `:${props.boardKey}` : ''}`);
watch(preferenceKey, () => {
  let saved = {};
  try { saved = JSON.parse(localStorage.getItem(preferenceKey.value) || '{}'); } catch {}
  // 舊版會自動保存none, 不能視為使用者明選；新版才保留明選的不分組。
  group.value = saved?.group === 'epic' ? 'epic' : saved?.version === 2 && saved?.group === 'none' ? 'none' : 'type';
  density.value = saved?.density === 'compact' ? 'compact' : 'comfortable';
  collapsed.value = Array.isArray(saved?.collapsed) ? saved.collapsed.filter(id => typeof id === 'string') : [];
}, { immediate: true });
watch([group, density, collapsed], () => {
  try { localStorage.setItem(preferenceKey.value, JSON.stringify({ version: 2, group: group.value, density: density.value, collapsed: collapsed.value })); } catch {}
});
const contextIndex = computed(() => buildIssueIndex(props.issues, props.entries));
const membersById = computed(() => new Map(props.members.map(member => [member.id, member.name])));
const stateCounts = computed(() => {
  const counts = new Map();
  for (const issue of props.visibleIssues) counts.set(issue.state, (counts.get(issue.state) || 0) + 1);
  return counts;
});
// 大型看板跨frame分批掛載：首批先呈現，之後每frame加一批直到全部卡片；欄位計數與進度永遠用完整數量。
// 只記數量不存卡片，範圍切換、撤權或新載入時直接依目前props重算，舊scope的卡片不會插回。
const BATCH = 50;
const renderLimit = ref(BATCH);
let batchFrame = 0;
function scheduleBatch() {
  cancelAnimationFrame(batchFrame);
  if (renderLimit.value >= props.visibleIssues.length) return;
  batchFrame = requestAnimationFrame(() => { renderLimit.value += BATCH; scheduleBatch(); });
}
watch(() => [props.project.id, props.boardKey], () => { renderLimit.value = BATCH; scheduleBatch(); });
watch(() => props.visibleIssues.length, (count, previous = 0) => {
  const shown = Math.min(previous, renderLimit.value);
  renderLimit.value = count - shown > BATCH ? shown + BATCH : Math.max(renderLimit.value, count);
  scheduleBatch();
}, { immediate: true });
onBeforeUnmount(() => cancelAnimationFrame(batchFrame));
const renderingAll = computed(() => renderLimit.value >= props.visibleIssues.length);
const lanes = computed(() => {
  const rendered = renderingAll.value ? null : new Set(props.visibleIssues.slice(0, renderLimit.value).map(issue => issue.id));
  let rows;
  if (group.value === 'type') rows = [
    { id: 'type:epic', label: 'Epic', issues: props.visibleIssues.filter(issue => issue.type === 'epic') },
    { id: 'type:other', label: t('其他卡片'), issues: props.visibleIssues.filter(issue => issue.type !== 'epic') },
  ];
  else if (group.value === 'none') rows = [{ id: '', label: t('全部任務'), issues: props.visibleIssues }];
  else {
    const included = props.includedEpicIds ? new Set(props.includedEpicIds) : null;
    const epics = props.issues.filter(issue => issue.type === 'epic' && (!included || included.has(issue.id)));
    rows = epics.map(epic => ({ id: epic.id, label: epic.title, epic, issues: [] }));
    rows.push({ id: '', label: t('未歸類到 Epic'), issues: [] });
    const byId = new Map(rows.map(row => [row.id, row]));
    for (const issue of props.visibleIssues) {
      const key = byId.has(issue.id) ? issue.id : byId.has(issue.epicId) ? issue.epicId : '';
      byId.get(key).issues.push(issue);
    }
  }
  return rows.map(row => {
    const columns = new Map(states.value.map(state => [state.id, []]));
    const counts = new Map();
    for (const issue of row.issues) {
      counts.set(issue.state, (counts.get(issue.state) || 0) + 1);
      if (!rendered || rendered.has(issue.id)) columns.get(issue.state)?.push(issue);
    }
    return { ...row, columns, counts };
  });
});
function creationDefaults(lane, state) {
  return { state, epicId: group.value === 'epic' ? lane.id : '', type: group.value === 'type' && lane.id === 'type:epic' ? 'epic' : 'task' };
}
// 按編輯開抽屜會讓版面移動, 雙擊的第二下可能落在卡片本體；剛按過編輯就不再導到完整頁
let editClickedAt = -Infinity;
function edit(issue) { editClickedAt = performance.now(); emit('open', issue, false); }
function read(issue, event) {
  if (props.moving || (event?.type === 'dblclick' && performance.now() - editClickedAt < 600) || event?.target.closest('button:not(.card-title), select, label, a')) return;
  emit('open', issue, true);
}
onBeforeUnmount(() => emit('drag', false));
function toggle(id) { collapsed.value = collapsed.value.includes(id) ? collapsed.value.filter(item => item !== id) : [...collapsed.value, id]; }
function memberName(id) { return membersById.value.get(id) || id || t('未指派'); }
function option(items, id) { return items.find(item => item.id === id); }
function overdue(issue) { return issue.dueDate && issue.dueDate < props.today && !isClosed(issue); }
function startDrag(event, issue) { draggedId = issue.id; emit('drag', true); event.dataTransfer.setData('text/plain', issue.id); event.dataTransfer.effectAllowed = 'move'; }
function endDrag() { dragOver.value = ''; dropAt.value = { cell: '', index: -1 }; draggedId = ''; emit('drag', false); }
/** 依滑鼠高度找出要插在哪張卡前面；只算目前格子裡其他的卡 */
function trackDrop(event, lane, state) {
  dragOver.value = `${lane.id}:${state}`;
  if (!props.manual || !renderingAll.value) return;
  const cards = [...event.currentTarget.querySelectorAll(':scope > .issue-card')].filter(card => card.dataset.id !== draggedId);
  const index = cards.findIndex(card => { const box = card.getBoundingClientRect(); return event.clientY < box.top + box.height / 2; });
  dropAt.value = { cell: dragOver.value, index: index === -1 ? cards.length : index };
}
function dropBefore(lane, state, issue) {
  const target = dropAt.value;
  if (target.cell !== `${lane.id}:${state}` || target.index < 0) return false;
  const others = (lane.columns.get(state) || []).filter(item => item.id !== draggedId);
  return target.index < others.length ? others[target.index].id === issue.id : false;
}
function dropAtEnd(lane, state) {
  const target = dropAt.value;
  return target.cell === `${lane.id}:${state}` && target.index >= (lane.columns.get(state) || []).filter(item => item.id !== draggedId).length;
}
/** 拖曳能不能放到這一格：Epic／其他卡片分區不能互換 */
function fieldsFor(issue, state, lane) {
  if (group.value === 'type' && (issue.type === 'epic') !== (lane.id === 'type:epic')) return null;
  const fields = { ...(issue.state !== state ? { state } : {}), ...(group.value === 'epic' && issue.type !== 'epic' && (issue.epicId || '') !== lane.id ? { epicId: lane.id } : {}) };
  return fields;
}
/** 自訂順序：送出目標格放下後的完整順序；順序和欄位都沒變就不送 */
function reorder(issue, state, lane, index) {
  const fields = fieldsFor(issue, state, lane);
  if (!fields) return;
  const current = (lane.columns.get(state) || []).map(item => item.id);
  const others = current.filter(id => id !== issue.id);
  const orderedIds = [...others.slice(0, index), issue.id, ...others.slice(index)];
  if (!Object.keys(fields).length && orderedIds.join() === current.join()) return;
  emit('reorder', issue, { fields, orderedIds });
  return orderedIds.indexOf(issue.id);
}
/** 鍵盤與觸控：在同一格內上移或下移一張 */
function step(issue, state, lane, delta) {
  if (!canReorder.value || !renderingAll.value) return;
  const ids = (lane.columns.get(state) || []).map(item => item.id);
  const from = ids.indexOf(issue.id), to = from + delta;
  if (from < 0 || to < 0 || to >= ids.length) { announcement.value = t('已經在最{0}', [delta < 0 ? t('上方') : t('下方')]); return; }
  reorder(issue, state, lane, to);
  announcement.value = t('{0} 移到第 {1} 張', [issue.identifier, to + 1]);
}
function drop(event, state, lane) {
  const index = dropAt.value.cell === `${lane.id}:${state}` ? dropAt.value.index : -1;
  dragOver.value = ''; dropAt.value = { cell: '', index: -1 };
  const issue = !props.moving && props.canWrite && props.visibleIssues.find(item => item.id === event.dataTransfer.getData('text/plain'));
  if (issue && props.manual && renderingAll.value && index >= 0) reorder(issue, state, lane, index);
  else if (issue && (group.value !== 'type' || (issue.type === 'epic') === (lane.id === 'type:epic'))) emit('move', issue, { state, ...(group.value === 'epic' && issue.type !== 'epic' ? { epicId: lane.id } : {}) });
  draggedId = '';
  // 先送move讓送出中狀態接手暫停，再解除拖曳；卡片被樂觀移動後dragend可能不會觸發。
  emit('drag', false);
}
</script>

<template>
  <div class="visual-board" :class="{ 'compact-cards': density === 'compact', 'type-sections': group === 'type' }" :aria-label="t('任務看板')" :aria-busy="!renderingAll">
    <div class="board-tools">
      <div><span class="board-sync" :class="{ live: syncState === 'live' }" role="status" :aria-label="t('看板同步狀態')"><i></i>{{ syncState === 'live' ? t('即時同步') : syncState === 'connecting' ? t('連線中…') : t('定期更新') }}</span><span v-if="!renderingAll" class="board-sync" role="status" :aria-label="t('看板卡片呈現進度')">{{ t('顯示中') }} {{ Math.min(renderLimit, visibleIssues.length) }} / {{ visibleIssues.length }}</span><label>{{ t('泳道') }}<select v-model="group" :aria-label="t('看板泳道')"><option value="type">{{ t('Epic 與其他卡片') }}</option><option value="epic">{{ t('依 Epic 分組') }}</option><option value="none">{{ t('不分組') }}</option></select></label><label>{{ t('卡片') }}<select v-model="density" :aria-label="t('看板卡片密度')"><option value="comfortable">{{ t('詳細') }}</option><option value="compact">{{ t('精簡') }}</option></select></label></div>
    </div>
    <div class="board-scroll" tabindex="0" :aria-label="t('看板橫向捲動區')">
      <div class="board-canvas" :style="{ '--board-columns': states.length }">
        <div v-if="group !== 'type'" class="board-headings">
          <div v-for="state in states" :key="state.id" :data-state="state.id">
            <header class="column-header"><span><i :style="{ background: state.color }"></i>{{ state.label }}<b>{{ stateCounts.get(state.id) || 0 }}</b></span><button v-if="canCreate" class="icon-button" :aria-label="t('新增{0}任務', [state.label])" :disabled="Boolean(moving) || !canWrite" @click="emit('create', { state: state.id, epicId: '' })"><Icon name="plus" :size="16" /></button></header>
          </div>
        </div>
        <section v-for="lane in lanes" :key="lane.id" class="board-lane" :aria-label="t('泳道 {0}', [lane.label])">
          <header v-if="group !== 'none'" class="lane-header">
            <button class="lane-toggle" :aria-label="t('收合或展開 {0}', [lane.label])" :aria-expanded="!collapsed.includes(lane.id)" @click="toggle(lane.id)"><Icon name="chevron" :size="14" :class="{ expanded: !collapsed.includes(lane.id) }" /><strong>{{ lane.label }}</strong></button>
            <button v-if="lane.epic" class="lane-epic-link" :aria-label="t('查看 Epic {0}', [lane.epic.identifier])" @click="emit('open', lane.epic, true)">{{ lane.epic.identifier }}</button>
            <span class="lane-total">{{ lane.issues.length }} {{ t('張卡片') }}</span>
          </header>
          <template v-if="group === 'none' || !collapsed.includes(lane.id)">
            <p v-if="group === 'type' && !lane.issues.length" class="lane-empty">{{ t('沒有符合條件的卡片') }}<button v-if="canCreate" type="button" class="lane-add" :disabled="Boolean(moving) || !canWrite" :aria-label="t('在{0}新增{1}任務', [lane.label, states[0].label])" @click="emit('create', creationDefaults(lane, states[0].id))"><Icon name="plus" :size="15" />{{ t('新增卡片') }}</button></p>
            <template v-else>
              <div v-if="group === 'type'" class="board-headings">
                <div v-for="state in states" :key="state.id" :data-state="state.id">
                  <header class="column-header"><span><i :style="{ background: state.color }"></i>{{ state.label }}<b>{{ lane.counts.get(state.id) || 0 }}</b></span><button v-if="canCreate" class="icon-button" :aria-label="t('在{0}新增{1}任務', [lane.label, state.label])" :disabled="Boolean(moving) || !canWrite" @click="emit('create', creationDefaults(lane, state.id))"><Icon name="plus" :size="16" /></button></header>
                </div>
              </div>
              <div class="lane-columns">
                <div v-for="state in states" :key="state.id" class="board-cell" :class="{ 'drag-over': dragOver === `${lane.id}:${state.id}` }" :data-column="state.id" @dragover.prevent="trackDrop($event, lane, state.id)" @dragleave.self="dragOver = ''; dropAt = { cell: '', index: -1 }" @drop.prevent="drop($event, state.id, lane)">
                  <article v-for="issue in lane.columns.get(state.id)" :key="issue.id" class="issue-card" :class="{ moving: moving === issue.id, 'drop-before': dropBefore(lane, state.id, issue) }" :draggable="canWrite && !moving" :data-issue="issue.identifier" :data-id="issue.id" :aria-label="`${issue.identifier} ${issue.title} · ${state.label}`" @dblclick="read(issue, $event)" @dragstart="startDrag($event, issue)" @dragend="endDrag" @keydown.alt.up.prevent="step(issue, state.id, lane, -1)" @keydown.alt.down.prevent="step(issue, state.id, lane, 1)">
                    <div class="card-top"><span class="identifier">{{ issue.identifier }}</span><span class="type-tag">{{ option(TYPES, issue.type)?.label }}</span><span v-if="density === 'comfortable'" class="priority-label" :style="{ '--priority-color': option(PRIORITIES, issue.priority)?.color }" :title="t('優先級：{0}', [t(option(PRIORITIES, issue.priority)?.label)])"><span class="priority-bars" :class="issue.priority"><i></i><i></i><i></i></span></span><button v-if="canWrite" type="button" class="icon-button card-edit" :aria-label="t('編輯 {0}', [issue.identifier])" :title="t('編輯任務')" :disabled="Boolean(moving)" @click.stop="edit(issue)" @dblclick.stop><Icon name="edit" :size="15" /></button></div>
                    <button class="card-title" :aria-keyshortcuts="manual && canWrite ? 'Alt+ArrowUp Alt+ArrowDown' : undefined" :title="issue.title" @click="$event.detail === 0 && read(issue, $event)">{{ issue.title }}</button>
                    <p v-if="density === 'comfortable' && issue.description" class="board-card-description">{{ issue.description }}</p>
                    <TagLabels :tags="issue.tags || []" />
                    <div class="card-bottom"><span v-if="manual && canWrite" class="order-buttons"><button type="button" class="icon-button" :aria-label="t('{0} 上移', [issue.identifier])" :disabled="!canReorder" @click.stop="step(issue, state.id, lane, -1)"><Icon name="arrowUp" :size="14" /></button><button type="button" class="icon-button" :aria-label="t('{0} 下移', [issue.identifier])" :disabled="!canReorder" @click.stop="step(issue, state.id, lane, 1)"><Icon name="arrowDown" :size="14" /></button></span><span v-if="density === 'comfortable' && issue.dueDate" :class="['due-date', { overdue: overdue(issue) }]"><Icon name="clock" :size="12" />{{ issue.dueDate.slice(5).replace('-', '/') }}</span><span v-if="density === 'comfortable' && (issue.commentCount ?? issue.comments?.length ?? 0)" class="comment-count"><Icon name="message" :size="13" />{{ (issue.commentCount ?? issue.comments?.length ?? 0) }}</span><span class="board-assignee" :title="memberName(issue.assignee)"><span v-if="issue.assignee" class="avatar small">{{ memberName(issue.assignee).slice(0, 1) }}</span>{{ memberName(issue.assignee) }}</span></div>
                    <IssueContext v-if="density === 'comfortable'" :issue="issue" :index="contextIndex" :can-read-time="project.permissions?.includes('timeEntry.readOwn')" @open="emit('open', $event, true)" />
                  </article>
                  <div v-if="dropAtEnd(lane, state.id)" class="drop-line" aria-hidden="true"></div>
                  <button v-if="canCreate" class="lane-add" :aria-label="t('在{0}新增{1}任務', [lane.label, state.label])" :disabled="Boolean(moving) || !canWrite" @click="emit('create', creationDefaults(lane, state.id))"><Icon name="plus" :size="15" /><span>{{ t('新增卡片') }}</span></button>
                </div>
              </div>
            </template>
          </template>
        </section>
      </div>
    </div>
    <p class="sr-only" role="status" aria-live="polite">{{ announcement }}</p>
    <p v-if="group !== 'type' && !visibleIssues.length" class="board-empty-note">{{ t('沒有符合條件的卡片') }}</p>
  </div>
</template>

<style scoped>
.visual-board { background: #f7f8fb; position: relative; overflow: hidden; min-width: 0; max-width: 100%; }
.board-tools { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 16px; padding: 14px 20px; background: white; border-bottom: 1px solid #e7e9ef; font-size: 13px; color: #5f6673; }
.board-tools > div, .board-tools label { display: flex; align-items: center; gap: 10px; }
.board-tools > div { gap: 18px; flex-wrap: wrap; }.board-tools label { white-space: nowrap; }
.board-sync { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; color: #5f6673; }
.board-sync i { width: 6px; height: 6px; border-radius: 50%; background: #959ca8; }
.board-sync.live { color: #286b55; }.board-sync.live i { background: #397e71; }
.board-tools select { border: 1px solid #e0deea; border-radius: 5px; padding: 6px 9px; color: #354052; background: white; }
.board-scroll { overflow-x: auto; overflow-y: hidden; min-height: 240px; outline-offset: -2px; }
.board-canvas { min-width: max(100%, calc(var(--board-columns) * 250px)); }
.board-headings, .lane-columns { display: grid; grid-template-columns: repeat(var(--board-columns), minmax(250px, 1fr)); }
.board-headings { position: sticky; top: 0; z-index: 2; background: #f4f5f9; border-bottom: 1px solid #e0e3eb; }
.board-headings > div { border-right: 1px solid #e3e5ec; }
.column-header { padding: 10px 12px; margin: 0; }
.lane-header { display: flex; align-items: center; gap: 12px; padding: 9px 12px; border-bottom: 1px solid #e3e5ec; background: #f0f2f7; min-height: 42px; }
.lane-toggle { display: flex; align-items: center; gap: 8px; color: #343b49; font-size: 14px; text-align: left; min-width: 0; }
.lane-toggle strong { overflow-wrap: anywhere; }
.lane-toggle svg { flex-shrink: 0; transition: transform .15s; }
.lane-toggle .expanded { transform: rotate(90deg); }
.lane-epic-link { font-size: 12px; color: #5f6673; white-space: nowrap; }
.type-sections .lane-header { padding-right: 20px; }
.lane-total { margin-left: auto; font-size: 12px; color: #5f6673; white-space: nowrap; }
.lane-empty { display: flex; align-items: center; gap: 16px; padding: 12px; margin: 0; font-size: 13px; color: #5f6673; }
.board-cell { padding: 9px; min-height: 115px; border-right: 1px solid #e3e5ec; border-bottom: 1px solid #e3e5ec; }
.board-cell.drag-over { background: #eee7fb; box-shadow: inset 0 0 0 2px #b3a0dd; }
/* TT-428：放下位置的提示線 */
.issue-card.drop-before { box-shadow: 0 -3px 0 #6653bd, 0 1px 3px #262b3910; }
.drop-line { height: 3px; margin: -4px 0 6px; border-radius: 2px; background: #6653bd; }
/* 上移／下移按鈕給觸控裝置用, 滑鼠用拖曳、鍵盤用 Alt＋方向鍵 */
.order-buttons { display: none; gap: 2px; margin-right: auto; }
@media (pointer: coarse) { .order-buttons { display: inline-flex; } .order-buttons .icon-button { min-width: 44px; min-height: 44px; } }
.issue-card { background: white; border: 1px solid #e0e3eb; border-radius: 5px; box-shadow: 0 1px 3px #262b3910; padding: 12px; margin-bottom: 9px; }
.card-edit { min-width: 44px; min-height: 44px; margin: -8px -8px -8px auto; }
.issue-card:focus-visible { outline: 2px solid #6653bd; outline-offset: 2px; }
/* TT-290：詳細卡片原本間距層層疊加（上12＋標題6＋底16＋工時13）, 一欄只看得到5張；收緊間距, 長標題最多3行, 完整標題在tooltip */
.card-title { font-size: 14px; line-height: 1.55; margin: 2px 0 4px; color: #242936; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.card-top { gap: 8px; margin-bottom: 4px; }
.issue-card .card-bottom { margin-top: 8px; min-height: 24px; }
.issue-card :deep(.issue-context) { margin: 8px 0 0; padding-top: 8px; border-top: 1px solid #f0eef5; }
.issue-card :deep(.issue-context:empty) { display: none; }
.card-top .priority-label { margin-left: auto; }
.board-card-description { font-size: 13px; line-height: 1.6; color: #5f6673; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; overflow-wrap: anywhere; margin-bottom: 10px; white-space: pre-wrap; }
.board-assignee { display: flex; gap: 5px; align-items: center; margin-left: auto; font-size: 12px; color: #5f6673; }
.compact-cards .issue-card { padding: 8px 10px; }
.compact-cards .card-title { margin: 4px 0; font-size: 14px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.compact-cards .card-top { margin-bottom: 5px; }
.compact-cards .card-bottom { margin-top: 4px; }
.lane-add { display: flex; align-items: center; gap: 6px; color: #596276; padding: 7px 4px; font-size: 12px; }
.lane-add:hover { color: #8670b2; }
.board-empty-note { padding: 12px 20px; font-size: 13px; color: #5f6673; }
@media (max-width: 700px) { .board-tools { justify-content: flex-end; align-items: flex-start; flex-direction: column; padding: 12px; } }
</style>
