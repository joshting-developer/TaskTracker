import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

export default defineConfig(({ mode }) => {
  const target = process.env.VITE_BUILD_TARGET || (['public', 'app'].includes(mode) ? mode : 'combined');
  if (!['combined', 'public', 'app'].includes(target)) throw new Error('Invalid VITE_BUILD_TARGET');
  const publicOnly = target === 'public', appOnly = target === 'app';
  const appOrigin = (process.env.VITE_APP_ORIGIN || '').replace(/\/+$/, '');
  if (appOrigin && !/^https?:\/\/[a-z0-9.-]+(:\d+)?$/i.test(appOrigin)) throw new Error('VITE_APP_ORIGIN must be an origin without a path');
  if (publicOnly && !appOrigin) throw new Error('Public build requires VITE_APP_ORIGIN');
  // TT-207／208：公開頁需要絕對網址的canonical／og標籤由建置時VITE_SITE_ORIGIN填入；未設定時移除這些標籤，不寫死網域。
  const siteOrigin = (process.env.VITE_SITE_ORIGIN || '').replace(/\/+$/, '');
  if (siteOrigin && !/^https?:\/\/[a-z0-9.-]+(:\d+)?$/i.test(siteOrigin)) throw new Error('VITE_SITE_ORIGIN必須是不含路徑的origin，例如https://tasks.example.com');
  if (publicOnly && siteOrigin === appOrigin) throw new Error('Public and app origins must differ');
  if ((publicOnly || appOnly) && !siteOrigin) throw new Error('Split build requires VITE_SITE_ORIGIN');
  // TT-437：功能子頁已移除, 公開頁只剩首頁；舊網址由 nginx 轉回首頁對應章節。
  const publicPaths = ['/'];
  const deviceIconNames = ['pavenro-app-v1-192.png', 'pavenro-app-v1-512.png', 'pavenro-maskable-v1-512.png'];
  const brandBytes = name => readFileSync(fileURLToPath(new URL(`./public-assets/brand/${name}`, import.meta.url)));
  const manifest = JSON.stringify({ id: '/app/', name: 'Pavenro', short_name: 'Pavenro', start_url: '/app/', scope: '/',
    display: 'standalone', background_color: '#23242b', theme_color: '#23242b',
    icons: deviceIconNames.map((name, index) => ({ src: `/brand/${name}`, sizes: index === 0 ? '192x192' : '512x512', type: 'image/png', purpose: index === 2 ? 'maskable' : 'any' })) });
  // Serve identical static bytes in dev, preview and the built runtime.
  const staticAssets = {
    '/og-image.png': { source: readFileSync(fileURLToPath(new URL('./public-assets/brand/pavenro-og-v1.png', import.meta.url))), type: 'image/png', cache: 'no-cache' },
    '/site.webmanifest': { source: manifest, type: 'application/manifest+json', cache: 'no-cache' },
    '/favicon.ico': { source: brandBytes('pavenro-v1.ico'), type: 'image/x-icon', cache: 'public, max-age=86400' },
    ...Object.fromEntries(deviceIconNames.map(name => [`/brand/${name}`, { source: brandBytes(name), type: 'image/png', cache: 'public, max-age=31536000, immutable' }])),
  };
  const publicOrigin = {
    name: 'tasktracker-public-origin',
    transformIndexHtml(html) {
      html = siteOrigin ? html.replaceAll('__SITE_ORIGIN__', siteOrigin) : html.replace(/^[ \t]*<(?:link|meta)\b[^>]*__SITE_ORIGIN__[^>]*>\r?\n/gm, '');
      if (publicOnly) {
        html = html.replace(/<script data-entry-redirect>[\s\S]*?<\/script>/, `<script>if(location.hash.startsWith('#/'))location.replace(${JSON.stringify(appOrigin + '/app/')}+location.hash)</script>`);
        html = html.replace(/<a\b[^>]*data-member[^>]*>[\s\S]*?<\/a>/g, '').replace(/<link rel="manifest"[^>]*>/g, '');
        html = html.replace(/href="(\/(?:login|app|admin)(?:[/?][^"]*)?|\/api\/public\/docs[^"]*)"/g, (_, path) => `href="${appOrigin}${path}"`);
      }
      return html;
    },
    // sitemap只收錄首頁, 不收錄登入及工作區。
    generateBundle: { order: 'post', handler(_, bundle) {
      if (appOnly) {
        this.emitFile({ type: 'asset', fileName: 'index.html', source: bundle['app/index.html'].source });
      }
      if (publicOnly) {
        this.emitFile({ type: 'asset', fileName: '404.html', source: '<!doctype html><html lang="en"><meta charset="utf-8"><title>Page not found | Pavenro</title><h1>Page not found</h1><a href="/">Pavenro</a></html>' });
        this.emitFile({ type: 'asset', fileName: '_redirects', source: `/login ${appOrigin}/login 302\n/login/ ${appOrigin}/login 302\n/app ${appOrigin}/app/ 302\n/app/* ${appOrigin}/app/:splat 302\n/admin ${appOrigin}/admin/ 302\n/admin/* ${appOrigin}/admin/:splat 302\n${['tasks', 'collaboration', 'time', 'integrations'].map(slug => `/features/${slug} /#${slug} 301\n/features/${slug}/ /#${slug} 301\n`).join('')}/features/* / 301\n` });
      }
      for (const [path, asset] of Object.entries(staticAssets).filter(([path]) => !publicOnly || path !== '/site.webmanifest')) this.emitFile({ type: 'asset', fileName: path.slice(1), source: asset.source });
      this.emitFile({ type: 'asset', fileName: 'robots.txt', source: appOnly ? 'User-agent: *\nDisallow: /\n' : `User-agent: *\nAllow: /\nDisallow: /api/\nDisallow: /app/\nDisallow: /admin/\n${siteOrigin ? `Sitemap: ${siteOrigin}/sitemap.xml\n` : ''}` });
      if (siteOrigin && !appOnly) this.emitFile({ type: 'asset', fileName: 'sitemap.xml', source: `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${publicPaths.map(path => `  <url><loc>${siteOrigin}${path}</loc></url>`).join('\n')}\n</urlset>\n` });
    } },
  };

  // TT-297：與正式nginx相同的入口路由，讓dev／preview（E2E）行為一致：/login用工作空間殼層、/app補斜線、舊/about轉首頁。
  const entryRoutes = (shell) => (request, response, next) => {
    const [path, query = ''] = request.url.split('?');
    if (publicOnly && path === '/site.webmanifest') { response.statusCode = 404; return response.end(); }
    if (Object.hasOwn(staticAssets, path)) {
      const asset = staticAssets[path]; response.setHeader('Content-Type', asset.type); response.setHeader('Cache-Control', asset.cache);
      response.setHeader('X-Content-Type-Options', 'nosniff'); return response.end(asset.source);
    }
    if (publicOnly && /^\/(?:login|app|admin)(?:[/?]|$)/.test(request.url)) { response.statusCode = 302; response.setHeader('Location', appOrigin + request.url); return response.end(); }
    const redirect = { '/about': '/', '/about/': '/', '/app': '/app/', '/admin': '/admin/', '/login/': '/login' }[path];
    if (redirect) { response.statusCode = 301; response.setHeader('Location', redirect + (query && redirect !== '/' ? `?${query}` : '')); return response.end(); }
    if (path === '/login') request.url = shell + (query ? `?${query}` : '');
    next();
  };
  const entryRouting = {
    name: 'tasktracker-entry-routes',
    configureServer: server => { server.middlewares.use(entryRoutes('/app/index.html')); },
    configurePreviewServer: server => { server.middlewares.use(entryRoutes('/app/index.html')); },
  };

  const proxy = publicOnly ? {} : {
    '/api': { target: process.env.API_PROXY_TARGET || 'http://127.0.0.1:3002', changeOrigin: false },
    '/reverb': { target: process.env.REVERB_PROXY_TARGET || 'http://127.0.0.1:8080', ws: true, changeOrigin: false, rewrite: path => path.replace(/^\/reverb(?=\/)/, '') },
  };

  return {
    define: { 'import.meta.env.VITE_BUILD_TARGET': JSON.stringify(target), 'import.meta.env.VITE_SITE_ORIGIN': JSON.stringify(siteOrigin) },
    plugins: [vue(), vuetify({ autoImport: true }), publicOrigin, entryRouting],
    base: './',
    publicDir: false,
    // 預先最佳化目前使用的Vuetify元件，避免首次開啟延遲表單時dev server重載草稿。
    optimizeDeps: { include: ['vuetify/components/VApp', 'vuetify/components/VAlert', 'vuetify/components/VBtn', 'vuetify/components/VCard', 'vuetify/components/VTextField', 'vuetify/components/VTextarea'] },
    server: { port: 5174, strictPort: true, proxy, watch: { usePolling: process.env.VITE_USE_POLLING === 'true' } },
    preview: { port: 4174, strictPort: true, proxy },
    build: {
      outDir: target === 'combined' ? 'dist' : `dist-${target}`, sourcemap: false, assetsInlineLimit: 0,
      // 兩個HTML入口會讓polyfill成為共用chunk而多一個請求；支援的瀏覽器皆原生支援modulepreload。
      modulePreload: { polyfill: false },
      // TT-297：首頁/是靜態產品介紹；/app/（亦服務/login）是工作空間SPA殼層；同一映像，不另做SSR。
      rollupOptions: { input: { ...(!appOnly ? { landing: fileURLToPath(new URL('./index.html', import.meta.url)) } : {}), ...(!publicOnly ? { app: fileURLToPath(new URL('./app/index.html', import.meta.url)), admin: fileURLToPath(new URL('./admin/index.html', import.meta.url)) } : {}) } },
    },
  };
});
