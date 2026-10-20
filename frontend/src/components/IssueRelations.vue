<script setup>
import { computed, ref } from 'vue';
import { categoryFor, statusesFor } from '../domain/model.js';
import Icon from './Icon.vue';
const props = defineProps({ issue: Object, issues: Array, project: Object, type: String, parentId: String, relatedIds: Array, saving: Boolean });
const emit = defineEmits(['parent', 'related', 'open', 'create-child']);
const picker = ref('');
const search = ref('');
const childTitle = ref('');
const creatingChild = ref(false);
const parent = computed(() => props.issues.find(item => item.id === props.parentId));
const children = computed(() => props.issue ? props.issues.filter(item => item.parentIssueId === props.issue.id) : []);
const done = computed(() => children.value.filter(item => categoryFor(item, props.project) === 'done').length);
const related = computed(() => (props.relatedIds || []).map(id => props.issues.find(item => item.id === id)).filter(Boolean));
const candidates = computed(() => props.issues.filter(item => {
  if (item.projectId !== props.project.id || item.id === props.issue?.id) return false;
  if (picker.value === 'parent' && (item.type === 'epic' || item.parentIssueId || item.id === props.parentId)) return false;
  if (picker.value === 'related' && props.relatedIds.includes(item.id)) return false;
  return `${item.identifier} ${item.title}`.toLowerCase().includes(search.value.trim().toLowerCase());
}).slice(0, 30));
function openPicker(mode) { picker.value = picker.value === mode ? '' : mode; search.value = ''; }
function select(item) {
  if (picker.value === 'parent') { emit('parent', item.id); picker.value = ''; }
  else emit('related', [...props.relatedIds, item.id].sort());
}
function resetChild() { childTitle.value = ''; creatingChild.value = false; }
defineExpose({ dirty: computed(() => Boolean(childTitle.value.trim())), resetChild });
const state = item => statusesFor(props.project).find(status => status.id === item.state)?.label || item.state;
</script>
<template>
  <section class="issue-relations" aria-label="任務關係">
    <div v-if="type !== 'epic' && (parentId || !children.length)" class="relation-parent">
      <template v-if="parentId"><span>父任務</span><button v-if="parent" class="relation-link" :aria-label="`開啟父任務 ${parent.identifier}`" :disabled="saving" @click="emit('open', parent)">{{ parent.identifier }} · {{ parent.title }}</button><span v-else>任務已無法存取</span><button class="text-button" :disabled="saving" @click="emit('parent', '')">解除父任務</button></template>
      <button v-else class="text-button" :aria-expanded="picker === 'parent'" :disabled="saving" @click="openPicker('parent')">設定父任務</button>
    </div>
    <section v-if="issue && type !== 'epic' && !parentId" class="relation-group" aria-label="子任務">
      <header><h3>子任務 <small v-if="children.length">{{ done }} / {{ children.length }} 完成</small></h3><button class="text-button" :disabled="saving" @click="creatingChild = !creatingChild">{{ creatingChild ? '收起新增' : '新增子任務' }}</button></header>
      <form v-if="creatingChild" class="child-create" @submit.prevent="emit('create-child', childTitle)"><label class="sr-only" for="child-title">子任務標題</label><input id="child-title" v-model="childTitle" placeholder="輸入子任務標題…" maxlength="180" required :disabled="saving" /><button class="button secondary" :disabled="saving || !childTitle.trim()">建立子任務</button><p>建立後可開啟設定負責人、狀態與工時</p></form>
      <div v-for="item in children" :key="item.id" class="relation-row"><button class="relation-link" :aria-label="`開啟子任務 ${item.identifier}`" :disabled="saving" @click="emit('open', item)"><span class="identifier">{{ item.identifier }}</span><span>{{ item.title }}</span></button><span class="relation-state">{{ state(item) }}</span></div>
    </section>
    <section class="relation-group" aria-label="關聯任務">
      <header><h3>關聯任務 <small v-if="related.length">{{ related.length }}</small></h3><button class="text-button" :aria-expanded="picker === 'related'" :disabled="saving" @click="openPicker('related')">新增關聯</button></header>
      <div v-for="item in related" :key="item.id" class="relation-row"><button class="relation-link" :aria-label="`開啟關聯任務 ${item.identifier}`" :disabled="saving" @click="emit('open', item)"><span class="identifier">{{ item.identifier }}</span><span>{{ item.title }}</span></button><span class="relation-state">{{ state(item) }}</span><button class="icon-button" :aria-label="`解除關聯 ${item.identifier}`" :disabled="saving" @click="emit('related', relatedIds.filter(id => id !== item.id))"><Icon name="close" :size="16" /></button></div>
    </section>
    <div v-if="picker" class="relation-picker" :aria-label="picker === 'parent' ? '選擇父任務' : '選擇關聯任務'">
      <div class="relation-search"><input v-model="search" :aria-label="picker === 'parent' ? '搜尋父任務' : '搜尋關聯任務'" placeholder="搜尋任務編號或標題…" :disabled="saving" /><button class="icon-button" aria-label="關閉任務選擇" :disabled="saving" @click="picker = ''"><Icon name="close" :size="16" /></button></div>
      <div class="relation-options"><button v-for="item in candidates" :key="item.id" :aria-label="`${picker === 'parent' ? '選為父任務' : '關聯'} ${item.identifier}`" :disabled="saving" @click="select(item)"><span class="identifier">{{ item.identifier }}</span><span>{{ item.title }}</span><span class="relation-state">{{ state(item) }}</span></button><p v-if="!candidates.length">沒有符合條件的任務</p></div>
    </div>
  </section>
</template>
<style scoped>
.issue-relations { margin: 24px 0; border-top: 1px solid #daddE5; padding-top: 16px; color: #343b49; }
.relation-parent, .relation-group header, .relation-row, .relation-search { display: flex; align-items: center; gap: 12px; }
.relation-parent { flex-wrap: wrap; margin-bottom: 14px; font-size: 13px; }
.relation-group + .relation-group { margin-top: 18px; }
.relation-group header { justify-content: space-between; min-height: 32px; }
h3 { margin: 0; font-size: 15px; font-weight: 600; }
h3 small { margin-left: 8px; font-size: 12px; color: #5f6673; font-weight: 400; }
.relation-row { padding: 10px 0; border-bottom: 1px solid #edf0f5; font-size: 14px; }
.relation-link { display: flex; gap: 10px; align-items: baseline; text-align: left; min-width: 0; overflow-wrap: anywhere; }
.relation-link:hover { color: #5844a0; }
.relation-state { margin-left: auto; flex-shrink: 0; font-size: 12px; color: #5f6673; }
input { min-width: 0; width: 100%; padding: 9px 10px; border: 1px solid #bfc6d2; border-radius: 5px; background: #fff; font-size: 14px; color: #242936; }
.relation-picker { margin-top: 12px; border: 1px solid #cbd0db; border-radius: 6px; padding: 10px; }
.relation-search input { flex: 1; }
.relation-options { max-height: 220px; overflow-y: auto; }
.relation-options > button { display: flex; gap: 10px; align-items: center; width: 100%; text-align: left; padding: 12px 4px; border-bottom: 1px solid #edf0f5; font-size: 14px; overflow-wrap: anywhere; }
.relation-options p, .child-create p { margin: 10px 0 0; font-size: 12px; color: #5f6673; }
.child-create { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0; }
.child-create input { flex: 1; }
.child-create p { width: 100%; }
@media (max-width: 700px) { .relation-row { flex-wrap: wrap; gap: 8px; } .relation-link { flex: 1 1 65%; } .relation-options > button { flex-wrap: wrap; } .child-create input { flex-basis: 100%; } }
</style>
