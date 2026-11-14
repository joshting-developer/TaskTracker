import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

// TT-207／208：公開頁需要絕對網址的canonical／og標籤由建置時VITE_SITE_ORIGIN填入；未設定時移除這些標籤，不寫死網域。
const siteOrigin = (process.env.VITE_SITE_ORIGIN || '').replace(/\/+$/, '');
if (siteOrigin && !/^https?:\/\/[a-z0-9.-]+(:\d+)?$/i.test(siteOrigin)) throw new Error('VITE_SITE_ORIGIN必須是不含路徑的origin，例如https://tasks.example.com');
const featureSlugs = ['tasks', 'collaboration', 'time', 'integrations'];
const publicPaths = ['/', ...featureSlugs.map(slug => `/features/${slug}/`)];
const publicOrigin = {
  name: 'tasktracker-public-origin',
  transformIndexHtml: html => siteOrigin ? html.replaceAll('__SITE_ORIGIN__', siteOrigin) : html.replace(/^[ \t]*<(?:link|meta)\b[^>]*__SITE_ORIGIN__[^>]*>\r?\n/gm, ''),
  // sitemap包含首頁與四個公開功能頁, 不收錄登入及工作區。
  generateBundle() {
    this.emitFile({ type: 'asset', fileName: 'og-image.png', source: readFileSync(fileURLToPath(new URL('./public-assets/og-image.png', import.meta.url))) });
    this.emitFile({ type: 'asset', fileName: 'robots.txt', source: `User-agent: *\nAllow: /\nDisallow: /api/\nDisallow: /app/\n${siteOrigin ? `Sitemap: ${siteOrigin}/sitemap.xml\n` : ''}` });
    if (siteOrigin) this.emitFile({ type: 'asset', fileName: 'sitemap.xml', source: `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${publicPaths.map(path => `  <url><loc>${siteOrigin}${path}</loc></url>`).join('\n')}\n</urlset>\n` });
  },
};

// TT-297：與正式nginx相同的入口路由，讓dev／preview（E2E）行為一致：/login用工作空間殼層、/app補斜線、舊/about轉首頁。
const entryRoutes = (shell) => (request, response, next) => {
  const [path, query = ''] = request.url.split('?');
  const redirect = { '/about': '/', '/about/': '/', '/app': '/app/', '/login/': '/login' }[path];
  if (redirect) { response.statusCode = 301; response.setHeader('Location', redirect + (query && redirect !== '/' ? `?${query}` : '')); return response.end(); }
  if (path === '/login') request.url = shell + (query ? `?${query}` : '');
  next();
};
const entryRouting = {
  name: 'tasktracker-entry-routes',
  configureServer: server => { server.middlewares.use(entryRoutes('/app/index.html')); },
  configurePreviewServer: server => { server.middlewares.use(entryRoutes('/app/index.html')); },
};

const proxy = {
  '/api': { target: process.env.API_PROXY_TARGET || 'http://127.0.0.1:3002', changeOrigin: false },
  '/reverb': { target: process.env.REVERB_PROXY_TARGET || 'http://127.0.0.1:8080', ws: true, changeOrigin: false, rewrite: path => path.replace(/^\/reverb(?=\/)/, '') },
};

export default defineConfig({
  plugins: [vue(), vuetify({ autoImport: true }), publicOrigin, entryRouting],
  base: './',
  publicDir: false,
  // 預先最佳化目前使用的Vuetify元件，避免首次開啟延遲表單時dev server重載草稿。
  optimizeDeps: { include: ['vuetify/components/VApp', 'vuetify/components/VAlert', 'vuetify/components/VBtn', 'vuetify/components/VCard', 'vuetify/components/VTextField', 'vuetify/components/VTextarea'] },
  server: { port: 5174, strictPort: true, proxy, watch: { usePolling: process.env.VITE_USE_POLLING === 'true' } },
  preview: { port: 4174, strictPort: true, proxy },
  build: {
    outDir: 'dist', sourcemap: false,
    // 兩個HTML入口會讓polyfill成為共用chunk而多一個請求；支援的瀏覽器皆原生支援modulepreload。
    modulePreload: { polyfill: false },
    // TT-297：首頁/是靜態產品介紹；/app/（亦服務/login）是工作空間SPA殼層；同一映像，不另做SSR。
    rollupOptions: { input: { landing: fileURLToPath(new URL('./index.html', import.meta.url)), app: fileURLToPath(new URL('./app/index.html', import.meta.url)), ...Object.fromEntries(featureSlugs.map(slug => [slug, fileURLToPath(new URL(`./features/${slug}/index.html`, import.meta.url))])) } },
  },
});
