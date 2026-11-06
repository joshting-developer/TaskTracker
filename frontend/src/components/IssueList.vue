<script setup>
import { computed } from 'vue';
import Icon from './Icon.vue';
import { isClosed, PRIORITIES, TYPES } from '../domain/model.js';

const props = defineProps({
  issues: { type: Array, required: true },
  states: { type: Array, required: true },
  members: { type: Array, required: true },
  canWrite: Boolean,
  today: { type: String, required: true },
});
const emit = defineEmits(['open']);
const rows = computed(() => {
  const members = new Map(props.members.map(member => [member.id, member.name]));
  const states = new Map(props.states.map(state => [state.id, state]));
  return props.issues.map(issue => ({
    issue,
    type: TYPES.find(type => type.id === issue.type)?.label || '未知類型',
    state: states.get(issue.state),
    priority: PRIORITIES.find(priority => priority.id === issue.priority),
    member: members.get(issue.assignee) || issue.assignee || '',
    overdue: Boolean(issue.dueDate && issue.dueDate < props.today && !isClosed(issue)),
    dueDate: issue.dueDate ? `${Number(issue.dueDate.slice(5, 7))}/${Number(issue.dueDate.slice(8))}` : '—',
  }));
});
</script>

<template>
  <div class="table-scroll">
    <table class="issue-table">
      <thead>
        <tr><th class="type-column" scope="col">類型</th><th>任務</th><th>狀態</th><th>優先級</th><th>負責人</th><th>到期日</th><th class="end-column"></th></tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.issue.id" :data-issue="row.issue.identifier">
          <td class="type-column"><span class="issue-type-label">{{ row.type }}</span></td>
          <td class="task-title-cell">
            <button class="task-title" @click="emit('open', row.issue, true)">{{ row.issue.title }}</button>
            <div class="task-subline"><span class="identifier">{{ row.issue.identifier }}</span></div>
          </td>
          <td><span class="status-badge" :class="row.issue.state" :style="{ '--status-color': row.state?.color }"><i></i>{{ row.state?.label }}</span></td>
          <td>
            <span class="priority-label" :style="{ '--priority-color': row.priority?.color }">
              <span class="priority-bars" :class="row.issue.priority"><i></i><i></i><i></i></span>{{ row.priority?.label }}
            </span>
          </td>
          <td>
            <span v-if="row.issue.assignee" class="assignee"><span class="avatar small">{{ row.member.slice(0, 1) }}</span>{{ row.member }}</span>
            <span v-else class="unassigned">未指派</span>
          </td>
          <td><span :class="['due-date', { overdue: row.overdue }]">{{ row.dueDate }}<span v-if="row.overdue" class="overdue-dot" title="已過期"></span></span></td>
          <td class="issue-row-actions">
            <button v-if="canWrite" class="text-button quick-edit" :aria-label="`快速編輯 ${row.issue.identifier}`" @click="emit('open', row.issue, false)">快速編輯</button>
            <button class="icon-button row-open" :aria-label="`開啟完整頁 ${row.issue.identifier}`" title="開啟完整頁" @click="emit('open', row.issue, true)"><Icon name="chevron" :size="17" /></button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
