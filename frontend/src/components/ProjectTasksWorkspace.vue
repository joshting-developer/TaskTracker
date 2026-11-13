<script setup>
import { computed, nextTick, onBeforeUnmount, ref, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import { useLeaveGuard } from '../ui/leaveGuard.js';
import Icon from './Icon.vue';
import { lazyFeature } from '../ui/lazyFeature.js';
import { useBoardRealtime } from '../ui/boardRealtime.js';
import { useIssueTransitions } from '../composables/useIssueTransitions.js';
import { useProjectTaskQueries } from '../composables/useProjectTaskQueries.js';
import { useIssueQuery } from '../composables/useIssueQuery.js';
import IssueFilters from './IssueFilters.vue';
import IssueList from './IssueList.vue';

const BoardList = lazyFeature(() => import('./BoardList.vue'));
const BoardDialog = lazyFeature(() => import('./BoardDialog.vue'));
const ProjectBoard = lazyFeature(() => import('./ProjectBoard.vue'));
const IssuePanel = lazyFeature(() => import('./IssuePanel.vue'));
const IssueDetail = lazyFeature(() => import('./IssueDetail.vue'));

const props = defineProps({ project: Object, mode: String, scope: { type: String, default: 'all' }, boardId: String, issueId: String, detailPage: Boolean, userId: String, workspaceId: String, today: String });
const emit = defineEmits(['board', 'list', 'issue', 'close-issue', 'notify', 'access', 'project', 'board-missing', 'issue-missing']);
const error = ref(''), filtersExpanded = ref(false);
const boardEditor = shallowRef(null), editor = shallowRef(null), detail = shallowRef(null), members = shallowRef([]), candidates = shallowRef([]);
const boardTool = ref(null), issueTool = ref(null), detailTool = ref(null);
const canWrite = computed(() => props.project.permissions.includes('issue.write'));
const canManage = computed(() => props.project.permissions.includes('board.manage'));
const canDelete = computed(() => props.project.permissions.includes('issue.delete'));
const scopeKey = computed(() => `${props.workspaceId}:${props.project.id}:${props.mode}:${props.boardId}:${props.scope}:${props.detailPage ? props.issueId : 'browse'}`);
const archiving = ref(false), archiveRequests = new Map();
const deleting = ref(false), deleteRequests = new Map();
const busy = computed(() => Boolean(archiving.value || deleting.value || issueTool.value?.saving || detailTool.value?.saving || boardTool.value?.saving));
const dirty = computed(() => Boolean(issueTool.value?.dirty || detailTool.value?.dirty || boardTool.value?.dirty));
let detailRequest, optionRequest, detailGeneration = 0, optionGeneration = 0, alive = true;
const rows = shallowRef([]);
// 拖曳中或移動送出中暫停背景換列，避免輪詢／即時通知把卡片換回舊位置或中斷拖曳。
const dragging = ref(false), holdBackground = shallowRef(false);
const detailRows = computed(() => unique([...(detail.value?.references || []), ...(editor.value?.issue?.references || []), ...candidates.value, ...rows.value, ...(detail.value ? [detail.value] : [])]));
const contextRows = computed(() => unique([...rows.value.map(row => row.epic).filter(Boolean), ...rows.value]));
const memberNames = computed(() => unique([...members.value, ...rows.value.filter(row => row.assignee).map(row => ({ id: row.assignee, name: row.assigneeName })), ...(detail.value?.assignee ? [{ id: detail.value.assignee, name: detail.value.assigneeName }] : [])]));
function rememberOption({kind, item}) {
  if (kind === 'member') members.value = unique([...members.value, item]);
  else candidates.value = unique([...candidates.value, item]);
}
function unique(items) { return [...new Map(items.map(item => [item.id, item])).values()]; }
const localScope = computed(() => props.scope), pageView = computed(() => props.mode === 'list' ? 'list' : 'board');
const filters = useIssueQuery({ remote: computed(() => props.mode === 'list'), project: computed(() => props.project), issues: computed(() => unique([...contextRows.value, ...candidates.value])), boardIssues: rows,
  members: memberNames, userId: computed(() => props.userId), today: computed(() => props.today), view: pageView, scope: localScope, projectId: computed(() => props.project.id) });
const filterInput = filters.filterInput;
const data = useProjectTaskQueries({ onDetail: loadDetail, detailMode: computed(() => Boolean(props.issueId && props.detailPage)), projectId: computed(() => props.project.id), boardId: computed(() => props.boardId), mode: computed(() => props.mode), context: scopeKey, rows, listQuery: computed(() => ({...filterInput.value, scope: props.scope, page: filters.page.value, perPage: 20})), blocked: computed(() => busy.value || dirty.value), hold: holdBackground, onProject: value => emit('project', value), onError: handleFailure, onSuccess: () => { error.value = ''; } });
const { board, boardPage, issuePage, listPage, listSearch, loading, loadingCards, pageVisible, reload } = data;
watch(issuePage, value => { if (value && filters.page.value > value.lastPage) filters.page.value = value.lastPage; });
const transition = useIssueTransitions({ canWrite, contextKey: scopeKey, saved: async () => { await reload(); if (detail.value) await loadDetail(); }, refresh: async () => { await reload(); if (detail.value) await loadDetail(); }, onError: value => { error.value = value; }, apply: applyMove });
const moving = transition.moving;
watch([dragging, moving], () => { holdBackground.value = dragging.value || Boolean(moving.value); }, { flush: 'sync' });
watch(scopeKey, () => { dragging.value = false; });
/** 拖曳放下即先移動卡片；失敗時若列仍是本次套用的版本才還原，避免覆蓋之後的伺服器資料。 */
function applyMove(issue, fields) {
  const original = rows.value.find(row => row.id === issue.id);
  if (!original) return undefined;
  const moved = { ...original, ...fields };
  rows.value = rows.value.map(row => row === original ? moved : row);
  return () => { if (rows.value.includes(moved)) rows.value = rows.value.map(row => row === moved ? original : row); };
}
useLeaveGuard({ dirty: () => Boolean(dirty.value), saving: () => Boolean(busy.value || moving.value) });
defineExpose({ create: prepareEditor, refresh: reload });
const realtime = useBoardRealtime({ active: computed(() => props.mode === 'board' && !props.detailPage && pageVisible.value), userId: computed(() => props.userId), workspaceId: computed(() => props.workspaceId), projectId: computed(() => props.project.id), refresh: () => reload(false) });

function handleFailure(failure) {
  if (['ABORTED', 'STALE_RESPONSE'].includes(failure.code)) return;
  error.value = failure.message;
  if (['UNAUTHENTICATED', 'FORBIDDEN', 'NOT_FOUND', 'WORKSPACE_CHANGED'].includes(failure.code)) {
    detail.value = null; editor.value = null; boardEditor.value = null;
    // 自訂看板已移除時回看板列表；若連Project也失效，列表查詢會再走一般撤權流程。
    if (failure.code === 'NOT_FOUND' && props.mode === 'board' && !props.issueId && props.boardId !== 'default') emit('board-missing');
    else emit('access');
  }
}

watch(canWrite, allowed => { if (!allowed) editor.value = null; });
watch(canManage, allowed => { if (!allowed) boardEditor.value = null; });
function lookup(kind, filters, options) {
  return kind === 'member' ? api.members(props.project.id, filters, options) : api.issueOptions(props.project.id, {...filters, kind, exclude: props.issueId || ''}, options);
}
function loadActivity(id, kind, filters, options) { return api.activity(props.project.id, id, kind, filters, options); }
async function loadDetail() {
  const id = props.issueId;
  const current = ++detailGeneration;
  detailRequest?.abort(); detailRequest = new AbortController();
  if (!id) { detail.value = null; return; }
  try {
    const value = await api.issue(props.project.id, id, { signal: detailRequest.signal });
    if (current !== detailGeneration) return false;
    detail.value = value;
    if (!props.detailPage && canWrite.value) await prepareEditor(value);
    return current === detailGeneration;
  } catch (failure) {
    if (current !== detailGeneration) return false;
    // Project仍可讀但卡片不存在／不可見時回Project首頁；Project失效則走一般撤權流程。
    if (failure.code === 'NOT_FOUND') { detail.value = null; editor.value = null; emit('issue-missing'); }
    else handleFailure(failure);
    return false;
  }
}
// 編輯器衝突後重讀：取得最新版本才重設表單，無論成功與否都結束儲存狀態。
async function reloadEditor(reset, settled) {
  try { if (await loadDetail()) { await nextTick(); reset?.(); } }
  finally { settled?.(); }
}
watch([() => props.issueId, () => props.detailPage], () => { editor.value = null; loadDetail(); }, { immediate: true });
async function prepareEditor(issue = null, defaults = {}) {
  const expected = scopeKey.value, selected = props.issueId, current = ++optionGeneration;
  optionRequest?.abort(); optionRequest = new AbortController();
  const requestOptions = { signal: optionRequest.signal };
  try {
    const [people, options] = await Promise.all([api.members(props.project.id, { perPage: 30 }, requestOptions), api.issueOptions(props.project.id, { perPage: 30, kind: 'epic' }, requestOptions)]);
    if (!alive || expected !== scopeKey.value || selected !== props.issueId || current !== optionGeneration || !canWrite.value) return;
    members.value = people.items; candidates.value = options.items;
    editor.value = { issue, ...defaults };
  } catch (failure) { handleFailure(failure); }
}
async function editBoard(item = null) {
  const expected = scopeKey.value;
  try {
    const current = item ? await api.board(props.project.id, item.id) : null;
    if (!alive || expected !== scopeKey.value || !canManage.value) return;
    boardEditor.value = { board: current };
  } catch (failure) { handleFailure(failure); }
}
async function savedBoard(value) { boardEditor.value = null; await nextTick(); emit('board', value.id); emit('notify', '看板已儲存'); await reload(); }
async function savedIssue(result, isNew = false) {
  const opened = editor.value;
  if (isNew) editor.value = null;
  else if (opened?.issue) {
    const fresh = await api.issue(props.project.id, opened.issue.id);
    detail.value = props.issueId === fresh.id ? fresh : detail.value;
    editor.value = { ...opened, issue: fresh };
  } else if (props.issueId) {
    detail.value = await api.issue(props.project.id, props.issueId);
  }
  await nextTick();
  await reload();
  emit('notify', '任務已儲存');
}

function closeEditor() { editor.value = null; if (!props.detailPage && props.issueId) emit('close-issue'); }
async function archive() {
  if (archiving.value || !window.confirm(`移除看板「${board.value.name}」？卡片保留`)) return;
  const key = `${board.value.id}:${board.value.revision}`;
  if (!archiveRequests.has(key)) archiveRequests.set(key, crypto.randomUUID());
  archiving.value = true;
  try {
    await api.archiveBoard({ id: board.value.id, expectedRevision: board.value.revision, requestId: archiveRequests.get(key) });
    archiveRequests.delete(key); archiving.value = false; emit('list');
  } catch (failure) { handleFailure(failure); }
  finally { archiving.value = false; }
}

// 永久刪除：確認後以目前revision送出，同一版本重試沿用requestId；成功回上一層，409（工時／子卡／版本）顯示原因。
async function removeIssue() {
  const issue = detail.value;
  if (!issue || deleting.value || !window.confirm(`永久刪除「${issue.identifier} ${issue.title}」？留言與歷史會一併刪除，無法復原。`)) return;
  const key = `${issue.id}:${issue.revision}`;
  if (!deleteRequests.has(key)) deleteRequests.set(key, crypto.randomUUID());
  deleting.value = true;
  try {
    await api.deleteIssue({ projectId: props.project.id, id: issue.id, expectedRevision: issue.revision, requestId: deleteRequests.get(key) });
    // 先解除忙碌再切回上一層，背景載入不會被blocked擋下。
    deleteRequests.delete(key); deleting.value = false; error.value = ''; detail.value = null;
    emit('close-issue'); await nextTick(); await reload(); emit('notify', '任務已刪除');
  } catch (failure) {
    if (failure.code === 'CONFLICT' || failure.code === 'REVISION_CONFLICT') error.value = failure.message;
    else handleFailure(failure);
  } finally { deleting.value = false; }
}

function beforeUnload(event) { if (dirty.value || busy.value) { event.preventDefault(); event.returnValue = ''; } }
window.addEventListener('beforeunload', beforeUnload);
onBeforeUnmount(() => { alive = false; optionGeneration++; optionRequest?.abort(); detailGeneration++; detailRequest?.abort(); window.removeEventListener('beforeunload', beforeUnload); });
</script>

<template>
  <div class="board-workspace-page">
    <div v-if="error" class="error-box" role="alert">{{ error }}<button class="text-button" @click="reload">重新載入</button></div>
    <p v-if="loading" role="status">載入中…</p>
    <IssueDetail v-if="detail && detailPage" ref="detailTool" :key="detail.id" :issue="detail" :busy="Boolean(transition.moving.value)" :project="project" :issues="detailRows" :members="memberNames" :entries="[]" :return-label="mode === 'list' ? '返回任務列表' : '返回看板'" :can-write="canWrite" :can-read-time="project.permissions.includes('timeEntry.readOwn') || project.permissions.includes('timeEntry.readTeam')" :can-log-time="project.permissions.includes('timeEntry.writeOwn')" :user-id="userId" :can-delete="canDelete" :deleting="deleting" :activity-loader="loadActivity" @delete="removeIssue" @edit="prepareEditor(detail)" @state="value => transition.moveIssue(detail, { state: value })" @open="(issue, full = true) => emit('issue', issue, full)" @close="emit('close-issue')" @saved="savedIssue" @reload="loadDetail" @time-updated="savedIssue" />
    <template v-else>
      <section v-if="mode === 'list'" class="task-workspace">
        <IssueFilters v-model:expanded="filtersExpanded" :lookup="lookup" :lookup-context="project.id" @option="rememberOption" v-model="filterInput" :states="filters.projectStates.value" :members="memberNames" :issues="detailRows" :active-filter-chips="filters.activeFilterChips.value" :filter-warnings="[]" @remove="filters.removeFilter" @clear="filters.clearFilters" />
        <IssueList :issues="rows" :states="filters.projectStates.value" :members="memberNames" :can-write="canWrite" :today="today" @open="(issue, full) => emit('issue', issue, full)" />
        <p v-if="issuePage && !issuePage.total && !loading" class="empty-state">沒有符合條件的任務</p>
        <footer v-if="issuePage" class="table-footer"><span role="status">{{ issuePage.total }} 筆結果</span><div v-if="issuePage.lastPage > 1" class="pagination"><button class="icon-button previous" aria-label="上一頁" :disabled="filters.page.value <= 1 || loading" @click="filters.page.value--"><Icon name="chevron" :size="15" /></button><span>{{ issuePage.page }} / {{ issuePage.lastPage }}</span><button class="icon-button" aria-label="下一頁" :disabled="filters.page.value >= issuePage.lastPage || loading" @click="filters.page.value++"><Icon name="chevron" :size="15" /></button></div></footer>
      </section>
      <template v-if="mode === 'boards' && boardPage">
        <BoardList :boards="[boardPage.defaultBoard, ...boardPage.items]" :issues="[]" :remote="true" :can-manage="canManage" :loading="loading" @search="value => { listPage = 1; listSearch = value; }" @open="id => emit('board', id)" @create="editBoard()" @edit="editBoard" />
        <div v-if="boardPage.lastPage > 1" class="pagination"><button class="button secondary" :disabled="listPage <= 1" @click="listPage--">上一頁</button><span>{{ listPage }} / {{ boardPage.lastPage }}</span><button class="button secondary" :disabled="listPage >= boardPage.lastPage" @click="listPage++">下一頁</button></div>
      </template>
      <section v-if="mode === 'board' && board" class="task-workspace">
        <header class="selected-board-heading"><div class="selected-board-identity"><button class="navigation-back" @click="emit('list')"><Icon name="chevron" :size="16" />返回看板列表</button><h3>{{ board.name }}</h3></div><div v-if="!board.isDefault && canManage" class="selected-board-actions"><button class="button secondary" @click="editBoard(board)">編輯收錄</button><button class="text-button danger-text" @click="archive">移除看板</button></div></header>
        <IssueFilters v-model:expanded="filtersExpanded" :lookup="lookup" :lookup-context="project.id" @option="rememberOption" v-model="filterInput" :states="filters.projectStates.value" :members="memberNames" :issues="contextRows" :active-filter-chips="filters.activeFilterChips.value" :filter-warnings="[]" @remove="filters.removeFilter" @clear="filters.clearFilters" />
        <p v-if="loadingCards" role="status">載入卡片中…</p>
        <ProjectBoard v-if="!loadingCards || rows.length" :project="project" :issues="contextRows" :visible-issues="filters.visibleIssues.value" :entries="[]" :members="memberNames" :user-id="userId" :today="today" :board-key="boardId" :sync-state="realtime" :can-write="canWrite" :can-create="canWrite && (board.isDefault || canManage)" :moving="moving" @open="(issue, full) => emit('issue', issue, full)" @drag="value => { dragging = value; }" @move="transition.moveIssue" @create="defaults => prepareEditor(null, defaults)" />
      </section>
    </template>
    <BoardDialog v-if="boardEditor" ref="boardTool" :project="project" :issues="[]" :board="boardEditor.board" :remote="true" @close="boardEditor = null" @saved="savedBoard" @reload="reload" />
    <IssuePanel :lookup="lookup" v-if="editor && canWrite" ref="issueTool" :key="editor.issue?.id || 'create'" :issue="editor.issue" :projects="[project]" :default-project="project.id" :default-type="editor.type || 'task'" :default-state="editor.state || project.statuses[0].id" :default-epic-id="editor.epicId || ''" :default-board-id="boardId" :members="memberNames" :issues="detailRows" :entries="[]" :activity-loader="loadActivity" :can-expand="true" @expand="emit('issue', detail, true)" @close="closeEditor" @open="(issue, full = false) => emit('issue', issue, full)" @saved="savedIssue" @reload="reloadEditor" />
  </div>
</template>
