<script setup>
import { computed, toRef, watch } from 'vue';
import { api } from '../api/index.js';
import { usePagedLookup } from '../composables/usePagedLookup.js';
import TodoNotifications from './TodoNotifications.vue';
const props = defineProps({ workspaceId: String, version: String });
const emit = defineEmits(['close', 'open']);
const query = usePagedLookup({ context: toRef(props, 'workspaceId'), load: (filters, options) => api.todoNotificationPage({ ...filters, perPage: 20 }, options) });
query.open();
watch(() => props.version, () => query.reload());
const notifications = computed(() => query.result.value?.items || []);
</script>
<template><TodoNotifications :notifications="notifications" :loading="query.loading.value" :error="query.error.value" :page="query.page.value" :page-count="query.result.value?.lastPage || 1" @page="query.page.value=$event" @reload="query.reload" @close="emit('close')" @open="emit('open',$event)" /></template>
