<script setup>
import '../guest-topbar.css';
defineProps({ current: { type: String, required: true } });
</script>
<template>
  <!-- TT-206／297：與首頁index.html相同結構；只在未登入畫面使用。 -->
  <header class="guest-topbar">
    <a class="guest-brand" href="/" aria-label="TaskTracker 首頁"><span class="guest-brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>TaskTracker</a>
    <nav class="guest-nav" aria-label="訪客導覽">
      <a href="/" :aria-current="current === 'about' ? 'page' : undefined">產品介紹</a>
      <a href="/login" :aria-current="current === 'login' ? 'page' : undefined">登入</a>
    </nav>
  </header>
</template>
