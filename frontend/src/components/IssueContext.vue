<script setup>
import { computed } from 'vue';
import { categoryFor, hours } from '../domain/model.js';
import Icon from './Icon.vue';
const props = defineProps({ issue: Object, issues: Array, entries: Array, project: Object, canReadTime: { type: Boolean, default: true } });
defineEmits(['open']);
const epic = computed(() => props.issues.find((item) => item.id === props.issue.epicId));
const spent = computed(() => props.entries.filter((entry) => entry.issueId === props.issue.id && !entry.voidedAt).reduce((sum, entry) => sum + entry.minutes, 0));
const parent = computed(() => props.issues.find(item => item.id === props.issue.parentIssueId));
const children = computed(() => props.issues.filter(item => item.parentIssueId === props.issue.id));
const completed = computed(() => children.value.filter(item => categoryFor(item, props.project) === 'done').length);
const related = computed(() => (props.issue.relatedIssueIds || []).map(id => props.issues.find(item => item.id === id)).filter(Boolean));
</script>
<template>
  <div class="issue-context">
    <button v-if="epic" class="context-epic" :aria-label="`查看 Epic ${epic.identifier}`" @click="$emit('open', epic)"><Icon name="diamond" :size="12" />{{ epic.title }}</button>
    <span v-if="canReadTime" class="context-hours" :title="`已投入 ${hours(spent)} / ${issue.estimateMinutes ? `預估 ${hours(issue.estimateMinutes)}` : '未估時'}`"><Icon name="clock" :size="12" />{{ hours(spent) }}<span>/ {{ issue.estimateMinutes ? hours(issue.estimateMinutes) : '未估' }}</span></span>
    <button v-if="parent" class="context-parent" :aria-label="`查看父任務 ${parent.identifier}`" @click="$emit('open', parent)"><Icon name="link" :size="12" />{{ parent.identifier }} · {{ parent.title }}</button>
    <button v-if="children.length" class="context-parent" :aria-label="`查看 ${issue.identifier} 的子任務`" @click="$emit('open', issue)">子任務 {{ completed }} / {{ children.length }}</button>
    <button v-if="related.length" class="context-related" :aria-label="`查看 ${issue.identifier} 的關聯任務`" @click="$emit('open', issue)"><Icon name="link" :size="12" />{{ related.length }} 個關聯</button>
  </div>
</template>
