import { createApp, watch } from 'vue';
import { createVuetify } from 'vuetify';
import { en, zhHant } from 'vuetify/locale';
import { locale } from './i18n/index.js';
import 'vuetify/styles';
import { aliases, mdi } from 'vuetify/iconsets/mdi-svg';
import App from './App.vue';
import './styles.css';
import './workspace.css';

const vuetify = createVuetify({
  icons: { defaultSet: 'mdi', aliases, sets: { mdi } },
  locale: { locale: locale.value === 'en' ? 'en' : 'zhHant', messages: { en, zhHant } },
  theme: { defaultTheme: 'tasktracker', themes: { tasktracker: { dark: false, colors: { primary: '#6654c5', secondary: '#397e71', background: '#ffffff' } } } },
  defaults: { VBtn: { rounded: 'lg', elevation: 0 }, VTextField: { variant: 'outlined', density: 'comfortable' } },
});

watch(locale, value => {
  vuetify.locale.current.value = value === 'en' ? 'en' : 'zhHant';
  document.documentElement.lang = value;
}, { immediate: true });

createApp(App).use(vuetify).mount('#app');
