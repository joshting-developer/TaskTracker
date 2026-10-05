<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Icon from './components/Icon.vue';
import IssuePanel from './components/IssuePanel.vue';
import ProjectDialog from './components/ProjectDialog.vue';
import ProjectHome from './components/ProjectHome.vue';
import { api } from './api/index.js';
import { dateInTaipei, PRIORITIES, STATES, TYPES } from './domain/model.js';

const workspace = ref({ projects: [], issues: [], meta: {} });
const loading = ref(true);
const error = ref('');
const view = ref('list');
const scope = ref('all');
const projectId = ref('');
const search = ref('');
const stateFilter = ref('');
const priorityFilter = ref('');
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
const dragOver = ref('');
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
const projectIssues = computed(() => issues.value.filter((item) => item.projectId === projectId.value));
const scopeIssues = computed(() => projectIssues.value.filter((item) => (scope.value !== 'mine' || item.assignee === '我') && (scope.value !== 'done' || item.state === 'done')));
const visibleIssues = computed(() => {
  const term = search.value.trim().toLocaleLowerCase();
  const rows = scopeIssues.value.filter((item) => (!stateFilter.value || item.state === stateFilter.value) && (!priorityFilter.value || item.priority === priorityFilter.value) && (!term || [item.title, item.description, item.identifier, item.assignee].some((value) => value.toLocaleLowerCase().includes(term))));
  return [...rows].sort((a, b) => {
    if (sort.value === 'priority') return PRIORITIES.findIndex((p) => p.id === a.priority) - PRIORITIES.findIndex((p) => p.id === b.priority) || b.updatedAt.localeCompare(a.updatedAt);
    if (sort.value === 'due') return (a.dueDate || '9999').localeCompare(b.dueDate || '9999') || b.updatedAt.localeCompare(a.updatedAt);
    return b.updatedAt.localeCompare(a.updatedAt);
  });
});
const pageCount = computed(() => Math.max(1, Math.ceil(visibleIssues.value.length / pageSize)));
const pagedIssues = computed(() => visibleIssues.value.slice((page.value - 1) * pageSize, page.value * pageSize));
const completed = computed(() => scopeIssues.value.filter((item) => item.state === 'done').length);
const progress = computed(() => scopeIssues.value.length ? Math.round(completed.value / scopeIssues.value.length * 100) : 0);
const title = computed(() => selectedProject.value?.name || '我的專案');
const filterActive = computed(() => search.value || stateFilter.value || priorityFilter.value);

watch([search, stateFilter, priorityFilter, sort, scope, projectId], () => { page.value = 1; });
watch(pageCount, (count) => { if (page.value > count) page.value = count; });
watch(view, () => {
  try { localStorage.setItem('tasktracker-view', JSON.stringify({ view: view.value })); } catch {}
});

async function refresh() {
  const generation = ++refreshGeneration;
  error.value = '';
  try {
    const data = await api.getWorkspace();
    if (generation !== refreshGeneration) return;
    workspace.value = data;
    if (selectedId.value && !projectId.value) {
      const legacyIssue = data.issues.find((item) => item.id === selectedId.value);
      if (legacyIssue) { history.replaceState(null, '', projectHash(legacyIssue.projectId, legacyIssue.id)); readHash(); }
      else { notify('找不到這個任務'); goHome(true); }
    }
    if (projectId.value && !data.projects.some((item) => item.id === projectId.value)) { notify('找不到這個專案'); goHome(true); }
    if (selectedId.value && !selectedIssue.value) { notify('找不到這個專案內的任務'); history.replaceState(null, '', projectHash(projectId.value)); readHash(); }
  } catch (failure) { if (generation === refreshGeneration) error.value = failure.message; }
  finally { if (generation === refreshGeneration) loading.value = false; }
}
function notify(message) {
  toast.value = message;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toast.value = ''; }, 3500);
}
function projectHash(id, issueId = '') {
  return id ? `#/projects/${encodeURIComponent(id)}${issueId ? `/issues/${encodeURIComponent(issueId)}` : ''}` : '#/';
}
function navigate(nextScope, id = projectId.value) {
  location.hash = projectHash(id);
  readHash();
  scope.value = nextScope;
  stateFilter.value = '';
  priorityFilter.value = '';
  search.value = '';
  sidebarOpen.value = false;
}
function goHome(replace = false) {
  if (replace) { history.replaceState(null, '', '#/'); readHash(); }
  else navigate('all', '');
  sidebarOpen.value = false;
}
function openIssue(issue) {
  opener = document.activeElement;
  location.hash = projectHash(issue.projectId, issue.id);
  readHash();
}
function readHash() {
  const route = location.hash.match(/^#\/projects\/([^/]+)(?:\/issues\/([^/]+))?$/);
  const legacyId = location.hash.match(/^#\/issue\/([^/]+)$/)?.[1];
  let nextProject = '', nextIssue = '';
  try {
    nextProject = route ? decodeURIComponent(route[1]) : '';
    nextIssue = route?.[2] ? decodeURIComponent(route[2]) : legacyId ? decodeURIComponent(legacyId) : '';
    if (legacyId) {
      const issue = issues.value.find((item) => item.id === nextIssue);
      if (issue) { nextProject = issue.projectId; history.replaceState(null, '', projectHash(nextProject, nextIssue)); }
    }
  } catch { history.replaceState(null, '', '#/'); }
  if (projectId.value !== nextProject) { scope.value = 'all'; clearFilters(); }
  projectId.value = nextProject;
  selectedId.value = nextIssue;
  creatingIssue.value = false;
  sidebarOpen.value = false;
}
async function closeIssue() {
  creatingIssue.value = false;
  selectedId.value = '';
  history.replaceState(null, '', projectHash(projectId.value));
  await nextTick();
  opener?.focus();
}
function newIssue(state = 'todo') {
  defaultState.value = typeof state === 'string' ? state : 'todo';
  opener = document.activeElement;
  if (!selectedProject.value) creatingProject.value = true;
  else creatingIssue.value = true;
}
async function savedIssue(issue, isNew) {
  ++refreshGeneration;
  workspace.value = { ...workspace.value, issues: [issue, ...issues.value.filter((item) => item.id !== issue.id)] };
  notify(isNew ? `已建立 ${issue.identifier}` : '已儲存');
  if (isNew) await closeIssue();
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
async function changeState(issue, state) {
  if (moving.value || state === issue.state) return;
  moving.value = issue.id;
  const signature = `${issue.id}:${issue.revision}:${state}`;
  if (!moveRequests.has(signature)) moveRequests.set(signature, crypto.randomUUID());
  try {
    const result = await api.updateIssue({ id: issue.id, state, expectedRevision: issue.revision, requestId: moveRequests.get(signature) });
    await savedIssue(result, false);
    moveRequests.delete(signature);
  } catch (failure) {
    error.value = failure.message;
    if (failure.code === 'REVISION_CONFLICT') { await refresh(); error.value = `${failure.message}, 清單已更新`; }
  } finally { moving.value = ''; }
}
function startDrag(event, issue) {
  event.dataTransfer.setData('text/plain', issue.id);
  event.dataTransfer.effectAllowed = 'move';
}
function drop(event, state) {
  dragOver.value = '';
  const issue = issues.value.find((item) => item.id === event.dataTransfer.getData('text/plain'));
  if (issue) changeState(issue, state);
}
function clearFilters() { search.value = ''; stateFilter.value = ''; priorityFilter.value = ''; }
function overdue(issue) { return issue.dueDate && issue.dueDate < today.value && issue.state !== 'done'; }
function shortDate(value) { return value ? `${Number(value.slice(5, 7))}/${Number(value.slice(8))}` : ''; }
function option(items, id) { return items.find((item) => item.id === id); }
function projectFor(issue) { return projects.value.find((item) => item.id === issue.projectId); }
async function reloadEditor(callback) { await refresh(); if (!error.value) { await nextTick(); callback(); } }
function keydown(event) {
  if (event.key === 'Escape') sidebarOpen.value = false;
}
onMounted(async () => {
  try {
    const preferences = JSON.parse(localStorage.getItem('tasktracker-view') || '{}');
    if (['list', 'board'].includes(preferences.view)) view.value = preferences.view;
  } catch {}
  readHash();
  window.addEventListener('hashchange', readHash);
  window.addEventListener('keydown', keydown);
  dateTimer = setInterval(() => { today.value = dateInTaipei(); }, 60000);
  await refresh();
});
onBeforeUnmount(() => { window.removeEventListener('hashchange', readHash); window.removeEventListener('keydown', keydown); clearTimeout(toastTimer); clearInterval(dateTimer); });
</script>

<template>
  <div class="app-shell">
    <button v-if="sidebarOpen" class="sidebar-scrim" aria-label="收起導覽" @click="sidebarOpen = false"></button>
    <aside class="sidebar" :class="{ open: sidebarOpen }">
      <a class="brand" href="#/" @click.prevent="goHome()"><span class="brand-mark"><span></span><span></span><span></span></span>TaskTracker<span class="brand-dot">.</span></a>
      <div class="workspace-switch"><span class="workspace-avatar">W</span><div><strong>我的工作空間</strong><span>一個計畫, 一步一步</span></div><span class="workspace-chevron">⌄</span></div>
      <p class="nav-label">工作空間</p>
      <nav aria-label="工作空間">
        <button class="nav-item" :class="{ active: !selectedProject }" @click="goHome()"><Icon name="folder" :size="18" />專案列表<span class="nav-count">{{ projects.length }}</span></button>
      </nav>
      <template v-if="selectedProject">
        <p class="nav-label projects-label">目前專案 · {{ selectedProject.code }}</p>
        <nav aria-label="專案任務">
          <button class="nav-item" :class="{ active: scope === 'all' }" @click="navigate('all')"><Icon name="grid" :size="18" />所有任務<span class="nav-count">{{ projectIssues.length }}</span></button>
          <button class="nav-item" :class="{ active: scope === 'mine' }" @click="navigate('mine')"><Icon name="user" :size="18" />我的任務<span class="nav-count">{{ projectIssues.filter(i => i.assignee === '我').length }}</span></button>
          <button class="nav-item" :class="{ active: scope === 'done' }" @click="navigate('done')"><Icon name="check" :size="18" />已完成<span class="nav-count">{{ projectIssues.filter(i => i.state === 'done').length }}</span></button>
        </nav>
      </template>
      <div class="nav-label projects-label">專案<button class="sidebar-add" aria-label="新增專案" @click="creatingProject = true"><Icon name="plus" :size="16" /></button></div>
      <nav class="project-nav" aria-label="專案">
        <button v-for="project in projects" :key="project.id" class="nav-item" :class="{ active: projectId === project.id }" @click="navigate('all', project.id)"><span class="project-dot" :style="{ background: project.color }"></span><span class="project-name">{{ project.name }}</span><span class="nav-count">{{ issues.filter(i => i.projectId === project.id).length }}</span></button>
        <p v-if="!projects.length" class="sidebar-empty">你的下一個計畫會在這裡</p>
        <button class="nav-item add-project" @click="creatingProject = true"><Icon name="plus" :size="17" />建立新專案</button>
      </nav>
      <div class="sidebar-bottom"><div class="sidebar-note"><span class="spark">✦</span><strong>小步前進, 也算進步</strong><p>先記下下一步<br />再把一件事情完成</p></div><div class="profile"><div class="avatar">我</div><div><strong>個人工作空間</strong><span>單人試用版</span></div><span class="online-dot"></span></div></div>
    </aside>

    <main class="main">
      <header class="topbar"><div class="breadcrumbs"><button class="icon-button mobile-menu" aria-label="開啟導覽" :aria-expanded="sidebarOpen" @click="sidebarOpen = !sidebarOpen"><Icon name="menu" /></button><Icon name="folder" :size="17" /><button class="breadcrumb-home" @click="goHome()">專案</button><Icon name="chevron" :size="13" /><strong>{{ selectedProject?.code || '專案列表' }}</strong></div><div class="topbar-end"><span class="local-indicator"><i></i>{{ workspace.meta.mode === 'gas-owner' ? '個人雲端版' : '個人試用版' }}</span><button class="icon-button" aria-label="重新整理工作空間" :disabled="loading || Boolean(moving)" @click="refresh"><Icon name="refresh" :size="18" /></button><div class="avatar tiny">我</div></div></header>
      <div class="workspace-content">
        <button v-if="selectedProject" class="back-to-projects" @click="goHome()"><Icon name="chevron" :size="14" />返回專案列表</button>
        <div class="page-heading"><div><div class="eyebrow">{{ selectedProject ? 'YOUR PROJECT, IN FOCUS' : 'A SPACE FOR EVERY PROJECT' }}</div><h1>{{ title }}<span class="heading-dot">.</span></h1><p>{{ selectedProject ? selectedProject.description || '把這個專案的下一步, 一件件完成' : '先選一個專案, 再專心處理裡面的事' }}</p></div><button v-if="selectedProject" class="button primary" :disabled="loading" @click="newIssue"><Icon name="plus" :size="18" />新增任務<span class="button-key">＋</span></button><button v-else class="button primary" :disabled="loading" @click="creatingProject = true"><Icon name="plus" :size="18" />建立專案</button></div>
        <ProjectHome v-if="!selectedProject" :projects="projects" :issues="issues" :loading="loading" :error="error" :demo-loading="demoLoading" @create="creatingProject = true" @open="id => navigate('all', id)" @demo="loadDemo" @retry="refresh" />
        <template v-else>

        <section class="overview" aria-label="任務概況">
          <div class="overview-stat"><span class="stat-icon purple"><Icon name="diamond" /></span><div><span class="stat-label">所有任務</span><strong>{{ scopeIssues.length }}<small>件待辦與計畫</small></strong></div></div>
          <div class="overview-stat"><span class="stat-icon amber"><Icon name="clock" /></span><div><span class="stat-label">進行中</span><strong>{{ scopeIssues.filter(i => i.state === 'in_progress').length }}<small>持續推進中</small></strong></div></div>
          <div class="overview-stat"><span class="stat-icon green"><Icon name="check" /></span><div><span class="stat-label">已完成</span><strong>{{ completed }}<small>每一步都算數</small></strong></div></div>
          <div class="overview-progress"><div><span class="stat-label">完成進度</span><strong>{{ progress }}<small>%</small></strong></div><div class="progress-track"><span :style="{ width: `${progress}%` }"></span></div><p>{{ scopeIssues.length ? `${completed} / ${scopeIssues.length} 件任務已完成` : '從第一個任務開始' }}</p></div>
        </section>

        <section class="task-workspace" aria-label="任務工作區">
          <div class="view-toolbar"><div class="view-tabs" role="tablist" aria-label="任務呈現方式"><button role="tab" :aria-selected="view === 'list'" :class="{ active: view === 'list' }" @click="view = 'list'"><Icon name="list" :size="17" />任務清單</button><button role="tab" :aria-selected="view === 'board'" :class="{ active: view === 'board' }" @click="view = 'board'"><Icon name="board" :size="17" />狀態看板</button></div><span class="result-count">{{ visibleIssues.length }} 個任務</span></div>
          <div class="filter-toolbar"><label class="search-field"><Icon name="search" :size="18" /><input v-model="search" placeholder="搜尋任務、編號或負責人…" aria-label="搜尋任務" /><button v-if="search" class="icon-button" aria-label="清除搜尋" @click="search = ''"><Icon name="close" :size="15" /></button></label><div class="filters"><Icon name="filter" :size="17" class="filter-icon" /><select v-model="stateFilter" aria-label="篩選狀態"><option value="">所有狀態</option><option v-for="state in STATES" :key="state.id" :value="state.id">{{ state.label }}</option></select><select v-model="priorityFilter" aria-label="篩選優先級"><option value="">所有優先級</option><option v-for="priority in PRIORITIES" :key="priority.id" :value="priority.id">{{ priority.label }}</option></select><select v-model="sort" aria-label="排序方式"><option value="updated">最近更新</option><option value="priority">優先級排序</option><option value="due">到期日排序</option></select></div></div>
          <div v-if="error" class="error-box workspace-error" role="alert">{{ error }}<button class="text-button" @click="refresh">重新載入</button></div>
          <div v-if="loading" class="empty-state"><span class="loader"></span><h2>正在整理你的工作空間</h2></div>
          <div v-else-if="!visibleIssues.length && view === 'list'" class="empty-state"><span class="empty-icon"><Icon :name="filterActive ? 'search' : 'check'" :size="32" /></span><h2>{{ filterActive ? '沒有符合條件的任務' : scope === 'done' ? '每件完成的事, 都會出現在這裡' : scope === 'mine' ? '目前沒有指派給你的任務' : '這裡準備好迎接第一個任務了' }}</h2><p>{{ filterActive ? '換個關鍵字, 或放寬篩選條件試試看' : scope === 'mine' ? '在任務負責人填入「我」, 就能在這裡集中查看' : '把需要做的事情寫下來, 就是很好的開始' }}</p><button v-if="filterActive" class="button secondary" @click="clearFilters">清除篩選</button><button v-else class="button secondary" @click="newIssue"><Icon name="plus" :size="17" />新增任務</button></div>
          <div v-else-if="view === 'list'" class="table-scroll"><table class="issue-table"><thead><tr><th class="type-column"><span class="sr-only">類型</span></th><th>任務</th><th>狀態</th><th>優先級</th><th>負責人</th><th>到期日</th><th class="end-column"></th></tr></thead><tbody><tr v-for="issue in pagedIssues" :key="issue.id" :data-issue="issue.identifier"><td class="type-column"><span class="type-symbol" :class="issue.type" :title="option(TYPES, issue.type)?.label"><Icon :name="issue.type === 'bug' ? 'flag' : issue.type === 'feature' ? 'diamond' : 'check'" :size="15" /></span></td><td class="task-title-cell"><button class="task-title" @click="openIssue(issue)">{{ issue.title }}</button><div class="task-subline"><span class="identifier">{{ issue.identifier }}</span><span class="project-mini-dot" :style="{ background: projectFor(issue)?.color }"></span><span>{{ projectFor(issue)?.name }}</span><span v-if="issue.comments.length" class="comment-count"><Icon name="message" :size="12" />{{ issue.comments.length }}</span></div></td><td><span class="status-badge" :class="issue.state"><i></i>{{ option(STATES, issue.state)?.label }}</span></td><td><span class="priority-label" :style="{ color: option(PRIORITIES, issue.priority)?.color }"><span class="priority-bars" :class="issue.priority"><i></i><i></i><i></i></span>{{ option(PRIORITIES, issue.priority)?.label }}</span></td><td><span v-if="issue.assignee" class="assignee"><span class="avatar small">{{ issue.assignee.slice(0, 1) }}</span>{{ issue.assignee }}</span><span v-else class="unassigned">未指派</span></td><td><span :class="['due-date', { overdue: overdue(issue) }]">{{ shortDate(issue.dueDate) || '—' }}<span v-if="overdue(issue)" class="overdue-dot" title="已過期"></span></span></td><td><button class="icon-button row-open" :aria-label="`開啟 ${issue.identifier}`" @click="openIssue(issue)"><Icon name="chevron" :size="17" /></button></td></tr></tbody></table></div>
          <div v-else class="board"><section v-for="state in STATES" :key="state.id" class="board-column" :class="{ 'drag-over': dragOver === state.id }" :data-state="state.id" @dragover.prevent="dragOver = state.id" @dragleave="dragOver = ''" @drop.prevent="drop($event, state.id)"><header class="column-header"><span><i :style="{ background: state.color }"></i>{{ state.label }}<b>{{ visibleIssues.filter(i => i.state === state.id).length }}</b></span><button class="icon-button" :aria-label="`新增${state.label}任務`" @click="newIssue(state.id)"><Icon name="plus" :size="16" /></button></header><article v-for="issue in visibleIssues.filter(i => i.state === state.id)" :key="issue.id" class="issue-card" :class="{ moving: moving === issue.id }" :draggable="!moving" :data-issue="issue.identifier" @dragstart="startDrag($event, issue)" @dragend="dragOver = ''"><div class="card-top"><span class="identifier">{{ issue.identifier }}</span><span class="priority-label" :style="{ color: option(PRIORITIES, issue.priority)?.color }" :title="`優先級：${option(PRIORITIES, issue.priority)?.label}`"><span class="priority-bars" :class="issue.priority"><i></i><i></i><i></i></span></span></div><button class="card-title" @click="openIssue(issue)">{{ issue.title }}</button><div class="card-project"><span class="project-mini-dot" :style="{ background: projectFor(issue)?.color }"></span>{{ projectFor(issue)?.name }}</div><div class="card-bottom"><span class="type-tag">{{ option(TYPES, issue.type)?.label }}</span><span v-if="issue.dueDate" :class="['due-date', { overdue: overdue(issue) }]"><Icon name="clock" :size="12" />{{ shortDate(issue.dueDate) }}</span><span v-if="issue.comments.length" class="comment-count"><Icon name="message" :size="13" />{{ issue.comments.length }}</span><span v-if="issue.assignee" class="avatar small" :title="issue.assignee">{{ issue.assignee.slice(0, 1) }}</span></div><label class="card-state"><span class="sr-only">{{ issue.identifier }} 的狀態</span><select :value="issue.state" :disabled="Boolean(moving)" :aria-label="`${issue.identifier} 的狀態`" @change="changeState(issue, $event.target.value)"><option v-for="next in STATES" :key="next.id" :value="next.id">{{ next.label }}</option></select></label></article><p v-if="!visibleIssues.some(i => i.state === state.id)" class="column-empty">{{ filterActive ? '沒有符合條件的任務' : '還沒有任務' }}</p></section></div>
          <footer v-if="projects.length && !loading" class="table-footer"><span>{{ view === 'board' ? '拖曳卡片或使用卡片狀態選單更新進度' : '點選任務, 查看詳細內容與討論' }}</span><div v-if="view === 'list' && visibleIssues.length" class="pagination"><span>{{ (page - 1) * pageSize + 1 }}–{{ Math.min(page * pageSize, visibleIssues.length) }} / {{ visibleIssues.length }}</span><button class="icon-button previous" aria-label="上一頁" :disabled="page === 1" @click="page--"><Icon name="chevron" :size="15" /></button><button class="icon-button" aria-label="下一頁" :disabled="page === pageCount" @click="page++"><Icon name="chevron" :size="15" /></button></div></footer>
        </section>
        </template>
        <footer class="page-footer"><span><Icon name="sun" :size="14" />保持專注, 也記得休息一下</span><span>TaskTracker <b>v0.1</b></span></footer>
      </div>
    </main>
    <IssuePanel v-if="creatingIssue || selectedIssue" :key="selectedIssue?.id || 'new'" :issue="selectedIssue" :projects="selectedProject ? [selectedProject] : []" :default-project="projectId" :default-state="defaultState" @close="closeIssue" @saved="savedIssue" @reload="reloadEditor" />
    <ProjectDialog v-if="creatingProject" @close="creatingProject = false" @created="createdProject" />
    <Transition name="toast"><div v-if="toast" class="toast-message" role="status"><span><Icon name="check" :size="16" /></span>{{ toast }}</div></Transition>
  </div>
</template>
