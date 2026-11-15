<script setup>
import { t } from '../i18n/index.js';
import { computed } from 'vue';
import Icon from './Icon.vue';
import TagLabels from './TagLabels.vue';
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
    type: TYPES.find(type => type.id === issue.type)?.label || t('未知類型'),
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
        <tr><th class="type-column" scope="col">{{ t('類型') }}</th><th>{{ t('任務') }}</th><th>{{ t('狀態') }}</th><th>{{ t('優先級') }}</th><th>{{ t('負責人') }}</th><th>{{ t('到期日') }}</th><th class="end-column"></th></tr>
      </thead>
      <tbody>
        <!-- TT-281：焦點在這列時按F2快速編輯；只是捷徑, 鉛筆按鈕仍是主要入口 -->
        <tr v-for="row in rows" :key="row.issue.id" :data-issue="row.issue.identifier" @keydown.f2.prevent="canWrite && emit('open', row.issue, false)">
          <td class="type-column"><span class="issue-type-label">{{ row.type }}</span></td>
          <td class="task-title-cell">
            <button class="task-title" @click="emit('open', row.issue, true)">{{ row.issue.title }}</button>
            <div class="task-subline"><span class="identifier">{{ row.issue.identifier }}</span></div>
            <TagLabels :tags="row.issue.tags || []" />
          </td>
          <td><span class="status-badge" :class="row.issue.state" :style="{ '--status-color': row.state?.color }"><i></i>{{ row.state?.label }}</span></td>
          <td>
            <span class="priority-label" :style="{ '--priority-color': row.priority?.color }">
              <span class="priority-bars" :class="row.issue.priority"><i></i><i></i><i></i></span>{{ t(row.priority?.label) }}
            </span>
          </td>
          <td>
            <span v-if="row.issue.assignee" class="assignee"><span class="avatar small">{{ row.member.slice(0, 1) }}</span>{{ row.member }}</span>
            <span v-else class="unassigned">{{ t('未指派') }}</span>
          </td>
          <td><span :class="['due-date', { overdue: row.overdue }]">{{ row.dueDate }}<span v-if="row.overdue" class="overdue-dot" :title="t('已過期')"></span></span></td>
          <td class="issue-row-actions">
            <button v-if="canWrite" class="icon-button row-action row-edit" :aria-label="t('快速編輯 {0}', [row.issue.identifier])" :title="t('快速編輯（F2）')" aria-keyshortcuts="F2" @click="emit('open', row.issue, false)"><Icon name="edit" :size="16" /></button>
            <button class="icon-button row-action row-open" :aria-label="t('開啟完整頁 {0}', [row.issue.identifier])" :title="t('開啟完整頁')" @click="emit('open', row.issue, true)"><Icon name="chevron" :size="17" /></button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
