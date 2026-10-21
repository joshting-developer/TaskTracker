<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import Icon from './Icon.vue';
import IssueRelations from './IssueRelations.vue';
import { api } from '../api/index.js';
import { FIELD_LABELS, hours, ISSUE_FIELDS, PRIORITIES, statusesFor, TYPES } from '../domain/model.js';

const props = defineProps({ issue: Object, projects: Array, defaultProject: String, defaultState: String, defaultType: String, defaultEpicId: String, defaultBoardId: String, members: Array, issues: Array, entries: Array, canExpand: Boolean });
const emit = defineEmits(['close', 'saved', 'reload', 'open', 'expand']);
const dialog = ref(null);
const form = reactive({ parentIssueId: '', relatedIssueIds: [] });
const relationsTool = ref(null);
const tab = ref('comments');
const comment = ref('');
const saving = ref(false);
const error = ref('');
const conflict = ref(false);
const savedValues = ref('');
const baseRevision = ref(0);
let childRequest = null;
let pending = null;
let commentRequest = null;

function values() {
  return { ...Object.fromEntries(ISSUE_FIELDS.map((key) => [key, form[key]])), projectId: form.projectId };
}
function resetForm() {
  baseRevision.value = props.issue?.revision || 0;
  Object.assign(form, {
    title: '', description: '', state: props.defaultState || 'todo', priority: 'normal', type: props.defaultType || 'task', assignee: '', dueDate: '', epicId: props.defaultEpicId || '', estimateMinutes: 0, parentIssueId: '', relatedIssueIds: [],
    ...(props.issue || {}), projectId: props.issue?.projectId || props.defaultProject || props.projects[0]?.id || '',
  });
  savedValues.value = JSON.stringify(values());
  error.value = '';
  conflict.value = false;
  pending = null;
}
const states = computed(() => statusesFor(props.projects[0]));
const epics = computed(() => props.issues.filter((issue) => issue.type === 'epic' && issue.id !== props.issue?.id));
const parentIssue = computed(() => props.issues.find(item => item.id === form.parentIssueId));
watch(() => form.parentIssueId, () => { if (parentIssue.value) form.epicId = parentIssue.value.epicId; });
const estimateHours = computed({ get: () => (form.estimateMinutes || 0) / 60, set: (value) => { form.estimateMinutes = Math.round(Number(value) * 60); } });
watch(() => form.type, (type) => { if (type === 'epic') { form.epicId = ''; form.estimateMinutes = 0; form.parentIssueId = ''; } });
const spent = computed(() => props.entries.filter((entry) => !entry.voidedAt && entry.issueId === props.issue?.id).reduce((sum, entry) => sum + entry.minutes, 0));
const dirty = computed(() => JSON.stringify(values()) !== savedValues.value);
defineExpose({ dirty: computed(() => dirty.value || Boolean(comment.value.trim()) || Boolean(relationsTool.value?.dirty)), saving });
watch(() => props.issue, () => { if (!dirty.value) resetForm(); });
function close() {
  if (saving.value) return;
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
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));

async function save() {
  if (saving.value) return;
  saving.value = true;
  error.value = '';
  const payload = { ...values(), ...(props.issue ? { id: props.issue.id, expectedRevision: baseRevision.value } : { boardId: props.defaultBoardId || 'default' }) };
  const signature = JSON.stringify(payload);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  try {
    const result = await api[props.issue ? 'updateIssue' : 'createIssue']({ ...payload, requestId: pending.requestId });
    savedValues.value = JSON.stringify(values());
    baseRevision.value = result.revision;
    pending = null;
    emit('saved', result, !props.issue);
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
  const payload = { projectId: form.projectId, title, parentIssueId: props.issue.id, state: states.value.find(state => state.category === 'active')?.id || states.value[0].id };
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
  if (dirty.value && !window.confirm('重新載入會捨棄表單修改, 要繼續嗎？')) return;
  emit('reload', () => resetForm());
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
        <button v-if="issue && canExpand" type="button" class="button secondary issue-expand" :disabled="saving" @click="emit('expand')"><Icon name="arrow" :size="16" />開啟完整頁</button>
        <button type="button" class="icon-button" aria-label="關閉任務" :disabled="saving" @click="close"><Icon name="close" /></button>
      </header>
      <div class="panel-content">
        <h2 id="issue-heading">{{ issue ? '任務詳細內容' : '新增一個任務' }}</h2>
        <p class="form-intro">{{ issue ? '更新進度, 留下討論, 把下一步寫清楚' : '先寫下要做的事, 細節可以慢慢補上' }}</p>
        <div v-if="error" class="error-box" role="alert">{{ error }} <button v-if="conflict" type="button" class="text-button" :disabled="saving" @click="reload">重新載入最新版本</button></div>
        <form id="issue-form" @submit.prevent="save">
          <label class="field">任務標題 <span class="required">*</span><input v-model="form.title" name="title" placeholder="要完成什麼？" maxlength="180" required :disabled="saving" /></label>
          <label class="field">描述<textarea v-model="form.description" name="description" placeholder="寫下背景、下一步或完成條件…" rows="5" maxlength="6000" :disabled="saving" /></label>
          <div class="form-grid">
            <label class="field">專案<select v-model="form.projectId" aria-label="專案" disabled required><option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option></select></label>
            <label class="field">狀態<select v-model="form.state" aria-label="狀態" :disabled="saving"><option v-for="state in states" :key="state.id" :value="state.id">{{ state.label }}</option></select></label>
            <label class="field">類型<select v-model="form.type" aria-label="類型" :disabled="saving"><option v-for="type in TYPES" :key="type.id" :value="type.id">{{ type.label }}</option></select></label>
            <label class="field">優先級<select v-model="form.priority" aria-label="優先級" :disabled="saving"><option v-for="priority in PRIORITIES" :key="priority.id" :value="priority.id">{{ priority.label }}</option></select></label>
            <label class="field">負責人<select v-model="form.assignee" aria-label="負責人" :disabled="saving"><option value="">未指派</option><option v-for="member in members" :key="member.id" :value="member.id">{{ member.name }}</option><option v-if="form.assignee && !members.some(member => member.id === form.assignee)" :value="form.assignee">{{ form.assignee }}（舊紀錄）</option></select></label>
            <label class="field">到期日<input v-model="form.dueDate" type="date" :disabled="saving" /></label>
            <label v-if="form.type !== 'epic'" class="field">所屬 Epic<select v-model="form.epicId" aria-label="所屬 Epic" :disabled="saving || Boolean(form.parentIssueId)"><option value="">不屬於 Epic</option><option v-for="epic in epics" :key="epic.id" :value="epic.id">{{ epic.identifier }} · {{ epic.title }}</option></select></label>
            <label v-if="form.type !== 'epic'" class="field">預估小時<input v-model="estimateHours" type="number" min="0" max="8760" step="any" :disabled="saving" /></label>
            <p v-else class="subtle epic-form-note">Epic 的完成率、預估與實際工時由子任務加總, 可到 Epic 概覽查看</p>
          </div>
        </form>
        <IssueRelations ref="relationsTool" :issue="issue" :issues="issues" :project="projects[0]" :type="form.type" :parent-id="form.parentIssueId" :related-ids="form.relatedIssueIds" :saving="saving" @parent="form.parentIssueId = $event" @related="form.relatedIssueIds = $event" @open="jump" @create-child="createChild" />
        <p v-if="issue && form.type !== 'epic'" class="detail-spent"><Icon name="clock" :size="14" />已投入 {{ hours(spent) }} / {{ form.estimateMinutes ? `預估 ${hours(form.estimateMinutes)}` : '尚未估時' }}</p>
        <section v-if="issue" class="discussion">
          <div class="discussion-tabs" role="tablist" aria-label="任務紀錄">
            <button :class="{ active: tab === 'comments' }" role="tab" :aria-selected="tab === 'comments'" @click="tab = 'comments'">留言 <span>{{ issue.comments.length }}</span></button>
            <button :class="{ active: tab === 'history' }" role="tab" :aria-selected="tab === 'history'" @click="tab = 'history'">異動紀錄 <span>{{ issue.history.length }}</span></button>
          </div>
          <div v-if="tab === 'comments'" class="comments">
            <p v-if="!issue.comments.length" class="quiet-message">還沒有留言, 留下你的下一步吧</p>
            <article v-for="entry in issue.comments" :key="entry.id" class="comment">
              <div class="avatar small">{{ entry.author.slice(0, 1) }}</div><div><div class="comment-meta"><strong>{{ entry.author }}</strong><time>{{ timestamp(entry.createdAt) }}</time></div><p>{{ entry.content }}</p></div>
            </article>
            <form @submit.prevent="sendComment" class="comment-form">
              <label class="field"><span class="sr-only">新增留言</span><textarea v-model="comment" placeholder="留下想法或進度…" maxlength="2000" rows="3" :disabled="saving" /></label>
              <button class="button secondary" :disabled="saving || !comment.trim()">送出留言 <Icon name="arrow" :size="16" /></button>
            </form>
          </div>
          <div v-else class="history-list">
            <article v-for="entry in [...issue.history].reverse()" :key="entry.id" class="history-entry">
              <span class="history-dot"></span><div><p><strong>{{ entry.actor }}</strong> {{ entry.kind === 'created' ? '建立了任務' : '更新了任務' }} <time>{{ timestamp(entry.at) }}</time></p><p v-for="change in entry.changes" :key="change.field" class="history-change">{{ FIELD_LABELS[change.field] || change.field }}：{{ display(change.field, change.before) }} → {{ display(change.field, change.after) }}</p></div>
            </article>
          </div>
        </section>
      </div>
      <footer class="panel-footer"><span class="subtle">{{ dirty ? '有尚未儲存的修改' : issue ? `版本 ${issue.revision} · 已儲存` : '新增後即可在清單與看板查看' }}</span><button type="submit" form="issue-form" class="button primary" :disabled="saving || (Boolean(issue) && !dirty)"><Icon :name="saving ? 'clock' : 'check'" :size="17" />{{ saving ? '儲存中…' : issue ? '儲存修改' : '建立任務' }}</button></footer>
    </div>
  </dialog>
</template>
