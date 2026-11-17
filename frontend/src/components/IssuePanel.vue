<script setup>
import { displayTimeZone, locale, t } from '../i18n/index.js';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, useId, watch } from 'vue';
import { toRef } from 'vue';
import { useIssueActivity } from '../composables/useIssueActivity.js';
import { onTabKeydown } from '../ui/tabs.js';
import Icon from './Icon.vue';
import IssueTagsEditor from './IssueTagsEditor.vue';
import PagedSelect from './PagedSelect.vue';
import IssueRelations from './IssueRelations.vue';
import MarkdownEditor from './MarkdownEditor.vue';
import MarkdownContent from './MarkdownContent.vue';
import { api } from '../api/index.js';
import UploadTray from './UploadTray.vue';
import { FIELD_LABELS, hours, ISSUE_FIELDS, PRIORITIES, statusesFor, TYPES } from '../domain/model.js';

const props = defineProps({ projectLoader: Function, projectOptionsLoader: Function, lookup: Function, activityLoader: Function, issue: Object, todo: Object, projects: Array, defaultProject: String, defaultState: String, defaultType: String, defaultEpicId: String, defaultBoardId: String, members: Array, issues: Array, entries: Array, canExpand: Boolean, inline: Boolean });
const emit = defineEmits(['close', 'saved', 'converted', 'reload', 'open', 'expand']);
const tagsTool = ref(null);
const dialog = ref(null), descriptionEditor = ref(null), saveError = ref(null);
const propertiesOpen = ref(true), titleError = ref('');
const formId = useId();
const form = reactive({ parentIssueId: '', relatedIssueIds: [] });
const relationsTool = ref(null), picked = ref([]), pickedMembers = ref([]), pickedProjects = ref([]), hydratedProject = ref(null), projectLoading = ref(false);
let projectRequest, projectGeneration=0;
function lookupForProject(kind, filters, options) { return props.lookup(kind, filters, options, form.projectId); }
function remember(item) { picked.value = [...picked.value.filter(row => row.id !== item.id), item]; }
const tab = ref('comments');

const activity = useIssueActivity({ issue: toRef(props, 'issue'), tab: computed(() => props.inline ? '' : tab.value), load: toRef(props, 'activityLoader') });
const activityComments = activity.comments, activityHistory = activity.history, activityCounts = activity.counts;
const tabId = useId();
const comment = ref('');
const commentEditor = ref(null), commentEditorKey = ref(0);
const saving = ref(false);
const error = ref('');
const conflict = ref(false);
const savedValues = ref('');
const baseRevision = ref(0);
const todoRevision = ref(0);
let childRequest = null;
let pending = null;
// A new issue that saved while some files failed to attach; saving again only retries the attach.
const created = ref(null);
let commentRequest = null;

function values() {
  return { ...Object.fromEntries(ISSUE_FIELDS.map((key) => [key, form[key]])), projectId: form.projectId };
}
function resetForm() {
  baseRevision.value = props.issue?.revision || 0;
  todoRevision.value = props.todo?.revision || 0;
  Object.assign(form, {
    title: '', description: '', state: props.defaultState || 'todo', priority: 'normal', type: props.defaultType || 'task', assignee: '', startDate: '', endDate: '', dueDate: '', epicId: props.defaultEpicId || '', estimateMinutes: 0, parentIssueId: '', relatedIssueIds: [],
    ...(props.todo ? { title: props.todo.title, description: props.todo.content ?? props.todo.note } : {}),
    ...(props.issue || {}), projectId: props.issue?.projectId || props.defaultProject || (props.todo ? '' : props.projects[0]?.id) || '',
  });
  // 詳細DTO的Epic估時是彙總顯示值；編輯基準須先採寫入允許的值，避免初始化即誤判草稿。
  if (form.type === 'epic') { form.epicId = ''; form.estimateMinutes = 0; form.parentIssueId = ''; }
  savedValues.value = JSON.stringify(values());
  error.value = '';
  conflict.value = false;
  pending = null; titleError.value = '';
}
const projectModel = computed({ get: () => form.projectId, set: value => {
  if (value === form.projectId) return;
  if (descriptionEditor.value?.attachmentDirty || /attachment:[a-f0-9-]+/i.test(form.description || '')) { conflict.value = false; error.value = t('描述仍有圖片或未完成上傳, 請先處理圖片再切換專案'); nextTick(() => saveError.value?.focus()); return; }
  form.projectId = value; error.value = '';
} });
const availableProjects = computed(() => [...new Map([...props.projects, ...pickedProjects.value].map(project=>[project.id,project])).values()]);
const selectedProject = computed(() => hydratedProject.value?.id === form.projectId ? hydratedProject.value : availableProjects.value.find(project => project.id === form.projectId));
watch(() => form.projectId, async id => {
  if (!props.projectLoader) return;
  const current=++projectGeneration; projectRequest?.abort();projectRequest=new AbortController();hydratedProject.value=null;
  if(!id){projectLoading.value=false;return;} projectLoading.value=true;
  try {const project=await props.projectLoader(id,{signal:projectRequest.signal});if(current!==projectGeneration)return;if(!project.permissions.includes('issue.write'))throw new Error(t('沒有這個專案的任務編輯權限'));hydratedProject.value=project;form.state=project.statuses.find(state=>state.id==='open')?.id||project.statuses[0].id;}
  catch(failure){if(current===projectGeneration&&!['ABORTED','STALE_RESPONSE'].includes(failure.code))error.value=failure.message;}
  finally{if(current===projectGeneration)projectLoading.value=false;}
});
const availableIssues = computed(() => [...new Map([...props.issues, ...picked.value].filter(issue => issue.projectId === form.projectId).map(item => [item.id, item])).values()]);
const availableMembers = computed(() => (props.todo && !props.lookup ? props.members.filter(member => selectedProject.value?.memberIds?.includes(member.id)) : [...new Map([...props.members, ...pickedMembers.value].map(item => [item.id,item])).values()]).filter(member => !member.disabled || member.id === form.assignee));
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
const propertySummary = computed(() => [
  TYPES.find(item => item.id === form.type)?.label || form.type,
  states.value.find(item => item.id === form.state)?.label,
  availableMembers.value.find(item => item.id === form.assignee)?.name || (form.assignee ? t('已指派') : t('未指派')),
].filter(Boolean).join(' · '));
const saveStatus = computed(() => {
  if (saving.value) return t('儲存中…');
  if (descriptionEditor.value?.uploadBusy) return t('附件上傳中…');
  if (error.value) return t('操作未完成, 修改已保留');
  if (descriptionEditor.value?.attachmentBlocked) return t('請重試或取消未完成的附件');
  if (dirty.value) return t('尚未儲存');
  return props.issue ? t('版本 {0} · 已儲存', [baseRevision.value]) : t('尚未建立');
});
function invalidField(event) {
  if (event.target.name === 'title') { titleError.value = t('請輸入任務標題'); return; }
  if (!propertiesOpen.value) {
    event.preventDefault(); error.value = event.target.validationMessage; propertiesOpen.value = true;
    nextTick(() => event.target.focus());
  }
}
watch(() => form.title, () => { titleError.value = ''; });
const inputDisabled = computed(() => saving.value || projectLoading.value);
const dirty = computed(() => JSON.stringify(values()) !== savedValues.value || Boolean(descriptionEditor.value?.attachmentDirty));
defineExpose({ dirty: computed(() => Boolean(tagsTool.value?.dirty) || dirty.value || Boolean(comment.value.trim()) || Boolean(commentEditor.value?.attachmentDirty) || Boolean(relationsTool.value?.dirty)), saving: computed(() => Boolean(tagsTool.value?.saving) || inputDisabled.value || Boolean(descriptionEditor.value?.uploadBusy) || Boolean(commentEditor.value?.uploadBusy)) });
watch(() => props.issue, () => { if (!dirty.value) resetForm(); });
function close() {
  if (inputDisabled.value) return;
  if ((dirty.value || comment.value.trim() || commentEditor.value?.attachmentDirty || relationsTool.value?.dirty) && !window.confirm(t('還有未儲存的內容, 確定要關閉嗎？'))) return;
  if (created.value) emit('saved', created.value, true); else emit('close');
}
function jump(issue) {
  if (saving.value || ((dirty.value || comment.value.trim() || commentEditor.value?.attachmentDirty || relationsTool.value?.dirty) && !window.confirm(t('還有未儲存的內容, 確定要切換任務嗎？')))) return;
  emit('open', issue);
}
function beforeUnload(event) {
  if (dirty.value || comment.value.trim() || commentEditor.value?.attachmentDirty || relationsTool.value?.dirty) { event.preventDefault(); event.returnValue = ''; }
}
onMounted(async () => {
  resetForm();
  if (!props.inline) dialog.value.showModal();
  window.addEventListener('beforeunload', beforeUnload);
  await nextTick();
  dialog.value.querySelector('[name="title"]').focus();
});
onBeforeUnmount(() => { projectGeneration++;projectRequest?.abort();window.removeEventListener('beforeunload', beforeUnload); });

/** Attach description files after the issue write, so binding never races the form's own revision. */
async function attachFiles(issue) {
  const attached = descriptionEditor.value?.filesPending ? await descriptionEditor.value.bindFiles(issue.id, issue.revision) : { revision: issue.revision, failed: 0 };
  return { ...issue, revision: attached.revision, failed: attached.failed };
}
async function save() {
  if (inputDisabled.value || descriptionEditor.value?.attachmentBlocked) return;
  if (!form.title?.trim()) { titleError.value = t('請輸入任務標題'); await nextTick(); dialog.value.querySelector('[name=title]')?.focus(); return; }
  if (props.projectLoader && !hydratedProject.value?.permissions.includes('issue.write')) { error.value=t('請先選擇可編輯的專案'); return; }
  saving.value = true;
  error.value = '';
  const fields = { ...values(), ...(props.issue ? { id: props.issue.id, expectedRevision: baseRevision.value } : { boardId: props.defaultBoardId || 'default' }) };
  const payload = props.todo ? { id: props.todo.id, expectedRevision: todoRevision.value, issue: fields } : fields;
  const signature = JSON.stringify(payload);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  try {
    const fieldsChanged = JSON.stringify(values()) !== savedValues.value;
    const written = created.value || (props.issue && !fieldsChanged ? { id: props.issue.id, revision: baseRevision.value } : await api[props.todo ? 'convertTodo' : props.issue ? 'updateIssue' : 'createIssue']({ ...payload, requestId: pending.requestId }));
    savedValues.value = JSON.stringify(values());
    pending = null;
    if (props.todo) { baseRevision.value = written.revision; emit('converted', written); }
    else {
      const { failed, ...result } = await attachFiles(written);
      baseRevision.value = result.revision;
      if (!failed) { created.value = null; emit('saved', result, !props.issue); }
      else {
        // Keep the panel open: closing a new issue here would drop the files that still need attaching.
        if (!props.issue) created.value = result; else emit('saved', result, false);
        error.value = t('任務已儲存, 但有 {0} 個檔案尚未附加, 可再按儲存重試', [failed]);
      }
    }
  } catch (failure) {
    error.value = failure.message;
    conflict.value = failure.code === 'REVISION_CONFLICT';
  } finally { saving.value = false; }
  if (error.value) { await nextTick(); saveError.value?.focus(); }
}
async function createChild(title) {
  if (saving.value || !title.trim()) return;
  if (dirty.value) { error.value = t('請先儲存目前任務的修改, 再建立子任務'); return; }
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
  const files = commentEditor.value?.filesPending || 0;
  if (saving.value || commentEditor.value?.attachmentBlocked || !(comment.value.trim() || files)) return;
  saving.value = true;
  error.value = '';
  try {
    let result = { id: props.issue.id, revision: props.issue.revision };
    if (comment.value.trim()) {
      const payload = { id: props.issue.id, content: comment.value, expectedRevision: props.issue.revision };
      const signature = JSON.stringify(payload);
      if (commentRequest?.signature !== signature) commentRequest = { signature, requestId: crypto.randomUUID() };
      result = await api.addComment({ ...payload, requestId: commentRequest.requestId });
      comment.value = ''; commentRequest = null;
    }
    // Comment files can only attach to the issue; keep the editor while any still needs a retry.
    const attached = files ? await commentEditor.value.bindFiles(props.issue.id, result.revision) : { revision: result.revision, failed: 0 };
    if (attached.failed) error.value = t('有 {0} 個檔案尚未附到任務, 可再按送出重試', [attached.failed]);
    else commentEditorKey.value++;
    emit('saved', { ...result, revision: attached.revision }, false);
  } catch (failure) {
    error.value = failure.message;
    conflict.value = failure.code === 'REVISION_CONFLICT';
  } finally { saving.value = false; }
}
function reload() {
  if (inputDisabled.value) return;
  if (dirty.value && !window.confirm(t('重新載入會捨棄表單修改, 要繼續嗎？'))) return;
  saving.value = true;
  emit('reload', () => resetForm(), () => { saving.value = false; });
}
function display(field, value) {
  if (field === 'tags') return (value || []).map(tag => tag.name || tag.id).join('、') || t('無');
  if (field === 'relatedIssueIds' || field === 'blockedBy') return (value || []).map((id) => props.issues.find((issue) => issue.id === id)?.identifier || id).join('、') || t('無');
  const options = { state: states.value, priority: PRIORITIES, type: TYPES }[field];
  if (field === 'assignee') return props.members.find((member) => member.id === value)?.name || value || t('未指派');
  if (field === 'epicId' || field === 'parentIssueId') return props.issues.find((issue) => issue.id === value)?.identifier || value || t('未連結');
  return options?.find((item) => item.id === value)?.label || value || t('未設定');
}
function timestamp(value) {
  return new Intl.DateTimeFormat(locale.value, { timeZone: displayTimeZone.value, month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(value));
}
</script>

<template>
  <component :is="inline ? 'section' : 'dialog'" ref="dialog" class="issue-dialog" :class="{ 'issue-editor-inline': inline }" :aria-label="inline ? t('編輯任務完整頁') : undefined" :aria-labelledby="inline ? undefined : 'issue-heading'" @cancel.prevent="close" @click="event => { if (!inline && event.target === dialog) close(); }">
    <div class="panel-shell">
      <header class="panel-header">
        <div class="panel-identity"><span>{{ issue?.identifier || selectedProject?.code }}</span><component :is="inline ? 'h1' : 'h2'" id="issue-heading">{{ inline ? t('編輯任務') : todo ? t('轉成正式卡片') : issue ? t('任務詳細內容') : t('新增一個任務') }}</component></div>
        <button v-if="issue && canExpand && !inline" type="button" class="button secondary issue-expand" :disabled="inputDisabled" @click="emit('expand')"><Icon name="arrow" :size="16" />{{ t('開啟完整頁') }}</button>
        <button v-if="inline" type="button" class="button secondary" :disabled="inputDisabled" @click="close">{{ t('取消') }}</button>
        <button v-else type="button" class="icon-button" :aria-label="t('關閉任務')" :disabled="inputDisabled" @click="close"><Icon name="close" /></button>
      </header>
      <div class="panel-content">

        <div v-if="error" ref="saveError" class="error-box" role="alert" tabindex="-1">{{ t(error) }}<p v-if="conflict">{{ t('目前修改仍保留, 重新載入會捨棄這份修改') }}</p> <button v-if="conflict" type="button" class="text-button" :disabled="inputDisabled" @click="reload">{{ t('重新載入最新版本') }}</button></div>
        <form id="issue-form" @submit.prevent="save" @invalid.capture="invalidField" :aria-busy="saving">
          <label class="field">{{ t('任務標題') }} <span class="required">*</span><input v-model="form.title" name="title" :placeholder="t('輸入任務標題…')" maxlength="180" required :disabled="inputDisabled" :aria-invalid="Boolean(titleError)" :aria-describedby="titleError ? `${formId}-title-error` : undefined" /><span v-if="titleError" :id="`${formId}-title-error`" class="issue-field-error" role="alert">{{ titleError }}</span></label>
          <div v-if="todo" class="issue-conversion-project"><PagedSelect autoload v-if="projectOptionsLoader" v-model="projectModel" :label="t('專案')" :options="availableProjects" :context="todo?.id || 'new'" :load="projectOptionsLoader" :disabled="inputDisabled || !todo" :empty-label="t('選擇所屬專案')" required @choose="item => { pickedProjects = [...pickedProjects.filter(project=>project.id!==item.id),item]; }" /><label v-else class="field">{{ t('專案') }}<select v-model="projectModel" :aria-label="t('專案')" :disabled="!todo || saving" required><option v-if="todo" value="" disabled>{{ t('選擇所屬專案') }}</option><option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option></select></label></div>
          <MarkdownEditor ref="descriptionEditor" spacious allow-attachments :allow-files="!todo" :file-note="t('儲存後附到任務')" v-model="form.description" :project-id="form.projectId" :label="t('描述')" name="description" :placeholder="t('輸入描述…')" :rows="5" :maxlength="6000" :disabled="inputDisabled" />
          <details class="issue-properties" :open="propertiesOpen" @toggle="propertiesOpen = $event.target.open"><summary><span>{{ t('任務屬性') }}</span><span>{{ propertySummary }}</span></summary>
          <div class="form-grid">
            <div v-if="!todo"><PagedSelect autoload v-if="projectOptionsLoader" v-model="projectModel" :label="t('專案')" :options="availableProjects" :context="todo?.id || 'new'" :load="projectOptionsLoader" :disabled="inputDisabled || !todo" :empty-label="t('選擇所屬專案')" required @choose="item => { pickedProjects = [...pickedProjects.filter(project=>project.id!==item.id),item]; }" /><label v-else class="field">{{ t('專案') }}<select v-model="projectModel" :aria-label="t('專案')" :disabled="!todo || saving" required><option v-if="todo" value="" disabled>{{ t('選擇所屬專案') }}</option><option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option></select></label></div>

            <label class="field">{{ t('狀態') }}<select v-model="form.state" :aria-label="t('狀態')" :disabled="inputDisabled"><option v-for="state in states" :key="state.id" :value="state.id">{{ state.label }}</option></select></label>
            <label class="field">{{ t('類型') }}<select v-model="form.type" :aria-label="t('類型')" :disabled="inputDisabled"><option v-for="type in TYPES" :key="type.id" :value="type.id">{{ t(type.label) }}</option></select></label>
            <label class="field">{{ t('優先級') }}<select v-model="form.priority" :aria-label="t('優先級')" :disabled="inputDisabled"><option v-for="priority in PRIORITIES" :key="priority.id" :value="priority.id">{{ t(priority.label) }}</option></select></label>
            <PagedSelect v-if="lookup" v-model="form.assignee" :label="t('負責人')" :options="availableMembers" :context="form.projectId" :load="(filters, options) => lookupForProject('member', filters, options)" :disabled="inputDisabled" :empty-label="t('未指派')" @choose="item => { pickedMembers = [...pickedMembers.filter(row => row.id !== item.id), item]; }" /><label v-else class="field">{{ t('負責人') }}<select v-model="form.assignee" :aria-label="t('負責人')" :disabled="inputDisabled"><option value="">{{ t('未指派') }}</option><option v-for="member in availableMembers" :key="member.id" :value="member.id">{{ member.name }}</option><option v-if="form.assignee && !availableMembers.some(member => member.id === form.assignee)" :value="form.assignee">{{ form.assignee }}{{ t('（舊紀錄）') }}</option></select></label>
            <label class="field">{{ t('計畫開始日') }}<input v-model="form.startDate" type="date" min="1000-01-01" max="9999-12-31" :disabled="inputDisabled" /></label>
            <label class="field">{{ t('計畫結束日') }}<input v-model="form.endDate" type="date" :min="form.startDate || '1000-01-01'" max="9999-12-31" :disabled="inputDisabled" /></label>
            <label class="field">{{ t('到期日') }}<input v-model="form.dueDate" type="date" :disabled="inputDisabled" /></label>
            <PagedSelect v-if="lookup && form.type !== 'epic'" v-model="form.epicId" :label="t('所屬 Epic')" :options="epics" :context="form.projectId" :load="(filters, options) => lookupForProject('epic', filters, options)" :disabled="inputDisabled || Boolean(form.parentIssueId)" :empty-label="t('不屬於 Epic')" @choose="remember" /><label v-else-if="form.type !== 'epic'" class="field">{{ t('所屬 Epic') }}<select v-model="form.epicId" :aria-label="t('所屬 Epic')" :disabled="inputDisabled || Boolean(form.parentIssueId)"><option value="">{{ t('不屬於 Epic') }}</option><option v-for="epic in epics" :key="epic.id" :value="epic.id">{{ epic.identifier }} · {{ epic.title }}</option></select></label>
            <label v-if="form.type !== 'epic'" class="field">{{ t('預估小時') }}<input v-model="estimateHours" type="number" min="0" max="8760" step="any" :disabled="inputDisabled" /></label>
          </div></details>
        </form>
        <IssueRelations v-if="selectedProject" ref="relationsTool" :lookup="lookup ? lookupForProject : undefined" :activity-loader="activityLoader" :issue="issue" :issues="availableIssues" :project="selectedProject" :type="form.type" :parent-id="form.parentIssueId" :related-ids="form.relatedIssueIds" :saving="inputDisabled" @candidate="remember" @parent="form.parentIssueId = $event" @related="form.relatedIssueIds = $event" @open="jump" @create-child="createChild" />
        <p v-if="issue && form.type !== 'epic'" class="detail-spent"><Icon name="clock" :size="14" />{{ t('已投入') }} {{ hours(spent) }} / {{ form.estimateMinutes ? t('預估 {0}', [hours(form.estimateMinutes)]) : t('尚未估時') }}</p>
        <section v-if="issue && !inline" class="discussion">
          <div class="discussion-tabs" role="tablist" :aria-label="t('任務紀錄')" @keydown="onTabKeydown">
            <button :class="{ active: tab === 'comments' }" :id="`${tabId}-comments-tab`" role="tab" :aria-controls="`${tabId}-activity`" :tabindex="tab === 'comments' ? 0 : -1" :aria-selected="tab === 'comments'" @click="tab = 'comments'">{{ t('留言') }} <span v-if="activityCounts.comments !== null">{{ activityCounts.comments }}</span></button>
            <button :class="{ active: tab === 'history' }" :id="`${tabId}-history-tab`" role="tab" :aria-controls="`${tabId}-activity`" :tabindex="tab === 'history' ? 0 : -1" :aria-selected="tab === 'history'" @click="tab = 'history'">{{ t('異動紀錄') }} <span v-if="activityCounts.history !== null">{{ activityCounts.history }}</span></button>
          </div>
          <div :id="`${tabId}-activity`" role="tabpanel" :aria-labelledby="`${tabId}-${tab}-tab`" tabindex="0">
          <div v-if="tab === 'comments'" class="comments">
            <p v-if="!activityComments.length" class="quiet-message">{{ t('尚無留言') }}</p>
            <article v-for="entry in activityComments" :key="entry.id" class="comment">
              <div class="avatar small">{{ entry.author.slice(0, 1) }}</div><div><div class="comment-meta"><strong>{{ entry.author }}</strong><time>{{ timestamp(entry.createdAt) }}</time></div><MarkdownContent :source="entry.content" :project-id="issue.projectId" /></div>
            </article>
            <form @submit.prevent="sendComment" class="comment-form">
              <MarkdownEditor ref="commentEditor" :key="commentEditorKey" allow-attachments allow-files :file-note="t('送出後附到任務')" :project-id="issue.projectId" v-model="comment" :label="t('新增留言')" :placeholder="t('輸入留言…')" :maxlength="2000" :rows="3" :disabled="inputDisabled" />
              <button class="button secondary" :disabled="inputDisabled || commentEditor?.attachmentBlocked || !(comment.trim() || commentEditor?.filesPending)">{{ t('送出留言') }} <Icon name="arrow" :size="16" /></button>
            </form>
          </div>
          <div v-else class="history-list">
            <article v-for="entry in activityHistory" :key="entry.id" class="history-entry">
              <span class="history-dot"></span><div><p><strong>{{ entry.actor }}</strong> {{ entry.kind === 'created' ? t('建立了任務') : t('更新了任務') }} <time>{{ timestamp(entry.at) }}</time></p><p v-for="change in entry.changes" :key="change.field" class="history-change">{{ t(FIELD_LABELS[change.field] || change.field) }}：{{ display(change.field, change.before) }} → {{ display(change.field, change.after) }}</p></div>
            </article>
          </div>
          </div>
        </section>
      </div>
      <footer class="panel-footer"><span class="subtle" role="status">{{ saveStatus }}</span><button type="submit" form="issue-form" class="button primary" :disabled="inputDisabled || descriptionEditor?.attachmentBlocked || (Boolean(issue) && !dirty) || (Boolean(todo) && !form.projectId)"><Icon :name="saving ? 'clock' : 'check'" :size="17" />{{ saving ? t('儲存中…') : todo ? t('建立卡片並完成轉換') : issue ? t('儲存修改') : created ? t('重試附加檔案') : t('建立任務') }}</button></footer>
    </div>
    <div v-if="!inline && activityLoader && ['comments','history'].includes(tab)" class="activity-pagination">
    <p v-if="activity.error.value" class="error-box" role="alert">{{ activity.error.value }}</p>
    <button v-if="activity.more.value" class="button secondary" :disabled="activity.loading.value" @click="activity.fetchPage(true)">{{ t('載入更多') }}</button>
  </div>
  <UploadTray v-if="!inline" />
  </component>
</template>

<style scoped>
.panel-identity { min-width: 0; display: flex; flex-wrap: wrap; gap: 4px 12px; align-items: baseline; }
.panel-identity > span { font-size: 12px; color: var(--text-secondary); }
.panel-identity :is(h1, h2) { font-size: 17px; font-weight: 600; margin: 0; }
.issue-properties { margin: 8px 0 20px; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
.issue-properties > summary { min-height: 48px; padding: 14px 0; cursor: pointer; color: var(--text-primary); font-size: 14px; }
.issue-properties > summary > :last-child { margin-left: 12px; color: var(--text-secondary); font-size: 12px; overflow-wrap: anywhere; }
.issue-properties > .form-grid { padding-top: 12px; }
.issue-field-error { display: block; margin-top: 6px; color: #a52c3d; font-size: 13px; }
.field input[aria-invalid="true"] { border-color: #a52c3d; }
.error-box p { margin: 8px 0; }
.error-box:focus-visible, .issue-properties > summary:focus-visible { outline: 2px solid #6653bd; outline-offset: 3px; }
.panel-footer .button { box-shadow: none; flex-shrink: 0; }
.panel-footer .subtle { min-width: 0; overflow-wrap: anywhere; }
@media(max-width: 500px) { .panel-header { padding: 16px 20px; }.issue-properties > summary > :last-child { display: block; margin: 4px 0 0; }.form-grid { grid-template-columns: 1fr; }.panel-footer { align-items: center; } }
.issue-editor-inline { position: relative; inset: auto; margin: 0; width: 100%; height: auto; max-height: none; box-shadow: none; }
.issue-editor-inline .panel-shell { height: auto; min-height: calc(100dvh - 60px); }
.issue-editor-inline .panel-content { overflow: visible; padding: 24px 32px; }
.issue-editor-inline #issue-form { display: grid; grid-template-columns: minmax(0, 1fr) 280px; grid-template-rows: auto 1fr; gap: 24px 32px; align-items: start; }
.issue-editor-inline #issue-form > .field, .issue-editor-inline #issue-form > .markdown-editor { grid-column: 1; min-width: 0; }
.issue-editor-inline [name="title"] { font-size: 22px; font-weight: 600; }
.issue-editor-inline .issue-properties { grid-column: 2; grid-row: 1 / span 2; margin: 0; padding-left: 24px; border: 0; border-left: 1px solid var(--border); }
.issue-editor-inline .issue-properties > summary { display: none; }
.issue-editor-inline .form-grid { grid-template-columns: 1fr; padding: 0; }
.issue-editor-inline .panel-footer { position: sticky; bottom: 0; z-index: 2; background: #fff; }
@media(max-width: 900px) {
  .issue-editor-inline .panel-content { padding: 20px; }
  .issue-editor-inline #issue-form { grid-template-columns: 1fr; grid-template-rows: auto; gap: 16px; }
  .issue-editor-inline .issue-properties { grid-column: 1; grid-row: auto; border-left: 0; border-top: 1px solid var(--border); padding: 20px 0 0; }
  .issue-editor-inline .form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 16px; }
}
@media(max-width: 500px) { .issue-editor-inline .form-grid { grid-template-columns: 1fr; } }
</style>
