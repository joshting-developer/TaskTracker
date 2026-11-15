<script setup>
import { computed } from 'vue';
import { t } from '../i18n/index.js';
const props = defineProps({ tags: { type: Array, default: () => [] }, max: { type: Number, default: 3 } });
const visible = computed(() => props.tags.slice(0, props.max));
const remaining = computed(() => props.tags.slice(props.max));
</script>
<template>
  <span v-if="tags.length" class="tag-labels" :aria-label="t('標籤')">
    <span v-for="tag in visible" :key="tag.id" class="tag-label"><i :style="{ backgroundColor: tag.color }" aria-hidden="true"></i>{{ tag.name }}</span>
    <span v-if="remaining.length" class="tag-overflow" :title="remaining.map(tag => tag.name).join('、')">+{{ remaining.length }}</span>
  </span>
</template>
<style scoped>
.tag-labels { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 8px; min-width: 0; margin-top: 6px; }
.tag-label { display: inline-flex; align-items: center; gap: 5px; max-width: 100%; color: var(--text-secondary); font-size: 12px; line-height: 1.6; overflow-wrap: anywhere; }
.tag-label i { flex: 0 0 6px; width: 6px; height: 6px; border-radius: 50%; }
.tag-overflow { font-size: 12px; color: var(--text-secondary); }
</style>
