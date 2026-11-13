<script setup>
import { computed, onBeforeUnmount, shallowRef, useTemplateRef } from 'vue';
import { PRIORITIES, TYPES } from '../domain/model.js';
import { CONDITIONS, defaultDirection } from '../domain/filters.js';
import Icon from './Icon.vue';
import PagedSelect from './PagedSelect.vue';

const props = defineProps({ lookup: Function, lookupContext: String, expanded: {type: Boolean, default: undefined},
  states: { type: Array, required: true },
  members: { type: Array, required: true },
  issues: { type: Array, required: true },
  activeFilterChips: { type: Array, required: true },
  filterWarnings: { type: Array, required: true },
});
const emit = defineEmits(['remove', 'clear', 'option', 'update:expanded']);
const model = defineModel({ type: Object, required: true });
const localExpanded = shallowRef(false);
const filtersOpen = computed({get: () => props.expanded ?? localExpanded.value, set: value => { localExpanded.value = value; emit('update:expanded', value); }});
const epics = computed(() => props.issues.filter(issue => issue.type === 'epic'));
// 更新完整的篩選輸入，不直接修改父元件物件中的欄位。
function field(key) {
  return computed({
    get: () => model.value[key],
    set: value => { model.value = { ...model.value, [key]: value }; },
  });
}
const search = field('search'), stateFilter = field('state'), priorityFilter = field('priority');
const assigneeFilter = field('assignee'), epicFilter = field('epicId'), typeFilter = field('type');
const conditionFilter = field('condition');
// TT-306：換排序時方向回到該排序的預設；方向按鈕只在預設與反向之間切換。
const sort = computed({ get: () => model.value.sort, set: value => { model.value = { ...model.value, sort: value, direction: '' }; } });
const DIRECTION_LABELS = { updated: { desc: '新到舊', asc: '舊到新' }, created: { desc: '新到舊', asc: '舊到新' }, priority: { asc: '緊急到低', desc: '低到緊急' }, due: { asc: '近到遠', desc: '遠到近' } };
const currentDirection = computed(() => model.value.direction || defaultDirection(model.value.sort));
const nextDirection = computed(() => currentDirection.value === 'asc' ? 'desc' : 'asc');
const directionLabel = computed(() => `目前${DIRECTION_LABELS[model.value.sort][currentDirection.value]}，切換為${DIRECTION_LABELS[model.value.sort][nextDirection.value]}`);
function toggleDirection() { model.value = { ...model.value, direction: nextDirection.value === defaultDirection(model.value.sort) ? '' : nextDirection.value }; }

// 狀態多選：focusout只處理鍵盤移出（relatedTarget在外）；點選項文字時relatedTarget為null，
// 若同步關閉details，Chrome 157會在label啟動checkbox時使渲染程序崩潰（TT-258）。點外部改由pointerdown關閉。
const statusPicker = useTemplateRef('statusPicker');
function closeStatusPicker() { if (statusPicker.value) statusPicker.value.open = false; }
function closeOnOutsidePointer(event) { if (!statusPicker.value?.contains(event.target)) closeStatusPicker(); }
function onStatusToggle() {
  const method = statusPicker.value?.open ? 'addEventListener' : 'removeEventListener';
  document[method]('pointerdown', closeOnOutsidePointer, true);
}
function onStatusFocusout(event) {
  if (event.relatedTarget && !statusPicker.value?.contains(event.relatedTarget)) closeStatusPicker();
}
onBeforeUnmount(() => document.removeEventListener('pointerdown', closeOnOutsidePointer, true));
</script>

<template>
  <div class="filter-toolbar">
    <label class="search-field">
      <Icon name="search" :size="18" />
      <input v-model="search" placeholder="搜尋任務、編號或負責人…" aria-label="搜尋任務" maxlength="180" />
      <button v-if="search" class="icon-button" aria-label="清除搜尋" @click="search = ''"><Icon name="close" :size="15" /></button>
    </label>
    <div class="filters">
      <button
        class="button secondary filter-toggle" :class="{ active: filtersOpen || activeFilterChips.length }"
        aria-label="任務篩選" :aria-expanded="filtersOpen" aria-controls="task-filters" @click="filtersOpen = !filtersOpen"
      >
        <Icon name="filter" :size="17" />篩選<span v-if="activeFilterChips.length" class="filter-count">{{ activeFilterChips.length }}</span>
      </button>
      <select v-model="sort" aria-label="排序方式">
        <option value="updated">更新時間</option><option value="created">建立日期</option><option value="priority">優先級</option><option value="due">到期日</option>
      </select>
      <button type="button" class="icon-button sort-direction" :aria-label="directionLabel" :title="directionLabel" @click="toggleDirection"><Icon :name="currentDirection === 'desc' ? 'arrowDown' : 'arrowUp'" :size="17" /></button>
    </div>
  </div>
  <div id="task-filters" v-show="filtersOpen" class="advanced-filters" role="group" aria-label="任務篩選條件">
    <div class="status-filter">
      <span>狀態</span>
      <details ref="statusPicker" @toggle="onStatusToggle" @keydown.esc.prevent="closeStatusPicker" @focusout="onStatusFocusout">
        <summary aria-label="篩選狀態">{{ stateFilter.length ? `已選 ${stateFilter.length} 個狀態` : '所有狀態' }}</summary>
        <div class="status-filter-options">
          <label v-for="state in states" :key="state.id">
            <input v-model="stateFilter" type="checkbox" :value="state.id" :aria-label="`篩選狀態 ${state.label}`" />{{ state.label }}
          </label>
          <button v-if="stateFilter.length" type="button" class="text-button" @click="stateFilter = []">清除狀態</button>
        </div>
      </details>
    </div>
    <label>優先級
      <select v-model="priorityFilter" aria-label="篩選優先級">
        <option value="">所有優先級</option>
        <option v-for="priority in PRIORITIES" :key="priority.id" :value="priority.id">{{ priority.label }}</option>
      </select>
    </label>
    <PagedSelect v-if="lookup" v-model="assigneeFilter" label="篩選負責人" :options="members" :context="lookupContext" :load="(filters, options) => lookup('member', filters, options)" empty-label="所有成員" @choose="item => emit('option', {kind:'member', item})" />
    <label v-else>負責人
      <select v-model="assigneeFilter" aria-label="篩選負責人">
        <option value="">所有成員</option>
        <option v-if="assigneeFilter && !members.some(member => member.id === assigneeFilter)" :value="assigneeFilter" disabled>成員已移除（需修正）</option>
        <option v-for="member in members" :key="member.id" :value="member.id">{{ member.name }}</option>
      </select>
    </label>
    <PagedSelect v-if="lookup" v-model="epicFilter" label="篩選 Epic" :options="epics" :fixed-options="[{id:'unlinked',name:'尚未掛入 Epic'}]" :context="lookupContext" :load="(filters, options) => lookup('epic', filters, options)" empty-label="所有 Epic" @choose="item => item.id !== 'unlinked' && emit('option', {kind:'epic', item})" />
    <label v-else>Epic
      <select v-model="epicFilter" aria-label="篩選 Epic">
        <option value="">所有 Epic</option><option value="unlinked">尚未掛入 Epic</option>
        <option v-if="epicFilter && epicFilter !== 'unlinked' && !epics.some(issue => issue.id === epicFilter)" :value="epicFilter" disabled>Epic 已移除（需修正）</option>
        <option v-for="epic in epics" :key="epic.id" :value="epic.id">{{ epic.title }}</option>
      </select>
    </label>
    <label>類型
      <select v-model="typeFilter" aria-label="篩選類型">
        <option value="">所有類型</option>
        <option v-for="type in TYPES" :key="type.id" :value="type.id">{{ type.label }}</option>
      </select>
    </label>
    <label>情況
      <select v-model="conditionFilter" aria-label="篩選情況">
        <option v-if="conditionFilter && !CONDITIONS.some(condition => condition.id === conditionFilter)" :value="conditionFilter" disabled>舊前置篩選已停用（需修正）</option>
        <option v-for="condition in CONDITIONS" :key="condition.id" :value="condition.id">{{ condition.label }}</option>
      </select>
    </label>
  </div>
  <div v-if="activeFilterChips.length" class="active-filter-chips" aria-label="已套用篩選">
    <button v-for="chip in activeFilterChips" :key="chip.key" class="active-filter-chip" :aria-label="`移除篩選：${chip.label}：${chip.name}`" @click="emit('remove', chip.key)">
      <span>{{ chip.label }}：{{ chip.name }}</span><Icon name="close" :size="13" />
    </button>
    <button class="text-button" @click="emit('clear')">清除全部篩選</button>
  </div>
  <div v-if="filterWarnings.length" class="filter-warning" role="alert">{{ filterWarnings.join('、') }}, 請重新選擇或清除條件。原條件保留, 沒有放寬搜尋</div>
</template>
