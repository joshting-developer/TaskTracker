<script setup>
import { onScopeDispose, ref, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import { useLeaveGuard } from '../ui/leaveGuard.js';
import ProjectSettings from './ProjectSettings.vue';
const props = defineProps({ project: Object });
const emit = defineEmits(['updated', 'access']);
const tool = ref(null), usage = shallowRef(null), error = ref(''), loading = ref(false);
let generation = 0, controller;
async function refresh() {
  const current = ++generation;
  controller?.abort();
  controller = new AbortController();
  loading.value = true;
  error.value = '';
  try {
    const result = await api.statusUsage(props.project.id, { signal: controller.signal });
    if (current === generation) usage.value = result.usage;
  } catch (failure) {
    if (current !== generation || ['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) return;
    usage.value = null;
    error.value = failure.message;
    if (['NOT_FOUND', 'FORBIDDEN', 'WORKSPACE_CHANGED', 'UNAUTHENTICATED'].includes(failure.code)) emit('access');
  } finally {
    if (current === generation) loading.value = false;
  }
}
watch(() => [props.project.id, props.project.revision], refresh, { immediate: true });
onScopeDispose(() => { generation++; controller?.abort(); });
useLeaveGuard({ dirty: () => Boolean(tool.value?.dirty), saving: () => Boolean(tool.value?.saving), unload: true });
defineExpose({ refresh });
</script>
<template>
  <div>
    <p v-if="loading" role="status">載入狀態用量中…</p>
    <div v-if="error" class="error-box" role="alert">{{ error }}<button class="text-button" @click="refresh">重試</button></div>
    <ProjectSettings ref="tool" :project="project" :usage="usage" :issues="[]" :permissions="project.permissions || []" @updated="emit('updated', $event)" />
  </div>
</template>
