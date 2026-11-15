<script setup>
import { t } from '../i18n/index.js';
import { computed, ref, watch } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({ remote: Boolean, total: Number, projects: Array, members: Array, canCreate: { type: Boolean, default: true }, loading: Boolean, error: String });
const emit=defineEmits(['create', 'edit', 'open', 'retry', 'search']);
const search = ref('');
watch(search, value=>{if(props.remote)emit('search',value);});
const cards = computed(() => props.remote ? props.projects : props.projects.filter(project => [project.name, project.code, project.description].some(value => (value || '').toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase()))));
function leadName(project) {
  if (project.leadName) return project.leadName;
  if (!project.leadId) return t('未設定');
  return props.members.find(member => member.id === project.leadId && !member.disabledAt)?.name || t('成員已無法使用');
}
</script>

<template>
  <section class="project-home" :aria-label="t('專案列表')">
    <div v-if="error" class="error-box" role="alert">{{ t(error) }}<button class="text-button" @click="$emit('retry')">{{ t('重新載入') }}</button></div>
    <div v-if="loading && !projects.length && !search" class="empty-state"><span class="loader"></span><h2>{{ t('正在載入專案…') }}</h2></div>
    <div v-else-if="!projects.length && !search" class="empty-state welcome-state project-welcome">

      <h2>{{ canCreate ? t('尚無專案') : t('尚未加入任何專案') }}</h2>

      <div class="empty-actions"><button v-if="canCreate" class="button primary" @click="$emit('create')"><Icon name="plus" :size="17" />{{ t('建立第一個專案') }}</button></div>

    </div>
    <template v-else>
      <div class="project-home-toolbar"><label class="search-field"><Icon name="search" :size="18" /><input v-model="search" :placeholder="t('搜尋專案名稱或代碼…')" :aria-label="t('搜尋專案')" /><button v-if="search" class="icon-button" :aria-label="t('清除專案搜尋')" @click="search = ''"><Icon name="close" :size="15" /></button></label><span class="project-list-count">{{ total ?? cards.length }} {{ t('個專案') }}</span></div>
      <div class="project-grid">
        <article v-for="project in cards" :key="project.id" class="project-card-shell"><button class="project-card" :aria-label="t('進入專案：{0}', [project.name])" @click="$emit('open', project.id)">
          <div class="project-card-top"><span class="project-card-icon" :style="{ color: project.color }"><Icon name="folder" :size="20" /></span><span class="project-code">{{ project.code }}</span></div>
          <h2>{{ project.name }}</h2><p>{{ project.description || t('尚未填寫專案介紹') }}</p>
          <div class="project-lead"><Icon name="user" :size="16" /><span>{{ t('負責人') }}</span><strong>{{ leadName(project) }}</strong></div>
          <div class="project-card-bottom"><span>{{ t('進入專案') }} <Icon name="chevron" :size="13" /></span></div>
        </button><button v-if="project.permissions?.includes('project.update')" class="text-button project-edit" :aria-label="t('編輯專案：{0}', [project.name])" @click="$emit('edit', project)">{{ t('編輯專案') }}</button></article>
        <button v-if="!search && canCreate" class="project-card new-project-card" @click="$emit('create')"><span class="new-project-icon"><Icon name="plus" :size="25" /></span><strong>{{ t('建立新專案') }}</strong></button>
      </div>
      <div v-if="!cards.length" class="empty-state"><span class="empty-icon"><Icon name="search" :size="30" /></span><h2>{{ t('沒有找到這個專案') }}</h2><button class="button secondary" @click="search = ''">{{ t('清除搜尋') }}</button></div>
    </template>
  </section>
</template>
