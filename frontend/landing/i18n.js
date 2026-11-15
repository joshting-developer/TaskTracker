import en from './en.json' with { type: 'json' };
import { explicitLocale, preferredLocale, saveLocale, localeStorageKey } from '../locale-preference.js';

export function initializePublicLocale() {
  const texts = [], attributes = [];
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  while (walker.nextNode()) {
    const node = walker.currentNode, key = node.textContent.trim();
    if (!node.parentElement.closest('script,style,code,pre,[data-public-locale]') && Object.hasOwn(en, key)) texts.push({ node, original: node.textContent, key });
  }
  for (const element of document.querySelectorAll('[aria-label],[alt],[title]')) {
    for (const attribute of ['aria-label','alt','title']) {
      const key = element.getAttribute(attribute);
      if (Object.hasOwn(en, key)) attributes.push({ element, attribute, key });
    }
  }
  const title = document.title;
  const picker = document.querySelector('[data-public-locale]');
  function apply(value) {
    document.documentElement.lang = value === 'en' ? 'en' : 'zh-Hant';
    for (const row of texts) row.node.textContent = value === 'en' ? row.original.replace(row.key, en[row.key]) : row.original;
    for (const row of attributes) row.element.setAttribute(row.attribute, value === 'en' ? en[row.key] : row.key);
    document.title = value === 'en' && Object.hasOwn(en, title) ? en[title] : title;
    if (picker) { picker.value = value; picker.setAttribute('aria-label', value === 'en' ? 'Language' : '語言'); }
  }
  picker?.addEventListener('change', () => { if (saveLocale(picker.value)) apply(picker.value); });
  window.addEventListener('storage', event => { if (event.key === localeStorageKey) apply(explicitLocale() || preferredLocale()); });
  apply(explicitLocale() || preferredLocale());
}
