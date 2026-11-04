<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Icon from './components/Icon.vue';
import IssuePanel from './components/IssuePanel.vue';
import IssueDetail from './components/IssueDetail.vue';
import IssueContext from './components/IssueContext.vue';
import ProjectDialog from './components/ProjectDialog.vue';
import ProjectHome from './components/ProjectHome.vue';
import ProjectBoard from './components/ProjectBoard.vue';
import BoardList from './components/BoardList.vue';
import BoardDialog from './components/BoardDialog.vue';
import { includedIssues } from './domain/boards.js';
import AuthGate from './components/AuthGate.vue';
import TeamDialog from './components/TeamDialog.vue';
import EpicOverview from './components/EpicOverview.vue';
import TimeCalendar from './components/TimeCalendar.vue';
import { contextKey, taskScope, writeContext } from './ui/workContext.js';
import ProjectSettings from './components/ProjectSettings.vue';
import ProjectMembers from './components/ProjectMembers.vue';
import TodoHome from './components/TodoHome.vue';
import TodoNotifications from './components/TodoNotifications.vue';
import NotificationPreview from './components/NotificationPreview.vue';
import { api } from './api/index.js';
import { categoryFor, dateInTaipei, PRIORITIES, statusesFor, TYPES } from './domain/model.js';
import { CONDITIONS, filterIssues, missingFilters } from './domain/filters.js';

const workspace = ref({ projects: [], issues: [], members: [], timeEntries: [], notifications: [], meta: {} });
const authenticated = ref(false);
const todoData = ref({ todos: [], notifications: [], members: [] });
const todoTool = ref(null), conversionTool = ref(null), convertingTodo = ref(null), todoNotificationsOpen = ref(false);
const todoUnread = computed(() => todoData.value.notifications.filter(item => !item.readAt).length);
const convertibleProjects = computed(() => workspace.value.projects.filter(project => project.permissions?.includes('issue.write')));
const teamOpen = ref(false);
const section = ref('tasks');
const sectionPermissions = { settings: 'project.statuses.update', members: 'project.members.manage', time: 'timeEntry.readOwn' };
const workspaceTool = ref(null);
const defaultType = ref('task');
const defaultEpicId = ref('');
const loading = ref(true);
const error = ref('');
const view = ref('list');
const boardId = ref('default'), boardEditor = ref(null), boardEditorTool = ref(null);
const scope = ref('all');
const projectId = ref('');
const search = ref('');
const stateFilter = ref('');
const priorityFilter = ref('');
const assigneeFilter = ref(''), epicFilter = ref(''), typeFilter = ref(''), conditionFilter = ref('');
const sort = ref('updated');
const filtersOpen = ref(false);
const page = ref(1);
const selectedId = ref('');
const detailPage = ref(false), editingDetail = ref(false), detailTool = ref(null), issueTool = ref(null);
let issueReturn = null;
const creatingIssue = ref(false);
const defaultState = ref('todo');
const creatingProject = ref(false);
const editingProject = ref(null);
const sidebarOpen = ref(false);
const toast = ref('');
const demoLoading = ref(false);
const moving = ref('');
const today = ref(dateInTaipei());
const pageSize = 20;
let refreshGeneration = 0;
let toastTimer;
let dateTimer;
let opener;
const moveRequests = new Map();
const demoRequestId = crypto.randomUUID();

const projects = computed(() => workspace.value.projects);
const issues = computed(() => workspace.value.issues);
const selectedProject = computed(() => projects.value.find((item) => item.id === projectId.value));
function projectCan(project, permission) { return Boolean(project?.permissions?.includes(permission)); }
const can = permission => projectCan(selectedProject.value, permission);
const canWrite = computed(() => can('issue.write'));
const creationBoardId = computed(() => section.value === 'tasks' && view.value === 'board' ? boardId.value : 'default');
const canCreateIssue = computed(() => canWrite.value && (creationBoardId.value === 'default' || can('board.manage')));
const projectMembers = computed(() => workspace.value.members.filter(member => selectedProject.value?.memberIds?.includes(member.id) && !member.disabledAt));
const selectedIssue = computed(() => issues.value.find((item) => item.id === selectedId.value && item.projectId === projectId.value));
const showIssuePage = computed(() => Boolean(selectedIssue.value && (detailPage.value || !canWrite.value)));
const projectAllIssues = computed(() => issues.value.filter((item) => item.projectId === projectId.value));
const projectIssues = computed(() => projectAllIssues.value.filter((item) => item.type !== 'epic'));
const projectBoards = computed(() => (workspace.value.boards || []).filter(board => board.projectId === projectId.value));
const selectedBoard = computed(() => projectBoards.value.find(board => board.id === boardId.value));
const boardIssues = computed(() => includedIssues(selectedBoard.value, projectAllIssues.value));
const boardEpicIds = computed(() => [...new Set([...boardIssues.value.filter(issue => issue.type === 'epic').map(issue => issue.id), ...boardIssues.value.map(issue => issue.epicId).filter(Boolean)])]);
const projectStates = computed(() => statusesFor(selectedProject.value));
const currentUserId = computed(() => workspace.value.meta.userId);
const workContextKey = computed(() => contextKey(currentUserId.value, workspace.value.meta.workspace?.id, selectedProject.value?.id));
function restoreTaskScope() {
  if (view.value === 'list' && section.value === 'tasks') scope.value = taskScope(workContextKey.value);
}
watch(workContextKey, restoreTaskScope, { flush: 'sync' });
const currentFilters = computed(() => ({ search: search.value, scope: scope.value, state: stateFilter.value, priority: priorityFilter.value, assignee: assigneeFilter.value, epicId: epicFilter.value, type: typeFilter.value, condition: conditionFilter.value, sort: sort.value, view: view.value }));
const filterWarnings = computed(() => selectedProject.value ? missingFilters(currentFilters.value, selectedProject.value, workspace.value.members, projectAllIssues.value) : []);
function memberName(id) { return workspace.value.members.find((member) => member.id === id)?.name || id || ''; }
function done(issue) { return categoryFor(issue, selectedProject.value) === 'done'; }
const visibleIssues = computed(() => selectedProject.value ? filterIssues(view.value === 'board' ? boardIssues.value : projectAllIssues.value, currentFilters.value, { project: selectedProject.value, userId: currentUserId.value, members: workspace.value.members, today: today.value }) : []);
const pageCount = computed(() => Math.max(1, Math.ceil(visibleIssues.value.length / pageSize)));
const pagedIssues = computed(() => visibleIssues.value.slice((page.value - 1) * pageSize, page.value * pageSize));
const title = computed(() => selectedProject.value?.name || '我的專案');
const currentFeature = computed(() => section.value === 'tasks'
  ? view.value === 'boards' ? '看板列表' : view.value === 'board' ? '任務看板' : scope.value === 'mine' ? '我的任務' : scope.value === 'done' ? '已完成' : '任務列表'
  : { epics: 'Epic 概覽', time: '工時月曆', settings: '狀態設定', members: '專案成員', notifications: '通知預覽' }[section.value]);
const filterActive = computed(() => search.value || stateFilter.value || priorityFilter.value || assigneeFilter.value || epicFilter.value || typeFilter.value || conditionFilter.value);
const filterFields = { state: stateFilter, priority: priorityFilter, assignee: assigneeFilter, epicId: epicFilter, type: typeFilter, condition: conditionFilter };
const activeFilterChips = computed(() => [
  { key: 'state', label: '狀態', value: stateFilter.value, name: projectStates.value.find(item => item.id === stateFilter.value)?.label || '已移除' },
  { key: 'priority', label: '優先級', value: priorityFilter.value, name: PRIORITIES.find(item => item.id === priorityFilter.value)?.label },
  { key: 'assignee', label: '負責人', value: assigneeFilter.value, name: workspace.value.members.find(item => item.id === assigneeFilter.value)?.name || '已移除' },
  { key: 'epicId', label: 'Epic', value: epicFilter.value, name: epicFilter.value === 'unlinked' ? '尚未掛入 Epic' : projectAllIssues.value.find(item => item.id === epicFilter.value)?.title || '已移除' },
  { key: 'type', label: '類型', value: typeFilter.value, name: TYPES.find(item => item.id === typeFilter.value)?.label },
  { key: 'condition', label: '情況', value: conditionFilter.value, name: CONDITIONS.find(item => item.id === conditionFilter.value)?.label || '已停用' },
].filter(item => item.value));

watch([search, stateFilter, priorityFilter, assigneeFilter, epicFilter, typeFilter, conditionFilter, sort, scope, projectId], () => { page.value = 1; });
watch(pageCount, (count) => { if (page.value > count) page.value = count; });
watch([projectId, view], () => { filtersOpen.value = false; });

async function refresh() {
  const generation = ++refreshGeneration;
  error.value = '';
  try {
    const data = await api.getWorkspace();
    if (generation !== refreshGeneration) return;
    workspace.value = data;
    authenticated.value = true;
    if (!data.meta.permissions?.includes('project.create')) creatingProject.value = false;
    if (editingProject.value && !projectCan(data.projects.find(item => item.id === editingProject.value.id), 'project.update')) editingProject.value = null;
    if (projectId.value && !selectedProject.value) {
      selectedId.value = ''; detailPage.value = false; editingDetail.value = false; creatingIssue.value = false; boardEditor.value = null; issueReturn = null;
      section.value = 'tasks'; projectId.value = ''; view.value = 'list'; boardId.value = 'default';
      clearFilters(); history.replaceState(null, '', '#/'); notify('專案不存在或你的存取權限已變更');
    }
    if (!canWrite.value) { creatingIssue.value = false; editingDetail.value = false; }
    if (!can('board.manage')) boardEditor.value = null;
    ensureSectionAccess();
    if (selectedId.value && !projectId.value) {
      const legacyIssue = data.issues.find((item) => item.id === selectedId.value);
      if (legacyIssue) { history.replaceState(null, '', projectHash(legacyIssue.projectId, legacyIssue.id)); readHash(); }
      else { notify('找不到這個任務'); goHome(true); }
    }
    if (projectId.value && !data.projects.some((item) => item.id === projectId.value)) { notify('找不到這個專案'); goHome(true); }
    if (projectId.value && view.value === 'board' && !selectedBoard.value) { notify('看板不存在或已移除'); navigate('all', projectId.value, 'boards'); }
    if (selectedId.value && !selectedIssue.value) { notify('找不到這個專案內的任務'); history.replaceState(null, '', projectHash(projectId.value)); readHash(); }
    // 專案撤權先反映, 待辦 API 暫時失敗不能阻擋私有專案的清除
    if (convertingTodo.value && !convertibleProjects.value.length) convertingTodo.value = null;
    const personal = data.meta.permissions?.includes('todo.read') ? await api.getTodos() : { todos: [], notifications: [], members: [] };
    if (generation !== refreshGeneration) return;
    todoData.value = personal;
    if (convertingTodo.value && !personal.todos.some(item => item.id === convertingTodo.value.id && item.capabilities.convert)) { convertingTodo.value = null; notify('待辦資格已改變, 已關閉轉卡表單'); }

  } catch (failure) { if (generation === refreshGeneration) { if (failure.code === 'UNAUTHENTICATED') { authenticated.value = false; todoData.value = { todos: [], notifications: [], members: [] }; convertingTodo.value = null; todoNotificationsOpen.value = false; teamOpen.value = false; creatingProject.value = false; editingProject.value = null; boardEditor.value = null; creatingIssue.value = false; selectedId.value = ''; detailPage.value = false; editingDetail.value = false; workspace.value = { projects: [], issues: [], members: [], timeEntries: [], notifications: [], meta: {} }; } else error.value = failure.message; } }
  finally { if (generation === refreshGeneration) loading.value = false; }
}
function notify(message) {
  toast.value = message;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toast.value = ''; }, 3500);
}
function projectHash(id, issueId = '', mode = view.value, selectedBoardId = boardId.value, fullPage = detailPage.value, targetSection = issueId ? 'tasks' : section.value) {
  const featurePath = targetSection !== 'tasks' ? `/${targetSection}` : mode === 'boards' ? '/boards' : mode === 'board' ? `/board${selectedBoardId === 'default' ? '' : `/${encodeURIComponent(selectedBoardId)}`}` : '';
  return id ? `#/projects/${encodeURIComponent(id)}${featurePath}${issueId ? `/issues/${encodeURIComponent(issueId)}${fullPage ? '?detail=1' : ''}` : ''}` : '#/';
}
function ensureSectionAccess() {
  const permission = sectionPermissions[section.value];
  if (selectedProject.value && permission && !can(permission)) {
    section.value = 'tasks'; view.value = 'list'; boardId.value = 'default';
    history.replaceState(null, '', projectHash(projectId.value));
    notify('你沒有此功能的存取權限, 已返回任務列表');
  }
}
function canLeave() {
  if (workspaceTool.value?.saving || detailTool.value?.saving || issueTool.value?.saving || todoTool.value?.saving || conversionTool.value?.saving) return false;
  return (!todoTool.value?.dirty && !conversionTool.value?.dirty && !workspaceTool.value?.dirty && !boardEditorTool.value?.dirty && !detailTool.value?.dirty && !issueTool.value?.dirty) || window.confirm('還有未儲存的內容, 確定要離開嗎？');
}
function changeSection(value) {
  if ((value !== section.value || selectedId.value) && canLeave()) {
    issueReturn = null;
    history.pushState(null, '', projectHash(projectId.value, '', 'list', 'default', false, value));
    readHash(true);
  }
}
async function logout() { if (!canLeave()) return; try { await api.logout(); ++refreshGeneration; authenticated.value = false; todoData.value = { todos: [], notifications: [], members: [] }; convertingTodo.value = null; todoNotificationsOpen.value = false; workspace.value = { projects: [], issues: [], members: [], timeEntries: [], notifications: [], meta: {} }; history.replaceState(null, '', '#/'); readHash(true); } catch (failure) { notify(failure.message); } }
async function authenticatedSession() { loading.value = true; readHash(); await refresh(); }
function convertTodo(item) {
  if (!canLeave()) return;
  convertingTodo.value = { ...item };
}
async function reloadConversion(callback, settled = () => {}) {
  try {
    await refresh();
    const current = todoData.value.todos.find(item => item.id === convertingTodo.value?.id);
    if (!error.value && current) { convertingTodo.value = { ...current }; await nextTick(); callback(); }
  } finally { settled(); }
}
async function convertedTodo(result) {
  convertingTodo.value = null;
  await refresh();
  const link = todoData.value.todos.find(item => item.id === result.id)?.convertedIssue;
  notify('已轉成正式卡片, 原待辦保留轉卡紀錄');
  if (link) openTodoIssue(link);
}
function openTodoIssue(link) {
  const issue = workspace.value.issues.find(item => item.id === link.id && item.projectId === link.projectId);
  if (issue) openIssue(issue, true); else notify('目前無法存取這張卡片');
}
function jumpFromConversion(issue) {
  convertingTodo.value = null;
  nextTick(() => openTodoIssue(issue));
}
async function openTodoNotification(item) {
  if (!canLeave()) return;
  try {
    await api.readTodoNotification({ id: item.id, requestId: crypto.randomUUID() });
    todoNotificationsOpen.value = false;
    await refresh();
    history.pushState(null, '', '#/'); readHash(true);
    await nextTick(); await todoTool.value?.focus(item.todoId);
  } catch (failure) { notify(failure.message); await refresh(); }
}
async function toolUpdated(message) { await refresh(); if (!error.value) notify(message); }
function navigate(nextScope, id = projectId.value, mode = 'list') {
  if (!canLeave()) return;
  issueReturn = null;
  section.value = 'tasks';
  location.hash = projectHash(id, '', mode);
  readHash(true);
  scope.value = nextScope;
  if (mode === 'list') writeContext(workContextKey.value, { scope: nextScope });
  clearFilters();
  sidebarOpen.value = false;
}
function openBoard(id) {
  if (!canLeave()) return;
  issueReturn = null;
  location.hash = projectHash(projectId.value, '', 'board', id); readHash(true); sidebarOpen.value = false;
}
async function boardSaved(board) {
  workspace.value = { ...workspace.value, boards: [...(workspace.value.boards || []).filter(item => item.id !== board.id), board] };
  boardEditor.value = null; await nextTick(); openBoard(board.id); await refresh(); notify('看板已儲存');
}
const boardArchiveRequests = new Map();
async function archiveBoard() {
  const board = selectedBoard.value;
  if (!board || board.isDefault || !window.confirm(`移除看板「${board.name}」？卡片與工時都會保留`)) return;
  const key = `${board.id}:${board.revision}`;
  if (!boardArchiveRequests.has(key)) boardArchiveRequests.set(key, crypto.randomUUID());
  try { await api.archiveBoard({ id: board.id, expectedRevision: board.revision, requestId: boardArchiveRequests.get(key) }); boardArchiveRequests.delete(key); navigate('all', projectId.value, 'boards'); await refresh(); notify('看板已移除, 卡片保留'); }
  catch (failure) { error.value = failure.message; if (failure.code === 'REVISION_CONFLICT') await refresh(); }
}
async function goToTodo() {
  // 首頁內定位不離開元件, 已輸入的速記／編輯草稿照常保留。
  if (selectedProject.value) { if (!canLeave()) return; history.pushState(null, '', '#/'); readHash(true); }
  sidebarOpen.value = false;
  await nextTick(); todoTool.value?.focusQuickEntry();
}
function goHome(replace = false) {
  if (replace) { history.replaceState(null, '', '#/'); readHash(); }
  else navigate('all', '');
  sidebarOpen.value = false;
}
function openIssue(issue, fullPage = detailPage.value, approved = false) {
  fullPage = fullPage || !projectCan(projects.value.find(project => project.id === issue.projectId), 'issue.write');
  if (!approved && !canLeave()) return;
  if (!selectedId.value) {
    opener = document.activeElement;
    issueReturn = { section: section.value, scope: scope.value, scroll: window.scrollY };
  }
  history.pushState(null, '', projectHash(issue.projectId, issue.id, view.value, boardId.value, fullPage));
  readHash(true);
  if (fullPage) nextTick(() => { window.scrollTo(0, 0); detailTool.value?.focus(); });
}
function jumpFromEditor(issue) {
  if (detailTool.value?.dirty && !window.confirm('完整頁還有未送出的留言, 確定要切換任務嗎？')) return;
  openIssue(issue, detailPage.value, true);
}
function issueReturnLabel() {
  return { epics: '返回 Epic 概覽', time: '返回工時月曆', notifications: '返回通知預覽' }[issueReturn?.section] || (view.value === 'board' ? '返回看板' : '返回任務列表');
}
function expandIssue() {
  if (!canLeave()) return;
  history.replaceState(null, '', projectHash(projectId.value, selectedId.value, view.value, boardId.value, true));
  readHash(true);
  nextTick(() => { window.scrollTo(0, 0); detailTool.value?.focus(); });
}
function readHash(skipGuard = false) {
  const [path, query = ''] = location.hash.split('?');
  const route = path.match(/^#\/projects\/([^/]+)(?:\/(board|boards)(?:\/((?!issues(?:\/|$))[^/]+))?)?(?:\/issues\/([^/]+))?$/);
  const featureRoute = path.match(/^#\/projects\/([^/]+)\/(epics|time|settings|members|notifications)$/);
  const legacyId = path.match(/^#\/issue\/([^/]+)$/)?.[1];
  const retiredView = route && new URLSearchParams(query).has('view');
  let nextProject = '', nextIssue = '', nextBoard = 'default';
  try {
    nextProject = route || featureRoute ? decodeURIComponent((route || featureRoute)[1]) : '';
    nextIssue = route?.[4] ? decodeURIComponent(route[4]) : legacyId ? decodeURIComponent(legacyId) : '';
    if (route?.[2] === 'board' && route[3]) nextBoard = decodeURIComponent(route[3]);
    if (legacyId) {
      const issue = issues.value.find((item) => item.id === nextIssue);
      if (issue) { nextProject = issue.projectId; history.replaceState(null, '', projectHash(nextProject, nextIssue, 'list')); }
    }
  } catch { history.replaceState(null, '', '#/'); }
  const nextMode = route?.[2] || 'list';
  const nextSection = featureRoute?.[2] || 'tasks';
  const nextDetail = Boolean(nextIssue && new URLSearchParams(query).get('detail') === '1');
  const issueChanged = selectedId.value !== nextIssue || detailPage.value !== nextDetail;
  const returning = Boolean(selectedId.value && !nextIssue);
  const projectChanged = projectId.value !== nextProject, modeChanged = view.value !== nextMode, boardChanged = boardId.value !== nextBoard;
  const sectionChanged = section.value !== nextSection;
  if (skipGuard !== true && (sectionChanged || issueChanged || projectChanged || modeChanged || boardChanged || retiredView) && !canLeave()) { history.replaceState(null, '', projectHash(projectId.value, selectedId.value)); return; }
  if (projectChanged || modeChanged || boardChanged || retiredView) {
    section.value = 'tasks'; scope.value = 'all'; boardEditor.value = null; clearFilters();
  }
  projectId.value = nextProject;
  view.value = nextMode; boardId.value = nextBoard;
  selectedId.value = nextIssue;
  detailPage.value = nextDetail;
  section.value = nextSection;
  if (issueChanged) editingDetail.value = false;
  if (nextDetail) section.value = 'tasks';
  if (returning && !projectChanged && !modeChanged && !boardChanged && issueReturn?.section === nextSection) {
    section.value = issueReturn.section; scope.value = issueReturn.scope;
    const scroll = issueReturn.scroll; issueReturn = null;
    nextTick(() => { opener?.focus({ preventScroll: true }); window.scrollTo(0, scroll); });
  }
  if (projectChanged || modeChanged || boardChanged) issueReturn = null;
  if (authenticated.value) ensureSectionAccess();
  if (projectChanged || modeChanged || sectionChanged) restoreTaskScope();
  if (retiredView) {
    history.replaceState(null, '', projectHash(nextProject, nextIssue, nextMode, nextBoard));
    notify('視圖功能已移除, 已開啟原專案頁面');
  }
  creatingIssue.value = false;
  sidebarOpen.value = false;
  if (authenticated.value && ((projectChanged && nextProject) || (nextMode === 'board' && !selectedBoard.value) || (nextIssue && !selectedIssue.value))) refresh();
}
async function closeIssue() {
  if (detailPage.value && !editingDetail.value && !canLeave()) return;
  if (detailPage.value && editingDetail.value) { editingDetail.value = false; await nextTick(); detailTool.value?.focus(); return; }
  creatingIssue.value = false; selectedId.value = ''; detailPage.value = false; editingDetail.value = false;
  if (issueReturn) { section.value = issueReturn.section; scope.value = issueReturn.scope; }
  history.replaceState(null, '', projectHash(projectId.value));
  const scroll = issueReturn?.scroll;
  issueReturn = null;
  await nextTick();
  opener?.focus({ preventScroll: true });
  if (scroll !== undefined) window.scrollTo(0, scroll);
}
function newIssue(state = '', type = 'task', epicId = '') {
  if (!canCreateIssue.value) return;
  defaultType.value = type;
  defaultEpicId.value = epicId;
  defaultState.value = typeof state === 'string' && state ? state : projectStates.value.find((item) => item.category === 'active')?.id || projectStates.value[0].id;
  opener = document.activeElement;
  if (!selectedProject.value) creatingProject.value = true;
  else creatingIssue.value = true;
}
async function savedIssue(issue, isNew) {
  ++refreshGeneration;
  workspace.value = { ...workspace.value, issues: [issue, ...issues.value.filter((item) => item.id !== issue.id)] };
  notify(isNew ? `已建立 ${issue.identifier}` : '已儲存');
  if (isNew) await closeIssue();
  await refresh();
}
async function createdProject(project) {
  ++refreshGeneration;
  workspace.value = { ...workspace.value, projects: [...projects.value.filter(item => item.id !== project.id), project] };
  const wasEditing = Boolean(editingProject.value);
  creatingProject.value = false;
  editingProject.value = null;
  goHome();
  await refresh();
  notify(wasEditing ? '專案設定已儲存' : `已建立「${project.name}」`);
}
async function loadDemo() {
  if (demoLoading.value) return;
  demoLoading.value = true;
  try {
    await api.loadDemo({ requestId: demoRequestId });
    await refresh();
    goHome();
    notify('示範專案已準備好, 點進專案就能開始試用');
  } catch (failure) { error.value = failure.message; }
  finally { demoLoading.value = false; }
}
async function moveIssue(issue, fields) {
  if (!canWrite.value) return;
  if (moving.value || Object.entries(fields).every(([key, value]) => issue[key] === value)) return;
  moving.value = issue.id;
  const signature = `${issue.id}:${issue.revision}:${JSON.stringify(fields)}`;
  if (!moveRequests.has(signature)) moveRequests.set(signature, crypto.randomUUID());
  try {
    const result = await api.updateIssue({ id: issue.id, ...fields, expectedRevision: issue.revision, requestId: moveRequests.get(signature) });
    await savedIssue(result, false);
    moveRequests.delete(signature);
  } catch (failure) {
    error.value = failure.message;
    if (failure.code === 'REVISION_CONFLICT') { await refresh(); error.value = `${failure.message}, 清單已更新`; }
  } finally { moving.value = ''; }
}
function clearFilters() { search.value = ''; stateFilter.value = ''; priorityFilter.value = ''; assigneeFilter.value = ''; epicFilter.value = ''; typeFilter.value = ''; conditionFilter.value = ''; }
function overdue(issue) { return issue.dueDate && issue.dueDate < today.value && !['done', 'canceled'].includes(categoryFor(issue, selectedProject.value)); }
function shortDate(value) { return value ? `${Number(value.slice(5, 7))}/${Number(value.slice(8))}` : ''; }
function option(items, id) { return items.find((item) => item.id === id); }
function projectFor(issue) { return projects.value.find((item) => item.id === issue.projectId); }
async function reloadEditor(callback, settled = () => {}) { try { await refresh(); if (!error.value) { await nextTick(); callback(); } } finally { settled(); } }
function beforeUnload(event) { if (todoTool.value?.dirty || conversionTool.value?.dirty || workspaceTool.value?.dirty || detailTool.value?.dirty) { event.preventDefault(); event.returnValue = ''; } }
function keydown(event) {
  if (event.key === 'Escape') sidebarOpen.value = false;
}
onMounted(async () => {
  readHash();
  window.addEventListener('hashchange', readHash);
  window.addEventListener('keydown', keydown);
  window.addEventListener('beforeunload', beforeUnload);
  window.addEventListener('tasktracker:access-changed', refresh);
  dateTimer = setInterval(() => { today.value = dateInTaipei(); if (authenticated.value) refresh(); }, 30000);
  await refresh();
});
onBeforeUnmount(() => { window.removeEventListener('hashchange', readHash); window.removeEventListener('keydown', keydown); window.removeEventListener('beforeunload', beforeUnload); window.removeEventListener('tasktracker:access-changed', refresh); clearTimeout(toastTimer); clearInterval(dateTimer); });
</script>

<template>
  <v-app>
  <AuthGate v-if="!authenticated && !loading && !error" @authenticated="authenticatedSession" />
  <div v-else-if="!authenticated" class="empty-state"><span class="loader"></span><h2>正在連線工作空間</h2><p v-if="error" role="alert">{{ error }}</p><button v-if="error" class="button secondary" @click="refresh">重試</button></div>
  <div v-else class="app-shell">
    <button v-if="sidebarOpen" class="sidebar-scrim" aria-label="收起導覽" @click="sidebarOpen = false"></button>
    <aside class="sidebar" :class="{ open: sidebarOpen }">
      <a class="brand" href="#/" aria-label="TaskTracker 返回首頁" title="返回首頁" @click.prevent="goHome()"><span class="brand-mark"><span></span><span></span><span></span></span>TaskTracker<span class="brand-dot">.</span></a>
      <template v-if="selectedProject">
        <div class="project-context" aria-label="目前專案">
          <span class="project-context-code" :style="{ color: selectedProject.color }">{{ selectedProject.code }}</span>
          <strong>{{ selectedProject.name }}</strong>
          <span class="project-context-workspace">{{ workspace.meta.workspace?.name }}</span>
        </div>
        <p class="nav-label">專案功能</p>
        <nav aria-label="專案任務">
          <button class="nav-item" :class="{ active: section === 'tasks' && view === 'list' && scope === 'all' }" :aria-current="section === 'tasks' && view === 'list' && scope === 'all' ? 'page' : undefined" aria-label="任務列表" @click="navigate('all')"><Icon name="list" :size="18" />任務列表<span class="nav-count">{{ projectIssues.length }}</span></button>
          <button class="nav-item" :class="{ active: section === 'tasks' && (view === 'board' || view === 'boards') }" :aria-current="section === 'tasks' && (view === 'board' || view === 'boards') ? 'page' : undefined" aria-label="任務看板" @click="navigate('all', projectId, 'boards')"><Icon name="board" :size="18" />任務看板</button>
          <button class="nav-item" :class="{ active: section === 'tasks' && view === 'list' && scope === 'mine' }" @click="navigate('mine')"><Icon name="user" :size="18" />我的任務<span class="nav-count">{{ projectIssues.filter(i => i.assignee === currentUserId).length }}</span></button>
          <button class="nav-item" :class="{ active: section === 'tasks' && view === 'list' && scope === 'done' }" @click="navigate('done')"><Icon name="check" :size="18" />已完成<span class="nav-count">{{ projectIssues.filter(i => done(i)).length }}</span></button>
          <button class="nav-item" :class="{ active: section === 'epics' }" :aria-current="section === 'epics' ? 'page' : undefined" @click="changeSection('epics')"><Icon name="diamond" :size="18" />Epic 概覽<span class="nav-count">{{ projectAllIssues.filter(i => i.type === 'epic').length }}</span></button>
          <button v-if="can('timeEntry.readOwn')" class="nav-item" :class="{ active: section === 'time' }" :aria-current="section === 'time' ? 'page' : undefined" @click="changeSection('time')"><Icon name="clock" :size="18" />工時月曆</button>
          <button v-if="can('project.statuses.update')" class="nav-item" :class="{ active: section === 'settings' }" :aria-current="section === 'settings' ? 'page' : undefined" @click="changeSection('settings')"><Icon name="filter" :size="18" />狀態設定</button>
          <button v-if="can('project.members.manage')" class="nav-item" :class="{ active: section === 'members' }" :aria-current="section === 'members' ? 'page' : undefined" aria-label="專案成員" @click="changeSection('members')"><Icon name="user" :size="18" />專案成員</button>
          <button class="nav-item" :class="{ active: section === 'notifications' }" :aria-current="section === 'notifications' ? 'page' : undefined" @click="changeSection('notifications')"><Icon name="message" :size="18" />通知預覽</button>
        </nav>
      </template>
      <template v-else>
        <button class="workspace-switch" aria-label="工作空間成員" @click="teamOpen = true"><span class="workspace-avatar">{{ workspace.meta.workspace?.name.slice(0, 1) }}</span><div><strong>{{ workspace.meta.workspace?.name }}</strong><span>{{ workspace.members.length }} 位可見成員 · {{ workspace.meta.permissions?.includes('workspace.invite') ? '管理與邀請' : '成員' }}</span></div><span class="workspace-chevron">⌄</span></button>
        <p class="nav-label">工作空間</p>
        <nav aria-label="工作空間">
          <button class="nav-item active" @click="goHome()"><Icon name="folder" :size="18" />專案列表<span class="nav-count">{{ projects.length }}</span></button>
        </nav>
      </template>
      <div class="sidebar-bottom"><div class="sidebar-note"><span class="spark">✦</span><strong>小步前進, 也算進步</strong><p>先記下下一步<br />再把一件事情完成</p></div><div class="profile"><div class="avatar">{{ workspace.meta.actor?.slice(0, 1) }}</div><div><strong>{{ workspace.meta.actor }}</strong><span>{{ workspace.meta.permissions?.includes('workspace.invite') ? '工作空間管理員' : '團隊成員' }}</span></div><button class="text-button" @click="logout">登出</button></div></div>
    </aside>

    <main class="main">
      <header class="topbar"><div class="breadcrumbs"><button class="icon-button mobile-menu" aria-label="開啟導覽" :aria-expanded="sidebarOpen" @click="sidebarOpen = !sidebarOpen"><Icon name="menu" /></button><Icon name="folder" :size="17" /><button class="breadcrumb-home" @click="goHome()">專案</button><Icon name="chevron" :size="13" /><strong :class="{ 'has-mobile-context': selectedProject }"><span class="project-breadcrumb">{{ selectedProject?.code || '專案列表' }}</span><span v-if="selectedProject" class="mobile-feature-context">{{ currentFeature }}</span></strong></div><div class="topbar-end"><button v-if="workspace.meta.permissions?.includes('todo.read')" class="button secondary todo-notification-toggle" :aria-label="`待辦通知${todoUnread ? `（${todoUnread} 則未讀）` : ''}`" @click="todoNotificationsOpen = true"><Icon name="bell" :size="17" /><span>待辦通知</span><b v-if="todoUnread">{{ todoUnread }}</b></button><span class="local-indicator"><i></i>本機協作試用</span><button class="icon-button" aria-label="重新整理工作空間" :disabled="loading || Boolean(moving)" @click="refresh"><Icon name="refresh" :size="18" /></button><div class="avatar tiny">{{ workspace.meta.actor?.slice(0, 1) }}</div></div></header>
      <div class="workspace-content" :class="{ 'board-workspace': section === 'tasks' && view === 'board', 'data-workspace': selectedProject && section === 'tasks', 'detail-workspace': showIssuePage }">
        <button v-if="selectedProject && section !== 'tasks'" class="back-to-projects" @click="goHome()"><Icon name="chevron" :size="14" />返回專案列表</button>
        <div v-if="showIssuePage && error" class="error-box workspace-error" role="alert">{{ error }}<button class="text-button" @click="refresh">重新載入</button></div>
        <IssueDetail v-if="showIssuePage" ref="detailTool" :key="selectedIssue.id" :issue="selectedIssue" :project="selectedProject" :issues="projectAllIssues" :members="projectMembers" :entries="workspace.timeEntries" :return-label="issueReturnLabel()" :busy="Boolean(moving)" :can-write="canWrite" :can-read-time="can('timeEntry.readOwn')" @state="state => moveIssue(selectedIssue, { state })" @close="closeIssue" @edit="canWrite && (editingDetail = true)" @open="issue => openIssue(issue, true)" @saved="savedIssue" @reload="refresh" />
        <div v-show="!showIssuePage" class="page-heading"><div><div class="eyebrow">{{ selectedProject ? 'YOUR PROJECT, IN FOCUS' : 'A SPACE FOR EVERY PROJECT' }}</div><h1>{{ title }}<span class="heading-dot">.</span></h1><p v-if="!selectedProject || (section !== 'tasks' && selectedProject.description)">{{ selectedProject ? selectedProject.description : '先選一個專案, 再專心處理裡面的事' }}</p></div><div class="heading-actions"><button v-if="!selectedProject && workspace.meta.permissions?.includes('todo.writeOwn')" class="button secondary home-todo-shortcut" @click="goToTodo">記小事</button><button v-if="selectedProject && canCreateIssue && !(section === 'tasks' && view === 'boards')" class="button primary" :disabled="loading" @click="newIssue"><Icon name="plus" :size="18" />新增任務<span class="button-key">＋</span></button><button v-else-if="!selectedProject && workspace.meta.permissions?.includes('project.create')" class="button primary" :disabled="loading" @click="creatingProject = true"><Icon name="plus" :size="18" />建立專案</button></div></div>
        <div v-if="!selectedProject" class="home-layout">
        <ProjectHome :projects="projects" :members="workspace.members" :can-create="workspace.meta.permissions?.includes('project.create')" :loading="loading" :error="error" :demo-loading="demoLoading" @create="creatingProject = true" @edit="editingProject = $event" @open="id => navigate('all', id)" @demo="loadDemo" @retry="refresh" />
        <TodoHome v-if="workspace.meta.permissions?.includes('todo.read')" ref="todoTool" :data="todoData" :user-id="currentUserId" :can-write="workspace.meta.permissions?.includes('todo.writeOwn')" :can-assign="workspace.meta.permissions?.includes('todo.assign')" :can-convert="Boolean(convertibleProjects.length)" :refresh="refresh" @convert="convertTodo" @open-issue="openTodoIssue" />
        </div>
        <template v-else>

        <BoardList v-show="!showIssuePage" v-if="section === 'tasks' && view === 'boards'" :boards="projectBoards" :issues="projectAllIssues" :can-manage="can('board.manage')" :loading="loading" @open="openBoard" @create="boardEditor = { board: null }" @edit="boardEditor = { board: $event }" />
        <section v-show="!showIssuePage" v-if="section === 'tasks' && view !== 'boards'" class="task-workspace" aria-label="任務工作區">
          <h2 class="sr-only">{{ currentFeature }}</h2>
          <header v-if="view === 'board' && selectedBoard" class="selected-board-heading"><div><button class="text-button" @click="navigate('all', projectId, 'boards')">返回看板列表</button><h3>{{ selectedBoard.name }}</h3><span class="subtle">{{ selectedBoard.isDefault ? '目前專案全部卡片' : '依收錄項目及 Epic 自動更新' }}</span></div><div v-if="!selectedBoard.isDefault && can('board.manage')" class="selected-board-actions"><button class="button secondary" @click="boardEditor = { board: selectedBoard }">編輯收錄</button><button class="text-button danger-text" @click="archiveBoard">移除看板</button></div></header>
          <div class="filter-toolbar">
            <label class="search-field"><Icon name="search" :size="18" /><input v-model="search" placeholder="搜尋任務、編號或負責人…" aria-label="搜尋任務" maxlength="180" /><button v-if="search" class="icon-button" aria-label="清除搜尋" @click="search = ''"><Icon name="close" :size="15" /></button></label>
            <div class="filters">
              <button class="button secondary filter-toggle" :class="{ active: filtersOpen || activeFilterChips.length }" aria-label="任務篩選" :aria-expanded="filtersOpen" aria-controls="task-filters" @click="filtersOpen = !filtersOpen"><Icon name="filter" :size="17" />篩選<span v-if="activeFilterChips.length" class="filter-count">{{ activeFilterChips.length }}</span></button>
              <select v-model="sort" aria-label="排序方式"><option value="updated">最近更新</option><option value="priority">優先級排序</option><option value="due">到期日排序</option></select>
            </div>
          </div>
          <div id="task-filters" v-show="filtersOpen" class="advanced-filters" role="group" aria-label="任務篩選條件">
            <label>狀態<select v-model="stateFilter" aria-label="篩選狀態"><option value="">所有狀態</option><option v-if="stateFilter && !projectStates.some(state => state.id === stateFilter)" :value="stateFilter" disabled>狀態已移除（需修正）</option><option v-for="state in projectStates" :key="state.id" :value="state.id">{{ state.label }}</option></select></label>
            <label>優先級<select v-model="priorityFilter" aria-label="篩選優先級"><option value="">所有優先級</option><option v-for="priority in PRIORITIES" :key="priority.id" :value="priority.id">{{ priority.label }}</option></select></label>
            <label>負責人<select v-model="assigneeFilter" aria-label="篩選負責人"><option value="">所有成員</option><option v-if="assigneeFilter && !workspace.members.some(member => member.id === assigneeFilter)" :value="assigneeFilter" disabled>成員已移除（需修正）</option><option v-for="member in workspace.members" :key="member.id" :value="member.id">{{ member.name }}</option></select></label>
            <label>Epic<select v-model="epicFilter" aria-label="篩選 Epic"><option value="">所有 Epic</option><option value="unlinked">尚未掛入 Epic</option><option v-if="epicFilter && epicFilter !== 'unlinked' && !projectAllIssues.some(issue => issue.id === epicFilter && issue.type === 'epic')" :value="epicFilter" disabled>Epic 已移除（需修正）</option><option v-for="epic in projectAllIssues.filter(issue => issue.type === 'epic')" :key="epic.id" :value="epic.id">{{ epic.title }}</option></select></label>
            <label>類型<select v-model="typeFilter" aria-label="篩選類型"><option value="">所有類型</option><option v-for="type in TYPES.filter(type => type.id !== 'epic')" :key="type.id" :value="type.id">{{ type.label }}</option></select></label>
            <label>情況<select v-model="conditionFilter" aria-label="篩選情況"><option v-if="conditionFilter && !CONDITIONS.some(condition => condition.id === conditionFilter)" :value="conditionFilter" disabled>舊前置篩選已停用（需修正）</option><option v-for="condition in CONDITIONS" :key="condition.id" :value="condition.id">{{ condition.label }}</option></select></label>
          </div>
          <div v-if="activeFilterChips.length" class="active-filter-chips" aria-label="已套用篩選">
            <button v-for="chip in activeFilterChips" :key="chip.key" class="active-filter-chip" :aria-label="`移除篩選：${chip.label}：${chip.name}`" @click="filterFields[chip.key].value = ''"><span>{{ chip.label }}：{{ chip.name }}</span><Icon name="close" :size="13" /></button>
            <button class="text-button" @click="clearFilters">清除全部篩選</button>
          </div>
          <div v-if="filterWarnings.length" class="filter-warning" role="alert">{{ filterWarnings.join('、') }}, 請重新選擇或清除條件。原條件保留, 沒有放寬搜尋</div>
          <div v-if="error" class="error-box workspace-error" role="alert">{{ error }}<button class="text-button" @click="refresh">重新載入</button></div>
          <div v-if="loading" class="empty-state"><span class="loader"></span><h2>正在整理你的工作空間</h2></div>
          <div v-else-if="!visibleIssues.length && view === 'list'" class="empty-state"><span class="empty-icon"><Icon :name="filterActive ? 'search' : 'check'" :size="32" /></span><h2>{{ filterActive ? '沒有符合條件的任務' : scope === 'done' ? '每件完成的事, 都會出現在這裡' : scope === 'mine' ? '目前沒有指派給你的任務' : '這裡準備好迎接第一個任務了' }}</h2><p>{{ filterActive ? '換個關鍵字, 或放寬篩選條件試試看' : scope === 'mine' ? '把任務指派給自己, 就能在這裡集中查看' : '把需要做的事情寫下來, 就是很好的開始' }}</p><button v-if="filterActive" class="button secondary" @click="clearFilters">清除篩選</button><button v-else-if="canWrite" class="button secondary" @click="newIssue"><Icon name="plus" :size="17" />新增任務</button></div>
          <div v-else-if="view === 'list'" class="table-scroll"><table class="issue-table"><thead><tr><th class="type-column" scope="col">類型</th><th>任務</th><th>狀態</th><th>優先級</th><th>負責人</th><th>到期日</th><th class="end-column"></th></tr></thead><tbody><tr v-for="issue in pagedIssues" :key="issue.id" :data-issue="issue.identifier"><td class="type-column"><span class="issue-type-label">{{ option(TYPES, issue.type)?.label || '未知類型' }}</span></td><td class="task-title-cell"><button class="task-title" @click="openIssue(issue, true)">{{ issue.title }}</button><div class="task-subline"><span class="identifier">{{ issue.identifier }}</span><span class="project-mini-dot" :style="{ background: projectFor(issue)?.color }"></span><span>{{ projectFor(issue)?.name }}</span><span v-if="issue.comments.length" class="comment-count"><Icon name="message" :size="12" />{{ issue.comments.length }}</span></div><IssueContext :issue="issue" :issues="projectAllIssues" :entries="workspace.timeEntries" :project="selectedProject" :can-read-time="can('timeEntry.readOwn')" @open="openIssue" /></td><td><span class="status-badge" :class="issue.state" :style="{ '--status-color': option(projectStates, issue.state)?.color }"><i></i>{{ option(projectStates, issue.state)?.label }}</span></td><td><span class="priority-label" :style="{ '--priority-color': option(PRIORITIES, issue.priority)?.color }"><span class="priority-bars" :class="issue.priority"><i></i><i></i><i></i></span>{{ option(PRIORITIES, issue.priority)?.label }}</span></td><td><span v-if="issue.assignee" class="assignee"><span class="avatar small">{{ memberName(issue.assignee).slice(0, 1) }}</span>{{ memberName(issue.assignee) }}</span><span v-else class="unassigned">未指派</span></td><td><span :class="['due-date', { overdue: overdue(issue) }]">{{ shortDate(issue.dueDate) || '—' }}<span v-if="overdue(issue)" class="overdue-dot" title="已過期"></span></span></td><td class="issue-row-actions"><button v-if="canWrite" class="text-button quick-edit" :aria-label="`快速編輯 ${issue.identifier}`" @click="openIssue(issue, false)">快速編輯</button><button class="icon-button row-open" :aria-label="`開啟完整頁 ${issue.identifier}`" title="開啟完整頁" @click="openIssue(issue, true)"><Icon name="chevron" :size="17" /></button></td></tr></tbody></table></div>
          <ProjectBoard v-else :key="`${currentUserId}:${projectId}:${boardId}`" :board-key="boardId" :included-epic-ids="boardEpicIds" :project="selectedProject" :issues="projectAllIssues" :visible-issues="visibleIssues" :entries="workspace.timeEntries" :members="projectMembers" :user-id="currentUserId" :can-write="canWrite" :can-create="canCreateIssue" :moving="moving" :today="today" @open="openIssue" @move="moveIssue" @create="input => newIssue(input.state, 'task', input.epicId)" />
          <footer v-if="projects.length && !loading" class="table-footer"><span v-if="view === 'list'" role="status">{{ visibleIssues.length }} 筆結果</span><span v-else>{{ canWrite ? '拖曳卡片或使用卡片狀態選單更新進度' : '目前為唯讀看板, 點擊卡片查看內容' }}</span><div v-if="view === 'list' && pageCount > 1" class="pagination"><span>{{ (page - 1) * pageSize + 1 }}–{{ Math.min(page * pageSize, visibleIssues.length) }} / {{ visibleIssues.length }}</span><button class="icon-button previous" aria-label="上一頁" :disabled="page === 1" @click="page--"><Icon name="chevron" :size="15" /></button><button class="icon-button" aria-label="下一頁" :disabled="page === pageCount" @click="page++"><Icon name="chevron" :size="15" /></button></div></footer>
        </section>
        <div v-if="error && section !== 'tasks'" class="error-box" role="alert">{{ error }}</div>
        <EpicOverview :can-write="canWrite" :can-read-time="can('timeEntry.readOwn')" v-if="section === 'epics'" :project="selectedProject" :issues="projectAllIssues" :entries="workspace.timeEntries" :members="projectMembers" @open="openIssue" @create="newIssue('', 'epic')" />
        <TimeCalendar v-if="section === 'time' && can('timeEntry.readOwn')" ref="workspaceTool" :key="workContextKey" :context-key="workContextKey" :project="selectedProject" :issues="projectAllIssues" :entries="workspace.timeEntries" :members="projectMembers" :user-id="currentUserId" @updated="toolUpdated" @open="openIssue" />
        <ProjectSettings v-if="section === 'settings' && can('project.statuses.update')" ref="workspaceTool" :project="selectedProject" :issues="projectAllIssues" :permissions="selectedProject.permissions || []" @updated="toolUpdated" />
        <ProjectMembers v-if="section === 'members' && can('project.members.manage')" ref="workspaceTool" :project="selectedProject" @updated="toolUpdated" />
        <NotificationPreview v-if="section === 'notifications'" :project="selectedProject" :notifications="workspace.notifications" :issues="projectAllIssues" :permissions="workspace.meta.permissions || []" @open="openIssue" />
        </template>
        <footer v-show="!showIssuePage" class="page-footer"><span><Icon name="sun" :size="14" />保持專注, 也記得休息一下</span><span>TaskTracker <b>v0.2</b></span></footer>
      </div>
    </main>
    <IssuePanel v-if="canWrite && (creatingIssue || (selectedIssue && (!detailPage || editingDetail)))" ref="issueTool" :can-expand="!detailPage" :key="selectedIssue?.id || 'new'" :issue="selectedIssue" :projects="selectedProject ? [selectedProject] : []" :default-project="projectId" :default-state="defaultState" :default-type="defaultType" :default-epic-id="defaultEpicId" :default-board-id="creationBoardId" :members="projectMembers" :issues="projectAllIssues" :entries="workspace.timeEntries" @expand="expandIssue" @open="jumpFromEditor" @close="closeIssue" @saved="savedIssue" @reload="reloadEditor" />
    <BoardDialog v-if="boardEditor" ref="boardEditorTool" :project="selectedProject" :issues="projectAllIssues" :board="boardEditor.board" @close="boardEditor = null" @saved="boardSaved" @reload="refresh" />
    <IssuePanel v-if="convertingTodo" ref="conversionTool" :key="`convert:${convertingTodo.id}`" :todo="convertingTodo" :projects="convertibleProjects" :members="workspace.members" :issues="workspace.issues" :entries="workspace.timeEntries" @close="convertingTodo = null" @converted="convertedTodo" @reload="reloadConversion" @open="jumpFromConversion" />
    <TodoNotifications v-if="todoNotificationsOpen" :notifications="todoData.notifications" @close="todoNotificationsOpen = false" @open="openTodoNotification" />
    <TeamDialog v-if="teamOpen" :members="workspace.members" :meta="workspace.meta" @close="teamOpen = false" />
    <ProjectDialog v-if="creatingProject || editingProject" :project="editingProject" :members="editingProject ? workspace.members.filter(member => editingProject.memberIds?.includes(member.id)) : workspace.members.filter(member => workspace.meta.projectLeadIds?.includes(member.id))" :user-id="currentUserId" @close="creatingProject = false; editingProject = null" @created="createdProject" />
    <Transition name="toast"><div v-if="toast" class="toast-message" role="status"><span><Icon name="check" :size="16" /></span>{{ toast }}</div></Transition>
  </div>
  </v-app>
</template>
