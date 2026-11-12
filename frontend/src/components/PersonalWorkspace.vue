<script setup>
import { computed, nextTick, ref, toRef } from 'vue';
import { api } from '../api/index.js';
import { usePersonalQueries } from '../composables/usePersonalQueries.js';
import TodoHome from './TodoHome.vue';
const props = defineProps({ userId: String, workspaceId: String, permissions: Array, versions: Object, resumed: Number });
const emit = defineEmits(['convert', 'open-issue', 'access']);
const tool = ref(null);
const query = usePersonalQueries({ versions: toRef(props, 'versions'), resumed: toRef(props, 'resumed'), draftId: computed(() => tool.value?.draftId), onError: failure => { if (['FORBIDDEN', 'UNAUTHENTICATED', 'WORKSPACE_CHANGED'].includes(failure.code))
    emit('access'); } });
const { scope, page, closedPage, showClosed, open, closed, counts, loading, error, data, canConvert } = query;
async function refresh() { await query.refresh(); }
async function focus(id) { await query.focus(id, { userId: props.userId, focus: async (id) => { await nextTick(); return tool.value?.focus(id); } }); }
defineExpose({ dirty: computed(() => Boolean(tool.value?.dirty)), saving: computed(() => Boolean(tool.value?.saving)), focus, focusQuickEntry: () => tool.value?.focusQuickEntry(), refresh });
</script>
<template>
  <div>
    <p v-if="loading" role="status">載入待辦中…</p><div v-if="error" class="error-box" role="alert">{{ error }}<button class="text-button" @click="refresh">重試</button></div>
    <TodoHome ref="tool" remote :data="data" :counts="counts" :user-id="userId" :workspace-id="workspaceId" :can-write="permissions.includes('todo.writeOwn')" :can-assign="permissions.includes('todo.assign')" :can-convert="canConvert" :refresh="refresh" :load-item="query.loadItem" :more-history="query.moreHistory" :load-members="api.todoAssignees" @scope="scope=$event" @closed="showClosed=$event" @convert="emit('convert',$event)" @open-issue="emit('open-issue',$event)" />
    <div v-if="open?.lastPage>1" class="pagination" aria-label="待辦分頁"><button class="text-button" :disabled="page<=1||loading" @click="page--">上一頁</button><span>{{ page }} / {{ open.lastPage }}</span><button class="text-button" :disabled="page>=open.lastPage||loading" @click="page++">下一頁</button></div>
    <div v-if="showClosed&&closed?.lastPage>1" class="pagination" aria-label="結束待辦分頁"><button class="text-button" :disabled="closedPage<=1||loading" @click="closedPage--">上一頁</button><span>{{ closedPage }} / {{ closed.lastPage }}</span><button class="text-button" :disabled="closedPage>=closed.lastPage||loading" @click="closedPage++">下一頁</button></div>
  </div>
</template>
