<script setup>
import { computed, nextTick, ref, toRef } from 'vue';
import { api } from '../api/index.js';
import { useLeaveGuard } from '../ui/leaveGuard.js';
import { usePersonalQueries } from '../composables/usePersonalQueries.js';
import TodoHome from './TodoHome.vue';
const props = defineProps({ userId: String, workspaceId: String, permissions: Array, versions: Object, resumed: Number });
const emit = defineEmits(['convert', 'open-issue', 'access']);
const tool = ref(null), active = ref(true);
const query = usePersonalQueries({ enabled: active, versions: toRef(props, 'versions'), resumed: toRef(props, 'resumed'), draftId: computed(() => tool.value?.draftId), onError: failure => { if (['FORBIDDEN', 'UNAUTHENTICATED', 'WORKSPACE_CHANGED'].includes(failure.code))
    emit('access'); } });
const { scope, page, open, counts, loading, error, data, canConvert } = query;
async function refresh() { await query.refresh(); }
async function focus(id) { await query.focus(id, { userId: props.userId, focus: async (id) => { await nextTick(); return tool.value?.focus(id, { loaded: true }); } }); }
useLeaveGuard({ dirty: () => Boolean(tool.value?.dirty), saving: () => Boolean(tool.value?.saving), unload: true });
defineExpose({ focus, focusQuickEntry: () => tool.value?.focusQuickEntry(), refresh });
</script>
<template>
  <div>
    <p v-if="loading" role="status">載入紙條中…</p><div v-if="error" class="error-box" role="alert">{{ error }}<button class="text-button" @click="refresh">重試</button></div>
    <TodoHome ref="tool" remote :scope="scope" :data="data" :counts="counts" :user-id="userId" :workspace-id="workspaceId" :can-write="permissions.includes('todo.writeOwn')" :can-assign="permissions.includes('todo.assign')" :can-convert="canConvert" :refresh="refresh" :load-item="query.loadItem" :more-history="query.moreHistory" :load-members="api.todoAssignees" @tab="active=$event !== 'create'" @scope="scope=$event" @convert="emit('convert',$event)" @open-issue="emit('open-issue',$event)" />
    <div v-if="active && open?.items?.length && open?.lastPage>1" class="pagination" aria-label="紙條分頁"><button class="text-button" :disabled="page<=1||loading" @click="page--">上一頁</button><span>{{ page }} / {{ open.lastPage }}</span><button class="text-button" :disabled="page>=open.lastPage||loading" @click="page++">下一頁</button></div>
  </div>
</template>
