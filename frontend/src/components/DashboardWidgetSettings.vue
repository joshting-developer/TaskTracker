<script setup>
import { computed, shallowRef, watch, onScopeDispose } from 'vue';
import { api } from '../api/index.js';
import { t } from '../i18n/index.js';
import PagedSelect from './PagedSelect.vue';
const props = defineProps({ widget: Object, disabled: Boolean });
const emit = defineEmits(['update']);
const filters = computed(() => props.widget.filters || {});
function filter(key, value) {
  const next = { ...filters.value };
  if (value) next[key] = value; else delete next[key];
  emit('update', { filters: next });
}
const states = shallowRef([]), error = shallowRef('');
let controller;
watch(() => filters.value.projectId, async id => {
  controller?.abort(); controller = new AbortController();
  states.value = []; error.value = '';
  if (!id) return;
  try { const project = await api.project(id, { signal: controller.signal }); states.value = project.statuses || []; }
  catch (failure) { if (failure.code !== 'ABORTED') error.value = failure.message; }
}, { immediate: true });
onScopeDispose(() => controller?.abort());
const stateOptions = computed(() => states.value.length ? states.value : [{ id: 'open', label: '待處理' }, { id: 'inprogress', label: '進行中' }, { id: 'review', label: '待驗收' }, { id: 'closed', label: '已結案' }]);
const projects = (query, options) => api.projectOptions({ ...query, perPage: 20 }, options);
const members = (query, options) => api.members(filters.value.projectId, { ...query, perPage: 20 }, options);
const tags = (query, options) => api.tags(filters.value.projectId, { ...query, perPage: 20 }, options);
</script>
<template>
  <details class="widget-settings">
    <summary>{{ t('設定小工具') }}</summary>
    <p v-if="error" class="field-error" role="alert">{{ t(error) }}</p>
    <fieldset :disabled="disabled">
      <label>{{ t('名稱') }}<input :value="widget.title || ''" maxlength="100" @change="emit('update', { title: $event.target.value.trim() })" /></label>
      <template v-if="widget.kind !== 'notes'">
        <label>{{ t('日期範圍') }}<select :aria-label="t('日期範圍')" :value="widget.kind" @change="emit('update', { kind: $event.target.value })"><option value="tasks">{{ t('全部') }}</option><option value="mine">{{ t('未結案') }}</option><option value="due">{{ t('七天內到期與逾期') }}</option><option value="starting">{{ t('七天內開始') }}</option><option value="recent">{{ t('最近更新') }}</option></select></label>
        <PagedSelect :model-value="filters.projectId || ''" :label="t('專案')" :load="projects" :disabled="disabled" @update:model-value="filter('projectId', $event)" />
        <label>{{ t('狀態') }}<select :aria-label="t('狀態')" :value="filters.state || ''" @change="filter('state', $event.target.value)"><option value="">{{ t('全部') }}</option><option v-for="state in stateOptions" :key="state.id" :value="state.id">{{ t(state.label) }}</option></select></label>
        <label>{{ t('優先級') }}<select :aria-label="t('優先級')" :value="filters.priority || ''" @change="filter('priority', $event.target.value)"><option value="">{{ t('全部') }}</option><option v-for="value in ['urgent','high','normal','low']" :key="value" :value="value">{{ t(value) }}</option></select></label>
        <label>{{ t('承辦人') }}<select :aria-label="t('承辦人')" :value="filters.assignee || 'mine'" @change="filter('assignee', $event.target.value)"><option value="mine">{{ t('我') }}</option><option value="all">{{ t('全部') }}</option><option value="unassigned">{{ t('未指派') }}</option><option v-if="filters.assignee && !['mine','all','unassigned'].includes(filters.assignee)" :value="filters.assignee">{{ t('指定成員') }}</option></select></label>
        <template v-if="filters.projectId">
          <PagedSelect :model-value="filters.assignee || ''" :label="t('指定成員')" :load="members" :context="filters.projectId" :disabled="disabled" @update:model-value="filter('assignee', $event)" />
          <PagedSelect :model-value="filters.tagId || ''" :label="t('標籤')" :load="tags" :context="filters.projectId" :disabled="disabled" @update:model-value="filter('tagId', $event)" />
        </template>
        <label>{{ t('到期日起') }}<input type="date" :value="filters.dueFrom || ''" @change="filter('dueFrom', $event.target.value)" /></label>
        <label>{{ t('到期日迄') }}<input type="date" :value="filters.dueTo || ''" @change="filter('dueTo', $event.target.value)" /></label>
      </template>
    </fieldset>
  </details>
</template>
<style scoped>
.widget-settings { margin: 8px 0; border: 1px solid #d9cff0; padding: 10px; background: #faf8fd; border-radius: 8px; }
summary { cursor: pointer; min-height: 32px; }
fieldset { border: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit,minmax(150px,1fr)); gap: 12px; min-width: 0; }
label { display: grid; gap: 4px; font-size: 13px; }
input, select { width: 100%; min-width: 0; min-height: 44px; box-sizing: border-box; border: 1px solid #d9cff0; border-radius: 4px; background: white; padding: 8px; }
</style>
