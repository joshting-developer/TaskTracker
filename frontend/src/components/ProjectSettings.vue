<script setup>
import { t } from '../i18n/index.js';
import { computed, ref, watch } from 'vue';
import { api } from '../api/index.js';
import { statusesFor } from '../domain/model.js';
import Icon from './Icon.vue';
const props = defineProps({ project: Object, issues: Array, usage: { type: Object, default: undefined }, permissions: Array });
const emit = defineEmits(['updated']);
const statuses = ref([]), snapshot = ref(''), error = ref(''), busy = ref(false);
const baseRevision = ref(1);
let pending;
function reset() { statuses.value = statusesFor(props.project).map((state) => ({ ...state, persisted: true, key: state.id })); snapshot.value = JSON.stringify(statuses.value); baseRevision.value = props.project.revision || 1; error.value = ''; pending = null; }
const dirty = computed(() => JSON.stringify(statuses.value) !== snapshot.value);
defineExpose({ dirty, saving: busy });
watch(() => props.project.id, reset, { immediate: true });
watch(() => props.project.revision, () => { if (!dirty.value) reset(); });
function used(id) { return props.usage === undefined ? props.issues.some((issue) => issue.state === id) : props.usage === null || Boolean(props.usage[id]); }
function required(id) { return ['open', 'closed'].includes(id); }
function existing(id) { return props.project.statuses.some(state => state.id === id); }
function addStatus() { statuses.value.push({ id: '', label: '', color: '#9386d9', persisted: false, key: crypto.randomUUID() }); }
function move(index, direction) { const next = [...statuses.value]; [next[index], next[index + direction]] = [next[index + direction], next[index]]; statuses.value = next; }
async function save() {
  if (busy.value) return;
  busy.value = true; error.value = '';
  const ids = statuses.value.map(state => state.id);
  if (ids.some(id => !existing(id) && !/^[a-z][a-z0-9_]{0,39}$/.test(id)) || new Set(ids).size !== ids.length) {
    error.value = t('type 不可重複；以小寫英文開頭，只能包含小寫英文、數字與底線，最多 40 字'); busy.value = false; return;
  }
  const payload = { projectId: props.project.id, expectedRevision: baseRevision.value, statuses: statuses.value.map(({ id, label, color }) => ({ id, label, color })) };
  const signature = JSON.stringify(payload);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  try { await api.updateStatuses({ ...payload, requestId: pending.requestId }); snapshot.value = JSON.stringify(statuses.value); emit('updated', t('狀態設定已儲存')); pending = null; }
  catch (failure) { error.value = failure.message; }
  finally { busy.value = false; }
}
</script>
<template>
  <section class="settings-workspace" :aria-label="t('專案狀態設定')"><header class="section-title"><div><h2>{{ t('狀態設定') }}</h2></div></header><div v-if="error" class="error-box" role="alert">{{ t(error) }}</div><form @submit.prevent="save" class="status-settings-form"><fieldset :disabled="busy || !permissions.includes('project.statuses.update')"><div v-for="(state, index) in statuses" :key="state.key" class="status-setting-row"><span class="status-order">{{ index + 1 }}</span><input v-model="state.color" type="color" :aria-label="t('狀態 {0} 顏色', [index + 1])" /><label class="field status-type">Type<input v-model="state.id" :readonly="state.persisted" :aria-label="t('狀態 {0} Type', [index + 1])" maxlength="100" required autocapitalize="none" spellcheck="false" /></label><label class="field">{{ t('顯示名稱') }}<input v-model="state.label" :aria-label="t('狀態 {0} 名稱', [index + 1])" maxlength="30" required /></label><div class="status-actions"><button type="button" class="icon-button" :disabled="index === 0" :aria-label="t('上移狀態 {0}', [index + 1])" @click="move(index, -1)">↑</button><button type="button" class="icon-button" :disabled="index === statuses.length - 1" :aria-label="t('下移狀態 {0}', [index + 1])" @click="move(index, 1)">↓</button><button type="button" class="icon-button" :disabled="state.persisted && (required(state.id) || used(state.id))" :title="required(state.id) ? t('必要狀態') : used(state.id) ? t('使用中, 請先移轉任務') : t('移除狀態')" :aria-label="t('移除狀態 {0}', [index + 1])" @click="statuses.splice(index, 1)"><Icon name="close" :size="17" /></button></div></div><button type="button" class="button secondary" :disabled="statuses.length >= 20" @click="addStatus"><Icon name="plus" :size="17" />{{ t('新增狀態') }}</button></fieldset><footer class="settings-footer"><p class="subtle">{{ permissions.includes('project.statuses.update') ? '' : t('需要此專案的狀態設定權限') }}</p><div><button v-if="dirty" type="button" class="text-button" :disabled="busy" @click="reset">{{ t('捨棄修改') }}</button><button v-if="permissions.includes('project.statuses.update')" class="button primary" :disabled="busy || !dirty">{{ busy ? t('儲存中…') : t('儲存狀態設定') }}</button></div></footer></form></section>
</template>
