<script setup>
import { computed, ref } from 'vue';
import { canonical } from '../domain/model.js';
import Icon from './Icon.vue';
const props = defineProps({ views: Array, activeId: String, userId: String, filters: Object, appliedRevision: Number });
defineEmits(['select', 'create', 'update', 'remove', 'reapply']);
const copied = ref(false);
const active = computed(() => props.views.find((view) => view.id === props.activeId));
const updated = computed(() => active.value && active.value.revision !== props.appliedRevision);
const canSave = computed(() => Object.entries(props.filters).some(([key, value]) => key === 'scope' ? value !== 'all' : key === 'sort' ? value !== 'updated' : key !== 'view' && Boolean(value)));
const changed = computed(() => active.value && canonical(active.value.filters) !== canonical(props.filters));
async function copy() { try { await navigator.clipboard.writeText(location.href); copied.value = true; setTimeout(() => { copied.value = false; }, 2000); } catch { copied.value = false; } }
</script>
<template>
  <div v-if="views.length || canSave" class="saved-filter-tools" aria-label="常用篩選">
    <label v-if="views.length" class="saved-filter-picker">常用篩選
      <select class="saved-filter-select" aria-label="套用常用篩選" :value="active?.id || ''" @change="$emit('select', views.find(item => item.id === $event.target.value))">
        <option value="" disabled>選擇已儲存條件…</option>
        <option v-for="view in views" :key="view.id" :value="view.id">{{ view.name }} · {{ view.visibility === 'team' ? '團隊' : '個人' }}</option>
      </select>
    </label>
    <div class="saved-filter-actions">
      <button v-if="active || canSave" class="text-button" @click="$emit('select', null)">重設篩選</button>
      <span v-if="updated || changed" class="view-adjusted">{{ updated ? '常用篩選有新版' : '篩選已調整' }}</span>
      <button v-if="active && (updated || changed)" class="text-button" @click="$emit('reapply', active)">重新套用</button>
      <button v-if="active?.visibility === 'team'" class="text-button" @click="copy">{{ copied ? '已複製' : '複製篩選連結' }}</button>
      <button v-if="active?.userId === userId" class="text-button" @click="$emit('update', active)">更新常用篩選</button>
      <button v-if="active?.userId === userId" class="text-button danger-text" @click="$emit('remove', active)">移除常用篩選</button>
      <button v-if="canSave" class="text-button" @click="$emit('create')"><Icon name="plus" :size="14" />儲存常用篩選</button>
    </div>
  </div>
</template>
