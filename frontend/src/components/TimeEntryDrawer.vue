<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, useId } from 'vue';
import { api } from '../api/index.js';
import { dateInTaipei } from '../domain/model.js';
import Icon from './Icon.vue';
import PagedSelect from './PagedSelect.vue';
import { onTabKeydown } from '../ui/tabs.js';
const props = defineProps({ showForm: { type: Boolean, default: true }, detailsLabel: { type: String, default: '' }, initialTab: { type: String, default: 'form' }, issues: { type: Array, default: () => [] }, lookup: Function, fixedIssues: { type: Array, default: () => [] }, defaultIssueId: { type: String, default: '' }, defaultDate: { type: String, default: dateInTaipei }, entry: Object, lockIssue: Boolean });
const emit = defineEmits(['close', 'saved', 'conflict']);
// 基準只在明確開啟表單時取得，不隨背景更新改寫。
const editing = props.entry ? { ...props.entry } : null;
const form = reactive({ issueId: editing?.issueId || props.defaultIssueId, workDate: editing?.workDate || props.defaultDate, duration: editing ? Number((editing.minutes / 60).toFixed(6)) : '', note: editing?.note || '' });
const initial = JSON.stringify(form), id = useId();
const dirty = computed(() => JSON.stringify(form) !== initial);
const busy = ref(false), error = ref(''), durationError = ref('');
const panel = ref(null), durationInput = ref(null), errorSummary = ref(null);
const availableIssues = computed(() => props.issues);
// TT-305：有明細時抽屜分成「填報」「明細」兩個分頁；沒有填報權限時只顯示明細。
const hasTabs = computed(() => Boolean(props.detailsLabel) && props.showForm);
const tab = ref(!props.showForm || (props.detailsLabel && props.initialTab === 'details') ? 'details' : 'form');
let pending, opener;
defineExpose({ dirty, saving: busy });
function close() {
  if (busy.value || (dirty.value && !window.confirm('捨棄尚未儲存的工時？'))) return;
  panel.value.close(); emit('close');
}
onMounted(() => {
  opener = document.activeElement;
  panel.value.showModal();
  if (tab.value === 'form') panel.value.querySelector(editing || props.lockIssue ? 'input[type="number"]' : 'select')?.focus();
  else panel.value.querySelector('[role="tab"][aria-selected="true"], .time-drawer-details summary')?.focus();
});
onBeforeUnmount(() => { panel.value?.close(); if (opener?.isConnected) opener.focus({ preventScroll: true }); });
async function save() {
  if (busy.value) return;
  error.value = ''; durationError.value = '';
  const rawMinutes = Number(form.duration) * 60, minutes = Math.round(rawMinutes);
  if (!Number.isFinite(rawMinutes) || minutes < 1 || rawMinutes > 1440) durationError.value = '請填入 1 分鐘至 24 小時的工時';
  else if (Math.abs(rawMinutes - minutes) > 0.001) durationError.value = '工時以整數分鐘計算；例如 0.25 小時為 15 分鐘、0.5 小時為 30 分鐘';
  if (durationError.value) { await nextTick(); durationInput.value?.focus(); return; }
  const payload = { issueId: form.issueId, workDate: form.workDate, minutes, note: form.note, ...(editing ? { id: editing.id, expectedRevision: editing.revision } : {}) };
  const signature = JSON.stringify(payload);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  busy.value = true;
  try {
    const result = await api[editing ? 'updateTime' : 'logTime']({ ...payload, requestId: pending.requestId });
    panel.value.close(); emit('saved', result);
  } catch (failure) {
    error.value = failure.message;
    if (failure.code === 'REVISION_CONFLICT') emit('conflict');
    await nextTick(); errorSummary.value?.focus();
  } finally { busy.value = false; }
}
</script>

<template>
  <dialog ref="panel" class="time-entry-drawer" :aria-labelledby="`time-entry-heading-${id}`" @cancel.prevent="close" @click="event => { if (event.target === panel) close(); }">
    <header class="time-drawer-header"><div><h3 :id="`time-entry-heading-${id}`">{{ !showForm ? detailsLabel : editing ? '修改你的工時' : '填報你的工時' }}</h3></div><button class="icon-button" aria-label="關閉工時面板" :disabled="busy" @click="close"><Icon name="close" /></button></header>
    <div v-if="hasTabs" class="time-drawer-tabs" role="tablist" aria-label="工時抽屜分頁" @keydown="onTabKeydown">
      <button :id="`time-tab-form-${id}`" type="button" role="tab" :aria-selected="tab === 'form'" :tabindex="tab === 'form' ? 0 : -1" :aria-controls="`time-panel-form-${id}`" @click="tab = 'form'">{{ editing ? '修改工時' : '填報工時' }}</button>
      <button :id="`time-tab-details-${id}`" type="button" role="tab" :aria-selected="tab === 'details'" :tabindex="tab === 'details' ? 0 : -1" :aria-controls="`time-panel-details-${id}`" @click="tab = 'details'">{{ detailsLabel }}</button>
    </div>
    <div v-if="detailsLabel" v-show="tab === 'details'" :id="`time-panel-details-${id}`" class="time-drawer-details" :role="hasTabs ? 'tabpanel' : undefined" :aria-labelledby="hasTabs ? `time-tab-details-${id}` : undefined"><slot name="details" /></div>
    <div v-if="showForm" v-show="tab === 'form'" :id="`time-panel-form-${id}`" class="time-form-card" :role="hasTabs ? 'tabpanel' : undefined" :aria-labelledby="hasTabs ? `time-tab-form-${id}` : undefined"><div v-if="error" ref="errorSummary" class="error-box" role="alert" tabindex="-1">{{ error }}</div><form @submit.prevent="save"><PagedSelect v-if="lookup" v-model="form.issueId" label="工時任務" :context="'time-issue'" :fixed-options="fixedIssues" :load="lookup" :autoload="!editing && !lockIssue" required :disabled="busy || Boolean(editing) || lockIssue" empty-label="選擇任務" /><label v-else class="field">任務<select v-model="form.issueId" aria-label="工時任務" required :disabled="busy || Boolean(editing) || lockIssue"><option value="" disabled>選擇任務</option><option v-for="issue in availableIssues" :key="issue.id" :value="issue.id">{{ issue.identifier }} · {{ issue.title }}</option></select></label><label class="field">工作日期<input v-model="form.workDate" type="date" :max="dateInTaipei()" required :disabled="busy" /></label><div class="field"><label :for="`time-duration-${id}`">投入小時</label><input :id="`time-duration-${id}`" ref="durationInput" v-model="form.duration" type="number" min="0.016666" max="24" step="any" placeholder="例如 1.5" required :disabled="busy" :aria-invalid="durationError ? 'true' : undefined" :aria-describedby="durationError ? `time-duration-error-${id}` : undefined" @input="durationError = ''" /><span v-if="durationError" :id="`time-duration-error-${id}`" class="field-error" role="alert">{{ durationError }}</span></div><label class="field">工時說明<textarea v-model="form.note" rows="3" maxlength="1000" placeholder="輸入工時說明…" :disabled="busy" /></label><button class="button primary" :disabled="busy || (!lookup && !availableIssues.length)"><Icon name="check" :size="17" />{{ busy ? '儲存中…' : editing ? '儲存工時修改' : '登記工時' }}</button><button v-if="editing || dirty" type="button" class="text-button time-cancel" :disabled="busy" @click="close">取消填寫</button></form><p v-if="!lookup && !availableIssues.length" class="subtle">尚無可填報的任務</p></div>
  </dialog>
</template>
