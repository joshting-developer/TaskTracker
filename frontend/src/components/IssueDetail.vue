<script setup>
import { computed, ref, watch } from 'vue';
import { onTabKeydown } from '../ui/tabs.js';
import Icon from './Icon.vue';
import MarkdownEditor from './MarkdownEditor.vue';
import MarkdownContent from './MarkdownContent.vue';
import TimeEntryDrawer from './TimeEntryDrawer.vue';
import { api } from '../api/index.js';
import { FIELD_LABELS, hours, PRIORITIES, statusesFor, TYPES } from '../domain/model.js';

const props = defineProps({ issue: Object, project: Object, issues: Array, members: Array, entries: Array, returnLabel: String, busy: Boolean, canWrite: { type: Boolean, default: true }, canReadTime: { type: Boolean, default: true }, canLogTime: { type: Boolean, default: false }, userId: String });
const emit = defineEmits(['close', 'open', 'edit', 'saved', 'reload', 'state', 'time-updated']);
const tab = ref('comments'), comment = ref(''), saving = ref(false), error = ref(''), conflict = ref(false);
const timeOpen = ref(false), timeDrawer = ref(null), editingTime = ref(null), timeFormKey = ref(0);
const heading = ref(null), propertiesExpanded = ref(false), sidebarTab = ref('properties');
let pending;
watch(() => props.canWrite, allowed => { if (!allowed) { comment.value = ''; pending = null; error.value = ''; conflict.value = false; } });
watch([() => props.canReadTime, () => props.canLogTime], ([read, write]) => {
  if (!read && !write && tab.value === 'time') tab.value = 'comments';
  if (!write) { timeOpen.value = false; editingTime.value = null; }
});
const commentDirty = computed(() => Boolean(comment.value.trim()));
const dirty = computed(() => commentDirty.value || Boolean(timeDrawer.value?.dirty));
const isSaving = computed(() => saving.value || Boolean(timeDrawer.value?.saving));
defineExpose({ dirty, saving: isSaving, focus: () => heading.value?.focus() });
const states = computed(() => statusesFor(props.project));
const state = issue => states.value.find(item => item.id === issue.state);
const memberName = id => props.members.find(member => member.id === id)?.name || id || '未指派';
const children = computed(() => props.issues.filter(issue => props.issue.type === 'epic' ? issue.epicId === props.issue.id : issue.parentIssueId === props.issue.id));
const timeIssues = computed(() => props.issue.type === 'epic' ? [props.issue, ...children.value] : [props.issue]);
const related = computed(() => (props.issue.relatedIssueIds || []).map(id => props.issues.find(issue => issue.id === id)).filter(Boolean));
const epic = computed(() => props.issues.find(issue => issue.id === props.issue.epicId));
const parent = computed(() => props.issues.find(issue => issue.id === props.issue.parentIssueId));
const relationGroups = computed(() => [
  { key: 'epic', label: '所屬 Epic', items: epic.value ? [epic.value] : [] },
  { key: 'parent', label: '父任務', items: parent.value ? [parent.value] : [] },
  { key: 'children', label: props.issue.type === 'epic' ? 'Epic 所屬卡片' : '子任務', items: children.value },
  { key: 'related', label: '關聯任務', items: related.value },
].filter(group => group.items.length));
const work = computed(() => props.entries.filter(entry => !entry.voidedAt && (entry.issueId === props.issue.id || (props.issue.type === 'epic' && children.value.some(issue => issue.id === entry.issueId)))).sort((a, b) => b.workDate.localeCompare(a.workDate)));
const spent = computed(() => work.value.reduce((sum, entry) => sum + entry.minutes, 0));
const estimate = computed(() => props.issue.type === 'epic' ? children.value.reduce((sum, issue) => sum + (issue.estimateMinutes || 0), 0) : props.issue.estimateMinutes);
function timestamp(value) {
  return new Intl.DateTimeFormat('zh-TW', { timeZone: 'Asia/Taipei', year: 'numeric', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(value));
}
function display(field, value) {
  if (field === 'relatedIssueIds' || field === 'blockedBy') return (value || []).map(id => props.issues.find(issue => issue.id === id)?.identifier || id).join('、') || '無';
  if (field === 'assignee') return memberName(value);
  if (field === 'epicId' || field === 'parentIssueId') return props.issues.find(issue => issue.id === value)?.identifier || value || '未連結';
  return ({ state: states.value, priority: PRIORITIES, type: TYPES }[field])?.find(item => item.id === value)?.label || value || '未設定';
}
function changeState(event) {
  const nextState = event.target.value;
  event.target.value = '';
  if (!props.canWrite || saving.value || props.busy || !nextState || nextState === props.issue.state) return;
  emit('state', nextState);
}
async function sendComment() {
  if (!props.canWrite || saving.value || props.busy || !commentDirty.value) return;
  saving.value = true; error.value = ''; conflict.value = false;
  const payload = { id: props.issue.id, content: comment.value, expectedRevision: props.issue.revision };
  const signature = JSON.stringify(payload);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  try {
    const result = await api.addComment({ ...payload, requestId: pending.requestId });
    comment.value = ''; pending = null; emit('saved', result, false);
  } catch (failure) { error.value = failure.message; conflict.value = failure.code === 'REVISION_CONFLICT'; }
  finally { saving.value = false; }
}
function openTime(entry = null) {
  if (!props.canLogTime || isSaving.value || props.busy || (entry && entry.userId !== props.userId)) return;
  editingTime.value = entry ? { ...entry } : null; timeFormKey.value++; timeOpen.value = true;
}
function timeSaved() {
  timeOpen.value = false; editingTime.value = null;
  emit('time-updated', '工時已儲存');
}
</script>

<template>
  <section class="issue-detail-page" aria-label="任務完整頁">
    <header class="issue-detail-toolbar">
      <button class="button secondary" :disabled="saving || busy" @click="emit('close')"><Icon name="chevron" :size="16" class="back-icon" />{{ returnLabel }}</button>
      <span class="identifier">{{ issue.identifier }}</span>
      <button v-if="canWrite" class="button primary" :disabled="saving || busy" @click="emit('edit')">編輯任務</button>
    </header>
    <div class="issue-state-control" role="group" aria-label="任務狀態" :aria-busy="busy">
      <span class="state-label">目前狀態</span>
      <span class="status-badge" :style="{ '--status-color': state(issue)?.color }" aria-live="polite" aria-atomic="true"><i aria-hidden="true"></i>{{ state(issue)?.label || issue.state }}</span>
      <label v-if="canWrite" class="state-action">
        <span class="sr-only">變更狀態</span>
        <select :disabled="saving || busy" @change="changeState">
          <option value="">變更狀態…</option>
          <option v-for="item in states" :key="item.id" :value="item.id" :disabled="issue.state === item.id">{{ item.label }}{{ issue.state === item.id ? '（目前）' : '' }}</option>
        </select>
      </label>
      <span v-else class="subtle">唯讀</span>
      <span v-if="busy" class="subtle" role="status">更新中…</span>
    </div>
    <div class="issue-detail-layout">
      <h1 ref="heading" tabindex="-1">{{ issue.title }}</h1>
      <div class="issue-detail-body">
        <section class="issue-detail-description" aria-label="任務描述"><h2>描述</h2><MarkdownContent v-if="issue.description" :source="issue.description" /><p v-else>尚未填寫描述</p></section>
        <section class="issue-detail-activity" aria-label="任務活動">
          <div class="discussion-tabs" role="tablist" aria-label="任務紀錄" @keydown="onTabKeydown">
            <button id="detail-comments-tab" role="tab" aria-controls="detail-activity" :tabindex="tab === 'comments' ? 0 : -1" :aria-selected="tab === 'comments'" :class="{ active: tab === 'comments' }" @click="tab = 'comments'">留言 <span>{{ issue.comments.length }}</span></button>
            <button id="detail-history-tab" role="tab" aria-controls="detail-activity" :tabindex="tab === 'history' ? 0 : -1" :aria-selected="tab === 'history'" :class="{ active: tab === 'history' }" @click="tab = 'history'">異動紀錄 <span>{{ issue.history.length }}</span></button>
            <button v-if="canReadTime || canLogTime" id="detail-time-tab" role="tab" aria-controls="detail-activity" :tabindex="tab === 'time' ? 0 : -1" :aria-selected="tab === 'time'" :class="{ active: tab === 'time' }" @click="tab = 'time'">工時 <span v-if="canReadTime">{{ work.length }}</span></button>
          </div>
          <div id="detail-activity" role="tabpanel" tabindex="0" :aria-labelledby="`detail-${tab}-tab`">
            <template v-if="tab === 'comments'">
              <p v-if="!issue.comments.length" class="quiet-message">尚無留言</p>
              <article v-for="entry in issue.comments" :key="entry.id" class="comment"><div class="avatar small">{{ entry.author.slice(0, 1) }}</div><div><div class="comment-meta"><strong>{{ entry.author }}</strong><time>{{ timestamp(entry.createdAt) }}</time></div><MarkdownContent :source="entry.content" /></div></article>
            </template>
            <template v-else-if="tab === 'history'">
              <article v-for="entry in [...issue.history].reverse()" :key="entry.id" class="history-entry"><span class="history-dot"></span><div><p><strong>{{ entry.actor }}</strong> {{ entry.kind === 'created' ? '建立了任務' : '更新了任務' }} <time>{{ timestamp(entry.at) }}</time></p><p v-for="change in entry.changes" :key="change.field" class="history-change">{{ FIELD_LABELS[change.field] || change.field }}：{{ display(change.field, change.before) }} → {{ display(change.field, change.after) }}</p></div></article>
            </template>
            <template v-else>
              <div v-if="canLogTime" class="issue-time-toolbar"><button class="button secondary" :disabled="busy || saving || !timeIssues.length" @click="openTime()"><Icon name="plus" :size="16" />填報工時</button></div>
              <template v-if="canReadTime">
                <p v-if="!work.length" class="quiet-message">尚無工時紀錄</p>
                <article v-for="entry in work" :key="entry.id" class="issue-detail-work"><div><strong>{{ entry.workDate }} · {{ memberName(entry.userId) }}</strong><p>{{ entry.note || '未填說明' }}</p></div><div class="issue-work-actions"><span>{{ hours(entry.minutes) }}</span><button v-if="canLogTime && entry.userId === userId" class="text-button" :disabled="busy || saving" :aria-label="`修改工時 ${entry.id}`" @click="openTime(entry)">修改</button></div></article>
              </template>
            </template>
          </div>
          <form v-if="canWrite" v-show="tab === 'comments'" class="comment-form" @submit.prevent="sendComment">
            <div v-if="error" class="error-box" role="alert">{{ error }}<button v-if="conflict" class="text-button" type="button" :disabled="saving || busy" @click="emit('reload')">更新任務資料（保留留言）</button></div>
            <MarkdownEditor v-model="comment" label="新增留言" :rows="3" :maxlength="2000" placeholder="輸入留言…" :disabled="saving || busy" />
            <button class="button secondary" :disabled="saving || busy || !commentDirty">{{ saving ? '送出中…' : '送出留言' }}</button>
          </form>
        </section>
      </div>
      <aside class="issue-detail-sidebar" aria-label="任務資訊">
        <button class="mobile-properties-toggle" :aria-expanded="propertiesExpanded" aria-controls="detail-sidebar-content" aria-label="屬性與關聯" @click="propertiesExpanded = !propertiesExpanded">
          屬性與關聯 <span>{{ propertiesExpanded ? '收起' : '展開' }}</span>
        </button>
        <div id="detail-sidebar-content" class="sidebar-content" :class="{ expanded: propertiesExpanded }">
          <div class="sidebar-tabs" role="tablist" aria-label="任務側欄" @keydown="onTabKeydown">
            <button id="detail-properties-tab" role="tab" aria-controls="detail-properties" :tabindex="sidebarTab === 'properties' ? 0 : -1" :aria-selected="sidebarTab === 'properties'" :class="{ active: sidebarTab === 'properties' }" @click="sidebarTab = 'properties'">屬性</button>
            <button id="detail-relations-tab" role="tab" aria-controls="detail-relations" :tabindex="sidebarTab === 'relations' ? 0 : -1" :aria-selected="sidebarTab === 'relations'" :class="{ active: sidebarTab === 'relations' }" @click="sidebarTab = 'relations'">關聯</button>
          </div>
          <div v-show="sidebarTab === 'properties'" id="detail-properties" class="sidebar-panel property-fields" role="tabpanel" tabindex="0" aria-labelledby="detail-properties-tab">
            <dl>
              <div><dt>類型</dt><dd>{{ TYPES.find(item => item.id === issue.type)?.label || issue.type }}</dd></div>
              <div><dt>優先級</dt><dd>{{ PRIORITIES.find(item => item.id === issue.priority)?.label || issue.priority }}</dd></div>
              <div><dt>負責人</dt><dd>{{ memberName(issue.assignee) }}</dd></div>
              <div><dt>到期日</dt><dd>{{ issue.dueDate || '未設定' }}</dd></div>
              <div><dt>預估工時</dt><dd>{{ estimate ? hours(estimate) : '尚未估時' }}</dd></div>
              <div v-if="canReadTime"><dt>已投入工時</dt><dd>{{ hours(spent) }}{{ issue.type === 'epic' ? '（含所屬卡片）' : '' }}</dd></div>
            </dl>
            <p class="subtle">版本 {{ issue.revision }} · 已儲存</p>
          </div>
          <div v-show="sidebarTab === 'relations'" id="detail-relations" class="sidebar-panel" role="tabpanel" tabindex="0" aria-labelledby="detail-relations-tab">
            <p v-if="!relationGroups.length" class="subtle">尚無關聯卡片</p>
            <section v-for="group in relationGroups" :key="group.key" class="relation-group" :aria-label="group.label">
              <h2>{{ group.label }} <span>{{ group.items.length }}</span></h2>
              <div v-for="item in group.items" :key="item.id" class="relation-item">
                <button @click="emit('open', item)"><span class="identifier">{{ item.identifier }}</span><span>{{ item.title }}</span></button>
                <span class="status-badge" :style="{ '--status-color': state(item)?.color }"><i aria-hidden="true"></i>{{ state(item)?.label || item.state }}</span>
              </div>
            </section>
          </div>
        </div>
      </aside>
    </div>
    <TimeEntryDrawer v-if="timeOpen && canLogTime" ref="timeDrawer" :key="timeFormKey" :issues="timeIssues" :default-issue-id="issue.id" lock-issue :entry="editingTime" @close="timeOpen = false; editingTime = null" @saved="timeSaved" @conflict="emit('reload')" />
  </section>
</template>

<style scoped>
.issue-time-toolbar { display: flex; justify-content: flex-end; margin: 16px 0; }
.issue-work-actions { display: flex; align-items: center; gap: 16px; flex-shrink: 0; }
.issue-work-actions button { min-height: 44px; }
.issue-detail-page { background: #fff; color: #242936; min-height: calc(100dvh - 60px); }
.issue-detail-toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: 16px; padding: 18px 24px; border-bottom: 1px solid #daddE5; }
.issue-detail-toolbar .primary { margin-left: auto; }
.back-icon { transform: rotate(180deg); }
.issue-state-control { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; padding: 10px 24px; border-bottom: 1px solid #daddE5; font-size: 14px; }
.state-label { color: #5f6673; }
.state-action { min-width: 0; max-width: 100%; }
.state-action select { appearance: auto; min-height: 44px; width: 100%; max-width: 240px; padding: 8px 32px 8px 12px; font: inherit; color: #424b5c; background-color: #fff; border: 1px solid #8c93a3; border-radius: 6px; cursor: pointer; }
.state-action select:hover:not(:disabled) { border-color: #6653bd; }
.state-action select:focus-visible { outline: 2px solid #6653bd; outline-offset: 2px; box-shadow: none; }
.state-action select:disabled { cursor: wait; opacity: 0.65; }
.issue-detail-layout { display: grid; grid-template-columns: minmax(0, 1fr) 320px; grid-template-rows: auto 1fr; }
.issue-detail-body { min-width: 0; padding: 0 32px 48px; grid-column: 1; }
h1 { font-size: 26px; line-height: 1.5; font-weight: 650; overflow-wrap: anywhere; margin: 0; padding: 28px 32px 24px; grid-column: 1; }
h1:focus { outline: none; }
h2 { font-size: 15px; font-weight: 600; margin-bottom: 12px; }
h2 span { color: #5f6673; font-weight: 400; margin-left: 8px; }
.issue-detail-description p { white-space: pre-wrap; overflow-wrap: anywhere; font-size: 15px; line-height: 1.9; }
.issue-detail-activity { margin-top: 28px; border-top: 1px solid #daddE5; padding-top: 18px; }
.issue-detail-sidebar { grid-column: 2; grid-row: 1 / span 2; padding: 18px 20px 24px; border-left: 1px solid #daddE5; background: #fcfcfe; min-width: 0; }
.sidebar-tabs { display: flex; gap: 20px; border-bottom: 1px solid #daddE5; margin-bottom: 20px; }
.sidebar-tabs button { min-height: 44px; padding: 10px 4px; border-bottom: 2px solid transparent; color: #5f6673; font-size: 14px; }
.sidebar-tabs button.active { border-bottom-color: #6653bd; color: #5844a0; font-weight: 600; }
.sidebar-tabs button:hover { color: #5844a0; }
.sidebar-panel { max-height: max(220px, calc(100dvh - 320px)); overflow-y: auto; scrollbar-gutter: stable; padding: 2px 4px 4px 0; overflow-wrap: anywhere; }
.sidebar-panel:focus-visible { outline: 2px solid #6653bd; outline-offset: 2px; }
.relation-group + .relation-group { margin-top: 22px; }
.relation-group h2 { font-size: 14px; margin-bottom: 6px; }
.relation-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: start; column-gap: 10px; padding: 8px 0; border-bottom: 1px solid #e6e8ee; }
.relation-item button { min-width: 0; min-height: 44px; display: flex; flex-direction: column; align-items: flex-start; gap: 3px; width: 100%; text-align: left; font-size: 14px; line-height: 1.6; overflow-wrap: anywhere; }
.relation-item button:hover { color: #5844a0; }
.relation-item .status-badge { margin-top: 4px; max-width: 104px; white-space: normal; }
.relation-item .status-badge i { flex-shrink: 0; }
.mobile-properties-toggle { display: none; }
dl { margin: 0; }
dl > div { margin-bottom: 22px; }
dt { color: #5f6673; font-size: 13px; margin-bottom: 7px; }
dd { margin: 0; font-size: 14px; line-height: 1.7; overflow-wrap: anywhere; }
dd button { text-align: left; color: #5844a0; }
.issue-detail-work { display: flex; gap: 16px; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid #edf0f5; font-size: 14px; }
.issue-detail-work > div { min-width: 0; }
.issue-detail-work p { margin-top: 7px; white-space: pre-wrap; overflow-wrap: anywhere; color: #5f6673; }
.issue-detail-work > span { flex-shrink: 0; }
.issue-detail-work + .subtle { margin-top: 14px; }
.comment-form .field { text-align: left; }
@media(max-width:1000px) { .issue-detail-layout { grid-template-columns: minmax(0, 1fr) 260px; } .issue-detail-body { padding: 0 24px 40px; } h1 { padding: 24px; } }
@media(max-width:700px) {
  .issue-state-control { padding: 10px 18px; gap: 10px; }
  .issue-detail-layout { display: flex; flex-direction: column; }
  .issue-detail-toolbar { padding: 14px 16px; gap: 10px; }
  .issue-detail-toolbar .button { padding: 9px 10px; font-size: 13px; }
  .issue-detail-body { padding: 22px 18px 30px; order: 2; }
  h1 { font-size: 23px; padding: 22px 18px; }
  .issue-detail-sidebar { order: 1; padding: 18px; border-left: 0; border-bottom: 1px solid #daddE5; }
  .mobile-properties-toggle { display: flex; width: 100%; gap: 12px; justify-content: space-between; text-align: left; font-size: 14px; min-height: 44px; align-items: center; }
  .mobile-properties-toggle span { flex-shrink: 0; color: #5844a0; }
  .sidebar-content { display: none; }
  .sidebar-content.expanded { display: block; padding-top: 12px; }
  .sidebar-panel { max-height: 55dvh; }
  dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
  dl > div { margin: 0; }
  .property-fields > p { margin-top: 14px; }
}
</style>
