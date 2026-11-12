import { createApp } from 'vue';
import { createVuetify } from 'vuetify';
import { zhHant } from 'vuetify/locale';
import 'vuetify/styles';
import { aliases, mdi } from 'vuetify/iconsets/mdi-svg';
import App from './App.vue';
import './styles.css';
import './workspace.css';

const vuetify = createVuetify({
  icons: { defaultSet: 'mdi', aliases, sets: { mdi } },
  locale: { locale: 'zhHant', messages: { zhHant } },
  theme: { defaultTheme: 'tasktracker', themes: { tasktracker: { dark: false, colors: { primary: '#6654c5', secondary: '#397e71', background: '#ffffff' } } } },
  defaults: { VBtn: { rounded: 'lg', elevation: 0 }, VTextField: { variant: 'outlined', density: 'comfortable' } },
});

createApp(App).use(vuetify).mount('#app');
