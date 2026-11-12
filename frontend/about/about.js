// TT-207：只判斷是否已登入；已登入隱藏訪客TopBar並把CTA換成回到工作空間。失敗一律當訪客，不讀任何私人資料。
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
const member = await signedIn();
root.classList.toggle('signed-in', member);
document.querySelectorAll('[data-guest-cta]').forEach(element => { element.hidden = member; });
document.querySelectorAll('[data-member-cta]').forEach(element => { element.hidden = !member; });
root.classList.remove('auth-pending');
