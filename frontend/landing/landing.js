// TT-355：不用自動播放, 無JS時三個情境皆可閱讀；切換保留鍵盤焦點。
const scenarios = document.querySelector('.scenarios');
if (scenarios) {
  const slides = [...scenarios.querySelectorAll('[data-slide]')];
  const controls = document.querySelector('.carousel-controls');
  const counter = controls.querySelector('[data-counter]');
  let current = 0;
  function showSlide(index) {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, i) => { slide.hidden = i !== current; });
    counter.textContent = `${current + 1} / ${slides.length}`;
  }
  scenarios.classList.add('enhanced');
  scenarios.setAttribute('aria-roledescription', '輪播');
  controls.hidden = false;
  controls.querySelector('[data-prev]').addEventListener('click', () => showSlide(current - 1));
  controls.querySelector('[data-next]').addEventListener('click', () => showSlide(current + 1));
  scenarios.addEventListener('keydown', event => {
    if (event.target !== scenarios || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
    event.preventDefault();
    showSlide(current + (event.key === 'ArrowRight' ? 1 : -1));
  });
  showSlide(0);
}
for (const link of document.querySelectorAll('.product-nav a')) {
  if (new URL(link.href).pathname === location.pathname) link.setAttribute('aria-current', 'page');
}

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
// head已經在轉址（舊hash連結或登入提示）就不再判斷, 避免兩個轉址互搶而弄丟#後的深層連結。
// TT-304：從工作空間帳號選單點「產品介紹」會帶 ?intro=1, 這時已登入也留在介紹頁, 上方選單換成「回到工作區」。
const intro = new URLSearchParams(location.search).get('intro') === '1';
function showMemberHeader() {
  document.querySelectorAll('[data-guest]').forEach(element => { element.hidden = true; });
  document.querySelectorAll('[data-member]').forEach(element => { element.hidden = false; });
}
if (window.__tasktrackerRedirecting) {
  // 等head的轉址完成
} else if (await signedIn()) {
  // 同步登入提示，下次開首頁時在head即轉址，不先閃出介紹頁。
  try { localStorage.setItem('tasktracker:signed-in', '1'); } catch { /* 無法儲存時每次以API判斷 */ }
  if (intro || location.pathname !== '/') { showMemberHeader(); root.classList.remove('auth-pending'); }
  else location.replace('/app/' + location.hash);
} else {
  root.classList.remove('auth-pending');
}
