<script setup>
import { onMounted, reactive, ref } from 'vue';
import { api } from '../api/index.js';
import Icon from './Icon.vue';
const emit = defineEmits(['close', 'created']);
const dialog = ref(null);
const form = reactive({ name: '', code: '', description: '' });
const saving = ref(false);
const error = ref('');
let pending;
onMounted(() => dialog.value.showModal());
function close() {
  if (saving.value) return;
  if ((form.name || form.code || form.description) && !window.confirm('專案還沒建立, 確定要關閉嗎？')) return;
  emit('close');
}
async function submit() {
  if (saving.value) return;
  saving.value = true;
  error.value = '';
  const signature = JSON.stringify(form);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  try { emit('created', await api.createProject({ ...form, requestId: pending.requestId })); }
  catch (failure) { error.value = failure.message; }
  finally { saving.value = false; }
}
</script>

<template>
  <dialog ref="dialog" class="project-dialog" aria-labelledby="project-heading" @cancel.prevent="close">
    <form @submit.prevent="submit">
      <div class="modal-top"><span class="modal-icon"><Icon name="folder" :size="24" /></span><button type="button" class="icon-button" aria-label="關閉專案表單" :disabled="saving" @click="close"><Icon name="close" /></button></div>
      <h2 id="project-heading">給下一個計畫一個家</h2><p class="form-intro">把相關任務放在一起, 專心完成一件事</p>
      <div v-if="error" class="error-box" role="alert">{{ error }}</div>
      <label class="field">專案名稱 <span class="required">*</span><input v-model="form.name" placeholder="例如：個人網站改版" maxlength="60" required autofocus :disabled="saving" /></label>
      <label class="field">專案代碼 <span class="required">*</span><input v-model="form.code" placeholder="例如：WEB" minlength="2" maxlength="8" pattern="[A-Za-z][A-Za-z0-9]{1,7}" required class="code-input" :disabled="saving" /><span class="field-hint">2–8 位英文字母或數字, 任務會使用 WEB-1 這樣的編號</span></label>
      <label class="field">簡短說明<textarea v-model="form.description" placeholder="這個專案想完成什麼？" rows="3" maxlength="500" :disabled="saving" /></label>
      <button class="button primary full-width" :disabled="saving"><Icon name="plus" :size="18" />{{ saving ? '建立中…' : '建立專案' }}</button>
    </form>
  </dialog>
</template>
