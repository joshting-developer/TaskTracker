<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { api } from '../api/index.js';
import Icon from './Icon.vue';
const props = defineProps({ project: Object, members: Array, userId: String });
const emit = defineEmits(['close', 'created']);
const dialog = ref(null);
const form = reactive({ name: props.project?.name || '', code: props.project?.code || '', description: props.project?.description || '', leadId: props.project ? props.project.leadId || '' : props.userId });
const initial = JSON.stringify(form);
const dirty = computed(() => JSON.stringify(form) !== initial);
const baseRevision = props.project?.revision;
const saving = ref(false);
const error = ref('');
let pending;
function beforeUnload(event) { if (dirty.value) { event.preventDefault(); event.returnValue = ''; } }
onMounted(() => { dialog.value.showModal(); window.addEventListener('beforeunload', beforeUnload); });
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
function close() {
  if (saving.value) return;
  if (dirty.value && !window.confirm('還有未儲存的專案設定, 確定要關閉嗎？')) return;
  emit('close');
}
async function submit() {
  if (saving.value) return;
  saving.value = true;
  error.value = '';
  const signature = JSON.stringify(form);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  try { emit('created', await api[props.project ? 'updateProject' : 'createProject']({ ...form, ...(props.project ? { id: props.project.id, expectedRevision: baseRevision } : {}), requestId: pending.requestId })); }
  catch (failure) { error.value = failure.code === 'REVISION_CONFLICT' ? '專案已被更新, 你的輸入已保留。請關閉後重新開啟表單取得最新設定' : failure.message; }
  finally { saving.value = false; }
}
</script>

<template>
  <dialog ref="dialog" class="project-dialog" aria-labelledby="project-heading" @cancel.prevent="close">
    <form @submit.prevent="submit">
      <div class="modal-top"><span class="modal-icon"><Icon name="folder" :size="24" /></span><button type="button" class="icon-button" aria-label="關閉專案表單" :disabled="saving" @click="close"><Icon name="close" /></button></div>
      <h2 id="project-heading">{{ project ? '編輯專案' : '建立專案' }}</h2>
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4" role="alert">{{ error }}</v-alert>
      <v-text-field v-model="form.name" label="專案名稱" aria-label="專案名稱" placeholder="例如：個人網站改版" maxlength="60" required autofocus :disabled="saving" />
      <v-text-field v-model="form.code" label="專案代碼" aria-label="專案代碼" placeholder="例如：WEB" minlength="2" maxlength="8" pattern="[A-Za-z][A-Za-z0-9]{1,7}" required :disabled="saving" :readonly="Boolean(project)" :hint="project ? '專案代碼不可修改' : '2–8 位英數字，首字為英文字母'" persistent-hint />
      <label class="field">專案負責人<select v-model="form.leadId" aria-label="專案負責人" :disabled="saving"><option value="">未設定</option><option v-for="member in members.filter(member => !member.disabledAt)" :key="member.id" :value="member.id">{{ member.name }}</option><option v-if="form.leadId && !members.some(member => member.id === form.leadId && !member.disabledAt)" :value="form.leadId" disabled>成員已無法使用（需重新設定）</option></select></label>
      <v-textarea v-model="form.description" label="專案介紹" aria-label="專案介紹" placeholder="輸入專案介紹…" rows="3" maxlength="500" :disabled="saving" />
      <v-btn type="submit" color="primary" block :loading="saving" :disabled="saving || (Boolean(project) && !dirty)" :prepend-icon="project ? 'mdi-check' : 'mdi-plus'">{{ project ? '儲存專案' : '建立專案' }}</v-btn>
    </form>
  </dialog>
</template>
