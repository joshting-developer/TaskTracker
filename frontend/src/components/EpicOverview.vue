<script setup>
import { computed } from 'vue';
import { epicSummary, hours, statusesFor } from '../domain/model.js';
import Icon from './Icon.vue';
const props = defineProps({ project: Object, issues: Array, entries: Array, members: Array });
defineEmits(['open', 'create']);
const epics = computed(() => props.issues.filter((issue) => issue.type === 'epic').map((issue) => ({ ...issue, summary: epicSummary(issue, props.issues, props.entries, props.project) })));
const unlinked = computed(() => props.issues.filter((issue) => issue.type !== 'epic' && !issue.epicId).length);
const memberName = (id) => props.members.find((member) => member.id === id)?.name || id || '未指派';
</script>
<template>
  <section class="epic-workspace" aria-label="Epic 概覽">
    <header class="section-title"><div><h2>把目標拆成可以完成的事</h2><p class="subtle">子任務進度與工時自動彙總, Epic 由人確認驗收</p></div><button class="button secondary" @click="$emit('create')"><Icon name="plus" :size="17" />新增 Epic</button></header>
    <div v-if="!epics.length" class="empty-state"><span class="empty-icon"><Icon name="diamond" :size="30" /></span><h2>先寫下一個大目標</h2><p>例如「會員系統上線」, 再把 Task 與 Bug 掛進來</p><button class="button primary" @click="$emit('create')">建立第一個 Epic</button></div>
    <div class="epic-grid"><article v-for="epic in epics" :key="epic.id" class="epic-card" :data-epic="epic.identifier">
      <div class="epic-card-top"><span class="identifier">{{ epic.identifier }} · EPIC</span><span class="status-badge" :style="{ color: statusesFor(project).find(s => s.id === epic.state)?.color }">{{ statusesFor(project).find(s => s.id === epic.state)?.label }}</span></div>
      <button class="epic-title" @click="$emit('open', epic)">{{ epic.title }}</button><p class="epic-description">{{ epic.description || '為這個目標寫下交付條件' }}</p>
      <div v-if="epic.summary.needsReview" class="review-note">需重新確認 · 已驗收的 Epic 有未結案子任務</div>
      <div class="epic-progress-label"><strong>{{ epic.summary.percent === null ? '尚無有效工作' : `${epic.summary.percent}%` }}</strong><span>{{ epic.summary.done }} / {{ epic.summary.total }} 完成 · {{ epic.summary.canceled }} 取消</span></div><div class="progress-track"><span :style="{ width: `${epic.summary.percent || 0}%` }"></span></div>
      <div class="epic-hours"><span>預估 <strong>{{ hours(epic.summary.estimateMinutes) }}</strong></span><span>已投入 <strong>{{ hours(epic.summary.spentMinutes) }}</strong></span><span>{{ memberName(epic.assignee) }}</span></div>
      <div class="epic-children"><button v-for="child in epic.summary.children" :key="child.id" :aria-label="child.title" @click="$emit('open', child)"><i :style="{ background: statusesFor(project).find(s => s.id === child.state)?.color }"></i><span class="identifier">{{ child.identifier }}</span><span>{{ child.title }}</span><small>{{ child.type === 'bug' ? 'Bug' : child.type === 'feature' ? '功能' : 'Task' }}</small></button><p v-if="!epic.summary.children.length" class="subtle">在任務詳細內容選擇這個 Epic, 就會出現在這裡</p></div>
    </article></div>
    <p class="subtle epic-footnote">{{ unlinked }} 個任務尚未掛入 Epic · 取消不當作完成, 工時仍保留 · Epic 不重複計入子任務工時</p>
  </section>
</template>
