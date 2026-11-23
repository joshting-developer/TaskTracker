import assert from 'node:assert/strict';
import { readFile, readdir, mkdir, writeFile } from 'node:fs/promises';
import { preview } from 'vite';
import { chromium } from 'playwright';
import { fixtureResponse } from './fixtures/verification.js';
assert.equal(process.env.E2E_ISOLATED, '1');
// 各 Agent 用自己的隔離後端
assert.ok(['http://127.0.0.1:13198', 'http://127.0.0.1:13199'].includes(process.env.E2E_API_URL));
assert.equal(process.env.VITE_SITE_ORIGIN, 'http://127.0.0.1:5200');
assert.equal(process.env.VITE_APP_ORIGIN, 'http://127.0.0.1:5199');
const site = process.env.VITE_SITE_ORIGIN, app = process.env.VITE_APP_ORIGIN;
const out = 'artifacts/tt375'; await mkdir(out, { recursive: true });
const publicFiles = await readdir('dist-public');
assert.ok(publicFiles.includes('404.html') && publicFiles.includes('_redirects'));
assert.ok(!publicFiles.some(name => ['app', 'admin', 'site.webmanifest'].includes(name)));
assert.ok(!(await readdir('dist-app')).includes('features'));
// TT-437：功能子頁已移除, Pages 也把舊網址轉回首頁對應章節
assert.ok(!publicFiles.includes('features'));
const redirects = await readFile('dist-public/_redirects','utf8');
for (const slug of ['tasks', 'collaboration', 'time', 'integrations']) assert.match(redirects, new RegExp(`^/features/${slug}/ /#${slug} 301$`, 'm'));
assert.match(redirects, /^\/features\/\* \/ 301$/m);
assert.equal(await readFile('dist-app/index.html','utf8'), await readFile('dist-app/app/index.html','utf8'));
// TT-377：工作區站要讓搜尋引擎看到舊公開網址的 301, 只擋工作區與 API
assert.equal(await readFile('dist-app/robots.txt','utf8'), 'User-agent: *\nDisallow: /api/\nDisallow: /app/\nDisallow: /admin/\nDisallow: /login\n');
assert.ok(publicFiles.includes('en') && (await readdir('dist-public/en')).includes('index.html'));
assert.match(redirects, /^\/about \/ 301$/m);
const sitemap = await readFile('dist-public/sitemap.xml','utf8');
assert.deepEqual([...sitemap.matchAll(/<loc>([^<]+)<\/loc>/g)].map(match => match[1]), [site+'/', site+'/en/']);
for (const file of await readdir('dist-public/assets')) {
  if (file.endsWith('.js')) assert.doesNotMatch(await readFile('dist-public/assets/'+file,'utf8'), /\/api\/auth\/current|\/api\/csrf-cookie|tasktracker:signed-in|createVuetify/);
}
let publicServer, appServer, browser;
const results = [], apiRequests = [], errors = [];
try {
  publicServer = await preview({ mode: 'public', preview: { host: '127.0.0.1', port: 5200, strictPort: true } });
  appServer = await preview({ mode: 'app', preview: { host: '127.0.0.1', port: 5199, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
  for (const locale of ['zh-TW','en-US']) {
    const context = await browser.newContext({ locale, reducedMotion:'reduce' });
    await context.addInitScript(() => localStorage.setItem('tasktracker:signed-in','1'));
    await context.route(app+'/**', route => route.abort());
    context.on('request', request => { if (new URL(request.url()).pathname.startsWith('/api/')) apiRequests.push(request.url()); });
    const page = await context.newPage(); page.on('pageerror', e => errors.push(e.message));
    for (const slug of ['']) {
      // TT-377：中文 /、英文 /en/ 各自是靜態頁, 初始 HTML 就是該語言
      const path = locale === 'en-US' ? '/en/' : '/';
      const raw = await (await fetch(site+path)).text();
      assert.match(raw, /<h1/); assert.match(raw, /<meta name="description"/);
      assert.match(raw, new RegExp(`<html lang="${locale === 'en-US' ? 'en' : 'zh-Hant'}">`));
      assert.match(raw, new RegExp(`<link rel="canonical" href="${site}${path}" />`));
      for (const [lang, href] of [['zh-Hant','/'],['en','/en/'],['x-default','/']]) assert.match(raw, new RegExp(`<link rel="alternate" hreflang="${lang}" href="${site}${href}" />`));
      assert.match(raw, /<script type="application\/ld\+json">/);
      if (locale === 'en-US') assert.doesNotMatch(raw.replace(/<script[\s\S]*?<\/script>|<!--[\s\S]*?-->|<select[\s\S]*?<\/select>/g, ''), /\p{Script=Han}/u, 'English HTML has no Chinese before JS');
      assert.ok(!raw.includes('tasktracker:signed-in') && !raw.includes('auth-pending'));
      assert.ok(!raw.includes('href="/login') && !raw.includes('rel="manifest"'));
      for (const width of [1440,768,390]) {
        await page.setViewportSize({ width,height:1000 }); await page.goto(site+path);
        assert.equal(new URL(page.url()).origin, site);
        assert.equal(await page.locator('h1').count(),1);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth),false);
        assert.equal(await page.locator('.guest-nav a').getAttribute('href'),app+'/login');
        assert.equal(await page.locator('.site-footer a[href$="/api/public/docs"]').getAttribute('href'),app+'/api/public/docs');
        if (locale === 'en-US') assert.doesNotMatch((await page.locator('body').innerText()).replaceAll('繁體中文',''), /\p{Script=Han}/u);
        await page.evaluate(() => document.fonts.ready);
        await page.screenshot({path:`${out}/public-${slug || 'home'}-${locale}-${width}.png`,fullPage:true});
        results.push({path,locale,width});
      }
    }
    await context.close();
  }
  assert.deepEqual(apiRequests,[],'Static site must not wake the API');
  const noJs = await browser.newPage({javaScriptEnabled:false}); await noJs.goto(site);
  assert.match(await noJs.locator('.story-section').innerText(),/Josh/); await noJs.close();
  const legacy = await browser.newContext({locale:'zh-TW'}); const legacyPage = await legacy.newPage();
  await legacyPage.goto(site+'/#/invite/synthetic-token');
  await legacyPage.waitForURL(url => url.origin===app);
  assert.equal(new URL(legacyPage.url()).hash,'#/invite/synthetic-token');
  await legacy.close();
  const context = await browser.newContext({locale:'zh-TW'}); const page = await context.newPage();
  page.on('pageerror', error => errors.push(error.message));
  const rpc = async (operation,input={}) => {
    const csrf = await (await context.request.get(app+'/api/csrf-cookie')).json();
    const response = await context.request.post(app+'/api/auth/'+operation,{headers:{'X-CSRF-TOKEN':csrf.csrfToken},data:input});
    assert.ok(response.ok(),await response.text());return fixtureResponse(context.request,response,input);
  };
  const email = `split-${crypto.randomUUID()}@example.test`;
  await rpc('register',{name:'拆站檢查',email,password:'isolated-split-password',workspaceName:'拆站合成空間'});
  await rpc('logout');
  await page.goto(app+'/login');
  await page.getByLabel('Email',{exact:true}).waitFor();
  for (const width of [1440,768,390]) {
    await page.setViewportSize({width,height:1000});
    await page.reload();
    await page.getByLabel('Email',{exact:true}).waitFor();
    await page.evaluate(() => document.fonts.ready);
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth),false);
    assert.equal(await page.locator('.guest-brand').getAttribute('href'),site+'/');
    await page.screenshot({path:`${out}/app-login-${width}.png`,fullPage:true});
  }
  await page.getByLabel('Email',{exact:true}).fill(email);
  await page.getByLabel('密碼',{exact:true}).fill('isolated-split-password');
  await page.getByRole('button',{name:'登入',exact:true}).click();
 await page.locator('.app-shell').waitFor().catch(async error => { await page.screenshot({path:out+'/app-failure.png'}); console.log('App page errors',errors); throw error; });
  assert.equal(new URL(page.url()).origin,app);
  const projectResponse = page.waitForResponse(response => new URL(response.url()).pathname === '/api/v1/projects' && response.request().method() === 'GET');
  await page.goto(app+'/app/#/projects'); await page.locator('.app-shell').waitFor();
  assert.ok((await projectResponse).ok(), 'Project query uses the authenticated app origin');
  await page.getByRole('heading',{name:'尚無專案',exact:true}).waitFor();
  assert.equal(new URL(page.url()).hash,'#/projects');
  for (const width of [1440,768,390]) {
    await page.setViewportSize({width,height:1000});
    await page.evaluate(() => document.fonts.ready);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth),false);
    await page.screenshot({path:`${out}/app-signed-in-${width}.png`,fullPage:true});
  }
  await page.setViewportSize({width:1440,height:1000});
  await page.getByRole('button',{name:'帳號選單',exact:true}).click();
  assert.equal(await page.locator('.account-intro').getAttribute('href'),site+'/');
  await page.locator('.account-intro').click(); await page.waitForURL(site+'/');
  assert.equal(await page.locator('h1').innerText(),'專案管理平台');
  assert.deepEqual(errors,[]);
  await context.close();
  await writeFile(`${out}/result.json`,JSON.stringify({passed:true,results,apiRequests,checks:['artifact-boundaries','bilingual-urls-hreflang-sitemap','no-api-with-app-offline','ignore-login-hint','bilingual-static-content','no-js','legacy-hash','isolated-app-login','app-intro-link']},null,2));
  console.log('PASS: split artifacts, 30 public views with app offline and zero API requests, legacy hash, isolated app authentication and intro link');
} finally { await browser?.close(); await publicServer?.close(); await appServer?.close(); }
