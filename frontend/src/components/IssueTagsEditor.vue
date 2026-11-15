<script setup>
import { computed, nextTick, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import { useLeaveGuard } from '../ui/leaveGuard.js';
import { t } from '../i18n/index.js';
import TagLabels from './TagLabels.vue';
import TagPicker from './TagPicker.vue';
const props = defineProps({ issue: Object, canWrite: Boolean, disabled: Boolean });
const emit = defineEmits(['changed']);
const editing = shallowRef(false), selected = shallowRef([]), saving = shallowRef(false), error = shallowRef(''), dialog = shallowRef(null);
const ids = computed(() => (props.issue.tags || []).map(tag => tag.id).sort());
const originalIds = shallowRef([]);
let originalRevision;
const dirty = computed(() => editing.value && JSON.stringify([...selected.value].sort()) !== JSON.stringify(originalIds.value));
useLeaveGuard({ dirty: () => dirty.value, saving: () => saving.value, unload: true });
let pending, opener;
watch(() => [props.issue.id, props.issue.revision], () => { if (!editing.value) selected.value = [...ids.value]; });
watch(() => props.canWrite, allowed => { if (!allowed && editing.value) { dialog.value?.close(); editing.value = false; pending = null; } });
async function open(event) { opener = event.currentTarget; originalIds.value = [...ids.value]; originalRevision = props.issue.revision; selected.value = [...originalIds.value]; error.value = ''; pending = null; editing.value = true; await nextTick(); dialog.value.showModal(); }
function close() { if (saving.value || (dirty.value && !window.confirm(t('要捨棄尚未儲存的標籤變更嗎？')))) return; dialog.value?.close(); editing.value = false; opener?.focus(); }
async function save() {
  if (saving.value || !dirty.value) return;
  const input = { expectedRevision: originalRevision, addTagIds: selected.value.filter(id => !originalIds.value.includes(id)), removeTagIds: originalIds.value.filter(id => !selected.value.includes(id)) };
  const signature = JSON.stringify(input);
  if (!pending || pending.signature !== signature) pending = { signature, input: { ...input, requestId: crypto.randomUUID() } };
  saving.value = true; error.value = '';
  try { await api.applyIssueTags(props.issue.projectId, props.issue.id, pending.input); dialog.value.close(); editing.value = false; emit('changed'); opener?.focus(); }
  catch (failure) { error.value = failure.message; }
  finally { saving.value = false; }
}
defineExpose({ dirty, saving });
</script>
<template>
  <section class="issue-tags-editor" :aria-label="t('任務標籤')">
    <div class="tags-heading"><span>{{ t('標籤') }}</span><button v-if="canWrite" type="button" class="text-button" :disabled="disabled || saving" @click="open">{{ t('編輯標籤') }}</button></div>
    <TagLabels :tags="issue.tags || []" :max="20" /><span v-if="!issue.tags?.length" class="subtle">{{ t('未設定') }}</span>
    <dialog v-if="editing" ref="dialog" class="tags-dialog" :aria-label="t('編輯標籤')" @cancel.prevent="close" @click="event => { if (event.target === dialog) close(); }">
      <form @submit.prevent="save"><header><h2>{{ t('編輯標籤') }}</h2><button type="button" class="button secondary" :disabled="saving" @click="close">{{ t('取消') }}</button></header>
        <p v-if="error" role="alert" class="error-box">{{ t(error) }}</p>
        <TagPicker v-model="selected" :project-id="issue.projectId" :options="issue.tags || []" :disabled="saving" />
        <footer><button class="button primary" :disabled="saving || !dirty">{{ saving ? t('儲存中…') : t('套用標籤') }}</button></footer>
      </form>
    </dialog>
  </section>
</template>
<style scoped>
.issue-tags-editor { padding: 16px 0; }
.tags-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 8px; font-size: 14px; color: var(--text-secondary); }
.tags-dialog { width: min(520px, calc(100vw - 32px)); padding: 24px; border: 1px solid var(--border); border-radius: 10px; color: var(--text-primary); background: white; }
.tags-dialog::backdrop { background: rgb(20 24 35 / 35%); }
.tags-dialog header, .tags-dialog footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px; }
.tags-dialog h2 { margin: 0; font-size: 18px; }
.tags-dialog footer { justify-content: flex-end; margin: 24px 0 0; }
</style>
