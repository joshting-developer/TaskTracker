import en from './en.json' with { type: 'json' };
import { saveLocale } from '../locale-preference.js';

// TT-377：頁面語言由網址決定（/ 中文、/en/ 英文）, 正文在建置時已換好；
// 這裡只補 JS 動態產生的文字, 語言選單改成前往另一個語言的網址並記住選擇。
export const publicLocalePaths = { 'zh-TW': '/', en: '/en/' };

export function initializePublicLocale() {
  const value = document.documentElement.lang === 'en' ? 'en' : 'zh-TW';
  if (value === 'en') {
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
      const node = walker.currentNode, key = node.textContent.trim();
      if (!node.parentElement.closest('script,style,code,pre,[data-public-locale]') && Object.hasOwn(en, key)) node.textContent = node.textContent.replace(key, en[key]);
    }
    for (const element of document.querySelectorAll('[aria-label],[alt],[title]')) {
      for (const attribute of ['aria-label', 'alt', 'title']) {
        const key = element.getAttribute(attribute);
        if (Object.hasOwn(en, key)) element.setAttribute(attribute, en[key]);
      }
    }
  }
  const picker = document.querySelector('[data-public-locale]');
  if (!picker) return;
  picker.value = value;
  picker.setAttribute('aria-label', value === 'en' ? 'Language' : '語言');
  picker.addEventListener('change', () => {
    if (!saveLocale(picker.value) || picker.value === value) return;
    location.assign(publicLocalePaths[picker.value] + location.search + location.hash);
  });
}
