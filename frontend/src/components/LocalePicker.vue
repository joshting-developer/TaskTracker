<script setup>
import { locale, setLocale, supportedLocales, t } from '../i18n/index.js';
defineProps({ menu: Boolean });
</script>
<template>
  <div v-if="menu" class="locale-menu" role="group" :aria-label="t('語言')">
    <span class="locale-caption" aria-hidden="true">{{ t('語言') }}</span>
    <button v-for="option in supportedLocales" :key="option.id" type="button" role="menuitemradio" tabindex="-1" :aria-checked="locale === option.id" :lang="option.id" @click="setLocale(option.id)">{{ option.name }}</button>
  </div>
  <label v-else class="locale-picker"><span>{{ t('語言') }}</span><select :value="locale" :aria-label="t('選擇語言')" @change="setLocale($event.target.value)"><option v-for="option in supportedLocales" :key="option.id" :value="option.id" :lang="option.id">{{ option.name }}</option></select></label>
</template>
<style scoped>
.locale-picker { display: flex; align-items: center; justify-content: flex-end; gap: 10px; margin-bottom: 16px; font-size: 14px; color: #5f6673; }
.locale-picker select { min-height: 44px; max-width: 180px; padding: 8px 30px 8px 10px; border: 1px solid #cbd0db; border-radius: 6px; background: #fff; color: #242936; font: inherit; }
.locale-picker select:focus-visible, .locale-menu button:focus-visible { outline: 2px solid #6654c5; outline-offset: 2px; }
.locale-menu { padding: 8px 0; border-top: 1px solid #dadde5; margin-top: 4px; }
.locale-caption { display: block; padding: 4px 12px; font-size: 12px; color: #5f6673; }
.locale-menu button { width: 100%; min-height: 44px; padding: 10px 12px; text-align: left; border-radius: 4px; font-size: 14px; }
.locale-menu button:hover, .locale-menu button[aria-checked="true"] { background: #f1eff8; }
.locale-menu button[aria-checked="true"] { font-weight: 650; }
</style>
