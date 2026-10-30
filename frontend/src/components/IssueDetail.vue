<script setup>
import { computed, ref, watch } from 'vue';
import Icon from './Icon.vue';
import { api } from '../api/index.js';
import { FIELD_LABELS, hours, PRIORITIES, statusesFor, TYPES } from '../domain/model.js';

const props = defineProps({ issue: Object, project: Object, issues: Array, members: Array, entries: Array, returnLabel: String, busy: Boolean, canWrite: { type: Boolean, default: true }, canReadTime: { type: Boolean, default: true } });
const emit = defineEmits(['close', 'open', 'edit', 'saved', 'reload', 'state']);
const tab = ref('comments'), comment = ref(''), saving = ref(false), error = ref(''), conflict = ref(false);
const heading = ref(null);
let pending;
watch(() => props.canWrite, allowed => { if (!allowed) { comment.value = ''; pending = null; error.value = ''; conflict.value = false; } });
watch(() => props.canReadTime, allowed => { if (!allowed && tab.value === 'time') tab.value = 'comments'; });
const dirty = computed(() => Boolean(comment.value.trim()));
defineExpose({ dirty, saving, focus: () => heading.value?.focus() });
const states = computed(() => statusesFor(props.project));
const state = issue => states.value.find(item => item.id === issue.state);
const memberName = id => props.members.find(member => member.id === id)?.name || id || '未指派';
const children = computed(() => props.issues.filter(issue => props.issue.type === 'epic' ? issue.epicId === props.issue.id : issue.parentIssueId === props.issue.id));
const related = computed(() => (props.issue.relatedIssueIds || []).map(id => props.issues.find(issue => issue.id === id)).filter(Boolean));
const epic = computed(() => props.issues.find(issue => issue.id === props.issue.epicId));
const parent = computed(() => props.issues.find(issue => issue.id === props.issue.parentIssueId));
const work = computed(() => props.entries.filter(entry => !entry.voidedAt && (props.issue.type === 'epic' ? children.value.some(issue => issue.id === entry.issueId) : entry.issueId === props.issue.id)).sort((a, b) => b.workDate.localeCompare(a.workDate)));
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
async function sendComment() {
  if (!props.canWrite || saving.value || props.busy || !dirty.value) return;
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
</script>

<template>
  <section class="issue-detail-page" aria-label="任務完整頁">
    <header class="issue-detail-toolbar">
      <button class="button secondary" :disabled="saving || busy" @click="emit('close')"><Icon name="chevron" :size="16" class="back-icon" />{{ returnLabel }}</button>
      <span class="identifier">{{ issue.identifier }}</span>
      <button v-if="canWrite" class="button primary" :disabled="saving || busy" @click="emit('edit')">編輯任務</button>
    </header>
    <div class="issue-state-tabs" role="group" aria-label="任務狀態">
      <button v-for="item in states" :key="item.id" type="button" :class="{ active: issue.state === item.id }" :aria-pressed="issue.state === item.id" :style="{ '--status-color': item.color }" :disabled="saving || busy || !canWrite" @click="emit('state', item.id)">{{ item.label }}</button>
      <span v-if="busy" class="subtle" role="status">更新中…</span>
    </div>
    <div class="issue-detail-layout">
      <h1 ref="heading" tabindex="-1">{{ issue.title }}</h1>
      <div class="issue-detail-body">
        <section class="issue-detail-description" aria-label="任務描述"><h2>描述</h2><p>{{ issue.description || '尚未填寫描述' }}</p></section>
        <section v-if="children.length || issue.type === 'epic'" class="issue-detail-links" :aria-label="issue.type === 'epic' ? 'Epic 所屬卡片' : '子任務'">
          <h2>{{ issue.type === 'epic' ? 'Epic 所屬卡片' : '子任務' }} <span>{{ children.length }}</span></h2>
          <div v-for="item in children" :key="item.id" class="issue-detail-link-row"><button @click="emit('open', item)"><span class="identifier">{{ item.identifier }}</span>{{ item.title }}</button><span class="status-badge" :style="{ '--status-color': state(item)?.color }"><i></i>{{ state(item)?.label || item.state }}</span></div>
          <p v-if="!children.length" class="subtle">尚無所屬卡片</p>
        </section>
        <section v-if="related.length" class="issue-detail-links" aria-label="關聯任務"><h2>關聯任務 <span>{{ related.length }}</span></h2><div v-for="item in related" :key="item.id" class="issue-detail-link-row"><button @click="emit('open', item)"><span class="identifier">{{ item.identifier }}</span>{{ item.title }}</button><span class="status-badge" :style="{ '--status-color': state(item)?.color }"><i></i>{{ state(item)?.label || item.state }}</span></div></section>
        <section class="issue-detail-activity" aria-label="任務活動">
          <div class="discussion-tabs" role="tablist" aria-label="任務紀錄">
            <button id="detail-comments-tab" role="tab" aria-controls="detail-activity" :aria-selected="tab === 'comments'" :class="{ active: tab === 'comments' }" @click="tab = 'comments'">留言 <span>{{ issue.comments.length }}</span></button>
            <button id="detail-history-tab" role="tab" aria-controls="detail-activity" :aria-selected="tab === 'history'" :class="{ active: tab === 'history' }" @click="tab = 'history'">異動紀錄 <span>{{ issue.history.length }}</span></button>
            <button v-if="canReadTime" id="detail-time-tab" role="tab" aria-controls="detail-activity" :aria-selected="tab === 'time'" :class="{ active: tab === 'time' }" @click="tab = 'time'">工時 <span>{{ work.length }}</span></button>
          </div>
          <div id="detail-activity" role="tabpanel" :aria-labelledby="`detail-${tab}-tab`">
            <template v-if="tab === 'comments'">
              <p v-if="!issue.comments.length" class="quiet-message">尚無留言</p>
              <article v-for="entry in issue.comments" :key="entry.id" class="comment"><div class="avatar small">{{ entry.author.slice(0, 1) }}</div><div><div class="comment-meta"><strong>{{ entry.author }}</strong><time>{{ timestamp(entry.createdAt) }}</time></div><p>{{ entry.content }}</p></div></article>
            </template>
            <template v-else-if="tab === 'history'">
              <article v-for="entry in [...issue.history].reverse()" :key="entry.id" class="history-entry"><span class="history-dot"></span><div><p><strong>{{ entry.actor }}</strong> {{ entry.kind === 'created' ? '建立了任務' : '更新了任務' }} <time>{{ timestamp(entry.at) }}</time></p><p v-for="change in entry.changes" :key="change.field" class="history-change">{{ FIELD_LABELS[change.field] || change.field }}：{{ display(change.field, change.before) }} → {{ display(change.field, change.after) }}</p></div></article>
            </template>
            <template v-else>
              <p v-if="!work.length" class="quiet-message">尚無工時紀錄</p>
              <article v-for="entry in work" :key="entry.id" class="issue-detail-work"><div><strong>{{ entry.workDate }} · {{ memberName(entry.userId) }}</strong><p>{{ entry.note || '未填說明' }}</p></div><span>{{ hours(entry.minutes) }}</span></article>
              <p class="subtle">{{ issue.type === 'epic' ? '加總所屬卡片, 不重複計算子任務' : '依目前任務的有效紀錄計算' }} · 工作日採 Asia/Taipei</p>
            </template>
          </div>
          <form v-if="canWrite" v-show="tab === 'comments'" class="comment-form" @submit.prevent="sendComment">
            <div v-if="error" class="error-box" role="alert">{{ error }}<button v-if="conflict" class="text-button" type="button" :disabled="saving || busy" @click="emit('reload')">更新任務資料（保留留言）</button></div>
            <label class="field">新增留言<textarea v-model="comment" rows="3" maxlength="2000" placeholder="留下想法或進度…" :disabled="saving || busy" /></label>
            <button class="button secondary" :disabled="saving || busy || !dirty">{{ saving ? '送出中…' : '送出留言' }}</button>
          </form>
        </section>
      </div>
      <aside class="issue-detail-properties" aria-label="任務屬性">
        <dl>
          <div><dt>類型</dt><dd>{{ TYPES.find(item => item.id === issue.type)?.label || issue.type }}</dd></div>
          <div><dt>優先級</dt><dd>{{ PRIORITIES.find(item => item.id === issue.priority)?.label || issue.priority }}</dd></div>
          <div><dt>負責人</dt><dd>{{ memberName(issue.assignee) }}</dd></div>
          <div><dt>到期日</dt><dd>{{ issue.dueDate || '未設定' }}</dd></div>
          <div v-if="epic"><dt>所屬 Epic</dt><dd><button @click="emit('open', epic)">{{ epic.identifier }} · {{ epic.title }}</button></dd></div>
          <div v-if="parent"><dt>父任務</dt><dd><button @click="emit('open', parent)">{{ parent.identifier }} · {{ parent.title }}</button></dd></div>
          <div><dt>預估工時</dt><dd>{{ estimate ? hours(estimate) : '尚未估時' }}</dd></div>
          <div v-if="canReadTime"><dt>已投入工時</dt><dd>{{ hours(spent) }}{{ issue.type === 'epic' ? '（所屬卡片加總）' : '' }}</dd></div>
        </dl>
        <p class="subtle">版本 {{ issue.revision }} · 已儲存</p>
      </aside>
    </div>
  </section>
</template>

<style scoped>
.issue-detail-page { background: #fff; color: #242936; min-height: calc(100dvh - 60px); }
.issue-detail-toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: 16px; padding: 18px 24px; border-bottom: 1px solid #daddE5; }
.issue-detail-toolbar .primary { margin-left: auto; }
.back-icon { transform: rotate(180deg); }
.issue-state-tabs { display: flex; align-items: center; gap: 8px; overflow-x: auto; padding: 0 24px; border-bottom: 1px solid #daddE5; }
.issue-state-tabs button { flex-shrink: 0; font-size: 14px; color: #5f6673; padding: 14px 16px 12px; border-bottom: 3px solid transparent; }
.issue-state-tabs button.active { color: #242936; font-weight: 600; border-bottom-color: var(--status-color, #6653bd); background: #f7f6fc; }
.issue-state-tabs button:hover { background: #f7f8fb; }
.issue-state-tabs .subtle { white-space: nowrap; }
.issue-detail-layout { display: grid; grid-template-columns: minmax(0, 1fr) 280px; }
.issue-detail-body { min-width: 0; padding: 0 32px 48px; grid-column: 1; }
h1 { font-size: 26px; line-height: 1.5; font-weight: 650; overflow-wrap: anywhere; margin: 0; padding: 28px 32px 24px; grid-column: 1; }
h1:focus { outline: none; }
h2 { font-size: 15px; font-weight: 600; margin-bottom: 12px; }
h2 span { color: #5f6673; font-weight: 400; margin-left: 8px; }
.issue-detail-description p { white-space: pre-wrap; overflow-wrap: anywhere; font-size: 15px; line-height: 1.9; }
.issue-detail-links, .issue-detail-activity { margin-top: 28px; border-top: 1px solid #daddE5; padding-top: 18px; }
.issue-detail-link-row { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #edf0f5; }
.issue-detail-link-row button { flex: 1; text-align: left; overflow-wrap: anywhere; font-size: 14px; }
.issue-detail-link-row .identifier { display: inline-block; margin-right: 10px; }
.issue-detail-link-row .status-badge { flex-shrink: 0; }
.issue-detail-properties { grid-column: 2; grid-row: 1 / span 2; padding: 30px 24px; border-left: 1px solid #daddE5; background: #fcfcfe; min-width: 0; }
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
@media(max-width:1000px) { .issue-detail-layout { grid-template-columns: minmax(0, 1fr) 230px; } .issue-detail-body { padding: 0 24px 40px; } h1 { padding: 24px; } }
@media(max-width:700px) {
  .issue-state-tabs { padding: 0 12px; gap: 2px; }
  .issue-state-tabs button { padding: 12px; }
  .issue-detail-layout { display: flex; flex-direction: column; }
  .issue-detail-toolbar { padding: 14px 16px; gap: 10px; }
  .issue-detail-toolbar .button { padding: 9px 10px; font-size: 13px; }
  .issue-detail-body { padding: 22px 18px 30px; order: 2; }
  h1 { font-size: 23px; padding: 22px 18px; }
  .issue-detail-properties { order: 1; padding: 18px; border-left: 0; border-bottom: 1px solid #daddE5; }
  dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
  dl > div { margin: 0; }
  .issue-detail-properties > p { margin-top: 14px; }
  .issue-detail-link-row { flex-wrap: wrap; }
}
</style>
