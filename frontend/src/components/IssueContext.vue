<script setup>
import { t } from '../i18n/index.js';
import { computed } from 'vue';
import { isClosed, hours } from '../domain/model.js';
import Icon from './Icon.vue';
const props = defineProps({ issue: Object, index: { type: Object, required: true }, canReadTime: { type: Boolean, default: true } });
defineEmits(['open']);
const epic = computed(() => props.index.byId.get(props.issue.epicId));
const summary = computed(() => props.index.summaries.get(props.issue.id));
const spentMinutes = computed(() => summary.value ? summary.value.spentMinutes : props.index.spent.get(props.issue.id) || 0);
const estimateMinutes = computed(() => summary.value ? summary.value.estimateMinutes : props.issue.estimateMinutes);
const parent = computed(() => props.index.byId.get(props.issue.parentIssueId));
const children = computed(() => props.index.children.get(props.issue.id) || []);
const completed = computed(() => children.value.filter(isClosed).length);
const related = computed(() => (props.issue.relatedIssueIds || []).map(id => props.index.byId.get(id)).filter(Boolean));
</script>
<template>
  <div class="issue-context">
    <button v-if="epic" class="context-epic" :aria-label="t('查看 Epic {0}', [epic.identifier])" @click="$emit('open', epic)"><Icon name="diamond" :size="12" />{{ epic.title }}</button>
    <span v-if="canReadTime" class="context-hours" :title="t('{0}已投入 {1} / {2}', [summary ? t('Epic所屬卡片加總 · ') : '', hours(spentMinutes), estimateMinutes ? t('預估 {0}', [hours(estimateMinutes)]) : t('未估時')])"><Icon name="clock" :size="12" />{{ hours(spentMinutes) }}<span>/ {{ estimateMinutes ? hours(estimateMinutes) : t('未估') }}</span></span>
    <button v-if="parent" class="context-parent" :aria-label="t('查看父任務 {0}', [parent.identifier])" @click="$emit('open', parent)"><Icon name="link" :size="12" />{{ parent.identifier }} · {{ parent.title }}</button>
    <button v-if="children.length" class="context-parent" :aria-label="t('查看 {0} 的子任務', [issue.identifier])" @click="$emit('open', issue)">{{ t('子任務') }} {{ completed }} / {{ children.length }}</button>
    <button v-if="related.length" class="context-related" :aria-label="t('查看 {0} 的關聯任務', [issue.identifier])" @click="$emit('open', issue)"><Icon name="link" :size="12" />{{ related.length }} {{ t('個關聯') }}</button>
  </div>
</template>
