<script setup>
import { computed, ref } from 'vue';
import { api } from '../api/index.js';
import IssuePanel from './IssuePanel.vue';
const props = defineProps({ todo: Object, user: Object });
const emit = defineEmits(['close', 'converted', 'reload', 'open']);
const tool = ref(null);
function lookup(kind, filters, options, project) { return kind === 'member' ? api.members(project, filters, options) : api.issueOptions(project, { ...filters, kind }, options); }
defineExpose({ dirty: computed(() => Boolean(tool.value?.dirty)), saving: computed(() => Boolean(tool.value?.saving)) });
</script>
<template><IssuePanel ref="tool" :todo="todo" :projects="[]" :members="[user]" :issues="[]" :entries="[]" :project-loader="api.project" :project-options-loader="api.projectOptions" :lookup="lookup" @close="emit('close')" @converted="emit('converted',$event)" @reload="(callback,settled)=>emit('reload',callback,settled)" @open="emit('open',$event)" /></template>
