<script setup>
import { computed, shallowRef, ref, watch, onScopeDispose, onMounted, useId } from 'vue';
import { api } from '../api/index.js';
import { t } from '../i18n/index.js';
import { PRIORITIES } from '../domain/model.js';
import PagedSelect from './PagedSelect.vue';
const props = defineProps({ widget: Object });
const emit = defineEmits(['apply', 'close']);
const widget = ref(JSON.parse(JSON.stringify(props.widget)));
const dialog = ref(null), headingId = useId();
const fields = { projectId: '專案', state: '狀態', priority: '優先級', assignee: '承辦人', tagId: '標籤', dueFrom: '到期日起', dueTo: '到期日迄' };
widget.value.filters = { assignee: 'mine', ...widget.value.filters };
const enabled = ref(Object.keys(widget.value.filters).filter(key => key !== 'assignee' || widget.value.filters.assignee !== 'all'));
const nextField = ref('');
const remaining = computed(() => Object.keys(fields).filter(key => !enabled.value.includes(key)));
function addField() { if (nextField.value) enabled.value.push(nextField.value); nextField.value = ''; }
function removeField(key) { enabled.value = enabled.value.filter(item => item !== key); filter(key, key === 'assignee' ? 'all' : ''); }
function project(value) { filter('projectId', value); filter('state', ''); filter('tagId', ''); if (!['mine', 'all', 'unassigned'].includes(filters.value.assignee)) filter('assignee', 'mine'); }
function apply() {
  const filters = Object.fromEntries(Object.entries(widget.value.filters).filter(([, value]) => value));
  emit('apply', { ...widget.value, filters });
}
onMounted(() => dialog.value.showModal());
onScopeDispose(() => dialog.value?.close());
const filters = computed(() => widget.value.filters || {});
function filter(key, value) {
  const next = { ...filters.value };
  if (value) next[key] = value; else delete next[key];
  widget.value.filters = next;
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
  <dialog ref="dialog" class="widget-settings" :aria-labelledby="headingId" @cancel.prevent="emit('close')">
    <form @submit.prevent="apply">
    <h2 :id="headingId">{{ t('設定小工具') }}</h2>
    <p v-if="error" class="field-error" role="alert">{{ t(error) }}</p>
    <fieldset>
      <label>{{ t('名稱') }}<input maxlength="100" v-model.trim="widget.title" /></label>
      <div class="dimension-fields">
        <label>{{ t('寬度') }}<select v-model.number="widget.columns" :aria-label="t('寬度')"><option v-for="columns in 10" :key="columns" :value="columns + 2">{{ Math.round((columns + 2) / 12 * 100) }}%</option></select></label>
        <label>{{ t('高度（px）') }}<input v-model.number="widget.height" type="number" min="180" max="1000" step="1" required /></label>
      </div>
      <template v-if="widget.kind !== 'notes'">
        <label>{{ t('任務範圍') }}<select :aria-label="t('任務範圍')" v-model="widget.kind"><option value="tasks">{{ t('全部') }}</option><option value="mine">{{ t('未結案') }}</option><option value="due">{{ t('七天內到期與逾期') }}</option><option value="starting">{{ t('七天內開始') }}</option><option value="recent">{{ t('最近更新') }}</option></select></label>
        <p class="condition-rule">{{ t('符合以下所有條件') }}</p>
        <div v-for="key in enabled" :key="key" class="condition-row">
          <PagedSelect v-if="key === 'projectId'" :model-value="filters.projectId || ''" :label="t(fields[key])" :load="projects" @update:model-value="project" />
          <label v-else-if="key === 'state'">{{ t(fields[key]) }}<select v-model="widget.filters.state" :aria-label="t(fields[key])"><option value="">{{ t('全部') }}</option><option v-for="state in stateOptions" :key="state.id" :value="state.id">{{ t(state.label) }}</option></select></label>
          <label v-else-if="key === 'priority'">{{ t(fields[key]) }}<select v-model="widget.filters.priority" :aria-label="t(fields[key])"><option value="">{{ t('全部') }}</option><option v-for="priority in PRIORITIES" :key="priority.id" :value="priority.id">{{ t(priority.label) }}</option></select></label>
          <div v-else-if="key === 'assignee'">
            <label>{{ t(fields[key]) }}<select v-model="widget.filters.assignee" :aria-label="t(fields[key])"><option value="mine">{{ t('我') }}</option><option value="all">{{ t('全部') }}</option><option value="unassigned">{{ t('未指派') }}</option><option v-if="!['mine','all','unassigned'].includes(filters.assignee)" :value="filters.assignee">{{ t('指定成員') }}</option></select></label>
            <PagedSelect v-if="filters.projectId" :model-value="filters.assignee" :label="t('指定成員')" :load="members" :context="filters.projectId" @update:model-value="filter('assignee', $event)" />
          </div>
          <div v-else-if="key === 'tagId'">
            <PagedSelect v-if="filters.projectId" :model-value="filters.tagId || ''" :label="t(fields[key])" :load="tags" :context="filters.projectId" @update:model-value="filter('tagId', $event)" />
            <p v-else>{{ t('請先選擇專案') }}</p>
          </div>
          <label v-else>{{ t(fields[key]) }}<input type="date" :value="filters[key] || ''" @change="filter(key, $event.target.value)" /></label>
          <button type="button" class="text-button" :aria-label="t('移除條件 {0}', [t(fields[key])])" @click="removeField(key)">{{ t('移除') }}</button>
        </div>
        <div v-if="remaining.length" class="condition-add"><label>{{ t('新增條件') }}<select v-model="nextField" :aria-label="t('新增條件')"><option value="">{{ t('選擇條件') }}</option><option v-for="key in remaining" :key="key" :value="key">{{ t(fields[key]) }}</option></select></label><button type="button" class="button" :disabled="!nextField" @click="addField">{{ t('加入') }}</button></div>
      </template>
    </fieldset>
    <footer><button type="button" class="button" @click="emit('close')">{{ t('取消') }}</button><button type="submit" class="button primary">{{ t('套用') }}</button></footer>
    </form>
  </dialog>
</template>
<style scoped>
.widget-settings { width: min(560px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); box-sizing: border-box; margin: auto; border: 1px solid #d9cff0; padding: 24px; background: #fff; border-radius: 10px; overflow: auto; }
.widget-settings::backdrop { background: rgb(24 20 38 / 35%); }
h2 { font-size: 20px; margin: 0 0 20px; }
fieldset { border: 0; padding: 0; display: grid; gap: 16px; min-width: 0; }
label { display: grid; gap: 6px; font-size: 13px; }
input, select { width: 100%; min-width: 0; min-height: 44px; box-sizing: border-box; border: 1px solid #d9cff0; border-radius: 4px; background: white; padding: 8px; }
.dimension-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.condition-rule { margin: 8px 0 0; font-size: 13px; color: #5f6673; }
.condition-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: end; gap: 12px; }
.condition-add { display: flex; align-items: end; gap: 12px; } .condition-add label { flex: 1; }
footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; }
button { min-height: 44px; }
</style>
