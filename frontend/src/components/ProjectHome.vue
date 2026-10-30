<script setup>
import { computed, ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({ projects: Array, members: Array, canCreate: { type: Boolean, default: true }, loading: Boolean, error: String, demoLoading: Boolean });
defineEmits(['create', 'edit', 'open', 'demo', 'retry']);
const search = ref('');
const cards = computed(() => props.projects.filter(project => [project.name, project.code, project.description].some(value => (value || '').toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase()))));
function leadName(project) {
  if (!project.leadId) return '未設定';
  return props.members.find(member => member.id === project.leadId && !member.disabledAt)?.name || '成員已無法使用';
}
</script>

<template>
  <section class="project-home" aria-label="專案列表">
    <div v-if="error" class="error-box" role="alert">{{ error }}<button class="text-button" @click="$emit('retry')">重新載入</button></div>
    <div v-if="loading" class="empty-state"><span class="loader"></span><h2>正在整理你的專案</h2></div>
    <div v-else-if="!projects.length" class="empty-state welcome-state project-welcome">
      <div class="empty-illustration"><span class="illustration-card"><Icon name="folder" :size="26" /><i></i><i></i></span><span class="illustration-spark">✦</span></div>
      <div class="eyebrow">EVERY IDEA STARTS WITH A PROJECT</div>
      <h2>{{ canCreate ? '你的下一個計畫, 從這裡開始' : '尚未加入任何專案' }}</h2>
      <p v-if="canCreate">先為你的計畫建立一個專案<br />點進專案後, 就能新增任務與使用看板</p><p v-else>你已加入工作空間, 請由管理者將你加入專案並授予權限</p>
      <div class="empty-actions"><button v-if="canCreate" class="button primary" @click="$emit('create')"><Icon name="plus" :size="17" />建立第一個專案</button><button v-if="canCreate" class="button secondary" :disabled="demoLoading" @click="$emit('demo')">{{ demoLoading ? '準備中…' : '試試示範專案' }}<Icon name="arrow" :size="16" /></button></div>
      <span v-if="canCreate" class="demo-note">示範內容會獨立建立, 可以自由修改</span>
    </div>
    <template v-else>
      <div class="project-home-toolbar"><label class="search-field"><Icon name="search" :size="18" /><input v-model="search" placeholder="搜尋專案名稱或代碼…" aria-label="搜尋專案" /><button v-if="search" class="icon-button" aria-label="清除專案搜尋" @click="search = ''"><Icon name="close" :size="15" /></button></label><span class="project-list-count">{{ cards.length }} 個專案</span></div>
      <div class="project-grid">
        <article v-for="project in cards" :key="project.id" class="project-card-shell"><button class="project-card" :aria-label="`進入專案：${project.name}`" @click="$emit('open', project.id)">
          <div class="project-card-top"><span class="project-card-icon" :style="{ color: project.color, background: `${project.color}16` }"><Icon name="folder" :size="23" /></span><span class="project-code">{{ project.code }}</span><Icon name="arrow" :size="19" class="project-enter-icon" /></div>
          <h2>{{ project.name }}</h2><p>{{ project.description || '尚未填寫專案介紹' }}</p>
          <div class="project-lead"><Icon name="user" :size="16" /><span>負責人</span><strong>{{ leadName(project) }}</strong></div>
          <div class="project-card-bottom"><span>進入專案 <Icon name="chevron" :size="13" /></span></div>
        </button><button v-if="project.permissions?.includes('project.update')" class="text-button project-edit" :aria-label="`編輯專案：${project.name}`" @click="$emit('edit', project)">編輯專案</button></article>
        <button v-if="!search && canCreate" class="project-card new-project-card" @click="$emit('create')"><span class="new-project-icon"><Icon name="plus" :size="25" /></span><strong>建立新專案</strong><span>給下一個計畫一個專屬空間</span></button>
      </div>
      <div v-if="!cards.length" class="empty-state"><span class="empty-icon"><Icon name="search" :size="30" /></span><h2>沒有找到這個專案</h2><p>換個關鍵字, 或建立一個新的專案</p><button class="button secondary" @click="search = ''">清除搜尋</button></div>
    </template>
  </section>
</template>
