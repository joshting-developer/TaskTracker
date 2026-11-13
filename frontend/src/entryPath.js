// TT-297：同一個SPA殼層服務/login與/app/；依登入狀態只換路徑、保留hash深層連結，不重新載入頁面。
const SIGNED_IN_HINT = 'tasktracker:signed-in';

/**
 * 已登入固定在/app/、未登入固定在/login，並同步首頁用的登入提示（只是提示，不是授權依據）。
 *
 * @param {boolean} signedIn 目前Session是否有效
 * @returns {void}
 */
export function syncEntryPath(signedIn) {
  const target = signedIn ? '/app/' : '/login';
  // 登入頁的query（例如建立工作空間模式）不帶進工作空間。
  if (location.pathname !== target) history.replaceState(history.state, '', target + (signedIn ? '' : location.search) + location.hash);
  try {
    if (signedIn) localStorage.setItem(SIGNED_IN_HINT, '1');
    else localStorage.removeItem(SIGNED_IN_HINT);
  } catch {
    // 無法存取storage時首頁改以API判斷，不影響登入。
  }
}
