<script setup>
import { computed, ref, watch } from 'vue';
import Icon from './Icon.vue';
import IssueContext from './IssueContext.vue';
import { categoryFor, PRIORITIES, statusesFor, TYPES } from '../domain/model.js';

const props = defineProps({ project: Object, issues: Array, visibleIssues: Array, entries: Array, members: Array, userId: String, moving: String, today: String });
const emit = defineEmits(['open', 'move', 'create']);
const group = ref('epic');
const density = ref('comfortable');
const collapsed = ref([]);
const dragOver = ref('');
const states = computed(() => statusesFor(props.project));
const preferenceKey = computed(() => `tasktracker-board:${props.userId}:${props.project.id}`);
watch(preferenceKey, () => {
  let saved = {};
  try { saved = JSON.parse(localStorage.getItem(preferenceKey.value) || '{}'); } catch {}
  group.value = saved?.group === 'none' ? 'none' : 'epic';
  density.value = saved?.density === 'compact' ? 'compact' : 'comfortable';
  collapsed.value = Array.isArray(saved?.collapsed) ? saved.collapsed.filter(id => typeof id === 'string') : [];
}, { immediate: true });
watch([group, density, collapsed], () => {
  try { localStorage.setItem(preferenceKey.value, JSON.stringify({ group: group.value, density: density.value, collapsed: collapsed.value })); } catch {}
});
const lanes = computed(() => {
  if (group.value === 'none') return [{ id: '', label: '全部任務', issues: props.visibleIssues }];
  const epics = props.issues.filter(issue => issue.type === 'epic');
  const rows = epics.map(epic => ({ id: epic.id, label: epic.title, epic, issues: props.visibleIssues.filter(issue => issue.epicId === epic.id) }));
  rows.push({ id: '', label: '未歸類到 Epic', issues: props.visibleIssues.filter(issue => !issue.epicId || !epics.some(epic => epic.id === issue.epicId)) });
  return rows;
});
function toggle(id) { collapsed.value = collapsed.value.includes(id) ? collapsed.value.filter(item => item !== id) : [...collapsed.value, id]; }
function memberName(id) { return props.members.find(member => member.id === id)?.name || id || '未指派'; }
function option(items, id) { return items.find(item => item.id === id); }
function overdue(issue) { return issue.dueDate && issue.dueDate < props.today && !['done', 'canceled'].includes(categoryFor(issue, props.project)); }
function startDrag(event, issue) { event.dataTransfer.setData('text/plain', issue.id); event.dataTransfer.effectAllowed = 'move'; }
function drop(event, state, lane) {
  dragOver.value = '';
  if (props.moving) return;
  const issue = props.visibleIssues.find(item => item.id === event.dataTransfer.getData('text/plain'));
  if (issue) emit('move', issue, { state, ...(group.value === 'epic' ? { epicId: lane.id } : {}) });
}
</script>

<template>
  <div class="visual-board" :class="{ 'compact-cards': density === 'compact' }" aria-label="任務看板">
    <div class="board-tools">
      <p>拖曳推進狀態, 或移到另一個 Epic 泳道重新歸類</p>
      <div><label>泳道<select v-model="group" aria-label="看板泳道"><option value="epic">依 Epic 分組</option><option value="none">不分組</option></select></label><label>卡片<select v-model="density" aria-label="看板卡片密度"><option value="comfortable">詳細</option><option value="compact">精簡</option></select></label></div>
    </div>
    <div class="board-scroll" tabindex="0" aria-label="看板橫向捲動區">
      <div class="board-canvas" :style="{ '--board-columns': states.length }">
        <div class="board-headings">
          <div v-for="state in states" :key="state.id" :data-state="state.id">
            <header class="column-header"><span><i :style="{ background: state.color }"></i>{{ state.label }}<b>{{ visibleIssues.filter(issue => issue.state === state.id).length }}</b></span><button class="icon-button" :aria-label="`新增${state.label}任務`" :disabled="Boolean(moving)" @click="emit('create', { state: state.id, epicId: '' })"><Icon name="plus" :size="16" /></button></header>
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
              <article v-for="issue in lane.issues.filter(item => item.state === state.id)" :key="issue.id" class="issue-card" :class="{ moving: moving === issue.id }" :draggable="!moving" :data-issue="issue.identifier" @dragstart="startDrag($event, issue)" @dragend="dragOver = ''">
                <div class="card-top"><span class="identifier">{{ issue.identifier }}</span><span class="type-tag">{{ option(TYPES, issue.type)?.label }}</span><span class="priority-label" :style="{ '--priority-color': option(PRIORITIES, issue.priority)?.color }" :title="`優先級：${option(PRIORITIES, issue.priority)?.label}`"><span class="priority-bars" :class="issue.priority"><i></i><i></i><i></i></span></span></div>
                <button class="card-title" @click="emit('open', issue)">{{ issue.title }}</button>
                <p v-if="density === 'comfortable' && issue.description" class="board-card-description">{{ issue.description }}</p>
                <div class="card-bottom"><span v-if="issue.dueDate" :class="['due-date', { overdue: overdue(issue) }]"><Icon name="clock" :size="12" />{{ issue.dueDate.slice(5).replace('-', '/') }}</span><span v-if="issue.comments.length" class="comment-count"><Icon name="message" :size="13" />{{ issue.comments.length }}</span><span class="board-assignee" :title="memberName(issue.assignee)"><span v-if="issue.assignee" class="avatar small">{{ memberName(issue.assignee).slice(0, 1) }}</span>{{ memberName(issue.assignee) }}</span></div>
                <IssueContext :issue="issue" :issues="issues" :entries="entries" :project="project" @open="emit('open', $event)" />
                <label class="card-state"><span class="sr-only">{{ issue.identifier }} 的狀態</span><select :value="issue.state" :disabled="Boolean(moving)" :aria-label="`${issue.identifier} 的狀態`" @change="emit('move', issue, { state: $event.target.value })"><option v-for="next in states" :key="next.id" :value="next.id">{{ next.label }}</option></select></label>
              </article>
              <button class="lane-add" :aria-label="`在${lane.label}新增${state.label}任務`" :disabled="Boolean(moving)" @click="emit('create', { state: state.id, epicId: group === 'epic' ? lane.id : '' })"><Icon name="plus" :size="15" /><span>新增卡片</span></button>
            </div>
          </div>
        </section>
      </div>
    </div>
    <p v-if="!visibleIssues.length" class="board-empty-note">目前沒有符合條件的卡片, 可調整篩選或在欄位內新增任務</p>
  </div>
</template>

<style scoped>
.visual-board { background: #f7f8fb; position: relative; overflow: hidden; min-width: 0; max-width: 100%; }
.board-tools { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 20px; background: white; border-bottom: 1px solid #e7e9ef; font-size: 13px; color: #5f6673; }
.board-tools > div, .board-tools label { display: flex; align-items: center; gap: 10px; }
.board-tools > div { gap: 18px; }
.board-tools select { border: 1px solid #e0deea; border-radius: 5px; padding: 6px 9px; color: #354052; background: white; }
.board-scroll { overflow: auto; max-height: calc(100vh - 280px); min-height: 240px; outline-offset: -2px; }
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
.card-title { font-size: 14px; line-height: 1.6; margin: 6px 0; color: #242936; }
.card-top { gap: 8px; }
.card-top .priority-label { margin-left: auto; }
.board-card-description { font-size: 13px; line-height: 1.6; color: #5f6673; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; overflow-wrap: anywhere; margin-bottom: 10px; white-space: pre-wrap; }
.board-assignee { display: flex; gap: 5px; align-items: center; margin-left: auto; font-size: 12px; color: #5f6673; }
.compact-cards .issue-card { padding: 9px; }
.compact-cards .card-title { margin: 5px 0; font-size: 14px; }
.compact-cards .card-top { margin-bottom: 5px; }
.compact-cards .card-bottom { margin-top: 7px; }
.lane-add { display: flex; align-items: center; gap: 6px; color: #596276; padding: 7px 4px; font-size: 12px; }
.lane-add:hover { color: #8670b2; }
.board-empty-note { padding: 12px 20px; font-size: 13px; color: #5f6673; }
@media (max-width: 700px) { .board-tools { align-items: flex-start; flex-direction: column; padding: 12px; } .board-scroll { max-height: 65vh; } }
</style>
