import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [vue()],
  base: './',
  publicDir: false,
  server: { port: 5174, strictPort: true, proxy: { '/api': 'http://127.0.0.1:3002' } },
  preview: { port: 4174, strictPort: true, proxy: { '/api': 'http://127.0.0.1:3002' } },
  build: {
    outDir: 'dist', sourcemap: false,
  },
});
