import { createApp, watch } from 'vue';
import { createVuetify } from 'vuetify';
import 'vuetify/styles';
import { locale, t } from '../i18n/index.js';
import AdminApp from './AdminApp.vue';
import './admin.css';

watch(locale, value => {
  document.documentElement.lang = value;
  document.title = `Pavenro · ${t('管理後台')}`;
}, { immediate: true });
createApp(AdminApp).use(createVuetify({
  theme: { defaultTheme: 'admin', themes: { admin: { dark: false, colors: { primary: '#6654c5', background: '#f7f8fa', surface: '#ffffff' } } } },
  defaults: { VBtn: { elevation: 0, rounded: 'lg', height: 44 } },
})).mount('#admin');
