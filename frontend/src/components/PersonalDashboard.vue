<script setup>
import { computed, onBeforeUnmount, onMounted, ref, shallowRef } from 'vue';
import { t } from '../i18n/index.js';
import { api } from '../api/index.js';
import DashboardWidgetSettings from './DashboardWidgetSettings.vue';
import DashboardWidgetFrame from './DashboardWidgetFrame.vue';
import PersonalDashboardPanel from './PersonalDashboardPanel.vue';
const props = defineProps({ permissions: Array });
const emit = defineEmits(['access']);
const refreshKey = ref(0);
defineExpose({ refresh: () => { refreshKey.value++; } });
const PANELS = { mine: '我的未結案任務', due: '七天內到期與逾期', starting: '七天內開始', recent: '最近更新', tasks: '任務', notes: '待處理紙條' };
const DEFAULTS = Object.keys(PANELS).filter(kind => kind !== 'tasks');
// TT-186：順序與顯示存在伺服器（以工作區成員為單位）；讀取失敗時仍用預設顯示, 不擋住摘要。
const preferences = shallowRef(null), draft = ref(null), saving = ref(false), editingId = ref(null), emptyWidgets = ref({}), message = ref('');
let controller;
const available = computed(() => preferences.value?.available || DEFAULTS.filter(kind => kind !== 'notes' || props.permissions?.includes('todo.read')));
const normalize = item => { const widget = typeof item === 'string' ? { id: item, kind: item, width: 1, height: 380 } : item; return { ...widget, width: Number(widget.width), height: Number(widget.height), columns: Number(widget.columns ?? widget.width * 4) }; };
const editingWidget = computed(() => draft.value?.find(item => item.id === editingId.value));
function updateWidget(value) { Object.assign(draft.value.find(item => item.id === editingId.value), value); editingId.value = null; }
const visible = computed(() => (draft.value || preferences.value?.widgets || DEFAULTS).map(normalize).filter(item => available.value.includes(item.kind)));
const hidden = computed(() => available.value);
async function load() {
  controller?.abort(); controller = new AbortController();
  try { preferences.value = await api.dashboardPreferences({ signal: controller.signal }); message.value = ''; }
  catch (failure) { if (failure.code !== 'ABORTED') message.value = failure.message; if (['UNAUTHENTICATED', 'FORBIDDEN', 'WORKSPACE_CHANGED'].includes(failure.code)) emit('access'); }
}
onMounted(load);
onBeforeUnmount(() => controller?.abort());
function customize() { draft.value = visible.value.map(item => ({ ...item })); message.value = ''; }
function cancel() { editingId.value = null; draft.value = null; message.value = ''; }
function move(index, step) { const list = [...draft.value]; [list[index], list[index + step]] = [list[index + step], list[index]]; draft.value = list; }
function hide(kind) { draft.value = draft.value.filter(item => item.id !== kind.id); }
function add(kind) { draft.value = [...draft.value, { ...normalize(kind), id: crypto.randomUUID() }]; }
async function save(widgets = draft.value) {
  saving.value = true;
  try { preferences.value = await api.saveDashboardPreferences({ widgets, expectedRevision: preferences.value?.revision ?? 0 }); draft.value = null; message.value = t('首頁設定已儲存'); }
  catch (failure) { message.value = failure.message; if (failure.code === 'REVISION_CONFLICT') { await load(); draft.value = null; } }
  finally { saving.value = false; }
}
</script>
<template>
  <section class="personal-dashboard" :aria-label="t('個人工作摘要')">
    <header class="dashboard-heading"><h1>{{ t('我的工作') }}</h1>
      <div class="dashboard-actions">
        <template v-if="draft"><button class="text-button" :disabled="saving" @click="save(DEFAULTS.filter(kind => available.includes(kind)))">{{ t('恢復預設') }}</button><button class="button" :disabled="saving" @click="cancel">{{ t('取消') }}</button><button class="button primary" :disabled="saving" @click="save()">{{ t('儲存') }}</button></template>
        <template v-else><button class="text-button" :disabled="!preferences" @click="customize">{{ t('自訂') }}</button><button class="text-button" @click="refreshKey++">{{ t('重新整理') }}</button></template>
      </div>
    </header>
    <p v-if="message" class="dashboard-message" role="status">{{ t(message) }}</p>
    <section v-if="draft && visible.length < 20 && hidden.length" class="dashboard-hidden" :aria-label="t('可加入的小工具')"><span>{{ t('加入小工具') }}</span><button v-for="kind in hidden" :key="kind" type="button" class="button" :disabled="saving" @click="add(kind)">＋ {{ t(PANELS[kind]) }}</button></section>
    <p v-if="!visible.length" class="dashboard-empty subtle">{{ t('首頁沒有顯示任何小工具, 可按「自訂」加入') }}</p>
    <div class="dashboard-grid">
      <div v-for="(widget, index) in visible" :key="widget.id" :style="{ '--columns': widget.columns }" class="dashboard-slot" :class="{ editing: draft }">
        <div v-if="draft" class="dashboard-tools" role="group" :aria-label="t('調整{0}', [t(PANELS[widget.kind])])"><button type="button" class="text-button" :disabled="saving || index === 0" @click="move(index, -1)">{{ t('上移') }}</button><button type="button" class="text-button" :disabled="saving || index === visible.length - 1" @click="move(index, 1)">{{ t('下移') }}</button><button type="button" class="text-button" :disabled="saving" @click="editingId = widget.id">{{ t('設定小工具') }}</button><button type="button" class="text-button" :disabled="saving" @click="hide(widget)">{{ t('隱藏') }}</button></div>
        <DashboardWidgetFrame :columns="widget.columns" :compact="emptyWidgets[widget.id]" :height="widget.height" :editing="!!draft && !saving" @resize="Object.assign(draft.find(item => item.id === widget.id), $event)">
        <PersonalDashboardPanel :kind="widget.kind" :title="widget.title || t(PANELS[widget.kind])" :filters="widget.filters || {}" :per-page="Math.min(20, Math.max(2, Math.floor((widget.height - 120) / (widget.columns >= 7 ? 64 : 100))))" @empty="emptyWidgets[widget.id] = $event" :refresh-key="refreshKey" @access="emit('access')" />
        </DashboardWidgetFrame>
      </div>
    </div>
    <DashboardWidgetSettings v-if="editingWidget" :widget="editingWidget" @apply="updateWidget" @close="editingId = null" />
  </section>
</template>
<style scoped>
.personal-dashboard { max-width: 1440px; min-width: 0; }
.dashboard-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
.dashboard-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; justify-content: flex-end; }
h1 { margin: 0; font-size: 24px; }
.dashboard-message { margin: -8px 0 16px; font-size: 13px; color: #5e5470; }
.dashboard-hidden { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; padding: 12px; border: 1px dashed #d9cff0; border-radius: 8px; font-size: 13px; color: #5e5470; }
.dashboard-empty { margin-bottom: 16px; }
.dashboard-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 20px; align-items: start; grid-auto-flow: row dense; }
.dashboard-slot { min-width: 0; grid-column: span var(--columns); }
.dashboard-slot.editing { outline: 2px dashed #d9cff0; outline-offset: 4px; border-radius: 8px; }
.dashboard-tools { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; margin-bottom: 6px; font-size: 12px; }
@media (min-width: 701px) and (max-width: 1000px) { .dashboard-slot { grid-column: span max(6, var(--columns)); } }
@media (max-width: 700px) { .dashboard-grid { grid-template-columns: minmax(0, 1fr); gap: 16px; } .dashboard-slot { grid-column: 1 !important; } .dashboard-heading { flex-wrap: wrap; } }
</style>
