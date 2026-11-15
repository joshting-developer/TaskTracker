<script setup>
import { computed, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';
import { t } from '../i18n/index.js';
import PagedSelect from './PagedSelect.vue';
import Icon from './Icon.vue';
const props = defineProps({ projectId: String, options: { type: Array, default: () => [] }, disabled: Boolean, label: { type: String, default: '' } });
const model = defineModel({ type: Array, default: () => [] });
const picked = shallowRef(''), loaded = shallowRef([]);
const selected = computed(() => {
  const lookup = new Map([...props.options, ...loaded.value].map(tag => [tag.id, tag]));
  return model.value.map(id => lookup.get(id) || { id, name: t('已移除標籤') });
});
watch(() => props.projectId, () => { loaded.value = []; picked.value = ''; });
async function load(filters, options) {
  const project = props.projectId;
  const page = await api.tags(project, { ...filters, perPage: 20 }, options);
  if (project === props.projectId) loaded.value = [...new Map([...loaded.value.filter(tag => model.value.includes(tag.id)), ...page.items].map(tag => [tag.id, tag])).values()];
  return page;
}
function choose(tag) {
  if (!model.value.includes(tag.id) && model.value.length < 20) model.value = [...model.value, tag.id];
  picked.value = '';
}
</script>
<template>
  <div class="tag-picker">
    <PagedSelect v-model="picked" :label="label || t('標籤')" :options="loaded" :load="load" :context="projectId" :disabled="disabled || model.length >= 20" :empty-label="t('選擇標籤')" @choose="choose" />
    <ul v-if="selected.length" class="selected-tags" :aria-label="t('已選標籤')">
      <li v-for="tag in selected" :key="tag.id"><i :style="{ backgroundColor: tag.color }" aria-hidden="true"></i><span>{{ tag.name }}</span><button type="button" class="icon-button" :disabled="disabled" :aria-label="t('移除標籤 {0}', [tag.name])" @click="model = model.filter(id => id !== tag.id)"><Icon name="close" :size="13" /></button></li>
    </ul>
  </div>
</template>
<style scoped>
.tag-picker { min-width: 0; }
.selected-tags { display: flex; flex-wrap: wrap; gap: 6px; list-style: none; padding: 0; margin: 8px 0 0; }
.selected-tags li { display: flex; align-items: center; gap: 6px; min-width: 0; max-width: 100%; border: 1px solid var(--border); border-radius: 6px; padding-left: 8px; font-size: 13px; }
.selected-tags li > span { overflow-wrap: anywhere; }
.selected-tags i { flex: 0 0 6px; height: 6px; border-radius: 50%; }
.selected-tags .icon-button { min-width: 32px; width: 32px; min-height: 32px; height: 32px; }
</style>
