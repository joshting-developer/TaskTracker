<script setup>
import { computed, nextTick, onMounted, reactive, shallowRef } from 'vue';
import { api } from '../api/index.js';
import { useLeaveGuard } from '../ui/leaveGuard.js';
import { t } from '../i18n/index.js';
const props = defineProps({ projectId: String, tag: Object });
const emit = defineEmits(['close', 'saved']);
const dialog = shallowRef(null), saving = shallowRef(false), error = shallowRef(''), conflict = shallowRef(false);
const form = reactive({ name: props.tag?.name || '', color: props.tag?.color || '#6653bd', description: props.tag?.description || '' });
const initial = shallowRef(JSON.stringify(form)), revision = shallowRef(props.tag?.revision || 0);
const dirty = computed(() => JSON.stringify(form) !== initial.value);
useLeaveGuard({ dirty: () => dirty.value, saving: () => saving.value, unload: true });
let pending;
onMounted(async () => { await nextTick(); dialog.value.showModal(); });
function close() { if (saving.value || (dirty.value && !window.confirm(t('要捨棄尚未儲存的標籤變更嗎？')))) return; dialog.value.close(); emit('close'); }
async function submit(remove = false) {
  if (saving.value || (remove && !window.confirm(t('刪除這個標籤及所有卡片上的套用？歷史紀錄會保留')))) return;
  const fields = remove ? {} : { name: form.name.trim(), color: form.color, description: form.description };
  const action = remove ? 'delete' : props.tag ? 'update' : 'create';
  const input = { ...fields, ...(props.tag ? { expectedRevision: revision.value } : {}) };
  const signature = JSON.stringify([action, input]);
  if (!pending || pending.signature !== signature) pending = { signature, input: { ...input, requestId: crypto.randomUUID() } };
  saving.value = true; error.value = ''; conflict.value = false;
  try {
    if (remove) await api.deleteTag(props.projectId, props.tag.id, pending.input);
    else if (props.tag) await api.updateTag(props.projectId, props.tag.id, pending.input);
    else await api.createTag(props.projectId, pending.input);
    dialog.value.close(); emit('saved');
  } catch (failure) { error.value = failure.message; conflict.value = failure.code === 'REVISION_CONFLICT'; }
  finally { saving.value = false; }
}
async function reload() {
  if (saving.value || (dirty.value && !window.confirm(t('要捨棄尚未儲存的標籤變更嗎？')))) return;
  saving.value = true;
  try { const tag = await api.tag(props.projectId, props.tag.id); Object.assign(form, { name: tag.name, color: tag.color, description: tag.description }); revision.value = tag.revision; initial.value = JSON.stringify(form); pending = null; conflict.value = false; error.value = ''; }
  catch (failure) { error.value = failure.message; }
  finally { saving.value = false; }
}
defineExpose({ dirty, saving });
</script>
<template>
  <dialog ref="dialog" class="tag-definition-dialog" :aria-label="tag ? t('編輯標籤定義') : t('新增標籤')" @cancel.prevent="close" @click="event => { if (event.target === dialog) close(); }">
    <form @submit.prevent="submit()"><header><h2>{{ tag ? t('編輯標籤定義') : t('新增標籤') }}</h2><button type="button" class="button secondary" :disabled="saving" @click="close">{{ t('取消') }}</button></header>
      <div v-if="error" role="alert" class="error-box">{{ t(error) }}<button v-if="conflict" type="button" class="text-button" :disabled="saving" @click="reload">{{ t('重新載入最新版本') }}</button></div>
      <label class="field">{{ t('標籤名稱') }}<input v-model="form.name" :disabled="saving" maxlength="40" required autofocus /></label>
      <label class="field">{{ t('標籤顏色') }}<input v-model="form.color" type="color" :disabled="saving" /></label>
      <label class="field">{{ t('標籤說明') }}<textarea v-model="form.description" :disabled="saving" maxlength="300" rows="3"></textarea></label>
      <footer><button v-if="tag" type="button" class="button danger" :disabled="saving" @click="submit(true)">{{ t('刪除標籤') }}</button><button class="button primary" :disabled="saving || !form.name.trim() || (Boolean(tag) && !dirty)">{{ saving ? t('儲存中…') : t('儲存標籤') }}</button></footer>
    </form>
  </dialog>
</template>
<style scoped>
.tag-definition-dialog { width: min(560px, calc(100vw - 32px)); border: 1px solid var(--border); border-radius: 10px; padding: 24px; color: var(--text-primary); background: white; }
.tag-definition-dialog::backdrop { background: rgb(20 24 35 / 35%); }
header, footer { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; }
h2 { margin: 0; font-size: 18px; }
.field { margin-bottom: 16px; }
input[type=color] { width: 64px; height: 40px; padding: 4px; }
footer { margin: 24px 0 0; justify-content: flex-end; }
footer .danger { margin-right: auto; }
</style>
