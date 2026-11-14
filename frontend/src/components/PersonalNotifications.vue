<script setup>
import { computed, ref, toRef, watch } from 'vue';
import { api } from '../api/index.js';
import { usePagedLookup } from '../composables/usePagedLookup.js';
import TodoNotifications from './TodoNotifications.vue';
const props = defineProps({ workspaceId: String, version: String, unread: Number });
const emit = defineEmits(['close', 'open', 'read']);
const query = usePagedLookup({ context: toRef(props, 'workspaceId'), load: (filters, options) => api.todoNotificationPage({ ...filters, perPage: 20 }, options) });
query.open();
watch(() => props.version, () => query.reload());
const marking = ref(false), writeError = ref('');
let requestId;
async function readAll() {
  if (marking.value || !props.unread) return;
  marking.value = true; writeError.value = '';
  requestId ||= crypto.randomUUID();
  try {
    await api.readAllTodoNotifications({ requestId });
    requestId = undefined;
    await query.reload();
    emit('read');
  } catch (failure) { writeError.value = failure.message; }
  finally { marking.value = false; }
}
const notifications = computed(() => query.result.value?.items || []);
</script>
<template><TodoNotifications :notifications="notifications" :loading="query.loading.value" :error="writeError || query.error.value" :marking="marking" :unread="unread" @read-all="readAll" :page="query.page.value" :page-count="query.result.value?.lastPage || 1" @page="query.page.value=$event" @reload="writeError ? readAll() : query.reload()" @close="emit('close')" @open="emit('open',$event)" /></template>
