<script setup>
import { computed, ref, watch } from 'vue';
import { api } from '../api/index.js';
import { statusesFor } from '../domain/model.js';
import Icon from './Icon.vue';
const props = defineProps({ project: Object, issues: Array, permissions: Array });
const emit = defineEmits(['updated']);
const statuses = ref([]), snapshot = ref(''), error = ref(''), busy = ref(false);
const baseRevision = ref(1);
let pending;
function reset() { statuses.value = statusesFor(props.project).map((state) => ({ ...state })); snapshot.value = JSON.stringify(statuses.value); baseRevision.value = props.project.revision || 1; error.value = ''; pending = null; }
const dirty = computed(() => JSON.stringify(statuses.value) !== snapshot.value);
defineExpose({ dirty });
watch(() => props.project.id, reset, { immediate: true });
watch(() => props.project.revision, () => { if (!dirty.value) reset(); });
const categories = [{ id: 'backlog', label: '待規劃' }, { id: 'active', label: '未結案' }, { id: 'done', label: '完成' }, { id: 'canceled', label: '取消' }];
function used(id) { return props.issues.some((issue) => issue.state === id); }
function addStatus() { statuses.value.push({ id: crypto.randomUUID(), label: '', color: '#9386d9', category: 'active' }); }
function move(index, direction) { const next = [...statuses.value]; [next[index], next[index + direction]] = [next[index + direction], next[index]]; statuses.value = next; }
async function save() {
  if (busy.value) return;
  busy.value = true; error.value = '';
  const payload = { projectId: props.project.id, expectedRevision: baseRevision.value, statuses: statuses.value };
  const signature = JSON.stringify(payload);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  try { await api.updateStatuses({ ...payload, requestId: pending.requestId }); snapshot.value = JSON.stringify(statuses.value); emit('updated', '狀態設定已儲存'); pending = null; }
  catch (failure) { error.value = failure.message; }
  finally { busy.value = false; }
}
</script>
<template>
  <section class="settings-workspace" aria-label="專案狀態設定"><header class="section-title"><div><h2>狀態設定</h2></div></header><div v-if="error" class="error-box" role="alert">{{ error }}</div><form @submit.prevent="save" class="status-settings-form"><fieldset :disabled="busy || !permissions.includes('project.statuses.update')"><div v-for="(state, index) in statuses" :key="state.id" class="status-setting-row"><span class="status-order">{{ index + 1 }}</span><input v-model="state.color" type="color" :aria-label="`狀態 ${index + 1} 顏色`" /><label class="field">名稱<input v-model="state.label" :aria-label="`狀態 ${index + 1} 名稱`" maxlength="30" required /></label><label class="field">分類<select v-model="state.category" :aria-label="`狀態 ${index + 1} 分類`"><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.label }}</option></select></label><div class="status-actions"><button type="button" class="icon-button" :disabled="index === 0" :aria-label="`上移狀態 ${index + 1}`" @click="move(index, -1)">↑</button><button type="button" class="icon-button" :disabled="index === statuses.length - 1" :aria-label="`下移狀態 ${index + 1}`" @click="move(index, 1)">↓</button><button type="button" class="icon-button" :disabled="used(state.id)" :title="used(state.id) ? '使用中, 請先移轉任務' : '移除狀態'" :aria-label="`移除狀態 ${index + 1}`" @click="statuses.splice(index, 1)"><Icon name="close" :size="17" /></button></div></div><button type="button" class="button secondary" :disabled="statuses.length >= 12" @click="addStatus"><Icon name="plus" :size="17" />新增狀態</button></fieldset><footer class="settings-footer"><p class="subtle">{{ permissions.includes('project.statuses.update') ? '' : '需要此專案的狀態設定權限' }}</p><div><button v-if="dirty" type="button" class="text-button" :disabled="busy" @click="reset">捨棄修改</button><button v-if="permissions.includes('project.statuses.update')" class="button primary" :disabled="busy || !dirty">{{ busy ? '儲存中…' : '儲存狀態設定' }}</button></div></footer></form></section>
</template>
