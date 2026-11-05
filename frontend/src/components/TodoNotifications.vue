<script setup>
import { onMounted, ref } from 'vue';
import Icon from './Icon.vue';
defineProps({ notifications: Array });
const emit = defineEmits(['close', 'open']);
const dialog = ref(null);
const labels = { assigned: '交給你一件待辦', edited: '更新待辦內容', completed: '完成你的交辦', reopened: '重新開啟待辦', returned: '將待辦退回給你', canceled: '取消待辦', converted: '將待辦轉成正式卡片' };
onMounted(() => dialog.value.showModal());
</script>
<template>
  <dialog ref="dialog" class="todo-notifications" aria-labelledby="todo-notification-heading" @cancel.prevent="emit('close')" @click="event => { if (event.target === dialog) emit('close'); }"><header><h2 id="todo-notification-heading">待辦通知</h2><button class="icon-button" aria-label="關閉待辦通知" @click="emit('close')"><Icon name="close" /></button></header><p v-if="!notifications.length" class="subtle">目前沒有待辦通知</p><div class="todo-notification-list"><button v-for="item in notifications" :key="item.id" :class="{ unread: !item.readAt }" @click="emit('open', item)"><span class="notification-state">{{ item.readAt ? '已讀' : '未讀' }}</span><strong>{{ item.actor }} {{ labels[item.event] }}</strong><span>{{ item.title }}</span><time>{{ new Date(item.createdAt).toLocaleString('zh-TW', { timeZone: 'Asia/Taipei' }) }}</time></button></div></dialog>
</template>
<style scoped>
.todo-notifications { position: fixed; inset: 0 0 0 auto; margin: 0; width: min(480px,100vw); height: 100dvh; max-height: 100dvh; max-width: 100vw; border: 0; border-radius: 0; background: #fff; color: #242936; padding: 24px; overflow: auto; }.todo-notifications::backdrop { background: #171b2b66; }.todo-notifications header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }.todo-notifications h2 { font-size: 20px; }.todo-notification-list button { display: block; width: 100%; text-align: left; padding: 16px 12px; border-bottom: 1px solid #daddE5; border-left: 3px solid transparent; }.todo-notification-list .unread { background: #f6f4ff; border-left-color: #6653bd; }.todo-notification-list strong { display: block; font-size: 14px; line-height: 1.8; margin-bottom: 6px; }.todo-notification-list span { display: block; font-size: 14px; overflow-wrap: anywhere; line-height: 1.8; }.todo-notification-list .notification-state { font-size: 12px; color: #5844a0; }.todo-notification-list time { display: block; font-size: 12px; color: #5f6673; margin-top: 10px; line-height: 1.8; }
</style>
