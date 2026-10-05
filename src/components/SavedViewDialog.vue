<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { api } from '../api/index.js';
import Icon from './Icon.vue';
const props = defineProps({ project: Object, filters: Object, savedView: Object });
const emit = defineEmits(['close', 'saved']);
const dialog = ref(null), busy = ref(false), error = ref('');
const capturedFilters = JSON.parse(JSON.stringify(props.filters));
const baseRevision = props.savedView?.revision;
const form = reactive({ name: props.savedView?.name || '', visibility: props.savedView?.visibility || 'private' });
const initial = JSON.stringify(form);
const dirty = computed(() => JSON.stringify(form) !== initial);
let pending;
function close() { if (!busy.value && (!dirty.value || window.confirm('還有未儲存的視圖設定, 確定要關閉嗎？'))) emit('close'); }
function beforeUnload(event) { if (dirty.value) { event.preventDefault(); event.returnValue = ''; } }
onMounted(() => { dialog.value.showModal(); window.addEventListener('beforeunload', beforeUnload); });
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
async function save() {
  if (busy.value) return;
  busy.value = true; error.value = '';
  const payload = { ...form, projectId: props.project.id, filters: capturedFilters, ...(props.savedView ? { id: props.savedView.id, expectedRevision: baseRevision } : {}) };
  const signature = JSON.stringify(payload);
  if (pending?.signature !== signature) pending = { signature, requestId: crypto.randomUUID() };
  try { emit('saved', await api[props.savedView ? 'updateSavedView' : 'createSavedView']({ ...payload, requestId: pending.requestId })); }
  catch (failure) { error.value = failure.message; }
  finally { busy.value = false; }
}
</script>
<template>
  <dialog ref="dialog" class="saved-view-dialog" aria-labelledby="saved-view-heading" @cancel.prevent="close"><header class="section-title"><div><div class="eyebrow">A SHORTCUT TO YOUR NEXT STEP</div><h2 id="saved-view-heading">{{ savedView ? '更新常用視圖' : '保存常用視圖' }}</h2></div><button class="icon-button" aria-label="關閉視圖設定" :disabled="busy" @click="close"><Icon name="close" /></button></header><p class="subtle">{{ savedView ? '用目前的篩選條件更新這個視圖, 分享範圍也可以調整' : '保存目前專案的篩選、排序與清單／看板方式' }}</p><div v-if="error" class="error-box" role="alert">{{ error }}</div><form @submit.prevent="save"><label class="field">視圖名稱<input v-model="form.name" placeholder="例如：我的未完成 Bug" maxlength="60" required :disabled="busy" /></label><label class="field">分享範圍<select v-model="form.visibility" aria-label="視圖分享範圍" :disabled="busy"><option value="private">只有我</option><option value="team">團隊共用</option></select></label><p class="view-share-note">{{ form.visibility === 'team' ? '目前工作空間內的成員可以使用, 只有你能更新或移除。我的任務依使用視圖的人判斷' : '只有你的帳號可以查看與使用, 換瀏覽器登入也會保留' }}</p><button class="button primary" :disabled="busy"><Icon name="check" :size="17" />{{ busy ? '儲存中…' : savedView ? '儲存視圖更新' : '儲存視圖' }}</button></form></dialog>
</template>
