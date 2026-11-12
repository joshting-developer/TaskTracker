<script setup>
import { computed, ref, toRef, useId, watch, onMounted } from 'vue';
import { usePagedLookup } from '../composables/usePagedLookup.js';
import Icon from './Icon.vue';
const props = defineProps({ label: String, options: { type: Array, default: () => [] }, fixedOptions: { type: Array, default: () => [] }, context: String, load: Function, autoload: Boolean, required: Boolean, disabled: Boolean, emptyLabel: { type: String, default: '未設定' } });
const model = defineModel({ type: String, default: '' });
const emit = defineEmits(['choose']);
const id = useId(), expanded = ref(false), chosen = ref(null);
const query = usePagedLookup({ context: toRef(props, 'context'), load: (...args) => props.load(...args) });
onMounted(() => { if (props.autoload)
  query.open(); });
watch(() => props.context, () => { chosen.value = null; });
const options = computed(() => [...new Map([...props.fixedOptions, ...(query.result.value?.items || props.options), ...props.options.filter(item => item.id === model.value), ...(chosen.value?.id === model.value ? [chosen.value] : [])].map(item => [item.id, item])).values()]);
const text = item => item.identifier ? `${item.identifier} · ${item.title}` : item.email ? `${item.name} · ${item.email}` : item.name;
function choose(event) { const item = options.value.find(item => item.id === event.target.value); if (item) {
  chosen.value = item;
  emit('choose', item);
} }
</script>
<template>
  <div class="field paged-select">
    <label :for="id">{{ label }}</label>
    <div class="paged-select-control"><select :id="id" v-model="model" :aria-label="label" :required="required" :disabled="disabled" @focus="query.open" @change="choose"><option value="">{{ emptyLabel }}</option><option v-for="item in options" :key="item.id" :value="item.id">{{ text(item) }}</option><option v-if="model && !options.some(item => item.id === model)" :value="model">{{ model }}（目前選擇）</option></select><button type="button" class="icon-button" :aria-label="`搜尋${label}選項`" :aria-expanded="expanded" :disabled="disabled" @click="expanded = !expanded; query.open()"><Icon name="search" :size="17" /></button></div>
    <div v-if="expanded" class="paged-select-search"><input v-model="query.search.value" :aria-label="`搜尋${label}`" placeholder="輸入名稱或編號…" :disabled="disabled" /><div class="pagination"><button type="button" class="text-button" :disabled="query.page.value <= 1 || query.loading.value" @click="query.page.value--">上一頁</button><span>{{ query.page.value }} / {{ query.result.value?.lastPage || 1 }}</span><button type="button" class="text-button" :disabled="!query.result.value || query.page.value >= query.result.value.lastPage || query.loading.value" @click="query.page.value++">下一頁</button></div></div>
    <span v-if="query.loading.value" role="status">載入選項中…</span><span v-if="query.error.value" class="field-error" role="alert">{{ query.error.value }}<button type="button" class="text-button" @click="query.reload">重試</button></span>
  </div>
</template>
<style scoped>
.paged-select-control { display: flex; gap: 4px; align-items: center; min-width: 0; }
.paged-select-control select { flex: 1; min-width: 0; }
.paged-select-search { display: grid; gap: 8px; }
.paged-select-search input { min-width: 0; width: 100%; }
</style>
