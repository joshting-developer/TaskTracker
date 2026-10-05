<script setup>
import { computed, ref } from 'vue';
import { canonical } from '../domain/model.js';
import Icon from './Icon.vue';
const props = defineProps({ views: Array, activeId: String, userId: String, filters: Object, appliedRevision: Number });
defineEmits(['select', 'create', 'update', 'remove', 'reapply']);
const copied = ref(false);
const active = computed(() => props.views.find((view) => view.id === props.activeId));
const updated = computed(() => active.value && active.value.revision !== props.appliedRevision);
const changed = computed(() => active.value && canonical(active.value.filters) !== canonical(props.filters));
async function copy() { try { await navigator.clipboard.writeText(location.href); copied.value = true; setTimeout(() => { copied.value = false; }, 2000); } catch { copied.value = false; } }
</script>
<template>
  <div class="saved-views-bar" aria-label="常用視圖"><div class="saved-view-chips"><button class="view-chip" :class="{ active: !activeId }" @click="$emit('select', null)">全部任務</button><button v-for="view in views" :key="view.id" class="view-chip" :class="{ active: activeId === view.id }" :aria-label="`套用視圖 ${view.name}`" :aria-pressed="activeId === view.id" @click="$emit('select', view)"><Icon :name="view.visibility === 'team' ? 'user' : 'filter'" :size="12" />{{ view.name }}<small>{{ view.visibility === 'team' ? '團隊' : '個人' }}</small></button></div><div class="view-actions"><span v-if="updated || changed" class="view-adjusted">{{ updated ? '視圖有新版' : '篩選已調整' }}</span><button v-if="active && (updated || changed)" class="text-button" @click="$emit('reapply', active)">重新套用</button><button v-if="active?.visibility === 'team'" class="text-button" @click="copy">{{ copied ? '已複製' : '複製視圖連結' }}</button><button v-if="active?.userId === userId" class="text-button" @click="$emit('update', active)">更新視圖</button><button v-if="active?.userId === userId" class="text-button danger-text" @click="$emit('remove', active)">移除視圖</button><button class="button secondary" @click="$emit('create')"><Icon name="plus" :size="14" />存為視圖</button></div></div>
</template>
