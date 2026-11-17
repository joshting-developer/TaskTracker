<script setup>
import { t } from '../i18n/index.js';
import { computed, onBeforeUnmount, watch } from 'vue';
import { useUploadTray } from '../composables/useUploadTray.js';
import Icon from './Icon.vue';

const { visible, entries, counts, collapsed, dismiss } = useUploadTray();
const title = computed(() => {
  const { total, done, waiting, active, failed, canceled } = counts.value, finished = done + waiting;
  if (active) return finished ? t('上傳中 {0}/{1}', [finished, total - canceled]) : t('已加入 {0} 個檔案', [total - canceled]);
  if (failed) return t('{0} 個成功、{1} 個失敗', [finished, failed]);
  if (waiting) return t('{0} 個檔案待附加', [waiting]);
  return done ? t('{0} 個檔案已完成', [done]) : t('已取消上傳');
});
// Success folds away by itself; failures stay until the user retries or removes them.
let timer;
const settled = computed(() => visible.value && !counts.value.active && !counts.value.failed ? (counts.value.waiting ? 'waiting' : 'done') : '');
watch(settled, state => {
  clearTimeout(timer);
  if (state === 'done') timer = setTimeout(dismiss, 4000);
  if (state === 'waiting') timer = setTimeout(() => { collapsed.value = true; }, 4000);
});
onBeforeUnmount(() => clearTimeout(timer));
function type(row) { return row.kind === 'image' ? t('圖片') : row.kind === 'file' ? (/pdf/i.test(row.upload?.mime || row.file.type) ? 'PDF' : 'ZIP') : t('不支援'); }
function status(entry) {
  const row = entry.row;
  if (row.state === 'queued') return t('等待上傳');
  if (row.state === 'uploading') return `${row.progress}%`;
  if (row.state === 'binding') return t('正在附加到任務');
  if (row.state === 'uploaded') return row.error ? t('尚未附到任務：{0}', [row.error]) : entry.note || t('儲存後附到任務');
  if (row.state === 'done') return row.kind === 'file' ? t('已附到任務') : t('已加入內容');
  if (row.state === 'canceled') return t('已取消');
  return row.error;
}
</script>

<template>
  <aside v-if="visible" class="upload-tray" :class="{ collapsed }" :aria-label="t('上傳進度')">
    <div class="tray-head">
      <p role="status" aria-live="polite" :class="{ failed: counts.failed }">{{ title }}</p>
      <button type="button" class="tray-icon" :aria-expanded="!collapsed" :aria-label="collapsed ? t('展開上傳進度') : t('收合上傳進度')" @click="collapsed = !collapsed"><Icon name="chevron" :size="16" /></button>
      <button v-if="!counts.active" type="button" class="tray-icon" :aria-label="t('關閉上傳進度')" @click="dismiss"><Icon name="close" :size="16" /></button>
    </div>
    <ul v-show="!collapsed">
      <li v-for="entry in entries" :key="entry.row.id" :class="entry.row.state">
        <span class="tray-name">{{ entry.row.file.name || t('圖片') }}</span>
        <span class="tray-type">{{ type(entry.row) }}</span>
        <progress v-if="entry.row.state === 'uploading'" :value="entry.row.progress" max="100" :aria-label="t('{0}上傳進度', [entry.row.file.name])"></progress>
        <span class="tray-status">{{ status(entry) }}</span>
        <span v-if="entry.cancel" class="tray-actions">
          <button v-if="entry.row.state === 'failed' && entry.retry" type="button" @click="entry.retry(entry.row)">{{ t('重試') }}</button>
          <button v-if="entry.row.state === 'rejected'" type="button" @click="entry.cancel(entry.row)">{{ t('移除') }}</button>
          <button v-else-if="['queued', 'failed'].includes(entry.row.state) || (entry.row.state === 'uploading' && entry.abortable !== false)" type="button" @click="entry.cancel(entry.row)">{{ t('取消') }}</button>
        </span>
      </li>
    </ul>
  </aside>
</template>

<style scoped>
.upload-tray { position: fixed; left: 24px; bottom: calc(24px + env(safe-area-inset-bottom)); z-index: 100; width: min(360px, calc(100vw - 24px)); max-height: min(420px, 60vh); display: flex; flex-direction: column; border: 1px solid #d6d1ee; border-radius: 8px; background: #fff; box-shadow: 0 8px 28px #2a1f4a24; color: #3f3a4c; font-size: 13px; }
.tray-head { display: flex; align-items: center; gap: 4px; padding: 4px 4px 4px 14px; }
.tray-head p { flex: 1; margin: 0; font-weight: 600; }
.tray-head p.failed { color: #a52c3d; }
.tray-icon { display: grid; place-items: center; width: 44px; height: 44px; color: #5844a0; border-radius: 6px; }
.tray-head .tray-icon:first-of-type svg { transform: rotate(-90deg); }
.upload-tray:not(.collapsed) .tray-head .tray-icon:first-of-type svg { transform: rotate(90deg); }
.upload-tray ul { margin: 0; padding: 0 14px 10px; list-style: none; overflow: auto; border-top: 1px solid #eeebf6; }
.upload-tray li { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; padding: 8px 0; border-bottom: 1px solid #f3f1f8; overflow-wrap: anywhere; }
.tray-name { flex: 1; min-width: 120px; }
.tray-type { padding: 1px 6px; border: 1px solid #d6d1ee; border-radius: 4px; color: #5844a0; font-size: 12px; }
.tray-status { flex-basis: 100%; color: #5f6673; font-size: 12px; }
li.failed .tray-status, li.rejected .tray-status { color: #a52c3d; }
li.done .tray-status { color: #2f7a4f; }
.upload-tray progress { width: 100%; accent-color: #6653bd; }
.tray-actions { display: flex; gap: 4px; }
.tray-actions button { min-height: 44px; padding: 8px; color: #5844a0; }
@media (max-width: 700px) { .upload-tray { left: 12px; right: 12px; width: auto; bottom: calc(88px + env(safe-area-inset-bottom)); max-height: 45vh; } }
</style>
