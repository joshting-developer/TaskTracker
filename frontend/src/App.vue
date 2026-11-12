<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, shallowRef, useTemplateRef, watch } from 'vue';
import Icon from './components/Icon.vue';
import IssueList from './components/IssueList.vue';
import IssueFilters from './components/IssueFilters.vue';
import { useIssueQuery } from './composables/useIssueQuery.js';
import { useIssueTransitions } from './composables/useIssueTransitions.js';
import { useToast } from './composables/useToast.js';
import { useRefreshQueue } from './composables/useRefreshQueue.js';
import { useWorkspaceClock } from './composables/useWorkspaceClock.js';
import IssuePanel from './components/IssuePanel.vue';
import IssueDetail from './components/IssueDetail.vue';
import ProjectDialog from './components/ProjectDialog.vue';
import ProjectBoard from './components/ProjectBoard.vue';
import BoardList from './components/BoardList.vue';
import ProjectTasksWorkspace from './components/ProjectTasksWorkspace.vue';
import BoardDialog from './components/BoardDialog.vue';
import { includedIssues } from './domain/boards.js';
import AuthGate from './components/AuthGate.vue';
import WorkspaceSettings from './components/WorkspaceSettings.vue';
import AccountMenu from './components/AccountMenu.vue';
import ProfileDialog from './components/ProfileDialog.vue';
import TimeCalendar from './components/TimeCalendar.vue';
import TimeAnalysis from './components/TimeAnalysis.vue';
import { contextKey, taskScope, writeContext } from './ui/workContext.js';
import { useBoardRealtime } from './ui/boardRealtime.js';
import ProjectStatusWorkspace from './components/ProjectStatusWorkspace.vue';
import ProjectMembers from './components/ProjectMembers.vue';
import PersonalWorkspace from './components/PersonalWorkspace.vue';
import ProjectDirectory from './components/ProjectDirectory.vue';
import TodoConversion from './components/TodoConversion.vue';
import { usePersonalSync } from './composables/usePersonalSync.js';
import PersonalNotifications from './components/PersonalNotifications.vue';
import NotificationPreview from './components/NotificationPreview.vue';
import { api, setWorkspaceContext } from './api/index.js';
import WorkspaceSwitcher from './components/WorkspaceSwitcher.vue';
import WorkspaceInvitation from './components/WorkspaceInvitation.vue';
import { statusesFor } from './domain/model.js';

const workspace = shallowRef({ projects: [], issues: [], members: [], timeEntries: [], notifications: [], meta: {} });
const authenticated = shallowRef(false);
const todoTool = useTemplateRef('todoTool'), conversionTool = useTemplateRef('conversionTool'), convertingTodo = shallowRef(null), todoNotificationsOpen = shallowRef(false);
const todoUnread = computed(() => personalSync.unread.value);
const switchingWorkspace = shallowRef(false), workspaceInviteToken = shallowRef(''), invitationTool = useTemplateRef('invitationTool');
const teamTab = shallowRef('general'), teamTool = useTemplateRef('teamTool');
const canManageWorkspace = computed(() => workspace.value.meta.permissions?.includes('workspace.ownership'));
const profileOpen = shallowRef(false), profileTool = useTemplateRef('profileTool'), accountMenu = useTemplateRef('accountMenu');
let settingsOpener;
const section = shallowRef('tasks');
const sectionPermissions = { settings: 'project.statuses.update', members: 'project.members.manage', time: 'timeEntry.readOwn' };
const workspaceTool = useTemplateRef('workspaceTool');
const defaultType = shallowRef('task');
const defaultEpicId = shallowRef('');
const loading = shallowRef(true);
const error = shallowRef('');
const view = shallowRef('list');
const isTaskRoute = computed(() => Boolean(projectId.value) && section.value === 'tasks' && ['list', 'boards', 'board'].includes(view.value));
const projectTasksTool = useTemplateRef('projectTasksTool'), projectDirectoryTool=useTemplateRef('projectDirectoryTool');
const boardId = shallowRef('default'), boardEditor = shallowRef(null), boardEditorTool = useTemplateRef('boardEditorTool');
const scope = shallowRef('all');
const projectId = shallowRef('');
const landing = shallowRef('home');
const analysisMode = shallowRef('index');
const canAnalyze = computed(() => workspace.value.meta.permissions?.includes('workspace.timeReports.read'));
const selectedId = shallowRef('');
const detailPage = shallowRef(false), editingDetail = shallowRef(false), detailTool = useTemplateRef('detailTool'), issueTool = useTemplateRef('issueTool');
let issueReturn = null;
const creatingIssue = shallowRef(false);
const defaultState = shallowRef('todo');
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
const issues = computed(() => workspace.value.issues);
const selectedProject = computed(() => projects.value.find((item) => item.id === projectId.value));
function projectCan(project, permission) { return Boolean(project?.permissions?.includes(permission)); }
const can = permission => projectCan(selectedProject.value, permission);
const canWrite = computed(() => can('issue.write'));
const creationBoardId = computed(() => section.value === 'tasks' && view.value === 'board' ? boardId.value : 'default');
const canCreateIssue = computed(() => canWrite.value && (creationBoardId.value === 'default' || can('board.manage')));
const showNewIssueAction = computed(() => selectedProject.value && section.value === 'tasks' && view.value === 'list' && scope.value === 'all' && canCreateIssue.value);
const projectMembers = computed(() => workspace.value.members.filter(member => selectedProject.value?.memberIds?.includes(member.id) && !member.disabledAt));
const selectedIssue = computed(() => issues.value.find((item) => item.id === selectedId.value && item.projectId === projectId.value));
const showIssuePage = computed(() => Boolean(!isTaskRoute.value && selectedIssue.value && (detailPage.value || !canWrite.value)));
const realtimeState = useBoardRealtime({
  active: computed(() => !isTaskRoute.value && authenticated.value && section.value === 'tasks' && view.value === 'board' && !showIssuePage.value),
  userId: computed(() => workspace.value.meta.userId),
  workspaceId: computed(() => workspace.value.meta.workspace?.id),
  projectId: computed(() => selectedProject.value?.id),
  refresh,
});
const projectAllIssues = computed(() => issues.value.filter((item) => item.projectId === projectId.value));
const projectBoards = computed(() => (workspace.value.boards || []).filter(board => board.projectId === projectId.value));
const selectedBoard = computed(() => projectBoards.value.find(board => board.id === boardId.value));
const boardIssues = computed(() => includedIssues(selectedBoard.value, projectAllIssues.value));
const boardEpicIds = computed(() => [...new Set([...boardIssues.value.filter(issue => issue.type === 'epic').map(issue => issue.id), ...boardIssues.value.map(issue => issue.epicId).filter(Boolean)])]);
const currentUserId = computed(() => workspace.value.meta.userId);
const workContextKey = computed(() => contextKey(currentUserId.value, workspace.value.meta.workspace?.id, selectedProject.value?.id));
function restoreTaskScope() {
  if (view.value === 'list' && section.value === 'tasks') scope.value = taskScope(workContextKey.value);
}
watch(workContextKey, restoreTaskScope, { flush: 'sync' });
const {
  filterInput, page, pageSize, pageCount, projectStates, visibleIssues, pagedIssues,
  filterWarnings, filterActive, activeFilterChips, removeFilter, clearFilters,
} = useIssueQuery({
  project: selectedProject, issues: projectAllIssues, boardIssues,
  members: computed(() => workspace.value.members), userId: currentUserId, today, view, scope, projectId,
});
const { moving, moveIssue } = useIssueTransitions({
  canWrite, contextKey: workContextKey, saved: issue => savedIssue(issue, false), refresh,
  onError: message => { error.value = message; },
});
const title = computed(() => selectedProject.value?.name || '專案列表');
const currentFeature = computed(() => section.value === 'tasks'
  ? view.value === 'boards' ? '看板列表' : view.value === 'board' ? '任務看板' : scope.value === 'mine' ? '我的任務' : '任務列表'
  : { time: '工時月曆', settings: '狀態設定', members: '專案成員', notifications: '通知預覽' }[section.value]);
async function refreshCurrent() {
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
    if (isScopedRoute.value) {
      const identity = await api.current();
      if (generation !== refreshGeneration) return;
      const previousWorkspace=workspace.value.meta.workspace?.id;
      if(previousWorkspace&&previousWorkspace!==identity.workspace.id){
        setWorkspaceContext(identity.workspace.id);
        workspace.value={projects:[],issues:[],boards:[],timeEntries:[],notifications:[],members:[identity.user],meta:{actor:identity.user.name,userId:identity.user.id,workspace:identity.workspace,permissions:identity.permissions}};
        resetWorkspaceUI();notify('工作空間已在其他分頁切換');
      }
      setWorkspaceContext(identity.workspace.id);
      const project = projectId.value ? await api.project(projectId.value) : null;
      if (generation !== refreshGeneration) return;
      workspace.value = { projects: project ? [project] : [], issues: [], boards: [], timeEntries: [], notifications: [], members: [identity.user],
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
      return;
    }
    const data = await api.getWorkspace();
    if (generation !== refreshGeneration) return;
    const previousWorkspace = workspace.value.meta.workspace?.id;
    if (previousWorkspace && previousWorkspace !== data.meta.workspace?.id) {
      resetWorkspaceUI(); notify('工作空間已切換，已關閉原工作空間的畫面');
    }
    setWorkspaceContext(data.meta.workspace?.id || '');
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
    if (convertingTodo.value) { const current=await api.todo(convertingTodo.value.id); if(!current.capabilities.convert){convertingTodo.value=null;notify('待辦資格已改變');} }

  } catch (failure) { if (generation === refreshGeneration) { if (failure.code === 'UNAUTHENTICATED') { authenticated.value = false; convertingTodo.value = null; todoNotificationsOpen.value = false; profileOpen.value = false; creatingProject.value = false; editingProject.value = null; boardEditor.value = null; creatingIssue.value = false; selectedId.value = ''; detailPage.value = false; editingDetail.value = false; workspace.value = { projects: [], issues: [], members: [], timeEntries: [], notifications: [], meta: {} }; } else if (isScopedRoute.value && ['FORBIDDEN', 'NOT_FOUND', 'WORKSPACE_CHANGED'].includes(failure.code)) {
      workspace.value = { ...workspace.value, projects: [], issues: [], boards: [] };
      selectedId.value = ''; creatingIssue.value = false; boardEditor.value = null;
      goHome(true); notify('專案存取權限已變更');
    } else error.value = failure.message; } }
  finally { if (generation === refreshGeneration) loading.value = false; }
}
function projectHash(id, issueId = '', mode = view.value, selectedBoardId = boardId.value, fullPage = detailPage.value, targetSection = issueId ? 'tasks' : section.value) {
  const featurePath = targetSection !== 'tasks' ? `/${targetSection}` : mode === 'boards' ? '/boards' : mode === 'board' ? `/board${selectedBoardId === 'default' ? '' : `/${encodeURIComponent(selectedBoardId)}`}` : '';
  return id ? `#/projects/${encodeURIComponent(id)}${featurePath}${issueId ? `/issues/${encodeURIComponent(issueId)}${fullPage ? '?detail=1' : ''}` : ''}` : landing.value === 'analysis' ? `#/time-analysis${analysisMode.value === 'index' ? '' : `/${analysisMode.value}`}` : landing.value === 'settings' ? workspaceSettingsHash(teamTab.value) : landing.value === 'projects' ? '#/projects' : '#/';
}
function issueMissing() { notify('找不到這個專案內的任務'); history.replaceState(null, '', projectHash(projectId.value)); readHash(true); }
function workspaceSettingsHash(tab = 'general') { return `#/workspace/settings${tab === 'members' ? '/members' : ''}`; }
function ensureSectionAccess() {
  if (landing.value === 'settings' && !canManageWorkspace.value) {
    history.replaceState(null, '', '#/'); readHash(true); notify('你沒有工作空間設定的存取權限');
  }
  if (landing.value === 'analysis' && !canAnalyze.value) {
    history.replaceState(null, '', '#/'); readHash(true);
    notify('你沒有工時分析的存取權限');
  }
  const permission = sectionPermissions[section.value];
  if (selectedProject.value && permission && !can(permission)) {
    section.value = 'tasks'; view.value = 'list'; boardId.value = 'default';
    history.replaceState(null, '', projectHash(projectId.value));
    notify('你沒有此功能的存取權限, 已返回任務列表');
  }
}
function canLeave() {
  if (switchingWorkspace.value || invitationTool.value?.saving || moving.value) return false;
  if (projectTasksTool.value?.saving || projectTasksTool.value?.dirty) { if (projectTasksTool.value?.saving || !window.confirm('還有未儲存的內容, 確定要離開嗎？')) return false; }
  if (teamTool.value?.saving || profileTool.value?.saving || workspaceTool.value?.saving || detailTool.value?.saving || issueTool.value?.saving || todoTool.value?.saving || conversionTool.value?.saving) return false;
  return (!teamTool.value?.dirty && !profileTool.value?.dirty && !todoTool.value?.dirty && !conversionTool.value?.dirty && !workspaceTool.value?.dirty && !boardEditorTool.value?.dirty && !detailTool.value?.dirty && !issueTool.value?.dirty) || window.confirm('還有未儲存的內容, 確定要離開嗎？');
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
  history.pushState(null, '', workspaceSettingsHash(tab));
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
async function logout() { if (!canLeave()) return; try { await api.logout(); setWorkspaceContext(''); ++refreshGeneration; ++refreshScope; authenticated.value = false; convertingTodo.value = null; todoNotificationsOpen.value = false; workspace.value = { projects: [], issues: [], members: [], timeEntries: [], notifications: [], meta: {} }; history.replaceState(null, '', '#/'); readHash(true); } catch (failure) { notify(failure.message); } }
function resetWorkspaceUI() {
  selectedId.value = ''; creatingIssue.value = false; detailPage.value = false; editingDetail.value = false;
  profileOpen.value = false; creatingProject.value = false; editingProject.value = null;
  boardEditor.value = null; convertingTodo.value = null; todoNotificationsOpen.value = false; issueReturn = null;
  projectId.value = ''; section.value = 'tasks'; view.value = 'list'; boardId.value = 'default';
  clearFilters(); workspaceInviteToken.value = ''; history.replaceState(null, '', '#/'); readHash(true);
}
async function changeWorkspace(workspaceId, inviteToken = '') {
  if (!inviteToken && !canLeave()) return false;
  switchingWorkspace.value = true; ++refreshGeneration; ++refreshScope;
  try {
    const session = inviteToken ? await api.acceptWorkspaceInvite({ inviteToken }) : await api.switchWorkspace({ workspaceId });
    resetWorkspaceUI();
    setWorkspaceContext(session.workspace.id);
    workspace.value = { projects: [], issues: [], members: [], timeEntries: [], notifications: [], meta: { actor: session.user.name, userId: session.user.id, workspace: session.workspace, permissions: session.permissions } };
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
    workspace.value = { projects: [], issues: [], members: [], timeEntries: [], notifications: [], meta: {} };
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
  if (fullPage) nextTick(() => { window.scrollTo(0, 0); detailTool.value?.focus(); });
}
function jumpFromEditor(issue) {
  if (detailTool.value?.dirty && !window.confirm('完整頁還有未儲存的內容，確定要切換任務嗎？')) return;
  openIssue(issue, detailPage.value, true);
}
function issueReturnLabel() {
  return { time: '返回工時月曆', notifications: '返回通知預覽' }[issueReturn?.section] || (view.value === 'board' ? '返回看板' : '返回任務列表');
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
  const analysisRoute = path.match(/^#\/time-analysis(?:\/(members|projects|trend))?$/);
  const nextAnalysis = analysisRoute?.[1] || 'index';
  const settingsRoute = path.match(/^#\/workspace\/settings(?:\/(general|members))?$/);
  const nextTeamTab = settingsRoute?.[1] || 'general';
  const nextLanding = settingsRoute ? 'settings' : analysisRoute ? 'analysis' : path === '#/projects' || path === '#/projects/' ? 'projects' : 'home';
  const nextInvite = path === '#/invite' ? new URLSearchParams(query).get('token') || '' : '';
  const landingChanged = landing.value !== nextLanding || (nextLanding === 'settings' && teamTab.value !== nextTeamTab) || analysisMode.value !== nextAnalysis || workspaceInviteToken.value !== nextInvite;
  const nextMode = route?.[2] || 'list';
  const retiredEpics = featureRoute?.[2] === 'epics';
  const nextSection = retiredEpics ? 'tasks' : featureRoute?.[2] || 'tasks';
  const nextDetail = Boolean(nextIssue && new URLSearchParams(query).get('detail') === '1');
  const issueChanged = selectedId.value !== nextIssue || detailPage.value !== nextDetail;
  const returning = Boolean(selectedId.value && !nextIssue);
  const projectChanged = projectId.value !== nextProject, modeChanged = view.value !== nextMode, boardChanged = boardId.value !== nextBoard;
  const sectionChanged = section.value !== nextSection;
  if (skipGuard !== true && (landingChanged || sectionChanged || issueChanged || projectChanged || modeChanged || boardChanged || retiredView || retiredEpics) && !canLeave()) { history.replaceState(null, '', projectHash(projectId.value, selectedId.value)); return; }
  if (projectChanged || modeChanged || boardChanged || retiredView) {
    section.value = 'tasks'; scope.value = 'all'; boardEditor.value = null; clearFilters();
  }
  landing.value = nextLanding; teamTab.value = nextTeamTab; analysisMode.value = nextAnalysis; workspaceInviteToken.value = nextInvite;
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
  if (retiredEpics) history.replaceState(null, '', projectHash(nextProject));
  if (retiredView) {
    history.replaceState(null, '', projectHash(nextProject, nextIssue, nextMode, nextBoard));
    notify('視圖功能已移除, 已開啟原專案頁面');
  }
  creatingIssue.value = false;
  sidebarOpen.value = false;
  if (authenticated.value && (projectChanged || modeChanged || boardChanged || sectionChanged || issueChanged || landingChanged)) refresh();
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
  if (isTaskRoute.value) { projectTasksTool.value?.create(null, {state: typeof state === 'string' ? state : '', type, epicId}); return; }
  defaultType.value = type;
  defaultEpicId.value = epicId;
  defaultState.value = typeof state === 'string' && state ? state : projectStates.value.find((item) => item.id === 'open')?.id || projectStates.value[0].id;
  opener = document.activeElement;
  if (!selectedProject.value) creatingProject.value = true;
  else creatingIssue.value = true;
}
async function savedIssue(issue, isNew) {
  ++refreshGeneration; ++refreshScope;
  workspace.value = { ...workspace.value, issues: [issue, ...issues.value.filter((item) => item.id !== issue.id)] };
  notify(isNew ? `已建立 ${issue.identifier}` : '已儲存');
  if (isNew) await closeIssue();
  await refresh();
}
async function createdProject(project) {
  ++refreshGeneration; ++refreshScope;
  workspace.value = { ...workspace.value, projects: [...projects.value.filter(item => item.id !== project.id), project] };
  const wasEditing = Boolean(editingProject.value);
  creatingProject.value = false;
  editingProject.value = null;
  goProjects();
  await refresh();
  await projectDirectoryTool.value?.refresh();
  notify(wasEditing ? '專案設定已儲存' : `已建立「${project.name}」`);
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
async function reloadEditor(callback, settled = () => {}) { try { await refresh(); if (!error.value) { await nextTick(); callback(); } } finally { settled(); } }
function beforeUnload(event) { if (teamTool.value?.dirty || profileTool.value?.dirty || todoTool.value?.dirty || conversionTool.value?.dirty || workspaceTool.value?.dirty || detailTool.value?.dirty) { event.preventDefault(); event.returnValue = ''; } }
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
  <AuthGate v-if="!authenticated && !loading && !error" @authenticated="authenticatedSession" />
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
          <button class="nav-item" :class="{ active: section === 'tasks' && view === 'list' && scope === 'all' }" :aria-current="section === 'tasks' && view === 'list' && scope === 'all' ? 'page' : undefined" aria-label="任務列表" @click="navigate('all')"><Icon name="list" :size="18" />任務列表<span v-if="!isTaskRoute" class="nav-count">{{ projectAllIssues.length }}</span></button>
          <button class="nav-item" :class="{ active: section === 'tasks' && (view === 'board' || view === 'boards') }" :aria-current="section === 'tasks' && (view === 'board' || view === 'boards') ? 'page' : undefined" aria-label="任務看板" @click="navigate('all', projectId, 'boards')"><Icon name="board" :size="18" />任務看板</button>
          <button class="nav-item" :class="{ active: section === 'tasks' && view === 'list' && scope === 'mine' }" @click="navigate('mine')"><Icon name="user" :size="18" />我的任務<span v-if="!isTaskRoute" class="nav-count">{{ projectAllIssues.filter(i => i.assignee === currentUserId).length }}</span></button>
          <button v-if="can('timeEntry.readOwn')" class="nav-item" :class="{ active: section === 'time' }" :aria-current="section === 'time' ? 'page' : undefined" @click="changeSection('time')"><Icon name="clock" :size="18" />工時月曆</button>
          <button v-if="can('project.statuses.update')" class="nav-item" :class="{ active: section === 'settings' }" :aria-current="section === 'settings' ? 'page' : undefined" @click="changeSection('settings')"><Icon name="filter" :size="18" />狀態設定</button>
          <button v-if="can('project.members.manage')" class="nav-item" :class="{ active: section === 'members' }" :aria-current="section === 'members' ? 'page' : undefined" aria-label="專案成員與權限" @click="changeSection('members')"><Icon name="user" :size="18" />成員與權限</button>
          <button class="nav-item" :class="{ active: section === 'notifications' }" :aria-current="section === 'notifications' ? 'page' : undefined" @click="changeSection('notifications')"><Icon name="message" :size="18" />通知預覽</button>
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
      <header class="topbar"><div class="breadcrumbs"><button class="icon-button mobile-menu" aria-label="開啟導覽" :aria-expanded="sidebarOpen" @click="sidebarOpen = !sidebarOpen"><Icon name="menu" /></button><Icon name="folder" :size="17" /><button class="breadcrumb-home" @click="selectedProject ? goProjects() : goHome()">{{ selectedProject ? '專案' : '工作空間' }}</button><Icon name="chevron" :size="13" /><strong :class="{ 'has-mobile-context': selectedProject }"><span class="project-breadcrumb">{{ selectedProject?.code || (landing === 'settings' ? '工作空間設定' : landing === 'analysis' ? '工時分析' : landing === 'projects' ? '專案列表' : '首頁') }}</span><span v-if="selectedProject" class="mobile-feature-context">{{ currentFeature }}</span></strong></div><div class="topbar-end"><button v-if="workspace.meta.permissions?.includes('todo.read')" class="button secondary todo-notification-toggle" :aria-label="`待辦通知${todoUnread ? `（${todoUnread} 則未讀）` : ''}`" @click="openTodoInbox"><Icon name="bell" :size="17" /><span>待辦通知</span><b v-if="todoUnread">{{ todoUnread }}</b></button><button class="icon-button" aria-label="重新整理工作空間" :disabled="loading || Boolean(moving)" @click="refreshCurrent"><Icon name="refresh" :size="18" /></button><AccountMenu ref="accountMenu" :name="workspace.meta.actor" :email="workspace.members.find(member => member.id === currentUserId)?.email" :workspace-name="workspace.meta.workspace?.name" @profile="openProfile" @logout="logout" /></div></header>
      <div class="workspace-content" :class="{ 'board-workspace': section === 'tasks' && view === 'board', 'data-workspace': selectedProject && section === 'tasks', 'detail-workspace': showIssuePage || (isTaskRoute && selectedId && detailPage) }">
        <button v-if="selectedProject && section !== 'tasks'" class="navigation-back back-to-projects" @click="goProjects()"><Icon name="chevron" :size="14" />返回專案列表</button>
        <div v-if="showIssuePage && error" class="error-box workspace-error" role="alert">{{ error }}<button class="text-button" @click="refresh({ join: true })">重新載入</button></div>
        <IssueDetail v-if="showIssuePage" ref="detailTool" :key="selectedIssue.id" :issue="selectedIssue" :project="selectedProject" :issues="projectAllIssues" :members="projectMembers" :entries="workspace.timeEntries" :return-label="issueReturnLabel()" :busy="Boolean(moving)" :can-write="canWrite" :can-read-time="can('timeEntry.readOwn')" :can-log-time="can('timeEntry.writeOwn')" :user-id="currentUserId" @time-updated="toolUpdated" @state="state => moveIssue(selectedIssue, { state })" @close="closeIssue" @edit="canWrite && (editingDetail = true)" @open="issue => openIssue(issue, true)" @saved="savedIssue" @reload="refresh" />
        <WorkspaceSettings v-if="!selectedProject && landing === 'settings' && canManageWorkspace" :key="workspace.meta.workspace?.id" ref="teamTool" :active-tab="teamTab" :meta="workspace.meta" @navigate="openWorkspaceSettings" @saved="refresh" @access="refresh" />
        <TimeAnalysis :key="workspace.meta.workspace?.id" v-if="!selectedProject && landing === 'analysis' && canAnalyze" :mode="analysisMode" :workspace-version="workspace" @navigate="goAnalysis" />
        <div v-if="!showIssuePage && !(isTaskRoute && selectedId) && (selectedProject || landing === 'projects')" class="page-heading"><div><h1>{{ title }}</h1><p v-if="selectedProject && section !== 'tasks' && selectedProject.description">{{ selectedProject.description }}</p></div><div class="heading-actions"><button v-if="showNewIssueAction" class="button primary" :disabled="loading" @click="newIssue"><Icon name="plus" :size="18" />新增任務</button><button v-else-if="!selectedProject && workspace.meta.permissions?.includes('project.create')" class="button primary" :disabled="loading" @click="creatingProject = true"><Icon name="plus" :size="18" />建立專案</button></div></div>
        <div v-if="!selectedProject && ['home', 'projects'].includes(landing)" :key="workspace.meta.workspace?.id" :class="landing === 'home' ? 'home-layout' : 'project-list-page'">
        <h1 v-if="landing === 'home'" class="sr-only">首頁</h1>
        <div v-if="landing === 'home' && error" class="error-box" role="alert">{{ error }}<button class="text-button" @click="refresh({ join: true })">重新載入</button></div>
        <ProjectDirectory v-if="landing === 'projects'" ref="projectDirectoryTool" :workspace-id="workspace.meta.workspace?.id" :can-create="workspace.meta.permissions?.includes('project.create')" :demo-loading="demoLoading" @create="creatingProject = true" @edit="editingProject = $event" @open="id => navigate('all', id)" @demo="loadDemo" />
        <PersonalWorkspace v-if="landing === 'home' && workspace.meta.permissions?.includes('todo.read')" ref="todoTool" :workspace-id="workspace.meta.workspace.id" :permissions="workspace.meta.permissions" :versions="personalSync.versions.value" :resumed="personalSync.resumed.value" :user-id="currentUserId"     @convert="convertTodo" @open-issue="openTodoIssue"  @access="refresh({join:true})"/>
        </div>
        <ProjectTasksWorkspace v-else-if="isTaskRoute && selectedProject" ref="projectTasksTool" :key="`${workspace.meta.workspace?.id}:${projectId}`" :project="selectedProject" :mode="view" :scope="scope" :board-id="boardId" :issue-id="selectedId" :detail-page="detailPage" :user-id="currentUserId" :workspace-id="workspace.meta.workspace.id" :today="today" @board="openBoard" @list="navigate('all', projectId, 'boards')" @issue="openIssue" @close-issue="closeIssue" @issue-missing="issueMissing" @board-missing="notify('看板不存在或已移除'); navigate('all', projectId, 'boards')" @notify="notify" @access="refresh({ join: true })" @project="project => { workspace = { ...workspace, projects: [project] }; }" />
        <template v-else-if="selectedProject">

        <BoardList v-show="!showIssuePage" v-if="section === 'tasks' && view === 'boards'" :boards="projectBoards" :issues="projectAllIssues" :can-manage="can('board.manage')" :loading="loading" @open="openBoard" @create="boardEditor = { board: null }" @edit="boardEditor = { board: $event }" />
        <section v-show="!showIssuePage" v-if="section === 'tasks' && view !== 'boards'" class="task-workspace" aria-label="任務工作區">
          <h2 class="sr-only">{{ currentFeature }}</h2>
          <header v-if="view === 'board' && selectedBoard" class="selected-board-heading"><div class="selected-board-identity"><button type="button" class="navigation-back" @click="navigate('all', projectId, 'boards')"><Icon name="chevron" :size="16" />返回看板列表</button><h3>{{ selectedBoard.name }}</h3></div><div v-if="!selectedBoard.isDefault && can('board.manage')" class="selected-board-actions"><button class="button secondary" @click="boardEditor = { board: selectedBoard }">編輯收錄</button><button class="text-button danger-text" @click="archiveBoard">移除看板</button></div></header>
          <IssueFilters
            :key="`${projectId}:${view}`" v-model="filterInput" :states="projectStates" :members="workspace.members"
            :issues="projectAllIssues" :active-filter-chips="activeFilterChips" :filter-warnings="filterWarnings"
            @remove="removeFilter" @clear="clearFilters"
          />
          <div v-if="error" class="error-box workspace-error" role="alert">{{ error }}<button class="text-button" @click="refresh({ join: true })">重新載入</button></div>
          <div v-if="loading" class="empty-state"><span class="loader"></span><h2>正在整理你的工作空間</h2></div>
          <div v-else-if="!visibleIssues.length && view === 'list'" class="empty-state"><span class="empty-icon"><Icon :name="filterActive ? 'search' : 'check'" :size="32" /></span><h2>{{ filterActive ? '沒有符合條件的任務' : scope === 'mine' ? '目前沒有指派給你的任務' : '尚無任務' }}</h2><button v-if="filterActive" class="button secondary" @click="clearFilters">清除篩選</button><button v-else-if="showNewIssueAction" class="button secondary" @click="newIssue"><Icon name="plus" :size="17" />新增任務</button></div>
          <IssueList v-else-if="view === 'list'" :issues="pagedIssues" :states="projectStates" :members="workspace.members" :can-write="canWrite" :today="today" @open="openIssue" />
          <ProjectBoard v-else :sync-state="realtimeState" :key="`${currentUserId}:${projectId}:${boardId}`" :board-key="boardId" :included-epic-ids="boardEpicIds" :project="selectedProject" :issues="projectAllIssues" :visible-issues="visibleIssues" :entries="workspace.timeEntries" :members="projectMembers" :user-id="currentUserId" :can-write="canWrite" :can-create="canCreateIssue" :moving="moving" :today="today" @open="openIssue" @move="moveIssue" @create="input => newIssue(input.state, 'task', input.epicId)" />
          <footer v-if="projects.length && !loading && view === 'list'" class="table-footer"><span v-if="view === 'list'" role="status">{{ visibleIssues.length }} 筆結果</span><span v-else>{{ canWrite ? '' : '唯讀' }}</span><div v-if="view === 'list' && pageCount > 1" class="pagination"><span>{{ (page - 1) * pageSize + 1 }}–{{ Math.min(page * pageSize, visibleIssues.length) }} / {{ visibleIssues.length }}</span><button class="icon-button previous" aria-label="上一頁" :disabled="page === 1" @click="page--"><Icon name="chevron" :size="15" /></button><button class="icon-button" aria-label="下一頁" :disabled="page === pageCount" @click="page++"><Icon name="chevron" :size="15" /></button></div></footer>
        </section>
        <div v-if="error && section !== 'tasks'" class="error-box" role="alert">{{ error }}</div>
        <TimeCalendar :can-write="can('timeEntry.writeOwn')" v-if="section === 'time' && can('timeEntry.readOwn')" ref="workspaceTool" :key="workContextKey" :context-key="workContextKey" :project="selectedProject" :can-read-team="can('timeEntry.readTeam')" :user-id="currentUserId" @updated="toolUpdated" @open="openIssue" @access="refresh" />
        <ProjectStatusWorkspace v-if="section === 'settings' && can('project.statuses.update')" ref="workspaceTool" :project="selectedProject" @updated="toolUpdated" @access="refresh" />
        <ProjectMembers @access="refresh" v-if="section === 'members' && can('project.members.manage')" ref="workspaceTool" :project="selectedProject" @updated="toolUpdated" />
        <NotificationPreview ref="workspaceTool" v-if="section === 'notifications'" :project="selectedProject" :permissions="workspace.meta.permissions || []" @open="openIssue" @access="refresh" />
        </template>
        <footer v-show="!showIssuePage" class="page-footer"><span>TaskTracker <b>v0.2</b></span></footer>
      </div>
    </main>
    <WorkspaceInvitation v-if="workspaceInviteToken" ref="invitationTool" :key="workspaceInviteToken" :token="workspaceInviteToken" :email="workspace.members.find(member => member.id === currentUserId)?.email" :accept="token => changeWorkspace('', token)" @close="closeInvitation" @logout="loginForInvitation" />
    <IssuePanel v-if="!isTaskRoute && canWrite && (creatingIssue || (selectedIssue && (!detailPage || editingDetail)))" ref="issueTool" :can-expand="!detailPage" :key="selectedIssue?.id || 'new'" :issue="selectedIssue" :projects="selectedProject ? [selectedProject] : []" :default-project="projectId" :default-state="defaultState" :default-type="defaultType" :default-epic-id="defaultEpicId" :default-board-id="creationBoardId" :members="projectMembers" :issues="projectAllIssues" :entries="workspace.timeEntries" @expand="expandIssue" @open="jumpFromEditor" @close="closeIssue" @saved="savedIssue" @reload="reloadEditor" />
    <BoardDialog v-if="boardEditor" ref="boardEditorTool" :project="selectedProject" :issues="projectAllIssues" :board="boardEditor.board" @close="boardEditor = null" @saved="boardSaved" @reload="refresh" />
    <TodoConversion v-if="convertingTodo" ref="conversionTool" :key="`convert:${convertingTodo.id}`" :todo="convertingTodo" :user="workspace.members.find(member=>member.id===currentUserId)" @close="convertingTodo=null" @converted="convertedTodo" @reload="reloadConversion" @open="jumpFromConversion" />
    <PersonalNotifications v-if="todoNotificationsOpen" :workspace-id="workspace.meta.workspace.id" :version="`${personalSync.versions.value?.notifications}:${personalSync.versions.value?.todos}`" @close="todoNotificationsOpen=false" @open="openTodoNotification" />

    <ProfileDialog v-if="profileOpen" ref="profileTool" @close="closeSettings('profile')" @saved="savedProfile" />
    <ProjectDialog :lookup="(filters,options)=>api.projectLeads({...filters,project:editingProject?.id},options)" v-if="creatingProject || editingProject" :project="editingProject" :members="workspace.members" :user-id="currentUserId" @close="creatingProject = false; editingProject = null" @created="createdProject" />
    <Transition name="toast"><div v-if="toast" class="toast-message" role="status"><span><Icon name="check" :size="16" /></span>{{ toast }}</div></Transition>
  </div>
  </v-app>
</template>
