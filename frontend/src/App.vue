<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Icon from './components/Icon.vue';
import IssuePanel from './components/IssuePanel.vue';
import IssueContext from './components/IssueContext.vue';
import SavedViewsBar from './components/SavedViewsBar.vue';
import SavedViewDialog from './components/SavedViewDialog.vue';
import ProjectDialog from './components/ProjectDialog.vue';
import ProjectHome from './components/ProjectHome.vue';
import ProjectBoard from './components/ProjectBoard.vue';
import AuthGate from './components/AuthGate.vue';
import TeamDialog from './components/TeamDialog.vue';
import EpicOverview from './components/EpicOverview.vue';
import TimeCalendar from './components/TimeCalendar.vue';
import ProjectSettings from './components/ProjectSettings.vue';
import NotificationPreview from './components/NotificationPreview.vue';
import { api } from './api/index.js';
import { categoryFor, dateInTaipei, PRIORITIES, statusesFor, TYPES } from './domain/model.js';
import { CONDITIONS, filterIssues, missingFilters, VIEW_DEFAULTS } from './domain/views.js';

const workspace = ref({ projects: [], issues: [], members: [], timeEntries: [], notifications: [], meta: {} });
const authenticated = ref(false);
const teamOpen = ref(false);
const section = ref('tasks');
const workspaceTool = ref(null);
const defaultType = ref('task');
const defaultEpicId = ref('');
const loading = ref(true);
const error = ref('');
const view = ref('list');
const scope = ref('all');
const projectId = ref('');
const search = ref('');
const stateFilter = ref('');
const priorityFilter = ref('');
const assigneeFilter = ref(''), epicFilter = ref(''), typeFilter = ref(''), conditionFilter = ref('');
const savedViewId = ref(''), appliedViewRevision = ref(0), savedViewDialog = ref(null);
let pendingViewRoute = false;
const sort = ref('updated');
const page = ref(1);
const selectedId = ref('');
const creatingIssue = ref(false);
const defaultState = ref('todo');
const creatingProject = ref(false);
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
const selectedIssue = computed(() => issues.value.find((item) => item.id === selectedId.value && item.projectId === projectId.value));
const projectAllIssues = computed(() => issues.value.filter((item) => item.projectId === projectId.value));
const projectIssues = computed(() => projectAllIssues.value.filter((item) => item.type !== 'epic'));
const projectStates = computed(() => statusesFor(selectedProject.value));
const currentUserId = computed(() => workspace.value.meta.userId);
const projectSavedViews = computed(() => (workspace.value.savedViews || []).filter((savedView) => savedView.projectId === projectId.value && (savedView.filters.view || 'list') === view.value));
const currentFilters = computed(() => ({ search: search.value, scope: scope.value, state: stateFilter.value, priority: priorityFilter.value, assignee: assigneeFilter.value, epicId: epicFilter.value, type: typeFilter.value, condition: conditionFilter.value, sort: sort.value, view: view.value }));
const filterWarnings = computed(() => selectedProject.value ? missingFilters(currentFilters.value, selectedProject.value, workspace.value.members, projectAllIssues.value) : []);
function memberName(id) { return workspace.value.members.find((member) => member.id === id)?.name || id || ''; }
function done(issue) { return categoryFor(issue, selectedProject.value) === 'done'; }
const scopeIssues = computed(() => projectIssues.value.filter((item) => (scope.value !== 'mine' || item.assignee === currentUserId.value) && (scope.value !== 'done' || done(item))));
const visibleIssues = computed(() => selectedProject.value ? filterIssues(projectAllIssues.value, currentFilters.value, { project: selectedProject.value, userId: currentUserId.value, members: workspace.value.members, today: today.value }) : []);
const pageCount = computed(() => Math.max(1, Math.ceil(visibleIssues.value.length / pageSize)));
const pagedIssues = computed(() => visibleIssues.value.slice((page.value - 1) * pageSize, page.value * pageSize));
const completed = computed(() => scopeIssues.value.filter((item) => done(item)).length);
const validTotal = computed(() => scopeIssues.value.filter((issue) => categoryFor(issue, selectedProject.value) !== 'canceled').length);
const progress = computed(() => validTotal.value ? Math.round(completed.value / validTotal.value * 100) : 0);
const title = computed(() => selectedProject.value?.name || '我的專案');
const filterActive = computed(() => search.value || stateFilter.value || priorityFilter.value || assigneeFilter.value || epicFilter.value || typeFilter.value || conditionFilter.value);

watch([search, stateFilter, priorityFilter, assigneeFilter, epicFilter, typeFilter, conditionFilter, sort, scope, projectId], () => { page.value = 1; });
watch(pageCount, (count) => { if (page.value > count) page.value = count; });

async function refresh() {
  const generation = ++refreshGeneration;
  error.value = '';
  try {
    const data = await api.getWorkspace();
    if (generation !== refreshGeneration) return;
    workspace.value = data;
    authenticated.value = true;
    if (pendingViewRoute && projectId.value) resolveViewRoute();
    else if (savedViewId.value && !projectSavedViews.value.some((savedView) => savedView.id === savedViewId.value)) {
      notify('找不到可使用的視圖'); savedViewId.value = ''; clearFilters(); scope.value = 'all'; history.replaceState(null, '', projectHash(projectId.value, selectedId.value));
    }
    if (selectedId.value && !projectId.value) {
      const legacyIssue = data.issues.find((item) => item.id === selectedId.value);
      if (legacyIssue) { history.replaceState(null, '', projectHash(legacyIssue.projectId, legacyIssue.id)); readHash(); }
      else { notify('找不到這個任務'); goHome(true); }
    }
    if (projectId.value && !data.projects.some((item) => item.id === projectId.value)) { notify('找不到這個專案'); goHome(true); }
    if (selectedId.value && !selectedIssue.value) { notify('找不到這個專案內的任務'); history.replaceState(null, '', projectHash(projectId.value)); readHash(); }
  } catch (failure) { if (generation === refreshGeneration) { if (failure.code === 'UNAUTHENTICATED') { authenticated.value = false; teamOpen.value = false; creatingProject.value = false; creatingIssue.value = false; savedViewDialog.value = null; selectedId.value = ''; } else error.value = failure.message; } }
  finally { if (generation === refreshGeneration) loading.value = false; }
}
function notify(message) {
  toast.value = message;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toast.value = ''; }, 3500);
}
function projectHash(id, issueId = '', selectedView = '', mode = view.value) {
  return id ? `#/projects/${encodeURIComponent(id)}${mode === 'board' ? '/board' : ''}${issueId ? `/issues/${encodeURIComponent(issueId)}` : ''}${selectedView ? `?view=${encodeURIComponent(selectedView)}` : ''}` : '#/';
}
function applySavedView(savedView) {
  const filters = { ...VIEW_DEFAULTS, ...savedView.filters };
  search.value = filters.search; scope.value = filters.scope; stateFilter.value = filters.state; priorityFilter.value = filters.priority;
  assigneeFilter.value = filters.assignee; epicFilter.value = filters.epicId; typeFilter.value = filters.type; conditionFilter.value = filters.condition;
  sort.value = filters.sort; view.value = filters.view; appliedViewRevision.value = savedView.revision;
}
function resolveViewRoute() {
  pendingViewRoute = false;
  const savedView = (workspace.value.savedViews || []).find((item) => item.projectId === projectId.value && item.id === savedViewId.value);
  if (savedView) { applySavedView(savedView); history.replaceState(null, '', projectHash(projectId.value, selectedId.value, savedView.id)); }
  else { savedViewId.value = ''; clearFilters(); scope.value = 'all'; notify('找不到可使用的視圖'); history.replaceState(null, '', projectHash(projectId.value, selectedId.value)); }
}
function selectSavedView(savedView) {
  if (!savedView) { navigate('all', projectId.value, view.value); return; }
  if (!canLeave()) return;
  section.value = 'tasks'; location.hash = projectHash(projectId.value, '', savedView.id, savedView.filters.view); readHash(true); applySavedView(savedView); sidebarOpen.value = false;
}
function openViewDialog(savedView = null) { savedViewDialog.value = { savedView, filters: JSON.parse(JSON.stringify(currentFilters.value)) }; }
async function savedViewSaved(savedView) {
  workspace.value = { ...workspace.value, savedViews: [savedView, ...(workspace.value.savedViews || []).filter((item) => item.id !== savedView.id)] };
  savedViewDialog.value = null; selectSavedView(savedView); await refresh(); notify('常用視圖已儲存');
}
const deleteViewRequests = new Map();
async function removeSavedView(savedView) {
  if (!window.confirm(`移除「${savedView.name}」常用視圖？任務資料會保留`)) return;
  const key = `${savedView.id}:${savedView.revision}`;
  if (!deleteViewRequests.has(key)) deleteViewRequests.set(key, crypto.randomUUID());
  try { await api.deleteSavedView({ id: savedView.id, expectedRevision: savedView.revision, requestId: deleteViewRequests.get(key) }); deleteViewRequests.delete(key); navigate('all', projectId.value, view.value); await refresh(); notify('視圖已移除'); }
  catch (failure) { if (failure.code === 'REVISION_CONFLICT') await refresh(); error.value = failure.message; }
}
function canLeave() { return !workspaceTool.value?.dirty || window.confirm('還有未儲存的內容, 確定要離開嗎？'); }
function changeSection(value) { if (value !== section.value && canLeave()) { section.value = value; sidebarOpen.value = false; } }
async function logout() { if (!canLeave()) return; try { await api.logout(); ++refreshGeneration; authenticated.value = false; workspace.value = { projects: [], issues: [], members: [], timeEntries: [], notifications: [], meta: {} }; history.replaceState(null, '', '#/'); readHash(true); } catch (failure) { notify(failure.message); } }
async function authenticatedSession() { loading.value = true; readHash(); await refresh(); }
async function toolUpdated(message) { await refresh(); if (!error.value) notify(message); }
function navigate(nextScope, id = projectId.value, mode = 'list') {
  if (!canLeave()) return;
  section.value = 'tasks';
  location.hash = projectHash(id, '', '', mode);
  readHash(true);
  scope.value = nextScope;
  clearFilters();
  sidebarOpen.value = false;
}
function goHome(replace = false) {
  if (replace) { history.replaceState(null, '', '#/'); readHash(); }
  else navigate('all', '');
  sidebarOpen.value = false;
}
function openIssue(issue) {
  opener = document.activeElement;
  location.hash = projectHash(issue.projectId, issue.id, savedViewId.value);
  readHash();
}
function readHash(skipGuard = false) {
  const [path, query = ''] = location.hash.split('?');
  const route = path.match(/^#\/projects\/([^/]+)(\/board)?(?:\/issues\/([^/]+))?$/);
  const legacyId = path.match(/^#\/issue\/([^/]+)$/)?.[1];
  const nextView = route ? new URLSearchParams(query).get('view') || '' : '';
  let nextProject = '', nextIssue = '';
  try {
    nextProject = route ? decodeURIComponent(route[1]) : '';
    nextIssue = route?.[3] ? decodeURIComponent(route[3]) : legacyId ? decodeURIComponent(legacyId) : '';
    if (legacyId) {
      const issue = issues.value.find((item) => item.id === nextIssue);
      if (issue) { nextProject = issue.projectId; history.replaceState(null, '', projectHash(nextProject, nextIssue, '', 'list')); }
    }
  } catch { history.replaceState(null, '', '#/'); }
  const nextMode = route?.[2] ? 'board' : 'list';
  const projectChanged = projectId.value !== nextProject, viewChanged = savedViewId.value !== nextView, modeChanged = view.value !== nextMode;
  if (projectChanged || viewChanged || modeChanged) {
    if (skipGuard !== true && !canLeave()) { history.replaceState(null, '', projectHash(projectId.value, selectedId.value, savedViewId.value)); return; }
    section.value = 'tasks'; scope.value = 'all'; clearFilters();
  }
  projectId.value = nextProject;
  view.value = nextMode;
  selectedId.value = nextIssue;
  savedViewId.value = nextView;
  if (nextView && (projectChanged || viewChanged || modeChanged)) { pendingViewRoute = true; if (authenticated.value) resolveViewRoute(); }
  if (!nextView) pendingViewRoute = false;
  creatingIssue.value = false;
  sidebarOpen.value = false;
}
async function closeIssue() {
  creatingIssue.value = false;
  selectedId.value = '';
  history.replaceState(null, '', projectHash(projectId.value, '', savedViewId.value));
  await nextTick();
  opener?.focus();
}
function newIssue(state = '', type = 'task', epicId = '') {
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
  workspace.value = { ...workspace.value, projects: [...projects.value, project] };
  creatingProject.value = false;
  goHome();
  notify(`已建立「${project.name}」`);
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
async function reloadEditor(callback) { await refresh(); if (!error.value) { await nextTick(); callback(); } }
function beforeUnload(event) { if (workspaceTool.value?.dirty) { event.preventDefault(); event.returnValue = ''; } }
function keydown(event) {
  if (event.key === 'Escape') sidebarOpen.value = false;
}
onMounted(async () => {
  readHash();
  window.addEventListener('hashchange', readHash);
  window.addEventListener('keydown', keydown);
  window.addEventListener('beforeunload', beforeUnload);
  dateTimer = setInterval(() => { today.value = dateInTaipei(); if (authenticated.value) refresh(); }, 30000);
  await refresh();
});
onBeforeUnmount(() => { window.removeEventListener('hashchange', readHash); window.removeEventListener('keydown', keydown); window.removeEventListener('beforeunload', beforeUnload); clearTimeout(toastTimer); clearInterval(dateTimer); });
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
          <button class="nav-item" :class="{ active: section === 'tasks' && view === 'board' }" :aria-current="section === 'tasks' && view === 'board' ? 'page' : undefined" aria-label="任務看板" @click="navigate('all', projectId, 'board')"><Icon name="board" :size="18" />任務看板</button>
          <button class="nav-item" :class="{ active: section === 'tasks' && view === 'list' && scope === 'mine' }" @click="navigate('mine')"><Icon name="user" :size="18" />我的任務<span class="nav-count">{{ projectIssues.filter(i => i.assignee === currentUserId).length }}</span></button>
          <button class="nav-item" :class="{ active: section === 'tasks' && view === 'list' && scope === 'done' }" @click="navigate('done')"><Icon name="check" :size="18" />已完成<span class="nav-count">{{ projectIssues.filter(i => done(i)).length }}</span></button>
          <button class="nav-item" :class="{ active: section === 'epics' }" @click="changeSection('epics')"><Icon name="diamond" :size="18" />Epic 概覽<span class="nav-count">{{ projectAllIssues.filter(i => i.type === 'epic').length }}</span></button>
          <button class="nav-item" :class="{ active: section === 'time' }" @click="changeSection('time')"><Icon name="clock" :size="18" />工時月曆</button>
          <button class="nav-item" :class="{ active: section === 'settings' }" @click="changeSection('settings')"><Icon name="filter" :size="18" />狀態設定</button>
          <button class="nav-item" :class="{ active: section === 'notifications' }" @click="changeSection('notifications')"><Icon name="message" :size="18" />通知預覽</button>
        </nav>
      </template>
      <template v-else>
        <button class="workspace-switch" aria-label="工作空間成員" @click="teamOpen = true"><span class="workspace-avatar">{{ workspace.meta.workspace?.name.slice(0, 1) }}</span><div><strong>{{ workspace.meta.workspace?.name }}</strong><span>{{ workspace.members.length }} 位成員 · 管理與邀請</span></div><span class="workspace-chevron">⌄</span></button>
        <p class="nav-label">工作空間</p>
        <nav aria-label="工作空間">
          <button class="nav-item active" @click="goHome()"><Icon name="folder" :size="18" />專案列表<span class="nav-count">{{ projects.length }}</span></button>
        </nav>
      </template>
      <div class="sidebar-bottom"><div class="sidebar-note"><span class="spark">✦</span><strong>小步前進, 也算進步</strong><p>先記下下一步<br />再把一件事情完成</p></div><div class="profile"><div class="avatar">{{ workspace.meta.actor?.slice(0, 1) }}</div><div><strong>{{ workspace.meta.actor }}</strong><span>{{ workspace.meta.permissions?.includes('workspace.invite') ? '工作空間管理員' : '團隊成員' }}</span></div><button class="text-button" @click="logout">登出</button></div></div>
    </aside>

    <main class="main">
      <header class="topbar"><div class="breadcrumbs"><button class="icon-button mobile-menu" aria-label="開啟導覽" :aria-expanded="sidebarOpen" @click="sidebarOpen = !sidebarOpen"><Icon name="menu" /></button><Icon name="folder" :size="17" /><button class="breadcrumb-home" @click="goHome()">專案</button><Icon name="chevron" :size="13" /><strong>{{ selectedProject?.code || '專案列表' }}</strong></div><div class="topbar-end"><span class="local-indicator"><i></i>本機協作試用</span><button class="icon-button" aria-label="重新整理工作空間" :disabled="loading || Boolean(moving)" @click="refresh"><Icon name="refresh" :size="18" /></button><div class="avatar tiny">{{ workspace.meta.actor?.slice(0, 1) }}</div></div></header>
      <div class="workspace-content" :class="{ 'board-workspace': section === 'tasks' && view === 'board', 'data-workspace': selectedProject && section === 'tasks' }">
        <button v-if="selectedProject" class="back-to-projects" @click="goHome()"><Icon name="chevron" :size="14" />返回專案列表</button>
        <div class="page-heading"><div><div class="eyebrow">{{ selectedProject ? 'YOUR PROJECT, IN FOCUS' : 'A SPACE FOR EVERY PROJECT' }}</div><h1>{{ title }}<span class="heading-dot">.</span></h1><p>{{ selectedProject ? section === 'tasks' && view === 'board' ? '任務看板 · 依 Epic 看全貌, 拖曳卡片推進工作' : selectedProject.description || '把這個專案的下一步, 一件件完成' : '先選一個專案, 再專心處理裡面的事' }}</p></div><button v-if="selectedProject" class="button primary" :disabled="loading" @click="newIssue"><Icon name="plus" :size="18" />新增任務<span class="button-key">＋</span></button><button v-else class="button primary" :disabled="loading" @click="creatingProject = true"><Icon name="plus" :size="18" />建立專案</button></div>
        <ProjectHome v-if="!selectedProject" :projects="projects" :issues="issues" :loading="loading" :error="error" :demo-loading="demoLoading" @create="creatingProject = true" @open="id => navigate('all', id)" @demo="loadDemo" @retry="refresh" />
        <template v-else>

        <section v-if="section === 'tasks' && view === 'list'" class="task-summary" aria-label="任務概況">
          <span>所有任務 <strong>{{ scopeIssues.length }}</strong></span>
          <span>未結案 <strong>{{ scopeIssues.filter(issue => ['active', 'backlog'].includes(categoryFor(issue, selectedProject))).length }}</strong></span>
          <span>已完成 <strong>{{ completed }}</strong></span>
          <span class="summary-progress">完成進度 <strong>{{ progress }}%</strong><small>{{ completed }} / {{ validTotal }}（取消不計入）</small></span>
        </section>

        <section v-if="section === 'tasks'" class="task-workspace" aria-label="任務工作區">
          <SavedViewsBar :views="projectSavedViews" :active-id="savedViewId" :user-id="currentUserId" :filters="currentFilters" :applied-revision="appliedViewRevision" @select="selectSavedView" @create="openViewDialog()" @update="openViewDialog" @remove="removeSavedView" @reapply="applySavedView" />
          <div class="view-toolbar"><h2 class="task-feature-title"><Icon :name="view === 'board' ? 'board' : 'list'" :size="18" />{{ view === 'board' ? '任務看板' : '任務列表' }}</h2><span class="result-count">{{ visibleIssues.length }} 個任務</span></div>
          <div class="filter-toolbar"><label class="search-field"><Icon name="search" :size="18" /><input v-model="search" placeholder="搜尋任務、編號或負責人…" aria-label="搜尋任務" maxlength="180" /><button v-if="search" class="icon-button" aria-label="清除搜尋" @click="search = ''"><Icon name="close" :size="15" /></button></label><div class="filters"><Icon name="filter" :size="17" class="filter-icon" /><select v-model="stateFilter" aria-label="篩選狀態"><option value="">所有狀態</option><option v-if="stateFilter && !projectStates.some(state => state.id === stateFilter)" :value="stateFilter" disabled>狀態已移除（需修正）</option><option v-for="state in projectStates" :key="state.id" :value="state.id">{{ state.label }}</option></select><select v-model="priorityFilter" aria-label="篩選優先級"><option value="">所有優先級</option><option v-for="priority in PRIORITIES" :key="priority.id" :value="priority.id">{{ priority.label }}</option></select><select v-model="sort" aria-label="排序方式"><option value="updated">最近更新</option><option value="priority">優先級排序</option><option value="due">到期日排序</option></select></div></div>
          <div class="advanced-filters"><label>負責人<select v-model="assigneeFilter" aria-label="篩選負責人"><option value="">所有成員</option><option v-if="assigneeFilter && !workspace.members.some(member => member.id === assigneeFilter)" :value="assigneeFilter" disabled>成員已移除（需修正）</option><option v-for="member in workspace.members" :key="member.id" :value="member.id">{{ member.name }}</option></select></label><label>Epic<select v-model="epicFilter" aria-label="篩選 Epic"><option value="">所有 Epic</option><option value="unlinked">尚未掛入 Epic</option><option v-if="epicFilter && epicFilter !== 'unlinked' && !projectAllIssues.some(issue => issue.id === epicFilter && issue.type === 'epic')" :value="epicFilter" disabled>Epic 已移除（需修正）</option><option v-for="epic in projectAllIssues.filter(issue => issue.type === 'epic')" :key="epic.id" :value="epic.id">{{ epic.title }}</option></select></label><label>類型<select v-model="typeFilter" aria-label="篩選類型"><option value="">所有類型</option><option v-for="type in TYPES.filter(type => type.id !== 'epic')" :key="type.id" :value="type.id">{{ type.label }}</option></select></label><label>情況<select v-model="conditionFilter" aria-label="篩選情況"><option v-for="condition in CONDITIONS" :key="condition.id" :value="condition.id">{{ condition.label }}</option></select></label><button v-if="filterActive" class="text-button" @click="clearFilters">清除篩選條件</button></div>
          <div v-if="filterWarnings.length" class="filter-warning" role="alert">{{ filterWarnings.join('、') }}, 請重新選擇篩選並更新視圖。原條件保留, 沒有放寬搜尋</div>
          <div v-if="error" class="error-box workspace-error" role="alert">{{ error }}<button class="text-button" @click="refresh">重新載入</button></div>
          <div v-if="loading" class="empty-state"><span class="loader"></span><h2>正在整理你的工作空間</h2></div>
          <div v-else-if="!visibleIssues.length && view === 'list'" class="empty-state"><span class="empty-icon"><Icon :name="filterActive ? 'search' : 'check'" :size="32" /></span><h2>{{ filterActive ? '沒有符合條件的任務' : scope === 'done' ? '每件完成的事, 都會出現在這裡' : scope === 'mine' ? '目前沒有指派給你的任務' : '這裡準備好迎接第一個任務了' }}</h2><p>{{ filterActive ? '換個關鍵字, 或放寬篩選條件試試看' : scope === 'mine' ? '把任務指派給自己, 就能在這裡集中查看' : '把需要做的事情寫下來, 就是很好的開始' }}</p><button v-if="filterActive" class="button secondary" @click="clearFilters">清除篩選</button><button v-else class="button secondary" @click="newIssue"><Icon name="plus" :size="17" />新增任務</button></div>
          <div v-else-if="view === 'list'" class="table-scroll"><table class="issue-table"><thead><tr><th class="type-column"><span class="sr-only">類型</span></th><th>任務</th><th>狀態</th><th>優先級</th><th>負責人</th><th>到期日</th><th class="end-column"></th></tr></thead><tbody><tr v-for="issue in pagedIssues" :key="issue.id" :data-issue="issue.identifier"><td class="type-column"><span class="type-symbol" :class="issue.type" :title="option(TYPES, issue.type)?.label"><Icon :name="issue.type === 'bug' ? 'flag' : issue.type === 'feature' ? 'diamond' : 'check'" :size="15" /></span></td><td class="task-title-cell"><button class="task-title" @click="openIssue(issue)">{{ issue.title }}</button><div class="task-subline"><span class="identifier">{{ issue.identifier }}</span><span class="project-mini-dot" :style="{ background: projectFor(issue)?.color }"></span><span>{{ projectFor(issue)?.name }}</span><span v-if="issue.comments.length" class="comment-count"><Icon name="message" :size="12" />{{ issue.comments.length }}</span></div><IssueContext :issue="issue" :issues="projectAllIssues" :entries="workspace.timeEntries" :project="selectedProject" @open="openIssue" /></td><td><span class="status-badge" :class="issue.state" :style="{ '--status-color': option(projectStates, issue.state)?.color }"><i></i>{{ option(projectStates, issue.state)?.label }}</span></td><td><span class="priority-label" :style="{ '--priority-color': option(PRIORITIES, issue.priority)?.color }"><span class="priority-bars" :class="issue.priority"><i></i><i></i><i></i></span>{{ option(PRIORITIES, issue.priority)?.label }}</span></td><td><span v-if="issue.assignee" class="assignee"><span class="avatar small">{{ memberName(issue.assignee).slice(0, 1) }}</span>{{ memberName(issue.assignee) }}</span><span v-else class="unassigned">未指派</span></td><td><span :class="['due-date', { overdue: overdue(issue) }]">{{ shortDate(issue.dueDate) || '—' }}<span v-if="overdue(issue)" class="overdue-dot" title="已過期"></span></span></td><td><button class="icon-button row-open" :aria-label="`開啟 ${issue.identifier}`" @click="openIssue(issue)"><Icon name="chevron" :size="17" /></button></td></tr></tbody></table></div>
          <ProjectBoard v-else :key="`${currentUserId}:${projectId}`" :project="selectedProject" :issues="projectAllIssues" :visible-issues="visibleIssues" :entries="workspace.timeEntries" :members="workspace.members" :user-id="currentUserId" :moving="moving" :today="today" @open="openIssue" @move="moveIssue" @create="input => newIssue(input.state, 'task', input.epicId)" />
          <footer v-if="projects.length && !loading" class="table-footer"><span>{{ view === 'board' ? '拖曳卡片或使用卡片狀態選單更新進度' : '點選任務, 查看詳細內容與討論' }}</span><div v-if="view === 'list' && visibleIssues.length" class="pagination"><span>{{ (page - 1) * pageSize + 1 }}–{{ Math.min(page * pageSize, visibleIssues.length) }} / {{ visibleIssues.length }}</span><button class="icon-button previous" aria-label="上一頁" :disabled="page === 1" @click="page--"><Icon name="chevron" :size="15" /></button><button class="icon-button" aria-label="下一頁" :disabled="page === pageCount" @click="page++"><Icon name="chevron" :size="15" /></button></div></footer>
        </section>
        <div v-if="error && section !== 'tasks'" class="error-box" role="alert">{{ error }}</div>
        <EpicOverview v-if="section === 'epics'" :project="selectedProject" :issues="projectAllIssues" :entries="workspace.timeEntries" :members="workspace.members" @open="openIssue" @create="newIssue('', 'epic')" />
        <TimeCalendar v-if="section === 'time'" ref="workspaceTool" :project="selectedProject" :issues="projectAllIssues" :entries="workspace.timeEntries" :members="workspace.members" :user-id="currentUserId" @updated="toolUpdated" @open="openIssue" />
        <ProjectSettings v-if="section === 'settings'" ref="workspaceTool" :project="selectedProject" :issues="projectAllIssues" :permissions="workspace.meta.permissions || []" @updated="toolUpdated" />
        <NotificationPreview v-if="section === 'notifications'" :project="selectedProject" :notifications="workspace.notifications" :issues="projectAllIssues" :permissions="workspace.meta.permissions || []" @open="openIssue" />
        </template>
        <footer class="page-footer"><span><Icon name="sun" :size="14" />保持專注, 也記得休息一下</span><span>TaskTracker <b>v0.2</b></span></footer>
      </div>
    </main>
    <IssuePanel v-if="creatingIssue || selectedIssue" :key="selectedIssue?.id || 'new'" :issue="selectedIssue" :projects="selectedProject ? [selectedProject] : []" :default-project="projectId" :default-state="defaultState" :default-type="defaultType" :default-epic-id="defaultEpicId" :members="workspace.members" :issues="projectAllIssues" :entries="workspace.timeEntries" @open="openIssue" @close="closeIssue" @saved="savedIssue" @reload="reloadEditor" />
    <SavedViewDialog v-if="savedViewDialog" :project="selectedProject" :filters="savedViewDialog.filters" :saved-view="savedViewDialog.savedView" @close="savedViewDialog = null" @saved="savedViewSaved" />
    <TeamDialog v-if="teamOpen" :members="workspace.members" :meta="workspace.meta" @close="teamOpen = false" />
    <ProjectDialog v-if="creatingProject" @close="creatingProject = false" @created="createdProject" />
    <Transition name="toast"><div v-if="toast" class="toast-message" role="status"><span><Icon name="check" :size="16" /></span>{{ toast }}</div></Transition>
  </div>
  </v-app>
</template>
