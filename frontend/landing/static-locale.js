// TT-377：建置時產生各語言的靜態介紹頁, 搜尋引擎不執行 JS 也能讀到正確語言的正文與 metadata。
// 規則與瀏覽器端 i18n.js 相同：文字節點去掉空白後完全符合字典 key 才換, 屬性只換 alt／aria-label／title／content。
import en from './en.json' with { type: 'json' };

export const publicLocales = [
  { id: 'zh-TW', lang: 'zh-Hant', path: '/', ogLocale: 'zh_TW' },
  { id: 'en', lang: 'en', path: '/en/', ogLocale: 'en_US' },
];

const has = key => Object.hasOwn(en, key);
const decode = value => value.replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
const escapeText = value => value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const escapeAttribute = value => escapeText(value).replace(/"/g, '&quot;');

function translate(html) {
  return html
    .replace(/(<script\b[\s\S]*?<\/script>|<style\b[\s\S]*?<\/style>|<select\b[\s\S]*?<\/select>)|>([^<]+)</g, (match, kept, text) => {
      if (kept) return kept;
      const key = decode(text).trim();
      return has(key) ? `>${escapeText(decode(text).replace(key, en[key]))}<` : match;
    })
    .replace(/(\s)(alt|aria-label|title|content)="([^"]*)"/g, (match, space, attribute, value) =>
      has(decode(value)) ? `${space}${attribute}="${escapeAttribute(en[decode(value)])}"` : match);
}

// 每個語言只留自己的截圖, 另一語言的 <picture> 不進 HTML, 首屏圖片改為立即載入。
function keepScreens(html, id) {
  const other = id === 'en' ? 'zh' : 'en';
  html = html.replace(new RegExp(`<picture class="${other}">[\\s\\S]*?</picture>`, 'g'), '');
  if (id === 'en') html = html.replace(/(<figure class="shot hero-shot">[\s\S]*?<img\b[^>]*?)loading="lazy"/, '$1loading="eager" fetchpriority="high"');
  return html;
}

function structuredData(html, locale, siteOrigin) {
  const url = siteOrigin + locale.path;
  const text = value => decode(value.replace(/<[^>]+>/g, '')).trim();
  const description = decode(html.match(/<meta name="description" content="([^"]*)"/)?.[1] || '');
  const faq = [...html.matchAll(/<details><summary>([\s\S]*?)<\/summary><p>([\s\S]*?)<\/p><\/details>/g)]
    .map(([, question, answer]) => ({ '@type': 'Question', name: text(question), acceptedAnswer: { '@type': 'Answer', text: text(answer) } }));
  const graph = [
    { '@type': 'WebSite', '@id': `${siteOrigin}/#website`, name: 'Pavenro', url: `${siteOrigin}/`, inLanguage: publicLocales.map(row => row.lang) },
    { '@type': 'SoftwareApplication', name: 'Pavenro', url, description, inLanguage: locale.lang, applicationCategory: 'BusinessApplication', operatingSystem: 'Web' },
    ...(faq.length ? [{ '@type': 'FAQPage', url, inLanguage: locale.lang, mainEntity: faq }] : []),
  ];
  // JSON 裡的 < 轉成 \u003c, 避免內容提前結束 script
  return `<script type="application/ld+json">${JSON.stringify({ '@context': 'https://schema.org', '@graph': graph }).replace(/</g, '\\u003c')}</script>`;
}

// html：已替換好 __SITE_ORIGIN__ 的中文頁；depth：輸出檔相對根目錄的層數, 用來修正 ./assets 等相對路徑
export function localizePage(html, id, { siteOrigin = '', depth = 0 } = {}) {
  const locale = publicLocales.find(row => row.id === id);
  if (id === 'en') {
    html = translate(html)
      .replace('<html lang="zh-Hant">', '<html lang="en">')
      .replace('<meta property="og:locale" content="zh_TW" />', '<meta property="og:locale" content="en_US" />\n    <meta property="og:locale:alternate" content="zh_TW" />')
      .replace(/<script data-locale-redirect>[\s\S]*?<\/script>\s*/, '')
      .replace(/href="\/\?intro=1"/g, 'href="/en/?intro=1"')
      .replace(/<option value="en" lang="en">/, '<option value="en" lang="en" selected>');
    if (siteOrigin) html = html.replace(`<link rel="canonical" href="${siteOrigin}/" />`, `<link rel="canonical" href="${siteOrigin}/en/" />`)
      .replace(`<meta property="og:url" content="${siteOrigin}/" />`, `<meta property="og:url" content="${siteOrigin}/en/" />`);
  } else {
    html = html.replace('<meta property="og:locale" content="zh_TW" />', '<meta property="og:locale" content="zh_TW" />\n    <meta property="og:locale:alternate" content="en_US" />');
  }
  html = keepScreens(html, id);
  if (siteOrigin) html = html.replace('</head>', `  ${structuredData(html, locale, siteOrigin)}\n  </head>`);
  if (depth) html = html.replace(/(\s(?:href|src|srcset)=")\.\//g, `$1${'../'.repeat(depth)}`);
  return html;
}

// 開發與檢查工具用：列出頁面上還沒有英文翻譯的中文文字
export function untranslated(html) {
  const rest = translate(html).replace(/<script\b[\s\S]*?<\/script>|<style\b[\s\S]*?<\/style>|<select\b[\s\S]*?<\/select>|<picture class="zh">[\s\S]*?<\/picture>/g, '');
  const texts = [...rest.matchAll(/>([^<]+)</g)].map(match => decode(match[1]).trim());
  const attributes = [...rest.matchAll(/\s(?:alt|aria-label|title|content)="([^"]*)"/g)].map(match => decode(match[1]));
  return [...new Set([...texts, ...attributes])].filter(value => /\p{Script=Han}/u.test(value));
}
