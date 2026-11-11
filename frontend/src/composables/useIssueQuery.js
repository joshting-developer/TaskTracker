import { computed, ref, shallowRef, watch, toValue } from 'vue';
import { PRIORITIES, statusesFor, TYPES } from '../domain/model.js';
import { CONDITIONS, filterIssues, missingFilters } from '../domain/filters.js';

// 任務查詢的唯一狀態來源；路由範圍由App提供，篩選、標籤與分頁一起維護。
export function useIssueQuery({ project, issues, boardIssues, members, userId, today, view, scope, projectId, remote = false }) {
  const search = shallowRef('');
  const stateFilter = ref([]);
  const priorityFilter = shallowRef(''), assigneeFilter = shallowRef(''), epicFilter = shallowRef('');
  const typeFilter = shallowRef(''), conditionFilter = shallowRef(''), sort = shallowRef('updated');
  const page = shallowRef(1);
  const pageSize = 20;
  const projectStates = computed(() => statusesFor(project.value));
  const filters = computed(() => ({
    search: search.value, scope: scope.value, state: stateFilter.value, priority: priorityFilter.value,
    assignee: assigneeFilter.value, epicId: epicFilter.value, type: typeFilter.value,
    condition: conditionFilter.value, sort: sort.value, view: view.value,
  }));
  const filterWarnings = computed(() => project.value ? missingFilters(filters.value, project.value, members.value, issues.value) : []);
  const visibleIssues = computed(() => toValue(remote) ? issues.value : project.value ? filterIssues(
    view.value === 'board' ? boardIssues.value : issues.value, filters.value,
    { project: project.value, userId: userId.value, members: members.value, today: today.value, includeEpics: true },
  ) : []);
  const pageCount = computed(() => Math.max(1, Math.ceil(visibleIssues.value.length / pageSize)));
  const pagedIssues = computed(() => visibleIssues.value.slice((page.value - 1) * pageSize, page.value * pageSize));
  const filterActive = computed(() => Boolean(search.value || stateFilter.value.length || priorityFilter.value || assigneeFilter.value || epicFilter.value || typeFilter.value || conditionFilter.value));
  const fields = { state: stateFilter, priority: priorityFilter, assignee: assigneeFilter, epicId: epicFilter, type: typeFilter, condition: conditionFilter };
  const filterInput = computed({
    get: () => ({
      search: search.value, state: stateFilter.value, priority: priorityFilter.value,
      assignee: assigneeFilter.value, epicId: epicFilter.value, type: typeFilter.value,
      condition: conditionFilter.value, sort: sort.value,
    }),
    set: value => {
      search.value = value.search;
      sort.value = value.sort;
      Object.entries(fields).forEach(([key, field]) => { field.value = value[key]; });
    },
  });
  const activeFilterChips = computed(() => [
    { key: 'state', label: '狀態', value: stateFilter.value.length, name: stateFilter.value.map(id => projectStates.value.find(item => item.id === id)?.label || '已移除').join('、') },
    { key: 'priority', label: '優先級', value: priorityFilter.value, name: PRIORITIES.find(item => item.id === priorityFilter.value)?.label },
    { key: 'assignee', label: '負責人', value: assigneeFilter.value, name: members.value.find(item => item.id === assigneeFilter.value)?.name || '已移除' },
    { key: 'epicId', label: 'Epic', value: epicFilter.value, name: epicFilter.value === 'unlinked' ? '尚未掛入 Epic' : issues.value.find(item => item.id === epicFilter.value)?.title || '已移除' },
    { key: 'type', label: '類型', value: typeFilter.value, name: TYPES.find(item => item.id === typeFilter.value)?.label },
    { key: 'condition', label: '情況', value: conditionFilter.value, name: CONDITIONS.find(item => item.id === conditionFilter.value)?.label || '已停用' },
  ].filter(item => item.value));

  function removeFilter(key) {
    if (fields[key]) fields[key].value = key === 'state' ? [] : '';
  }
  function clearFilters() {
    search.value = '';
    Object.keys(fields).forEach(removeFilter);
  }
  watch([search, stateFilter, priorityFilter, assigneeFilter, epicFilter, typeFilter, conditionFilter, sort, scope, projectId], () => { page.value = 1; });
  watch(pageCount, count => { if (!toValue(remote) && page.value > count) page.value = count; });

  return {
    filterInput, page, pageSize, pageCount, projectStates, visibleIssues, pagedIssues,
    filterWarnings, filterActive, activeFilterChips, removeFilter, clearFilters,
  };
}
