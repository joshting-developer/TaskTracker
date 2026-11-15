<script setup>
import { t } from '../i18n/index.js';
import '../guest-topbar.css';
defineProps({ current: { type: String, default: '' }, signupAvailable: { type: Boolean, default: true } });
</script>
<template>
  <!-- TT-298：與首頁index.html相同結構；只在未登入畫面使用。首頁本身就是介紹不放「產品介紹」, 登入頁才放, 讓人能明確回到介紹 -->
  <header class="guest-topbar">
    <a class="guest-brand" href="/" :aria-label="t('TaskTracker 首頁')"><span class="guest-brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>TaskTracker</a>
    <nav class="guest-nav" :aria-label="t('訪客導覽')">
      <a v-if="current === 'login'" href="/">{{ t('產品介紹') }}</a>
      <a href="/login" :aria-current="current === 'login' ? 'page' : undefined">{{ t('登入') }}</a>
      <a v-if="signupAvailable" class="guest-cta" href="/login?mode=register" :aria-label="t('建立工作空間')"><span class="guest-cta-extra">{{ t('免費') }}</span><span class="guest-cta-full">{{ t('建立工作空間') }}</span><span class="guest-cta-short">{{ t('註冊') }}</span></a>
    </nav>
  </header>
</template>
