import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';

const proxy = {
  '/api': { target: process.env.API_PROXY_TARGET || 'http://127.0.0.1:3002', changeOrigin: false },
  '/reverb': { target: process.env.REVERB_PROXY_TARGET || 'http://127.0.0.1:8080', ws: true, changeOrigin: false, rewrite: path => path.replace(/^\/reverb(?=\/)/, '') },
};

export default defineConfig({
  plugins: [vue(), vuetify({ autoImport: true })],
  base: './',
  publicDir: false,
  // 預先最佳化目前使用的Vuetify元件，避免首次開啟延遲表單時dev server重載草稿。
  optimizeDeps: { include: ['vuetify/components/VApp', 'vuetify/components/VAlert', 'vuetify/components/VBtn', 'vuetify/components/VCard', 'vuetify/components/VTextField', 'vuetify/components/VTextarea'] },
  server: { port: 5174, strictPort: true, proxy, watch: { usePolling: process.env.VITE_USE_POLLING === 'true' } },
  preview: { port: 4174, strictPort: true, proxy },
  build: {
    outDir: 'dist', sourcemap: false,
  },
});
