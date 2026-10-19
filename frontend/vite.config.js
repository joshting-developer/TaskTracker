import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';

const proxy = { '/api': { target: process.env.API_PROXY_TARGET || 'http://127.0.0.1:3002', changeOrigin: false } };

export default defineConfig({
  plugins: [vue(), vuetify({ autoImport: true })],
  base: './',
  publicDir: false,
  server: { port: 5174, strictPort: true, proxy, watch: { usePolling: process.env.VITE_USE_POLLING === 'true' } },
  preview: { port: 4174, strictPort: true, proxy },
  build: {
    outDir: 'dist', sourcemap: false,
  },
});
