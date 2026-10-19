<script setup>
import { computed } from 'vue';
import { categoryFor, hours, liveBlockers } from '../domain/model.js';
import Icon from './Icon.vue';
const props = defineProps({ issue: Object, issues: Array, entries: Array, project: Object });
defineEmits(['open']);
const epic = computed(() => props.issues.find((item) => item.id === props.issue.epicId));
const spent = computed(() => props.entries.filter((entry) => entry.issueId === props.issue.id && !entry.voidedAt).reduce((sum, entry) => sum + entry.minutes, 0));
const blockers = computed(() => liveBlockers(props.issue, props.issues, props.project));
</script>
<template>
  <div class="issue-context">
    <button v-if="epic" class="context-epic" :aria-label="`查看 Epic ${epic.identifier}`" @click="$emit('open', epic)"><Icon name="diamond" :size="12" />{{ epic.title }}</button>
    <span class="context-hours" :title="`已投入 ${hours(spent)} / ${issue.estimateMinutes ? `預估 ${hours(issue.estimateMinutes)}` : '未估時'}`"><Icon name="clock" :size="12" />{{ hours(spent) }}<span>/ {{ issue.estimateMinutes ? hours(issue.estimateMinutes) : '未估' }}</span></span>
    <button v-if="blockers.length && categoryFor(issue, project) !== 'canceled'" class="context-blocked" :title="blockers.map(item => item.identifier).join('、')" :aria-label="`${issue.identifier} ${categoryFor(issue, project) === 'done' ? '依賴需確認' : '被阻擋'}`" @click="$emit('open', blockers[0])"><Icon name="link" :size="12" />{{ categoryFor(issue, project) === 'done' ? '依賴需確認' : `被 ${blockers.length} 項阻擋` }}</button>
  </div>
</template>
