<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Icon from './Icon.vue';
import IssueContext from './IssueContext.vue';
import { buildIssueIndex } from '../domain/issueIndex.js';
import { isClosed, PRIORITIES, statusesFor, TYPES } from '../domain/model.js';

const props = defineProps({ syncState: { type: String, default: 'polling' }, project: Object, issues: Array, visibleIssues: Array, entries: Array, members: Array, userId: String, moving: String, today: String, boardKey: String, includedEpicIds: Array, canWrite: { type: Boolean, default: true }, canCreate: { type: Boolean, default: true } });
const emit = defineEmits(['open', 'move', 'create']);
const group = ref('none');
const density = ref('comfortable');
const collapsed = ref([]);
const dragOver = ref('');
const states = computed(() => statusesFor(props.project));
const preferenceKey = computed(() => `tasktracker-board:${props.userId}:${props.project.id}${props.boardKey && props.boardKey !== 'default' ? `:${props.boardKey}` : ''}`);
watch(preferenceKey, () => {
  let saved = {};
  try { saved = JSON.parse(localStorage.getItem(preferenceKey.value) || '{}'); } catch {}
  group.value = saved?.group === 'epic' ? 'epic' : 'none';
  density.value = saved?.density === 'compact' ? 'compact' : 'comfortable';
  collapsed.value = Array.isArray(saved?.collapsed) ? saved.collapsed.filter(id => typeof id === 'string') : [];
}, { immediate: true });
watch([group, density, collapsed], () => {
  try { localStorage.setItem(preferenceKey.value, JSON.stringify({ group: group.value, density: density.value, collapsed: collapsed.value })); } catch {}
});
const contextIndex = computed(() => buildIssueIndex(props.issues, props.entries));
const membersById = computed(() => new Map(props.members.map(member => [member.id, member.name])));
const stateCounts = computed(() => {
  const counts = new Map();
  for (const issue of props.visibleIssues) counts.set(issue.state, (counts.get(issue.state) || 0) + 1);
  return counts;
});
const lanes = computed(() => {
  let rows;
  if (group.value === 'none') rows = [{ id: '', label: '全部任務', issues: props.visibleIssues }];
  else {
    const included = props.includedEpicIds ? new Set(props.includedEpicIds) : null;
    const epics = props.issues.filter(issue => issue.type === 'epic' && (!included || included.has(issue.id)));
    rows = epics.map(epic => ({ id: epic.id, label: epic.title, epic, issues: [] }));
    rows.push({ id: '', label: '未歸類到 Epic', issues: [] });
    const byId = new Map(rows.map(row => [row.id, row]));
    for (const issue of props.visibleIssues) {
      const key = byId.has(issue.id) ? issue.id : byId.has(issue.epicId) ? issue.epicId : '';
      byId.get(key).issues.push(issue);
    }
  }
  return rows.map(row => {
    const columns = new Map(states.value.map(state => [state.id, []]));
    for (const issue of row.issues) columns.get(issue.state)?.push(issue);
    return { ...row, columns };
  });
});
let readingTimer;
function clearReading() { clearTimeout(readingTimer); }
function read(issue, event) {
  clearReading();
  if (event.detail === 0 || event.pointerType === 'touch' || !props.canWrite) { emit('open', issue, true); return; }
  // 保留單擊標題閱讀，等待雙擊判定後才導頁，避免先跳完整頁。
  readingTimer = setTimeout(() => emit('open', issue, true), 400);
}
function edit(issue, event) {
  clearReading();
  if (!props.canWrite || props.moving || (event?.target.closest('button:not(.card-title), select, label, a'))) return;
  emit('open', issue, false);
}
function keyboardEdit(issue, event) {
  if (event.target !== event.currentTarget) return;
  event.preventDefault(); edit(issue);
}
watch(() => props.moving, clearReading);
onBeforeUnmount(clearReading);
function toggle(id) { collapsed.value = collapsed.value.includes(id) ? collapsed.value.filter(item => item !== id) : [...collapsed.value, id]; }
function memberName(id) { return membersById.value.get(id) || id || '未指派'; }
function option(items, id) { return items.find(item => item.id === id); }
function overdue(issue) { return issue.dueDate && issue.dueDate < props.today && !isClosed(issue); }
function startDrag(event, issue) { clearReading(); event.dataTransfer.setData('text/plain', issue.id); event.dataTransfer.effectAllowed = 'move'; }
function drop(event, state, lane) {
  dragOver.value = '';
  if (props.moving || !props.canWrite) return;
  const issue = props.visibleIssues.find(item => item.id === event.dataTransfer.getData('text/plain'));
  if (issue) emit('move', issue, { state, ...(group.value === 'epic' && issue.type !== 'epic' ? { epicId: lane.id } : {}) });
}
</script>

<template>
  <div class="visual-board" :class="{ 'compact-cards': density === 'compact' }" aria-label="任務看板">
    <div class="board-tools">
      <div><span class="board-sync" :class="{ live: syncState === 'live' }" role="status" aria-label="看板同步狀態"><i></i>{{ syncState === 'live' ? '即時同步' : syncState === 'connecting' ? '連線中…' : '定期更新' }}</span><label>泳道<select v-model="group" aria-label="看板泳道"><option value="epic">依 Epic 分組</option><option value="none">不分組</option></select></label><label>卡片<select v-model="density" aria-label="看板卡片密度"><option value="comfortable">詳細</option><option value="compact">精簡</option></select></label></div>
    </div>
    <div class="board-scroll" tabindex="0" aria-label="看板橫向捲動區">
      <div class="board-canvas" :style="{ '--board-columns': states.length }">
        <div class="board-headings">
          <div v-for="state in states" :key="state.id" :data-state="state.id">
            <header class="column-header"><span><i :style="{ background: state.color }"></i>{{ state.label }}<b>{{ stateCounts.get(state.id) || 0 }}</b></span><button v-if="canCreate" class="icon-button" :aria-label="`新增${state.label}任務`" :disabled="Boolean(moving) || !canWrite" @click="emit('create', { state: state.id, epicId: '' })"><Icon name="plus" :size="16" /></button></header>
          </div>
        </div>
        <section v-for="lane in lanes" :key="lane.id" class="board-lane" :aria-label="`泳道 ${lane.label}`">
          <header v-if="group === 'epic'" class="lane-header">
            <button class="lane-toggle" :aria-label="`收合或展開 ${lane.label}`" :aria-expanded="!collapsed.includes(lane.id)" @click="toggle(lane.id)"><Icon name="chevron" :size="14" :class="{ expanded: !collapsed.includes(lane.id) }" /><strong>{{ lane.label }}</strong></button>
            <button v-if="lane.epic" class="lane-epic-link" :aria-label="`查看 Epic ${lane.epic.identifier}`" @click="emit('open', lane.epic)">{{ lane.epic.identifier }}</button>
            <span class="lane-total">{{ lane.issues.length }} 張卡片</span>
          </header>
          <div v-if="group === 'none' || !collapsed.includes(lane.id)" class="lane-columns">
            <div v-for="state in states" :key="state.id" class="board-cell" :class="{ 'drag-over': dragOver === `${lane.id}:${state.id}` }" :data-column="state.id" @dragover.prevent="dragOver = `${lane.id}:${state.id}`" @dragleave.self="dragOver = ''" @drop.prevent="drop($event, state.id, lane)">
              <article v-for="issue in lane.columns.get(state.id)" :key="issue.id" class="issue-card" :class="{ moving: moving === issue.id }" :draggable="canWrite && !moving" :data-issue="issue.identifier" tabindex="0" :aria-label="`${issue.identifier} ${issue.title} · ${state.label}`" @dblclick="edit(issue, $event)" @keydown.f2="keyboardEdit(issue, $event)" @dragstart="startDrag($event, issue)" @dragend="dragOver = ''">
                <div class="card-top"><span class="identifier">{{ issue.identifier }}</span><span class="type-tag">{{ option(TYPES, issue.type)?.label }}</span><span v-if="density === 'comfortable'" class="priority-label" :style="{ '--priority-color': option(PRIORITIES, issue.priority)?.color }" :title="`優先級：${option(PRIORITIES, issue.priority)?.label}`"><span class="priority-bars" :class="issue.priority"><i></i><i></i><i></i></span></span><button v-if="canWrite" type="button" class="icon-button card-edit" :aria-label="`編輯 ${issue.identifier}`" title="編輯任務（也可雙擊卡片或按 F2）" :disabled="Boolean(moving)" @click="clearReading(); emit('open', issue, false)"><Icon name="edit" :size="15" /></button></div>
                <button class="card-title" @click="read(issue, $event)">{{ issue.title }}</button>
                <p v-if="density === 'comfortable' && issue.description" class="board-card-description">{{ issue.description }}</p>
                <div class="card-bottom"><span v-if="density === 'comfortable' && issue.dueDate" :class="['due-date', { overdue: overdue(issue) }]"><Icon name="clock" :size="12" />{{ issue.dueDate.slice(5).replace('-', '/') }}</span><span v-if="density === 'comfortable' && issue.comments.length" class="comment-count"><Icon name="message" :size="13" />{{ issue.comments.length }}</span><span class="board-assignee" :title="memberName(issue.assignee)"><span v-if="issue.assignee" class="avatar small">{{ memberName(issue.assignee).slice(0, 1) }}</span>{{ memberName(issue.assignee) }}</span></div>
                <IssueContext v-if="density === 'comfortable'" :issue="issue" :index="contextIndex" :can-read-time="project.permissions?.includes('timeEntry.readOwn')" @open="emit('open', $event)" />
              </article>
              <button v-if="canCreate" class="lane-add" :aria-label="`在${lane.label}新增${state.label}任務`" :disabled="Boolean(moving) || !canWrite" @click="emit('create', { state: state.id, epicId: group === 'epic' ? lane.id : '' })"><Icon name="plus" :size="15" /><span>新增卡片</span></button>
            </div>
          </div>
        </section>
      </div>
    </div>
    <p v-if="!visibleIssues.length" class="board-empty-note">沒有符合條件的卡片</p>
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
.lane-total { margin-left: auto; font-size: 12px; color: #5f6673; white-space: nowrap; }
.board-cell { padding: 9px; min-height: 115px; border-right: 1px solid #e3e5ec; border-bottom: 1px solid #e3e5ec; }
.board-cell.drag-over { background: #eee7fb; box-shadow: inset 0 0 0 2px #b3a0dd; }
.issue-card { background: white; border: 1px solid #e0e3eb; border-radius: 5px; box-shadow: 0 1px 3px #262b3910; padding: 12px; margin-bottom: 9px; }
.card-edit { min-width: 44px; min-height: 44px; margin: -8px -8px -8px auto; }
.issue-card:focus-visible { outline: 2px solid #6653bd; outline-offset: 2px; }
.card-title { font-size: 14px; line-height: 1.6; margin: 6px 0; color: #242936; }
.card-top { gap: 8px; }
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
