// One explicit language choice for this browser, shared by public pages and the app.
export const localeStorageKey = 'tasktracker:locale';
export const normalizeLocale = value => /^en(?:-|$)/i.test(value || '') ? 'en' : /^zh(?:-|$)/i.test(value || '') ? 'zh-TW' : '';
let explicit = '';
export function explicitLocale() {
  try { return normalizeLocale(globalThis.localStorage?.getItem(localeStorageKey)) || explicit; } catch { return explicit; }
}
export function preferredLocale() {
  const languages = globalThis.window ? globalThis.navigator?.languages || [globalThis.navigator?.language] : [];
  return languages.map(normalizeLocale).find(Boolean) || 'zh-TW';
}
export function saveLocale(value) {
  const next = normalizeLocale(value);
  if (!['en', 'zh-TW'].includes(value) || !next) return false;
  explicit = next;
  try { globalThis.localStorage?.setItem(localeStorageKey, next); } catch { /* Keep the choice for this visit. */ }
  return true;
}
