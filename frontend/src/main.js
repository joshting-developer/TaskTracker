import { createApp } from 'vue';
import { createVuetify } from 'vuetify';
import { zhHant } from 'vuetify/locale';
import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';
import App from './App.vue';
import './styles.css';

const vuetify = createVuetify({
  locale: { locale: 'zhHant', messages: { zhHant } },
  theme: { defaultTheme: 'tasktracker', themes: { tasktracker: { dark: false, colors: { primary: '#8d76bd', secondary: '#64b0a0', background: '#faf9fd' } } } },
  defaults: { VBtn: { rounded: 'lg', elevation: 0 }, VTextField: { variant: 'outlined', density: 'comfortable' } },
});

createApp(App).use(vuetify).mount('#app');
