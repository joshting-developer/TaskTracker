// Isolated test ports and launch instructions: docs/development.md
// 13029 CI預設、13049文件測試、13089舊Codex效能環境、13198 Claude、13199 Codex；5174／3002主服務永遠不在清單內
export const ISOLATED_API_URLS = Object.freeze([
  'http://127.0.0.1:13029',
  'http://127.0.0.1:13049',
  'http://127.0.0.1:13089',
  'http://127.0.0.1:13198',
  'http://127.0.0.1:13199',
]);

/**
 * 確認E2E_API_URL是隔離測試後端, 不是就直接停止, 避免對現用資料建帳號或改資料。
 *
 * @param {{ requireIsolatedFlag?: boolean }} [options] requireIsolatedFlag為true時另外要求E2E_ISOLATED=1
 * @returns {string} 通過檢查的E2E_API_URL
 */
export function requireIsolatedApi({ requireIsolatedFlag = false } = {}) {
  const api = process.env.E2E_API_URL || '';
  if (requireIsolatedFlag && process.env.E2E_ISOLATED !== '1') throw new Error('請設定E2E_ISOLATED=1, 明確宣告是隔離環境');
  let namedStandalone = false;
  try {
    const target = new URL(api);
    namedStandalone = process.env.E2E_ISOLATED === '1' && /^tasktracker-[a-z0-9-]+$/.test(process.env.E2E_COMPOSE_PROJECT || '') && target.protocol === 'http:' && target.hostname === '127.0.0.1' && Number(target.port) >= 13000 && Number(target.port) < 15000;
  } catch {}
  if (!ISOLATED_API_URLS.includes(api) && !namedStandalone) throw new Error(`E2E_API_URL只允許隔離測試後端：${ISOLATED_API_URLS.join('、')}`);
  return api;
}
