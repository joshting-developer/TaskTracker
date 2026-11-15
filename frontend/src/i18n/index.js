import { readonly, shallowRef } from 'vue';
import zhTW from './zh-TW.json' with { type: 'json' };
import en from './en.json' with { type: 'json' };

const dictionaries = { 'zh-TW': zhTW, en };
const language = value => /^en(?:-|$)/i.test(value || '') ? 'en' : /^zh(?:-|$)/i.test(value || '') ? 'zh-TW' : '';
let account = 'guest';
const storageKey = () => `tasktracker:locale:${account}`;
function stored() { try { return language(globalThis.localStorage?.getItem(storageKey())); } catch { return ''; } }
function preferred() {
  const languages = globalThis.window ? globalThis.navigator?.languages || [globalThis.navigator?.language] : [];
  return languages.map(language).find(Boolean) || 'zh-TW';
}
const current = shallowRef(stored() || preferred());
export const locale = readonly(current);
export const supportedLocales = [{ id: 'zh-TW', name: '繁體中文' }, { id: 'en', name: 'English' }];
export function setLocale(value) {
  if (!Object.hasOwn(dictionaries, value)) return false;
  current.value = value;
  try { globalThis.localStorage?.setItem(storageKey(), value); } catch { /* Preference still works for this visit. */ }
  return true;
}
export function setLocaleAccount(id) {
  const next = id || 'guest';
  if (account === next) return;
  const guestChoice = account === 'guest' && next !== 'guest' ? current.value : '';
  account = next;
  current.value = stored() || guestChoice || preferred();
}
const reverse = new Map();
for (const [key, text] of Object.entries(en)) if (!reverse.has(text)) reverse.set(text, key);
const interpolate = (text, values) => text.replace(/\{(\d+)\}/g, (match, key) => values[key] === undefined ? match : String(values[key]));
export function t(key, values = []) {
  if (typeof key !== 'string') return key ?? '';
  const canonical = Object.hasOwn(zhTW, key) ? key : reverse.get(key) || key;
  const dictionary = dictionaries[current.value];
  return interpolate(Object.hasOwn(dictionary, canonical) ? dictionary[canonical] : key, values);
}
export function formatNumber(value, options = {}) { return new Intl.NumberFormat(current.value, options).format(value); }
export function formatDate(value, options = {}) {
  return new Intl.DateTimeFormat(current.value, { timeZone: 'Asia/Taipei', ...options }).format(new Date(value));
}
const errorMessages = {
  UNAUTHENTICATED: '請先登入', FORBIDDEN: '你沒有執行此操作的權限', NOT_FOUND: '找不到要求的資料',
  CONFLICT: '資料已更新, 請重新載入後重試', REVISION_CONFLICT: '資料已更新, 請重新載入後重試',
  INVALID_INPUT: '請確認輸入的資料', VALIDATION_ERROR: '請確認輸入的資料', CSRF_EXPIRED: '驗證已過期, 請重試',
  RATE_LIMITED: '操作太頻繁, 請稍後重試', INVALID_CREDENTIALS: '信箱或密碼不正確',
  INTERNAL_ERROR: '服務暫時無法處理請求', NETWORK_ERROR: '暫時無法連線，請稍後重試',
};
export function translateError(code, message) {
  if (current.value === 'zh-TW') return message;
  if (Object.hasOwn(en, message) || reverse.has(message)) return t(message);
  return t(errorMessages[code] || '請求失敗');
}

export function projectRoleLabel(role) {
  const keys = { lead: '角色：負責人', maintainer: '角色：專案管理者', developer: '角色：開發者', reader: '角色：讀者' };
  return keys[role?.id] ? t(keys[role.id]) : role?.label || role?.id || '';
}
export function workspaceRoleLabel(role) {
  const id = typeof role === 'string' ? role : role?.id;
  return ['user', 'member', 'developer', 'maintainer'].includes(id) ? t('角色：成員') : id === 'owner' ? 'Owner' : ['manager', 'admin'].includes(id) ? 'Manager' : role?.label || id || '';
}
