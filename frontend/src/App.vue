<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, shallowRef, useTemplateRef, watch } from 'vue';
import Icon from './components/Icon.vue';
import { lazyFeature } from './ui/lazyFeature.js';
import { useToast } from './composables/useToast.js';
import { useRefreshQueue } from './composables/useRefreshQueue.js';
import { useWorkspaceClock } from './composables/useWorkspaceClock.js';
import AuthGate from './components/AuthGate.vue';
import AccountMenu from './components/AccountMenu.vue';
import { contextKey, taskScope, writeContext } from './ui/workContext.js';
import { usePersonalSync } from './composables/usePersonalSync.js';
import { api, setWorkspaceContext } from './api/index.js';
import { syncEntryPath } from './entryPath.js';
import { provideLeaveGuards } from './ui/leaveGuard.js';
import { buildHash, parseHash, settingsHash } from './ui/hashRoutes.js';
import WorkspaceSwitcher from './components/WorkspaceSwitcher.vue';

const ProjectDialog = lazyFeature(() => import('./components/ProjectDialog.vue'));
const ProjectTasksWorkspace = lazyFeature(() => import('./components/ProjectTasksWorkspace.vue'));
const WorkspaceSettings = lazyFeature(() => import('./components/WorkspaceSettings.vue'));
const PersonalTokensDialog = lazyFeature(() => import('./components/PersonalTokensDialog.vue'));
const tokensOpen = shallowRef(false);
const ProfileDialog = lazyFeature(() => import('./components/ProfileDialog.vue'));
const TimeCalendar = lazyFeature(() => import('./components/TimeCalendar.vue'));
const TimeAnalysis = lazyFeature(() => import('./components/TimeAnalysis.vue'));
const ProjectStatusWorkspace = lazyFeature(() => import('./components/ProjectStatusWorkspace.vue'));
const ProjectMembers = lazyFeature(() => import('./components/ProjectMembers.vue'));
const PersonalWorkspace = lazyFeature(() => import('./components/PersonalWorkspace.vue'));
const ProjectDirectory = lazyFeature(() => import('./components/ProjectDirectory.vue'));
const TodoConversion = lazyFeature(() => import('./components/TodoConversion.vue'));
const PersonalNotifications = lazyFeature(() => import('./components/PersonalNotifications.vue'));
const NotificationPreview = lazyFeature(() => import('./components/NotificationPreview.vue'));
const WorkspaceInvitation = lazyFeature(() => import('./components/WorkspaceInvitation.vue'));

const leaveGuards = provideLeaveGuards();
const workspace = shallowRef({ projects: [], members: [], meta: {} });
const authenticated = shallowRef(false), pendingVerification = shallowRef(null), authPageKey = shallowRef('');
const todoTool = useTemplateRef('todoTool'), convertingTodo = shallowRef(null), todoNotificationsOpen = shallowRef(false);
const todoUnread = computed(() => personalSync.unread.value);
const switchingWorkspace = shallowRef(false), workspaceInviteToken = shallowRef('');
const teamTab = shallowRef('general'), teamTool = useTemplateRef('teamTool');
const canManageWorkspace = computed(() => workspace.value.meta.permissions?.includes('workspace.ownership'));
const profileOpen = shallowRef(false), accountMenu = useTemplateRef('accountMenu');
let settingsOpener;
const section = shallowRef('tasks');
const sectionPermissions = { settings: 'project.statuses.update', members: 'project.members.manage', time: 'timeEntry.readOwn' };
// TT-314：通知預覽只在local／testing開放, 由後端目前身分的features決定, 正式環境隱藏入口。
const notificationPreviewEnabled = shallowRef(false);
const workspaceTool = useTemplateRef('workspaceTool');
const loading = shallowRef(true);
const error = shallowRef('');
// TT-297：Session確定後才校正路徑（已登入/app/、顯示登入表單時/login），連線中或錯誤時不動網址。
watch(() => authenticated.value ? 'app' : !loading.value && !error.value ? 'login' : '', where => { if (where) syncEntryPath(where === 'app'); }, { immediate: true });
const view = shallowRef('list');
const isTaskRoute = computed(() => Boolean(projectId.value) && section.value === 'tasks' && ['list', 'boards', 'board'].includes(view.value));
const projectTasksTool = useTemplateRef('projectTasksTool'), projectDirectoryTool=useTemplateRef('projectDirectoryTool');
const boardId = shallowRef('default');
const scope = shallowRef('all');
const projectId = shallowRef('');
const landing = shallowRef('home');
const analysisMode = shallowRef('index');
const canAnalyze = computed(() => workspace.value.meta.permissions?.includes('workspace.timeReports.read'));
const selectedId = shallowRef('');
const detailPage = shallowRef(false);
let issueReturn = null;
const creatingProject = shallowRef(false);
const editingProject = shallowRef(null);
const sidebarOpen = shallowRef(false);
const { toast, notify } = useToast();
const demoLoading = shallowRef(false);
const refresh = useRefreshQueue({
  scope: () => `${refreshScope}:${authenticated.value}:${switchingWorkspace.value}:${workspace.value.meta.workspace?.id || ''}:${projectId.value}:${view.value}:${section.value}`,
  load: refreshWorkspace,
});
const isScopedRoute=computed(()=>isTaskRoute.value || (projectId.value && ['settings','notifications','time','members'].includes(section.value)) || (!projectId.value && ['home','projects','settings','analysis'].includes(landing.value)));
const personalSync=usePersonalSync({active:computed(()=>authenticated.value&&!switchingWorkspace.value&&workspace.value.meta.permissions?.includes('todo.read')),context:computed(()=>`${workspace.value.meta.userId}:${workspace.value.meta.workspace?.id}`),onError:failure=>{if(['UNAUTHENTICATED','FORBIDDEN','WORKSPACE_CHANGED'].includes(failure.code))refresh({join:true});}});
const today = useWorkspaceClock({ active: computed(() => authenticated.value && !switchingWorkspace.value && !isScopedRoute.value), refresh: () => refresh({ join: true }) });
let refreshGeneration = 0, refreshScope = 0;
let opener;
const demoRequestId = crypto.randomUUID();

const projects = computed(() => workspace.value.projects);
const selectedProject = computed(() => projects.value.find((item) => item.id === projectId.value));
function projectCan(project, permission) { return Boolean(project?.permissions?.includes(permission)); }
const can = permission => projectCan(selectedProject.value, permission);
const canWrite = computed(() => can('issue.write'));
const creationBoardId = computed(() => section.value === 'tasks' && view.value === 'board' ? boardId.value : 'default');
const canCreateIssue = computed(() => canWrite.value && (creationBoardId.value === 'default' || can('board.manage')));
const showNewIssueAction = computed(() => selectedProject.value && section.value === 'tasks' && view.value === 'list' && scope.value === 'all' && canCreateIssue.value);
const currentUserId = computed(() => workspace.value.meta.userId);
const workContextKey = computed(() => contextKey(currentUserId.value, workspace.value.meta.workspace?.id, selectedProject.value?.id));
function restoreTaskScope() {
  if (view.value === 'list' && section.value === 'tasks') scope.value = taskScope(workContextKey.value);
}
watch(workContextKey, restoreTaskScope, { flush: 'sync' });
// 報表只在工作區、操作者、有效權限改變或手動重新整理時重查；一般導覽刷新換掉workspace物件不觸發重讀。
const manualRefresh = shallowRef(0);
const analysisVersion = computed(() => [workspace.value.meta.workspace?.id || '', workspace.value.meta.userId || '', (workspace.value.meta.permissions || []).join(','), manualRefresh.value].join('|'));
const title = computed(() => selectedProject.value?.name || '專案列表');
const currentFeature = computed(() => section.value === 'tasks'
  ? view.value === 'boards' ? '看板列表' : view.value === 'board' ? '任務看板' : scope.value === 'mine' ? '我的任務' : '任務列表'
  : { time: '工時月曆', settings: '狀態設定', members: '專案成員', notifications: '通知預覽' }[section.value]);
async function refreshCurrent() {
  manualRefresh.value++;
  await refresh({ join: true });
  await projectTasksTool.value?.refresh(false);
  await projectDirectoryTool.value?.refresh();
  await todoTool.value?.refresh();
  await workspaceTool.value?.refresh?.();
  await teamTool.value?.refresh?.();
  await personalSync.refresh();
}
function openTodoInbox() { todoNotificationsOpen.value = true; }

async function refreshWorkspace() {
  const generation = ++refreshGeneration;
  error.value = '';
  try {
    // 所有頁面都只取身分＋目前Project；整包getWorkspace已退役，見docs/workspace-payload-retirement-2026-11-12.md。
    if (/^#\/activate\/[a-f0-9]{64}$/.test(location.hash)) { authenticated.value = false; return; }
    const identity = await api.current();
    if (identity.verificationRequired) { pendingVerification.value = identity; authenticated.value = false; return; }
    pendingVerification.value = null;
    if (generation !== refreshGeneration) return;
    notificationPreviewEnabled.value = identity.features?.notificationPreview === true;
    const previousWorkspace=workspace.value.meta.workspace?.id;
    if(previousWorkspace&&previousWorkspace!==identity.workspace.id){
      setWorkspaceContext(identity.workspace.id);
      workspace.value={projects:[],members:[identity.user],meta:{actor:identity.user.name,userId:identity.user.id,workspace:identity.workspace,permissions:identity.permissions}};
      resetWorkspaceUI();notify('工作空間已在其他分頁切換');
    }
    setWorkspaceContext(identity.workspace.id);
    const project = projectId.value ? await api.project(projectId.value) : null;
    if (generation !== refreshGeneration) return;
    workspace.value = { projects: project ? [project] : [], members: [identity.user],
      meta: { actor: identity.user.name, userId: identity.user.id, workspace: identity.workspace, permissions: identity.permissions } };
    authenticated.value = true;
    if(editingProject.value){
      try{const current=await api.project(editingProject.value.id);if(!current.permissions.includes('project.update')){editingProject.value=null;notify('專案編輯資格已變更');}}
      catch(failure){if(['NOT_FOUND','FORBIDDEN'].includes(failure.code)){editingProject.value=null;notify('專案編輯資格已變更');}else throw failure;}
    }
    ensureSectionAccess();
    if (selectedId.value && !projectId.value) {
      try {
        const found = await api.issueLocation(selectedId.value);
        if (generation !== refreshGeneration) return;
        history.replaceState(null, '', projectHash(found.projectId, found.id)); readHash(true);
      } catch (failure) { if (failure.code !== 'NOT_FOUND') throw failure; notify('找不到這個任務'); goHome(true); }
    }
    await nextTick();
  } catch (failure) { if (generation === refreshGeneration) { if (failure.code === 'UNAUTHENTICATED') { authenticated.value = false; convertingTodo.value = null; todoNotificationsOpen.value = false; profileOpen.value = false; tokensOpen.value = false; creatingProject.value = false; editingProject.value = null; selectedId.value = ''; detailPage.value = false; workspace.value = { projects: [], members: [], meta: {} }; } else if (isScopedRoute.value && ['FORBIDDEN', 'NOT_FOUND', 'WORKSPACE_CHANGED'].includes(failure.code)) {
      workspace.value = { ...workspace.value, projects: [] };
      selectedId.value = '';
      goHome(true); notify('專案存取權限已變更');
    } else error.value = failure.message; } }
  finally { if (generation === refreshGeneration) loading.value = false; }
}
function projectHash(id, issueId = '', mode = view.value, selectedBoardId = boardId.value, fullPage = detailPage.value, targetSection = section.value) {
  return buildHash({ project: id, issue: issueId, mode, board: selectedBoardId, detail: fullPage, section: targetSection, landing: landing.value, analysis: analysisMode.value, teamTab: teamTab.value });
}
function issueMissing() { notify('找不到這個專案內的任務'); history.replaceState(null, '', projectHash(projectId.value)); readHash(true); }
function ensureSectionAccess() {
  if (landing.value === 'settings' && !canManageWorkspace.value) {
    history.replaceState(null, '', '#/'); readHash(true); notify('你沒有工作空間設定的存取權限');
  }
  if (landing.value === 'analysis' && !canAnalyze.value) {
    history.replaceState(null, '', '#/'); readHash(true);
    notify('你沒有工時分析的存取權限');
  }
  const permission = sectionPermissions[section.value];
  if (selectedProject.value && ((permission && !can(permission)) || (section.value === 'notifications' && !notificationPreviewEnabled.value))) {
    section.value = 'tasks'; view.value = 'list'; boardId.value = 'default';
    history.replaceState(null, '', projectHash(projectId.value));
    notify('你沒有此功能的存取權限, 已返回任務列表');
  }
}
function canLeave() {
  // 各草稿owner自己登記（ui/leaveGuard.js）；儲存中一律不離開, 有未儲存內容先確認。
  if (switchingWorkspace.value || leaveGuards.saving()) return false;
  return !leaveGuards.dirty() || window.confirm('還有未儲存的內容, 確定要離開嗎？');
}
function changeSection(value) {
  if ((value !== section.value || selectedId.value) && canLeave()) {
    issueReturn = null;
    history.pushState(null, '', projectHash(projectId.value, '', 'list', 'default', false, value));
    readHash(true);
  }
}
function openWorkspaceSettings(tab = 'general') {
  if (!canManageWorkspace.value) return;
  if (landing.value === 'settings' && teamTab.value === tab) { sidebarOpen.value = false; return; }
  if (!canLeave()) return;
  history.pushState(null, '', settingsHash(tab));
  readHash(true); sidebarOpen.value = false;
}
function openProfile() { settingsOpener = document.activeElement; profileOpen.value = true; }
async function closeSettings(kind) {
  if (kind === 'profile') profileOpen.value = false;
  await nextTick();
  const box = settingsOpener?.getBoundingClientRect();
  const hiddenSidebar = innerWidth <= 700 && settingsOpener?.closest('.sidebar') && !sidebarOpen.value;
  if (!hiddenSidebar && settingsOpener?.isConnected && box?.width && box.right > 0 && box.left < innerWidth) settingsOpener.focus();
  else accountMenu.value?.focus();
}
async function savedProfile() { await refresh(); await closeSettings('profile'); notify('個人資料已儲存'); }
async function logout() { if (!canLeave()) return; try { await api.logout(); setWorkspaceContext(''); ++refreshGeneration; ++refreshScope; authenticated.value = false; tokensOpen.value = false; convertingTodo.value = null; todoNotificationsOpen.value = false; workspace.value = { projects: [], members: [], meta: {} }; history.replaceState(null, '', '/login'); readHash(true); } catch (failure) { notify(failure.message); } }
function resetWorkspaceUI() {
  selectedId.value = ''; detailPage.value = false;
  profileOpen.value = false; tokensOpen.value = false; creatingProject.value = false; editingProject.value = null;
  convertingTodo.value = null; todoNotificationsOpen.value = false; issueReturn = null;
  projectId.value = ''; section.value = 'tasks'; view.value = 'list'; boardId.value = 'default';
  workspaceInviteToken.value = ''; history.replaceState(null, '', '#/'); readHash(true);
}
async function changeWorkspace(workspaceId, inviteToken = '') {
  if (!inviteToken && !canLeave()) return false;
  switchingWorkspace.value = true; ++refreshGeneration; ++refreshScope;
  try {
    const session = inviteToken ? await api.acceptWorkspaceInvite({ inviteToken }) : await api.switchWorkspace({ workspaceId });
    resetWorkspaceUI();
    setWorkspaceContext(session.workspace.id);
    workspace.value = { projects: [], members: [], meta: { actor: session.user.name, userId: session.user.id, workspace: session.workspace, permissions: session.permissions } };
    await refresh(); notify(`已進入 ${session.workspace.name}`);
    return true;
  } finally { switchingWorkspace.value = false; }
}
function closeInvitation() { workspaceInviteToken.value = ''; history.replaceState(null, '', '#/'); readHash(true); }
async function loginForInvitation() {
  if (!canLeave()) return;
  const token = workspaceInviteToken.value;
  try {
    await api.logout(); setWorkspaceContext(''); ++refreshGeneration; ++refreshScope;
    resetWorkspaceUI(); authenticated.value = false;
    workspace.value = { projects: [], members: [], meta: {} };
    history.replaceState(null, '', `#/invite?token=${encodeURIComponent(token)}`); readHash(true);
  } catch (failure) { notify(failure.message); }
}
async function authenticatedSession() { loading.value = true; readHash(); await refresh(); }
function convertTodo(item) {
  if (!canLeave()) return;
  convertingTodo.value = { ...item };
}
async function reloadConversion(callback, settled = () => {}) {
  try {
    const current=await api.todo(convertingTodo.value.id);
    if(!current.capabilities.convert){convertingTodo.value=null;notify('待辦已無法轉卡');return;}
    convertingTodo.value=current; await nextTick();callback();
  } catch(failure){notify(failure.message);} finally {settled();}
}
async function convertedTodo(result) {
  const item=await api.todo(result.id);
  convertingTodo.value=null;await nextTick();await todoTool.value?.refresh();await personalSync.refresh();
  notify('已轉成正式卡片');if(item.convertedIssue)openTodoIssue(item.convertedIssue);
}
function openTodoIssue(link) { openIssue(link,true); }

function jumpFromConversion(issue) {
  convertingTodo.value = null;
  nextTick(() => openTodoIssue(issue));
}
async function openTodoNotification(item) {
  if (!canLeave()) return;
  try {
    await api.readTodoNotification({ id: item.id, requestId: crypto.randomUUID() });
    todoNotificationsOpen.value = false;
    await personalSync.refresh();
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
  sidebarOpen.value = false;
}
function openBoard(id) {
  if (!canLeave()) return;
  issueReturn = null;
  location.hash = projectHash(projectId.value, '', 'board', id); readHash(true); sidebarOpen.value = false;
}
function goLanding(page, replace = false) {
  if (!replace && !projectId.value && landing.value === page) { sidebarOpen.value = false; return; }
  if (!replace && !canLeave()) { sidebarOpen.value = false; return; }
  history[replace ? 'replaceState' : 'pushState'](null, '', page === 'projects' ? '#/projects' : '#/');
  readHash(true);
  sidebarOpen.value = false;
}
function goHome(replace = false) { goLanding('home', replace); }
function goProjects() { goLanding('projects'); }
function goAnalysis(mode = 'index') {
  if (!canAnalyze.value || !canLeave()) return;
  history.pushState(null, '', `#/time-analysis${mode === 'index' ? '' : `/${mode}`}`);
  readHash(true); sidebarOpen.value = false;
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
  if (fullPage) nextTick(() => window.scrollTo(0, 0));
}
function readHash(skipGuard = false) {
  authPageKey.value = /^#\/activate\/[a-f0-9]{64}$/.test(location.hash) ? location.hash : '';
  if (authPageKey.value) { pendingVerification.value = null; authenticated.value = false; return; }
  const next = parseHash(location.hash);
  if (next.malformed) history.replaceState(null, '', '#/');
  const { project: nextProject, issue: nextIssue, board: nextBoard, mode: nextMode, section: nextSection, detail: nextDetail, landing: nextLanding, teamTab: nextTeamTab, analysis: nextAnalysis, invite: nextInvite, retiredEpics, retiredView } = next;
  const landingChanged = landing.value !== nextLanding || (nextLanding === 'settings' && teamTab.value !== nextTeamTab) || analysisMode.value !== nextAnalysis || workspaceInviteToken.value !== nextInvite;
  const issueChanged = selectedId.value !== nextIssue || detailPage.value !== nextDetail;
  const returning = Boolean(selectedId.value && !nextIssue);
  const projectChanged = projectId.value !== nextProject, modeChanged = view.value !== nextMode, boardChanged = boardId.value !== nextBoard;
  const sectionChanged = section.value !== nextSection;
  if (skipGuard !== true && (landingChanged || sectionChanged || issueChanged || projectChanged || modeChanged || boardChanged || retiredView || retiredEpics) && !canLeave()) { history.replaceState(null, '', projectHash(projectId.value, selectedId.value)); return; }
  if (projectChanged || modeChanged || boardChanged || retiredView) {
    section.value = 'tasks'; scope.value = 'all';
  }
  landing.value = nextLanding; teamTab.value = nextTeamTab; analysisMode.value = nextAnalysis; workspaceInviteToken.value = nextInvite;
  projectId.value = nextProject;
  view.value = nextMode; boardId.value = nextBoard;
  selectedId.value = nextIssue;
  detailPage.value = nextDetail;
  section.value = nextSection;
  if (nextDetail) section.value = 'tasks';
  if (returning && !projectChanged && !modeChanged && !boardChanged && issueReturn?.section === nextSection) {
    section.value = issueReturn.section; scope.value = issueReturn.scope;
    const scroll = issueReturn.scroll; issueReturn = null;
    nextTick(() => { opener?.focus({ preventScroll: true }); window.scrollTo(0, scroll); });
  }
  if (projectChanged || modeChanged || boardChanged) issueReturn = null;
  if (authenticated.value) ensureSectionAccess();
  if (projectChanged || modeChanged || sectionChanged) restoreTaskScope();
  if (retiredEpics) history.replaceState(null, '', projectHash(nextProject));
  if (retiredView) {
    history.replaceState(null, '', projectHash(nextProject, nextIssue, nextMode, nextBoard));
    notify('視圖功能已移除, 已開啟原專案頁面');
  }
  sidebarOpen.value = false;
  if (authenticated.value && (projectChanged || modeChanged || boardChanged || sectionChanged || issueChanged || landingChanged)) refresh();
}
async function closeIssue() {
  if (detailPage.value && !canLeave()) return;
  selectedId.value = ''; detailPage.value = false;
  if (issueReturn) { section.value = issueReturn.section; scope.value = issueReturn.scope; }
  history.replaceState(null, '', projectHash(projectId.value));
  const scroll = issueReturn?.scroll;
  issueReturn = null;
  await nextTick();
  opener?.focus({ preventScroll: true });
  if (scroll !== undefined) window.scrollTo(0, scroll);
}
// 任務頁一律由ProjectTasksWorkspace建立；App只轉交標題列的「新增任務」。
function newIssue() {
  if (canCreateIssue.value && isTaskRoute.value) projectTasksTool.value?.create(null, { state: '', type: 'task', epicId: '' });
}
async function createdProject(project, name) {
  ++refreshGeneration; ++refreshScope;
  const wasEditing = Boolean(editingProject.value);
  creatingProject.value = false;
  editingProject.value = null;
  goProjects();
  await refresh();
  await projectDirectoryTool.value?.refresh();
  notify(wasEditing ? '專案設定已儲存' : `已建立「${name}」`);
}
async function loadDemo() {
  if (demoLoading.value) return;
  demoLoading.value = true;
  try {
    await api.loadDemo({ requestId: demoRequestId });
    await refresh();
    goProjects();
    await nextTick(); await projectDirectoryTool.value?.refresh();
    notify('已載入示範專案');
  } catch (failure) { error.value = failure.message; }
  finally { demoLoading.value = false; }
}
function beforeUnload(event) { if (leaveGuards.dirty({ unload: true })) { event.preventDefault(); event.returnValue = ''; } }
function keydown(event) {
  if (event.key === 'Escape') sidebarOpen.value = false;
}
onMounted(async () => {
  readHash();
  window.addEventListener('hashchange', readHash);
  window.addEventListener('keydown', keydown);
  window.addEventListener('beforeunload', beforeUnload);
  window.addEventListener('tasktracker:access-changed', refresh);
  await refresh();
});
onBeforeUnmount(() => { ++refreshGeneration; ++refreshScope; window.removeEventListener('hashchange', readHash); window.removeEventListener('keydown', keydown); window.removeEventListener('beforeunload', beforeUnload); window.removeEventListener('tasktracker:access-changed', refresh); });
</script>

<template>
  <v-app>
  <AuthGate :key="authPageKey" v-if="!authenticated && !loading && !error" :pending="pendingVerification" @authenticated="authenticatedSession" />
  <div v-else-if="!authenticated" class="empty-state"><span class="loader"></span><h2>正在連線工作空間</h2><p v-if="error" role="alert">{{ error }}</p><button v-if="error" class="button secondary" @click="refresh({ join: true })">重試</button></div>
  <div v-else class="app-shell">
    <button v-if="sidebarOpen" class="sidebar-scrim" aria-label="收起導覽" @click="sidebarOpen = false"></button>
    <aside class="sidebar" :class="{ open: sidebarOpen }">
      <a class="brand" href="#/" aria-label="TaskTracker 返回首頁" title="返回首頁" @click.prevent="goHome()"><span class="brand-mark"><span></span><span></span><span></span></span>TaskTracker<span class="brand-dot">.</span></a>
      <WorkspaceSwitcher :workspace="workspace.meta.workspace" :switching="switchingWorkspace" :change="changeWorkspace" />
      <template v-if="selectedProject">
        <div class="project-context" aria-label="目前專案">
          <span class="project-context-code" :style="{ color: selectedProject.color }">{{ selectedProject.code }}</span>
          <strong>{{ selectedProject.name }}</strong>
          <span class="project-context-workspace">{{ workspace.meta.workspace?.name }}</span>
        </div>
        <p class="nav-label">專案功能</p>
        <nav aria-label="專案任務">
          <button class="nav-item" :class="{ active: section === 'tasks' && view === 'list' && scope === 'all' }" :aria-current="section === 'tasks' && view === 'list' && scope === 'all' ? 'page' : undefined" aria-label="任務列表" @click="navigate('all')"><Icon name="list" :size="18" />任務列表</button>
          <button class="nav-item" :class="{ active: section === 'tasks' && (view === 'board' || view === 'boards') }" :aria-current="section === 'tasks' && (view === 'board' || view === 'boards') ? 'page' : undefined" aria-label="任務看板" @click="navigate('all', projectId, 'boards')"><Icon name="board" :size="18" />任務看板</button>
          <button class="nav-item" :class="{ active: section === 'tasks' && view === 'list' && scope === 'mine' }" @click="navigate('mine')"><Icon name="user" :size="18" />我的任務</button>
          <button v-if="can('timeEntry.readOwn')" class="nav-item" :class="{ active: section === 'time' }" :aria-current="section === 'time' ? 'page' : undefined" @click="changeSection('time')"><Icon name="clock" :size="18" />工時月曆</button>
          <button v-if="can('project.statuses.update')" class="nav-item" :class="{ active: section === 'settings' }" :aria-current="section === 'settings' ? 'page' : undefined" @click="changeSection('settings')"><Icon name="filter" :size="18" />狀態設定</button>
          <button v-if="can('project.members.manage')" class="nav-item" :class="{ active: section === 'members' }" :aria-current="section === 'members' ? 'page' : undefined" aria-label="專案成員與權限" @click="changeSection('members')"><Icon name="user" :size="18" />成員與權限</button>
          <button v-if="notificationPreviewEnabled" class="nav-item" :class="{ active: section === 'notifications' }" :aria-current="section === 'notifications' ? 'page' : undefined" @click="changeSection('notifications')"><Icon name="message" :size="18" />通知預覽</button>
        </nav>
      </template>
      <template v-else>
        <p class="nav-label">工作空間</p>
        <nav aria-label="工作空間">
          <button class="nav-item" :class="{ active: landing === 'home' }" :aria-current="landing === 'home' ? 'page' : undefined" @click="goHome()"><Icon name="grid" :size="18" />首頁</button>
          <button class="nav-item" :class="{ active: landing === 'projects' }" :aria-current="landing === 'projects' ? 'page' : undefined" aria-label="專案列表" @click="goProjects()"><Icon name="folder" :size="18" />專案列表<span v-if="!isScopedRoute" class="nav-count">{{ projects.length }}</span></button>
        </nav>
      </template>
      <nav v-if="workspace.meta.permissions?.includes('workspace.ownership')" aria-label="工作空間管理"><button class="nav-item" :class="{ active: landing === 'settings' && !selectedProject }" :aria-current="landing === 'settings' && !selectedProject ? 'page' : undefined" @click="openWorkspaceSettings()"><Icon name="settings" :size="18" />工作空間設定</button></nav>
      <nav v-if="canAnalyze" aria-label="工作空間分析"><button class="nav-item" :class="{ active: landing === 'analysis' && !selectedProject }" :aria-current="landing === 'analysis' && !selectedProject ? 'page' : undefined" @click="goAnalysis()"><Icon name="clock" :size="18" />工時分析</button></nav>
    </aside>

    <main class="main">
      <header class="topbar"><div class="breadcrumbs"><button class="icon-button mobile-menu" aria-label="開啟導覽" :aria-expanded="sidebarOpen" @click="sidebarOpen = !sidebarOpen"><Icon name="menu" /></button><Icon name="folder" :size="17" /><button class="breadcrumb-home" @click="selectedProject ? goProjects() : goHome()">{{ selectedProject ? '專案' : '工作空間' }}</button><Icon name="chevron" :size="13" /><strong :class="{ 'has-mobile-context': selectedProject }"><span class="project-breadcrumb">{{ selectedProject?.code || (landing === 'settings' ? '工作空間設定' : landing === 'analysis' ? '工時分析' : landing === 'projects' ? '專案列表' : '首頁') }}</span><span v-if="selectedProject" class="mobile-feature-context">{{ currentFeature }}</span></strong></div><div class="topbar-end"><button v-if="workspace.meta.permissions?.includes('todo.read')" class="button secondary todo-notification-toggle" :aria-label="`待辦通知${todoUnread ? `（${todoUnread} 則未讀）` : ''}`" @click="openTodoInbox"><Icon name="bell" :size="17" /><span>待辦通知</span><b v-if="todoUnread">{{ todoUnread }}</b></button><button class="icon-button" aria-label="重新整理工作空間" :disabled="loading" @click="refreshCurrent"><Icon name="refresh" :size="18" /></button><AccountMenu ref="accountMenu" :name="workspace.meta.actor" :email="workspace.members.find(member => member.id === currentUserId)?.email" :workspace-name="workspace.meta.workspace?.name" @profile="openProfile" @tokens="tokensOpen = true" @logout="logout" /></div></header>
      <div class="workspace-content" :class="{ 'board-workspace': section === 'tasks' && view === 'board', 'data-workspace': selectedProject && section === 'tasks', 'detail-workspace': isTaskRoute && selectedId && detailPage }">
        <button v-if="selectedProject && section !== 'tasks'" class="navigation-back back-to-projects" @click="goProjects()"><Icon name="chevron" :size="14" />返回專案列表</button>
        <WorkspaceSettings v-if="!selectedProject && landing === 'settings' && canManageWorkspace" :key="workspace.meta.workspace?.id" ref="teamTool" :active-tab="teamTab" :meta="workspace.meta" @navigate="openWorkspaceSettings" @saved="refresh" @access="refresh" />
        <TimeAnalysis :key="workspace.meta.workspace?.id" v-if="!selectedProject && landing === 'analysis' && canAnalyze" :mode="analysisMode" :workspace-version="analysisVersion" @navigate="goAnalysis" />
        <div v-if="!(isTaskRoute && selectedId) && (selectedProject || landing === 'projects')" class="page-heading"><div><h1>{{ title }}</h1><p v-if="selectedProject && section !== 'tasks' && selectedProject.description">{{ selectedProject.description }}</p></div><div class="heading-actions"><button v-if="showNewIssueAction" class="button primary" :disabled="loading || (isTaskRoute && !projectTasksTool)" @click="newIssue"><Icon name="plus" :size="18" />新增任務</button><button v-else-if="!selectedProject && workspace.meta.permissions?.includes('project.create')" class="button primary" :disabled="loading" @click="creatingProject = true"><Icon name="plus" :size="18" />建立專案</button></div></div>
        <div v-if="!selectedProject && ['home', 'projects'].includes(landing)" :key="workspace.meta.workspace?.id" :class="landing === 'home' ? 'home-layout' : 'project-list-page'">
        <h1 v-if="landing === 'home'" class="sr-only">首頁</h1>
        <div v-if="landing === 'home' && error" class="error-box" role="alert">{{ error }}<button class="text-button" @click="refresh({ join: true })">重新載入</button></div>
        <ProjectDirectory v-if="landing === 'projects'" ref="projectDirectoryTool" :workspace-id="workspace.meta.workspace?.id" :can-create="workspace.meta.permissions?.includes('project.create')" :demo-loading="demoLoading" @create="creatingProject = true" @edit="editingProject = $event" @open="id => navigate('all', id)" @demo="loadDemo" />
        <PersonalWorkspace v-if="landing === 'home' && workspace.meta.permissions?.includes('todo.read')" ref="todoTool" :workspace-id="workspace.meta.workspace.id" :permissions="workspace.meta.permissions" :versions="personalSync.versions.value" :resumed="personalSync.resumed.value" :user-id="currentUserId"     @convert="convertTodo" @open-issue="openTodoIssue"  @access="refresh({join:true})"/>
        </div>
        <ProjectTasksWorkspace v-else-if="isTaskRoute && selectedProject" ref="projectTasksTool" :key="`${workspace.meta.workspace?.id}:${projectId}`" :project="selectedProject" :mode="view" :scope="scope" :board-id="boardId" :issue-id="selectedId" :detail-page="detailPage" :user-id="currentUserId" :workspace-id="workspace.meta.workspace.id" :today="today" @board="openBoard" @list="navigate('all', projectId, 'boards')" @issue="openIssue" @close-issue="closeIssue" @issue-missing="issueMissing" @board-missing="notify('看板不存在或已移除'); navigate('all', projectId, 'boards')" @notify="notify" @access="refresh({ join: true })" @project="project => { workspace = { ...workspace, projects: [project] }; }" />
        <template v-else-if="selectedProject">
        <div v-if="error && section !== 'tasks'" class="error-box" role="alert">{{ error }}</div>
        <TimeCalendar :can-write="can('timeEntry.writeOwn')" v-if="section === 'time' && can('timeEntry.readOwn')" ref="workspaceTool" :key="workContextKey" :context-key="workContextKey" :project="selectedProject" :can-read-team="can('timeEntry.readTeam')" :user-id="currentUserId" @updated="toolUpdated" @open="openIssue" @access="refresh" />
        <ProjectStatusWorkspace v-if="section === 'settings' && can('project.statuses.update')" ref="workspaceTool" :project="selectedProject" @updated="toolUpdated" @access="refresh" />
        <ProjectMembers @access="refresh" v-if="section === 'members' && can('project.members.manage')" ref="workspaceTool" :project="selectedProject" @updated="toolUpdated" />
        <NotificationPreview ref="workspaceTool" v-if="section === 'notifications' && notificationPreviewEnabled" :project="selectedProject" :permissions="workspace.meta.permissions || []" @open="openIssue" @access="refresh" />
        </template>
        <footer class="page-footer"><span>TaskTracker <b>v0.2</b></span></footer>
      </div>
    </main>
    <WorkspaceInvitation v-if="workspaceInviteToken" :key="workspaceInviteToken" :token="workspaceInviteToken" :email="workspace.members.find(member => member.id === currentUserId)?.email" :accept="token => changeWorkspace('', token)" @close="closeInvitation" @logout="loginForInvitation" />
    <TodoConversion v-if="convertingTodo" :key="`convert:${convertingTodo.id}`" :todo="convertingTodo" :user="workspace.members.find(member=>member.id===currentUserId)" @close="convertingTodo=null" @converted="convertedTodo" @reload="reloadConversion" @open="jumpFromConversion" />
    <PersonalNotifications v-if="todoNotificationsOpen" :workspace-id="workspace.meta.workspace.id" :version="`${personalSync.versions.value?.notifications}:${personalSync.versions.value?.todos}`" @close="todoNotificationsOpen=false" @open="openTodoNotification" />

    <PersonalTokensDialog v-if="tokensOpen" :key="workspace.meta.workspace?.id" :workspace-name="workspace.meta.workspace?.name" @close="tokensOpen = false" />
    <ProfileDialog v-if="profileOpen" @close="closeSettings('profile')" @saved="savedProfile" />
    <ProjectDialog :lookup="(filters,options)=>api.projectLeads({...filters,project:editingProject?.id},options)" v-if="creatingProject || editingProject" :project="editingProject" :members="workspace.members" :user-id="currentUserId" @close="creatingProject = false; editingProject = null" @created="createdProject" />
    <Transition name="toast"><div v-if="toast" class="toast-message" role="status"><span><Icon name="check" :size="16" /></span>{{ toast }}</div></Transition>
  </div>
  </v-app>
</template>
