<script setup>
import { computed, nextTick, onScopeDispose, ref, shallowRef, useId } from 'vue';
import { api } from '../api/index.js';
import { t } from '../i18n/index.js';
// TT-446：三個時程視圖共用的未排程入口。只補開始／結束日, 存檔沿用卡片更新與 revision 衝突處理；不做拖曳改期或多選批次。
const PER_PAGE = 20;
const props = defineProps({ project: Object, count: Number, assignee: { type: String, default: '' }, includeClosed: Boolean });
const emit = defineEmits(['saved', 'open', 'access']);
const dialog = ref(null), headingId = useId(), opened = ref(false);
const result = shallowRef(null), rows = ref({}), loading = ref(false), error = ref(''), page = ref(1), notice = ref('');
const canWrite = computed(() => props.project?.permissions?.includes('issue.write'));
let controller, generation = 0;

async function load() {
  const current = ++generation;
  controller?.abort(); controller = new AbortController();
  loading.value = true; error.value = '';
  try {
    const data = await api.scheduleUnscheduled(props.project.id, { assignee: props.assignee, includeClosed: props.includeClosed ? 1 : 0, page: page.value, perPage: PER_PAGE }, { signal: controller.signal });
    if (current !== generation) return;
    if (page.value > data.lastPage && data.lastPage >= 1) { page.value = data.lastPage; return load(); }
    result.value = data;
    // 已在編輯的列保留草稿, 其他列以伺服器最新日期為準
    rows.value = Object.fromEntries(data.items.map(item => [item.id, rows.value[item.id]?.revision === item.revision ? rows.value[item.id] : { revision: item.revision, startDate: item.startDate, endDate: item.endDate, saving: false, error: '' }]));
  } catch (failure) {
    if (current !== generation || ['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) return;
    error.value = failure.message;
    if (['NOT_FOUND', 'FORBIDDEN', 'WORKSPACE_CHANGED', 'UNAUTHENTICATED'].includes(failure.code)) { close(); emit('access'); }
  } finally { if (current === generation) loading.value = false; }
}
async function show() {
  opened.value = true; page.value = 1; notice.value = ''; result.value = null; rows.value = {};
  await nextTick();
  dialog.value.showModal();
  load();
}
function close() { generation++; controller?.abort(); dialog.value?.close(); opened.value = false; }
function turn(step) { page.value += step; load(); }

async function save(item) {
  const row = rows.value[item.id];
  if (row.saving) return;
  if (!row.startDate && !row.endDate) { row.error = t('請填開始日或結束日'); return; }
  if (row.startDate && row.endDate && row.endDate < row.startDate) { row.error = t('計畫結束日不可早於開始日'); return; }
  row.saving = true; row.error = ''; notice.value = '';
  const payload = { id: item.id, startDate: row.startDate, endDate: row.endDate, expectedRevision: row.revision };
  const signature = JSON.stringify(payload);
  // 同一份內容重送沿用 requestId, 避免逾時後重複寫入
  if (row.signature !== signature) Object.assign(row, { signature, requestId: crypto.randomUUID() });
  try {
    await api.updateIssue({ ...payload, requestId: row.requestId });
    notice.value = t('{0} 已排進時程', [item.identifier]);
    delete rows.value[item.id];
    emit('saved');
    await load();
  } catch (failure) {
    row.saving = false;
    if (failure.code === 'REVISION_CONFLICT') {
      // 不覆蓋別人的修改：重讀最新版本, 使用者確認後再存
      delete rows.value[item.id];
      notice.value = t('{0} 剛被其他人修改, 已重新讀取最新內容, 請確認後再存', [item.identifier]);
      await load();
    } else row.error = failure.message;
  }
}
function open(item) { close(); emit('open', item); }
onScopeDispose(() => { generation++; controller?.abort(); });
defineExpose({ show });
</script>
<template>
  <button v-if="count" type="button" class="text-button schedule-unscheduled-trigger" @click="show">{{ t('{0} 張任務尚未排程', [count]) }}</button>
  <dialog v-if="opened" ref="dialog" class="board-dialog schedule-unscheduled" :aria-labelledby="headingId" @cancel.prevent="close">
    <header class="board-dialog-heading"><h2 :id="headingId">{{ t('尚未排程的任務') }}</h2><button type="button" class="icon-button" :aria-label="t('關閉')" @click="close">×</button></header>
    <p class="subtle">{{ t('填上計畫開始或結束日後, 任務就會出現在週曆、人員排程與甘特；只填一端視為單日') }}</p>
    <p v-if="!canWrite" class="subtle">{{ t('你沒有這個專案的編輯權限, 只能查看') }}</p>
    <p v-if="notice" class="schedule-unscheduled-notice" role="status">{{ notice }}</p>
    <p v-if="error" class="error-box" role="alert">{{ t(error) }} <button type="button" class="text-button" @click="load">{{ t('重試') }}</button></p>
    <p v-else-if="loading && !result" role="status">{{ t('載入中…') }}</p>
    <p v-else-if="result && !result.items.length" class="subtle">{{ t('所有任務都已排程') }}</p>
    <ul v-if="result?.items.length" class="schedule-unscheduled-list" :aria-busy="loading">
      <li v-for="item in result.items" :key="item.id">
        <button type="button" class="text-button schedule-unscheduled-title" @click="open(item)"><span class="identifier">{{ item.identifier }}</span> {{ item.title }}</button>
        <p class="subtle">{{ item.stateLabel }} · {{ item.assigneeName || t('未指派') }}<template v-if="item.dueDate"> · {{ t('到期日 {0}', [item.dueDate]) }}</template></p>
        <form v-if="rows[item.id]" class="schedule-unscheduled-dates" novalidate @submit.prevent="save(item)">
          <label>{{ t('計畫開始日') }}<input v-model="rows[item.id].startDate" type="date" :disabled="!canWrite || rows[item.id].saving" /></label>
          <label>{{ t('計畫結束日') }}<input v-model="rows[item.id].endDate" type="date" :min="rows[item.id].startDate || undefined" :disabled="!canWrite || rows[item.id].saving" /></label>
          <button v-if="canWrite" type="submit" class="button primary" :disabled="rows[item.id].saving" :aria-label="t('儲存 {0} 的日期', [item.identifier])">{{ rows[item.id].saving ? t('儲存中…') : t('儲存') }}</button>
          <p v-if="rows[item.id].error" class="field-error" role="alert">{{ t(rows[item.id].error) }}</p>
        </form>
      </li>
    </ul>
    <footer v-if="result?.lastPage > 1" class="pagination" :aria-label="t('未排程分頁')"><button type="button" class="text-button" :disabled="loading || page <= 1" @click="turn(-1)">{{ t('上一頁') }}</button><span>{{ page }} / {{ result.lastPage }}</span><button type="button" class="text-button" :disabled="loading || page >= result.lastPage" @click="turn(1)">{{ t('下一頁') }}</button></footer>
  </dialog>
</template>
<style scoped>
.schedule-unscheduled-trigger { font-size: 13px; }
.schedule-unscheduled-list { list-style: none; padding: 0; margin: 12px 0 0; }
.schedule-unscheduled-list li { padding: 12px 0; border-top: 1px solid var(--border); }
.schedule-unscheduled-list li p { margin: 4px 0 0; font-size: 13px; }
.schedule-unscheduled-title { text-align: left; font-size: 15px; overflow-wrap: anywhere; }
.schedule-unscheduled-dates { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 8px 12px; margin-top: 8px; }
.schedule-unscheduled-dates label { display: grid; gap: 4px; font-size: 13px; }
.schedule-unscheduled-dates .button.primary { width: auto; margin: 0; }
.schedule-unscheduled-dates .field-error { flex-basis: 100%; margin: 0; }
.schedule-unscheduled-notice { margin: 8px 0 0; font-size: 13px; color: #2f6b3a; }
.pagination { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 12px; font-size: 13px; }
</style>
