import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';
import { fileURLToPath } from 'node:url';

// TT-207／208：公開頁需要絕對網址的canonical／og標籤由建置時VITE_SITE_ORIGIN填入；未設定時移除這些標籤，不寫死網域。
const siteOrigin = (process.env.VITE_SITE_ORIGIN || '').replace(/\/+$/, '');
if (siteOrigin && !/^https?:\/\/[a-z0-9.-]+(:\d+)?$/i.test(siteOrigin)) throw new Error('VITE_SITE_ORIGIN必須是不含路徑的origin，例如https://tasks.example.com');
const publicOrigin = {
  name: 'tasktracker-public-origin',
  transformIndexHtml: html => siteOrigin ? html.replaceAll('__SITE_ORIGIN__', siteOrigin) : html.replace(/^[ \t]*<(?:link|meta)\b[^>]*__SITE_ORIGIN__[^>]*>\r?\n/gm, ''),
};

const proxy = {
  '/api': { target: process.env.API_PROXY_TARGET || 'http://127.0.0.1:3002', changeOrigin: false },
  '/reverb': { target: process.env.REVERB_PROXY_TARGET || 'http://127.0.0.1:8080', ws: true, changeOrigin: false, rewrite: path => path.replace(/^\/reverb(?=\/)/, '') },
};

export default defineConfig({
  plugins: [vue(), vuetify({ autoImport: true }), publicOrigin],
  base: './',
  publicDir: false,
  // 預先最佳化目前使用的Vuetify元件，避免首次開啟延遲表單時dev server重載草稿。
  optimizeDeps: { include: ['vuetify/components/VApp', 'vuetify/components/VAlert', 'vuetify/components/VBtn', 'vuetify/components/VCard', 'vuetify/components/VTextField', 'vuetify/components/VTextarea'] },
  server: { port: 5174, strictPort: true, proxy, watch: { usePolling: process.env.VITE_USE_POLLING === 'true' } },
  preview: { port: 4174, strictPort: true, proxy },
  build: {
    outDir: 'dist', sourcemap: false,
    // 公開介紹頁是同一映像的第二個靜態HTML入口（/about/），不另做SSR。
    rollupOptions: { input: { main: fileURLToPath(new URL('./index.html', import.meta.url)), about: fileURLToPath(new URL('./about/index.html', import.meta.url)) } },
  },
});
