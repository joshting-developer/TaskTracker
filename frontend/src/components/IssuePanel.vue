<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, useId, watch } from 'vue';
import { toRef } from 'vue';
import { useIssueActivity } from '../composables/useIssueActivity.js';
import { onTabKeydown } from '../ui/tabs.js';
import Icon from './Icon.vue';
import PagedSelect from './PagedSelect.vue';
import IssueRelations from './IssueRelations.vue';
import MarkdownEditor from './MarkdownEditor.vue';
import MarkdownContent from './MarkdownContent.vue';
import { api } from '../api/index.js';
import { FIELD_LABELS, hours, ISSUE_FIELDS, PRIORITIES, statusesFor, TYPES } from '../domain/model.js';

const props = defineProps({ projectLoader: Function, projectOptionsLoader: Function, lookup: Function, activityLoader: Function, issue: Object, todo: Object, projects: Array, defaultProject: String, defaultState: String, defaultType: String, defaultEpicId: String, defaultBoardId: String, members: Array, issues: Array, entries: Array, canExpand: Boolean });
const emit = defineEmits(['close', 'saved', 'converted', 'reload', 'open', 'expand']);
const dialog = ref(null);
const form = reactive({ parentIssueId: '', relatedIssueIds: [] });
const relationsTool = ref(null), picked = ref([]), pickedMembers = ref([]), pickedProjects = ref([]), hydratedProject = ref(null), projectLoading = ref(false);
let projectRequest, projectGeneration=0;
function lookupForProject(kind, filters, options) { return props.lookup(kind, filters, options, form.projectId); }
function remember(item) { picked.value = [...picked.value.filter(row => row.id !== item.id), item]; }
const tab = ref('comments');

const activity = useIssueActivity({ issue: toRef(props, 'issue'), tab, load: toRef(props, 'activityLoader') });
const activityComments = activity.comments, activityHistory = activity.history;
const tabId = useId();
const comment = ref('');
const saving = ref(false);
const error = ref('');
const conflict = ref(false);
const savedValues = ref('');
const baseRevision = ref(0);
const todoRevision = ref(0);
let childRequest = null;
let pending = null;
let commentRequest = null;

function values() {
  return { ...Object.fromEntries(ISSUE_FIELDS.map((key) => [key, form[key]])), projectId: form.projectId };
}
function resetForm() {
  baseRevision.value = props.issue?.revision || 0;
  todoRevision.value = props.todo?.revision || 0;
  Object.assign(form, {
    title: '', description: '', state: props.defaultState || 'todo', priority: 'normal', type: props.defaultType || 'task', assignee: '', dueDate: '', epicId: props.defaultEpicId || '', estimateMinutes: 0, parentIssueId: '', relatedIssueIds: [],
    ...(props.todo ? { title: props.todo.title, description: props.todo.content ?? props.todo.note } : {}),
    ...(props.issue || {}), projectId: props.issue?.projectId || props.defaultProject || (props.todo ? '' : props.projects[0]?.id) || '',
  });
  savedValues.value = JSON.stringify(values());
  error.value = '';
  conflict.value = false;
  pending = null;
}
const availableProjects = computed(() => [...new Map([...props.projects, ...pickedProjects.value].map(project=>[project.id,project])).values()]);
const selectedProject = computed(() => hydratedProject.value?.id === form.projectId ? hydratedProject.value : availableProjects.value.find(project => project.id === form.projectId));
watch(() => form.projectId, async id => {
  if (!props.projectLoader) return;
  const current=++projectGeneration; projectRequest?.abort();projectRequest=new AbortController();hydratedProject.value=null;
  if(!id){projectLoading.value=false;return;} projectLoading.value=true;
  try {const project=await props.projectLoader(id,{signal:projectRequest.signal});if(current!==projectGeneration)return;if(!project.permissions.includes('issue.write'))throw new Error('沒有這個專案的任務編輯權限');hydratedProject.value=project;form.state=project.statuses.find(state=>state.id==='open')?.id||project.statuses[0].id;}
  catch(failure){if(current===projectGeneration&&!['ABORTED','STALE_RESPONSE'].includes(failure.code))error.value=failure.message;}
  finally{if(current===projectGeneration)projectLoading.value=false;}
});
const availableIssues = computed(() => [...new Map([...props.issues, ...picked.value].filter(issue => issue.projectId === form.projectId).map(item => [item.id, item])).values()]);
const availableMembers = computed(() => props.todo && !props.lookup ? props.members.filter(member => selectedProject.value?.memberIds?.includes(member.id)) : [...new Map([...props.members, ...pickedMembers.value].map(item => [item.id,item])).values()]);
const states = computed(() => statusesFor(selectedProject.value));
const epics = computed(() => availableIssues.value.filter((issue) => issue.type === 'epic' && issue.id !== props.issue?.id));
const parentIssue = computed(() => availableIssues.value.find(item => item.id === form.parentIssueId));
watch(() => form.projectId, (value, before) => {
  if (!props.todo || value === before) return;
  form.state = states.value.find(state => state.id === 'open')?.id || states.value[0]?.id || 'todo';
  form.epicId = ''; form.parentIssueId = ''; form.relatedIssueIds = [];
  form.assignee = props.lookup ? props.todo.assigneeId : availableMembers.value.some(member => member.id === props.todo.assigneeId) ? props.todo.assigneeId : '';
});
// 詳細頁的既有父項參照只有顯示欄位；保留表單原Epic，僅由完整候選的明確歸屬更新。
watch(() => form.parentIssueId, () => { if (parentIssue.value && Object.hasOwn(parentIssue.value, 'epicId')) form.epicId = parentIssue.value.epicId; });
const estimateHours = computed({ get: () => (form.estimateMinutes || 0) / 60, set: (value) => { form.estimateMinutes = Math.round(Number(value) * 60); } });
watch(() => form.type, (type) => { if (type === 'epic') { form.epicId = ''; form.estimateMinutes = 0; form.parentIssueId = ''; } });
const spent = computed(() => props.issue?.spentMinutes ?? props.entries.filter((entry) => !entry.voidedAt && entry.issueId === props.issue?.id).reduce((sum, entry) => sum + entry.minutes, 0));
const inputDisabled = computed(() => saving.value || projectLoading.value);
const dirty = computed(() => JSON.stringify(values()) !== savedValues.value);
defineExpose({ dirty: computed(() => dirty.value || Boolean(comment.value.trim()) || Boolean(relationsTool.value?.dirty)), saving: inputDisabled });
watch(() => props.issue, () => { if (!dirty.value) resetForm(); });
function close() {
  if (inputDisabled.value) return;
  if ((dirty.value || comment.value.trim() || relationsTool.value?.dirty) && !window.confirm('還有未儲存的內容, 確定要關閉嗎？')) return;
  emit('close');
}
function jump(issue) {
  if (saving.value || ((dirty.value || comment.value.trim() || relationsTool.value?.dirty) && !window.confirm('還有未儲存的內容, 確定要切換任務嗎？'))) return;
  emit('open', issue);
}
function beforeUnload(event) {
  if (dirty.value || comment.value.trim() || relationsTool.value?.dirty) { event.preventDefault(); event.returnValue = ''; }
}
onMounted(async () => {
  resetForm();
  dialog.value.showModal();
  window.addEventListener('beforeunload', beforeUnload);
  await nextTick();
  dialog.value.querySelector('[name="title"]').focus();
});
onBeforeUnmount(() => { projectGeneration++;projectRequest?.abort();window.removeEventListener('beforeunload', beforeUnload); });

async function save() {
  if (inputDisabled.value) return;
  if (props.projectLoader && !hydratedProject.value?.permissions.includes('issue.write')) { error.value='請先選擇可編輯的專案'; return; }
  saving.value = true;
  error.value = '';
  const fields = { ...values(), ...(props.issue ? { id: props.issue.id, expectedRevision: baseRevision.value } : { boardId: props.defaultBoardId || 'default' }) };
  const payload = props.todo ? { id: props.todo.id, expectedRevision: todoRevision.value, issue: fields } : fields;
  const signature = JSON.stringify(payload);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  try {
    const result = await api[props.todo ? 'convertTodo' : props.issue ? 'updateIssue' : 'createIssue']({ ...payload, requestId: pending.requestId });
    savedValues.value = JSON.stringify(values());
    baseRevision.value = result.revision;
    pending = null;
    if (props.todo) emit('converted', result); else emit('saved', result, !props.issue);
  } catch (failure) {
    error.value = failure.message;
    conflict.value = failure.code === 'REVISION_CONFLICT';
  } finally { saving.value = false; }
}
async function createChild(title) {
  if (saving.value || !title.trim()) return;
  if (dirty.value) { error.value = '請先儲存目前任務的修改, 再建立子任務'; return; }
  saving.value = true;
  error.value = '';
  const payload = { projectId: form.projectId, title, parentIssueId: props.issue.id, state: states.value.find(state => state.id === 'open')?.id || states.value[0].id };
  const signature = JSON.stringify(payload);
  if (childRequest?.signature !== signature) childRequest = { signature, requestId: crypto.randomUUID() };
  try {
    const child = await api.createIssue({ ...payload, requestId: childRequest.requestId });
    relationsTool.value.resetChild();
    childRequest = null;
    emit('saved', child, false);
  } catch (failure) { error.value = failure.message; }
  finally { saving.value = false; }
}
async function sendComment() {
  if (saving.value || !comment.value.trim()) return;
  saving.value = true;
  error.value = '';
  const payload = { id: props.issue.id, content: comment.value, expectedRevision: props.issue.revision };
  const signature = JSON.stringify(payload);
  if (commentRequest?.signature !== signature) commentRequest = { signature, requestId: crypto.randomUUID() };
  try {
    const result = await api.addComment({ ...payload, requestId: commentRequest.requestId });
    comment.value = '';
    commentRequest = null;
    emit('saved', result, false);
  } catch (failure) {
    error.value = failure.message;
    conflict.value = failure.code === 'REVISION_CONFLICT';
  } finally { saving.value = false; }
}
function reload() {
  if (inputDisabled.value) return;
  if (dirty.value && !window.confirm('重新載入會捨棄表單修改, 要繼續嗎？')) return;
  saving.value = true;
  emit('reload', () => resetForm(), () => { saving.value = false; });
}
function display(field, value) {
  if (field === 'relatedIssueIds' || field === 'blockedBy') return (value || []).map((id) => props.issues.find((issue) => issue.id === id)?.identifier || id).join('、') || '無';
  const options = { state: states.value, priority: PRIORITIES, type: TYPES }[field];
  if (field === 'assignee') return props.members.find((member) => member.id === value)?.name || value || '未指派';
  if (field === 'epicId' || field === 'parentIssueId') return props.issues.find((issue) => issue.id === value)?.identifier || value || '未連結';
  return options?.find((item) => item.id === value)?.label || value || '未設定';
}
function timestamp(value) {
  return new Intl.DateTimeFormat('zh-TW', { timeZone: 'Asia/Taipei', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(value));
}
</script>

<template>
  <dialog ref="dialog" class="issue-dialog" aria-labelledby="issue-heading" @cancel.prevent="close" @click="event => { if (event.target === dialog) close(); }">
    <div class="panel-shell">
      <header class="panel-header">
        <div class="eyebrow"><Icon name="diamond" :size="16" /> {{ issue?.identifier || 'NEW ISSUE' }} <span class="subtle">/ {{ issue ? '任務詳細內容' : '建立任務' }}</span></div>
        <button v-if="issue && canExpand" type="button" class="button secondary issue-expand" :disabled="inputDisabled" @click="emit('expand')"><Icon name="arrow" :size="16" />開啟完整頁</button>
        <button type="button" class="icon-button" aria-label="關閉任務" :disabled="inputDisabled" @click="close"><Icon name="close" /></button>
      </header>
      <div class="panel-content">
        <h2 id="issue-heading">{{ todo ? '轉成正式卡片' : issue ? '任務詳細內容' : '新增一個任務' }}</h2>

        <div v-if="error" class="error-box" role="alert">{{ error }} <button v-if="conflict" type="button" class="text-button" :disabled="inputDisabled" @click="reload">重新載入最新版本</button></div>
        <form id="issue-form" @submit.prevent="save">
          <label class="field">任務標題 <span class="required">*</span><input v-model="form.title" name="title" placeholder="輸入任務標題…" maxlength="180" required :disabled="inputDisabled" /></label>
          <MarkdownEditor v-model="form.description" label="描述" name="description" placeholder="輸入描述…" :rows="5" :maxlength="6000" :disabled="inputDisabled" />
          <div class="form-grid">
            <PagedSelect autoload v-if="projectOptionsLoader" v-model="form.projectId" label="專案" :options="availableProjects" :context="todo?.id || 'new'" :load="projectOptionsLoader" :disabled="inputDisabled" empty-label="選擇所屬專案" required @choose="item => { pickedProjects = [...pickedProjects.filter(project=>project.id!==item.id),item]; }" /><label v-else class="field">專案<select v-model="form.projectId" aria-label="專案" :disabled="!todo || saving" required><option v-if="todo" value="" disabled>選擇所屬專案</option><option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option></select></label>
            <label class="field">狀態<select v-model="form.state" aria-label="狀態" :disabled="inputDisabled"><option v-for="state in states" :key="state.id" :value="state.id">{{ state.label }}</option></select></label>
            <label class="field">類型<select v-model="form.type" aria-label="類型" :disabled="inputDisabled"><option v-for="type in TYPES" :key="type.id" :value="type.id">{{ type.label }}</option></select></label>
            <label class="field">優先級<select v-model="form.priority" aria-label="優先級" :disabled="inputDisabled"><option v-for="priority in PRIORITIES" :key="priority.id" :value="priority.id">{{ priority.label }}</option></select></label>
            <PagedSelect v-if="lookup" v-model="form.assignee" label="負責人" :options="availableMembers" :context="form.projectId" :load="(filters, options) => lookupForProject('member', filters, options)" :disabled="inputDisabled" empty-label="未指派" @choose="item => { pickedMembers = [...pickedMembers.filter(row => row.id !== item.id), item]; }" /><label v-else class="field">負責人<select v-model="form.assignee" aria-label="負責人" :disabled="inputDisabled"><option value="">未指派</option><option v-for="member in availableMembers" :key="member.id" :value="member.id">{{ member.name }}</option><option v-if="form.assignee && !availableMembers.some(member => member.id === form.assignee)" :value="form.assignee">{{ form.assignee }}（舊紀錄）</option></select></label>
            <label class="field">到期日<input v-model="form.dueDate" type="date" :disabled="inputDisabled" /></label>
            <PagedSelect v-if="lookup && form.type !== 'epic'" v-model="form.epicId" label="所屬 Epic" :options="epics" :context="form.projectId" :load="(filters, options) => lookupForProject('epic', filters, options)" :disabled="inputDisabled || Boolean(form.parentIssueId)" empty-label="不屬於 Epic" @choose="remember" /><label v-else-if="form.type !== 'epic'" class="field">所屬 Epic<select v-model="form.epicId" aria-label="所屬 Epic" :disabled="inputDisabled || Boolean(form.parentIssueId)"><option value="">不屬於 Epic</option><option v-for="epic in epics" :key="epic.id" :value="epic.id">{{ epic.identifier }} · {{ epic.title }}</option></select></label>
            <label v-if="form.type !== 'epic'" class="field">預估小時<input v-model="estimateHours" type="number" min="0" max="8760" step="any" :disabled="inputDisabled" /></label>
          </div>
        </form>
        <IssueRelations v-if="selectedProject" ref="relationsTool" :lookup="lookup ? lookupForProject : undefined" :activity-loader="activityLoader" :issue="issue" :issues="availableIssues" :project="selectedProject" :type="form.type" :parent-id="form.parentIssueId" :related-ids="form.relatedIssueIds" :saving="inputDisabled" @candidate="remember" @parent="form.parentIssueId = $event" @related="form.relatedIssueIds = $event" @open="jump" @create-child="createChild" />
        <p v-if="issue && form.type !== 'epic'" class="detail-spent"><Icon name="clock" :size="14" />已投入 {{ hours(spent) }} / {{ form.estimateMinutes ? `預估 ${hours(form.estimateMinutes)}` : '尚未估時' }}</p>
        <section v-if="issue" class="discussion">
          <div class="discussion-tabs" role="tablist" aria-label="任務紀錄" @keydown="onTabKeydown">
            <button :class="{ active: tab === 'comments' }" :id="`${tabId}-comments-tab`" role="tab" :aria-controls="`${tabId}-activity`" :tabindex="tab === 'comments' ? 0 : -1" :aria-selected="tab === 'comments'" @click="tab = 'comments'">留言 <span>{{ activityComments.length }}</span></button>
            <button :class="{ active: tab === 'history' }" :id="`${tabId}-history-tab`" role="tab" :aria-controls="`${tabId}-activity`" :tabindex="tab === 'history' ? 0 : -1" :aria-selected="tab === 'history'" @click="tab = 'history'">異動紀錄 <span>{{ activityHistory.length }}</span></button>
          </div>
          <div :id="`${tabId}-activity`" role="tabpanel" :aria-labelledby="`${tabId}-${tab}-tab`" tabindex="0">
          <div v-if="tab === 'comments'" class="comments">
            <p v-if="!activityComments.length" class="quiet-message">尚無留言</p>
            <article v-for="entry in activityComments" :key="entry.id" class="comment">
              <div class="avatar small">{{ entry.author.slice(0, 1) }}</div><div><div class="comment-meta"><strong>{{ entry.author }}</strong><time>{{ timestamp(entry.createdAt) }}</time></div><MarkdownContent :source="entry.content" /></div>
            </article>
            <form @submit.prevent="sendComment" class="comment-form">
              <MarkdownEditor v-model="comment" label="新增留言" placeholder="輸入留言…" :maxlength="2000" :rows="3" :disabled="inputDisabled" />
              <button class="button secondary" :disabled="inputDisabled || !comment.trim()">送出留言 <Icon name="arrow" :size="16" /></button>
            </form>
          </div>
          <div v-else class="history-list">
            <article v-for="entry in [...activityHistory].reverse()" :key="entry.id" class="history-entry">
              <span class="history-dot"></span><div><p><strong>{{ entry.actor }}</strong> {{ entry.kind === 'created' ? '建立了任務' : '更新了任務' }} <time>{{ timestamp(entry.at) }}</time></p><p v-for="change in entry.changes" :key="change.field" class="history-change">{{ FIELD_LABELS[change.field] || change.field }}：{{ display(change.field, change.before) }} → {{ display(change.field, change.after) }}</p></div>
            </article>
          </div>
          </div>
        </section>
      </div>
      <footer class="panel-footer"><span class="subtle">{{ dirty ? '有尚未儲存的修改' : issue ? `版本 ${issue.revision} · 已儲存` : '' }}</span><button type="submit" form="issue-form" class="button primary" :disabled="inputDisabled || (Boolean(issue) && !dirty) || (Boolean(todo) && !form.projectId)"><Icon :name="saving ? 'clock' : 'check'" :size="17" />{{ saving ? '儲存中…' : todo ? '建立卡片並完成轉換' : issue ? '儲存修改' : '建立任務' }}</button></footer>
    </div>
    <div v-if="activityLoader && ['comments','history'].includes(tab)" class="activity-pagination">
    <p v-if="activity.error.value" class="error-box" role="alert">{{ activity.error.value }}</p>
    <button v-if="activity.more.value" class="button secondary" :disabled="activity.loading.value" @click="activity.fetchPage(true)">載入更多</button>
  </div>

  </dialog>
</template>
