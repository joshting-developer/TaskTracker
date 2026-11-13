// TT-207／297：首頁只判斷是否已登入；已登入直接進工作空間/app/。失敗一律當訪客，不讀任何私人資料。
const root = document.documentElement;
async function signedIn() {
  try {
    const csrf = await (await fetch('/api/csrf-cookie', { credentials: 'same-origin' })).json();
    const response = await fetch('/api/auth/current', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: '{}' });
    return response.ok && (await response.json()).ok === true;
  } catch {
    return false;
  }
}
if (await signedIn()) {
  // 同步登入提示，下次開首頁時在head即轉址，不先閃出介紹頁。
  try { localStorage.setItem('tasktracker:signed-in', '1'); } catch { /* 無法儲存時每次以API判斷 */ }
  location.replace('/app/');
} else {
  root.classList.remove('auth-pending');
}
