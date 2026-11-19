import { initializePublicLocale } from './i18n.js';
// TT-435：功能介紹有兩種呈現, 都不自動輪播。
// - 一般捲動（手機、平板、減少動態、無法固定畫面時）：功能區收成分頁＋截圖堆疊, 點分頁切換
// - 首屏故事（大螢幕）：參考 JT, 首屏固定在畫面上, 往下捲標題淡出、換成各功能說明, 截圖跟著淡入切換,
//   整組截圖隨捲動距離連續小幅轉動；功能說明與截圖是從功能區整個搬過去, 不複製內容
// 無JS時四個功能照順序完整顯示。要在 initializePublicLocale 之前跑, 搬動的文字節點才會被翻譯。
const showcase = document.querySelector('[data-showcase]');
const hero = document.querySelector('.hero');
if (showcase && hero) {
  const aspects = [...showcase.querySelectorAll('[data-aspect]')];
  const railLinks = [...hero.querySelectorAll('.feature-rail a')];
  const tablist = Object.assign(document.createElement('div'), { className: 'showcase-tabs' });
  const panels = Object.assign(document.createElement('div'), { className: 'showcase-panels' });
  const stage = Object.assign(document.createElement('div'), { className: 'showcase-stage' });
  const rig = Object.assign(document.createElement('div'), { className: 'showcase-rig' });
  tablist.setAttribute('role', 'tablist');
  tablist.setAttribute('aria-label', '功能總覽');
  tablist.setAttribute('aria-orientation', 'vertical');
  stage.append(rig);
  const tabs = [], copies = [], shots = [];
  aspects.forEach((aspect, index) => {
    const copy = aspect.querySelector('.aspect-copy');
    const shot = aspect.querySelector('.shot');
    const tab = Object.assign(document.createElement('button'), { type: 'button', className: 'showcase-tab', id: `${aspect.id}-tab` });
    tab.setAttribute('role', 'tab');
    tab.setAttribute('aria-controls', aspect.id);
    const icon = railLinks[index]?.querySelector('svg')?.cloneNode(true);
    const label = document.createElement('span');
    const title = document.createElement('strong');
    title.textContent = copy.querySelector('h3').textContent;
    label.append(title);
    const summary = railLinks[index]?.querySelector('small');
    if (summary) label.append(summary.cloneNode(true));
    tab.append(...(icon ? [icon] : []), label);
    // 保留 #tasks 等錨點：id 從整個 article 移到功能說明上
    copy.id = aspect.id;
    copy.setAttribute('aria-labelledby', tab.id);
    copy.setAttribute('role', 'tabpanel');
    tabs.push(tab); copies.push(copy); shots.push(shot);
    tablist.append(tab); panels.append(copy); rig.append(shot);
  });
  showcase.replaceChildren(tablist, stage, panels);
  showcase.classList.add('enhanced');

  const section = showcase.closest('.feature-section');
  const heading = section.querySelector('.wrap > h2');
  const heroCopy = hero.querySelector('.hero-copy');
  const heroPreview = hero.querySelector('.hero-preview');
  const heroShot = heroPreview.querySelector('.hero-shot');
  const heroStage = Object.assign(document.createElement('div'), { className: 'hero-stage' });
  const chapters = Object.assign(document.createElement('div'), { className: 'hero-chapters' });
  const heroStack = Object.assign(document.createElement('div'), { className: 'hero-stack' });
  const heroRig = Object.assign(document.createElement('div'), { className: 'hero-rig' });
  heroStack.append(heroRig);

  let story = false, current = -1, chapter = -1;
  // 一般捲動的分頁切換
  function activate(index, { focus = false } = {}) {
    index = (index + tabs.length) % tabs.length;
    if (index === current) return;
    current = index;
    tabs.forEach((tab, i) => {
      tab.setAttribute('aria-selected', String(i === index));
      tab.tabIndex = i === index ? 0 : -1;
    });
    copies.forEach((copy, i) => {
      copy.hidden = !story && i !== index;
      copy.classList.toggle('is-active', i === index);
    });
    shots.forEach((shot, i) => {
      const depth = (i - index + shots.length) % shots.length;
      shot.style.setProperty('--depth', depth);
      shot.classList.toggle('is-front', depth === 0);
      // 後面幾張只是視覺上的堆疊, 不讓螢幕閱讀器重複念圖片說明
      if (!story) shot.setAttribute('aria-hidden', String(depth !== 0));
    });
    if (focus) tabs[index].focus();
  }

  // 首屏故事：-1 是一開始的標題, 0～3 是各功能
  function showChapter(next) {
    if (next === chapter) return;
    chapter = next;
    hero.classList.toggle('is-unfolded', next >= 0);
    railLinks.forEach((link, i) => link.classList.toggle('is-active', i === next));
    [heroShot, ...shots].forEach((shot, i) => {
      shot.classList.toggle('is-current', i - 1 === next);
      shot.classList.toggle('is-past', i - 1 < next);
      shot.setAttribute('aria-hidden', String(i - 1 !== next));
    });
    if (next >= 0) activate(next);
  }
  // 跟 JT 一樣把上方選單一起放進固定層, 故事期間選單留在畫面上, 捲完才一起離開
  const topbar = document.querySelector('.guest-topbar');
  const topbarNext = topbar?.nextElementSibling;
  function enterStory() {
    story = true;
    // 第一次套用時不要播淡入, 避免首屏一載入就閃一下
    hero.classList.add('is-settling');
    heroStage.append(...hero.children);
    if (topbar) heroStage.prepend(topbar);
    hero.append(heroStage);
    copies.forEach(copy => { copy.hidden = false; copy.removeAttribute('role'); });
    chapters.append(heading, ...copies);
    heroCopy.after(chapters);
    heroRig.append(heroShot, ...shots);
    heroPreview.prepend(heroStack);
    section.hidden = true;
    hero.classList.add('is-story');
    chapter = -2;
    requestAnimationFrame(() => requestAnimationFrame(() => hero.classList.remove('is-settling')));
  }
  function leaveStory() {
    story = false;
    hero.classList.remove('is-story', 'is-unfolded');
    if (topbar) topbarNext.before(topbar);
    hero.append(...heroStage.children);
    heroStage.remove();
    section.querySelector('.wrap').prepend(heading);
    panels.append(...copies);
    rig.append(...shots);
    heroPreview.prepend(heroShot);
    heroStack.remove();
    railLinks.forEach(link => link.classList.remove('is-active'));
    [heroShot, ...shots].forEach(shot => { shot.classList.remove('is-current', 'is-past'); shot.removeAttribute('aria-hidden'); });
    heroShot.removeAttribute('aria-hidden');
    section.hidden = false;
    const index = current; current = -1;
    copies.forEach(copy => copy.setAttribute('role', 'tabpanel'));
    activate(index < 0 ? 0 : index);
  }

  // 捲動位置對應：跟 JT 一樣先留一段給標題, 後面 70% 平分給四個功能
  const START = 0.16, SPAN = 0.7;
  const range = () => {
    const top = hero.getBoundingClientRect().top + scrollY;
    return { top, distance: hero.offsetHeight - heroStage.offsetHeight };
  };
  const chapterPoint = index => START + SPAN * (index + 0.5) / tabs.length;
  function scrollToChapter(index, smooth = true) {
    const { top, distance } = range();
    scrollTo({ top: top + distance * chapterPoint(index), behavior: smooth ? 'smooth' : 'instant' });
  }

  // 截圖跟著捲動連續轉動, 滑鼠傾斜用緩動追上去, 避免一格一格跳
  const finePointer = matchMedia('(hover: hover) and (pointer: fine)');
  let frame = 0, tiltX = 0, tiltY = 0, targetX = 0, targetY = 0;
  function render() {
    frame = 0;
    if (!story) return;
    const { top, distance } = range();
    const progress = Math.min(1, Math.max(0, (scrollY - top) / distance));
    hero.style.setProperty('--progress', progress.toFixed(4));
    showChapter(progress < START ? -1 : Math.min(tabs.length - 1, Math.floor((progress - START) / SPAN * tabs.length)));
    tiltX += (targetX - tiltX) * 0.1;
    tiltY += (targetY - tiltY) * 0.1;
    hero.style.setProperty('--tilt-x', `${(tiltX * 5).toFixed(3)}deg`);
    hero.style.setProperty('--tilt-y', `${(-tiltY * 3).toFixed(3)}deg`);
    if (Math.abs(targetX - tiltX) > 0.002 || Math.abs(targetY - tiltY) > 0.002) schedule();
  }
  const schedule = () => { frame ||= requestAnimationFrame(render); };
  addEventListener('pointermove', event => {
    if (!story || !finePointer.matches) return;
    targetX = event.clientX / innerWidth - 0.5;
    targetY = event.clientY / innerHeight - 0.5;
    schedule();
  }, { passive: true });
  addEventListener('scroll', schedule, { passive: true });
  addEventListener('resize', schedule, { passive: true });

  // 跟 JT 相同的固定條件：大螢幕、沒開減少動態、不是省電環境
  const storyQuery = matchMedia('(min-width: 1101px) and (min-height: 760px) and (prefers-reduced-motion: no-preference)');
  const lowPower = navigator.connection?.saveData === true || (navigator.hardwareConcurrency || 8) <= 2;
  function syncMode() {
    const next = storyQuery.matches && !lowPower;
    if (next === story) return;
    if (next) enterStory(); else leaveStory();
    targetX = targetY = 0;
    schedule();
  }

  // 分頁與首屏功能列：一般捲動直接切換, 首屏故事捲到那一章
  const select = (index, options) => story ? scrollToChapter(index) : activate(index, options);
  tabs.forEach((tab, i) => tab.addEventListener('click', () => select(i)));
  tablist.addEventListener('keydown', event => {
    const moves = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 };
    if (event.key in moves) activate(current + moves[event.key], { focus: true });
    else if (event.key === 'Home') activate(0, { focus: true });
    else if (event.key === 'End') activate(tabs.length - 1, { focus: true });
    else return;
    event.preventDefault();
  });
  rig.addEventListener('click', event => {
    const index = shots.indexOf(event.target.closest('.shot'));
    if (index >= 0) activate(index);
  });
  railLinks.forEach((link, i) => link.addEventListener('click', event => {
    if (!story) return;
    event.preventDefault();
    scrollToChapter(i);
  }));
  const hashIndex = () => copies.findIndex(copy => `#${copy.id}` === location.hash);
  addEventListener('hashchange', () => {
    const index = hashIndex();
    if (index >= 0) select(index);
  });

  activate(0);
  syncMode();
  storyQuery.addEventListener('change', syncMode);
  if (hashIndex() >= 0) {
    if (story) scrollToChapter(hashIndex(), false);
    else { activate(hashIndex()); showcase.scrollIntoView(); }
  }
}
for (const link of document.querySelectorAll('.product-nav a')) {
  if (new URL(link.href).pathname === location.pathname) link.setAttribute('aria-current', 'page');
}

initializePublicLocale();

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
if (import.meta.env.VITE_BUILD_TARGET === 'public') {
  root.classList.remove('auth-pending');
} else if (window.__tasktrackerRedirecting) {
  // 等head的轉址完成
} else if (await signedIn()) {
  // 同步登入提示，下次開首頁時在head即轉址，不先閃出介紹頁。
  try { localStorage.setItem('tasktracker:signed-in', '1'); } catch { /* 無法儲存時每次以API判斷 */ }
  if (intro || location.pathname !== '/') { showMemberHeader(); root.classList.remove('auth-pending'); }
  else location.replace('/app/' + location.hash);
} else {
  root.classList.remove('auth-pending');
}
