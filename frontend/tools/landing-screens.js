// TT-432：介紹頁用的真實產品截圖。只接 Agent 隔離後端, 用合成帳號和合成資料 seed, 繁中／英文各一個工作空間。
// 用法：先 npm run build, 再 E2E_ISOLATED=1 E2E_API_URL=http://127.0.0.1:13198 E2E_FRONTEND_PORT=5198 node tools/landing-screens.js
// 原始 PNG 放 artifacts/landing-screens（不進 Git）, 轉好的 WebP 放 public-assets/screens/<locale>/。
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { randomUUID } from 'node:crypto';
import { preview } from 'vite';
import { chromium, request } from 'playwright';

const api = process.env.E2E_API_URL;
assert.equal(process.env.E2E_ISOLATED, '1', '只接隔離後端');
assert.ok(['http://127.0.0.1:13198', 'http://127.0.0.1:13199'].includes(api), '只允許 Agent 隔離後端 13198／13199');
const port = Number(process.env.E2E_FRONTEND_PORT || 5198);
const origin = `http://127.0.0.1:${port}`;
const raw = 'artifacts/landing-screens';
const only = process.env.SHOTS ? process.env.SHOTS.split(',') : null;

const copy = {
  'zh-TW': {
    workspace: '產品協作', owner: '林怡君', members: ['陳柏宇', '王思涵'],
    projects: [['網站改版', 'WEB'], ['行動 App', 'APP']],
    epic: '網站改版交付',
    tags: [['前端', '#6350bd'], ['設計', '#c2410c'], ['上線必要', '#047857']],
    board: ['本次交付', '11 月底上線需要完成的工作'],
    issues: [
      ['整理使用者回饋', 'task', 'open', 0, ['設計'], '彙整客服與訪談回饋, 標出要在這次改版處理的項目'],
      ['調整手機版導覽', 'task', 'inprogress', 1, ['前端', '上線必要'], '手機版選單改成兩欄, 保留語言切換與登入入口'],
      ['首頁載入效能優化', 'task', 'inprogress', 2, ['前端'], '首屏圖片改用 WebP 並延後載入非首屏區塊'],
      ['更新價格頁文案', 'task', 'open', 2, ['設計'], '依新方案調整價格頁說明與常見問題'],
      ['修正登入錯誤提示', 'bug', 'closed', 1, ['前端', '上線必要'], '密碼錯誤時顯示可理解的提示, 不暴露帳號是否存在'],
      ['補上表單無障礙標籤', 'task', 'closed', 0, ['前端'], '聯絡表單的欄位補上 label 與錯誤說明'],
    ],
    appIssues: [['推播通知設定', 'task', 'inprogress', 2], ['離線草稿同步', 'task', 'open', 1]],
    comments: ['導覽兩欄版本已上測試站, 請幫忙用手機看一下', '看過了, 語言切換的位置可以再往右一點'],
    todo: ['補上手機導覽驗證', '請在 iPhone 與 Android 各測一次導覽, 結果記在卡片上', '收到, 今天下午測完回報', '測試項目整理好了, 需要排進這次改版'],
    convert: '手機導覽跨裝置驗證',
    notes: ['實作', '測試', '討論', '修正'],
    tokens: ['部署腳本', '每週報表', 'CI 同步'],
  },
  en: {
    workspace: 'Product team', owner: 'Emma Lin', members: ['Ryan Chen', 'Mia Wang'],
    projects: [['Website refresh', 'WEB'], ['Mobile app', 'APP']],
    epic: 'Website refresh launch',
    tags: [['Frontend', '#6350bd'], ['Design', '#c2410c'], ['Launch blocker', '#047857']],
    board: ['This release', 'Work that must ship by the end of November'],
    issues: [
      ['Review customer feedback', 'task', 'open', 0, ['Design'], 'Group support and interview feedback and mark what this release should cover'],
      ['Rework mobile navigation', 'task', 'inprogress', 1, ['Frontend', 'Launch blocker'], 'Use a two-column mobile menu and keep language and sign-in visible'],
      ['Speed up the home page', 'task', 'inprogress', 2, ['Frontend'], 'Serve hero images as WebP and lazy-load sections below the fold'],
      ['Update pricing copy', 'task', 'open', 2, ['Design'], 'Align the pricing page and FAQ with the new plans'],
      ['Fix sign-in error message', 'bug', 'closed', 1, ['Frontend', 'Launch blocker'], 'Show a clear message for a wrong password without revealing whether the account exists'],
      ['Label contact form fields', 'task', 'closed', 0, ['Frontend'], 'Add labels and error descriptions to the contact form'],
    ],
    appIssues: [['Push notification settings', 'task', 'inprogress', 2], ['Sync offline drafts', 'task', 'open', 1]],
    comments: ['The two-column menu is on staging, please check it on a phone', 'Looks good. The language switch could move a little to the right'],
    todo: ['Check mobile navigation on devices', 'Please test the navigation on one iPhone and one Android phone and note the results on the card', 'Got it, I will report back this afternoon', 'Test notes are ready. This should go into the current release'],
    convert: 'Cross-device check for mobile navigation',
    notes: ['Implementation', 'Testing', 'Discussion', 'Fixes'],
    tokens: ['Deploy script', 'Weekly report', 'CI sync'],
  },
};

class Session {
  constructor(context) { this.context = context; this.csrf = ''; }
  async call(path, data) {
    const options = { headers: { Accept: 'application/json', ...(data ? { 'X-CSRF-TOKEN': this.csrf } : {}) }, ...(data ? { data } : {}) };
    const response = data ? await this.context.post(path, options) : await this.context.get(path, options);
    this.csrf = response.headers()['x-csrf-token'] || this.csrf;
    const body = await response.json().catch(() => ({}));
    if (!response.ok()) throw new Error(`${path} ${response.status()} ${JSON.stringify(body).slice(0, 300)}`);
    return body.data ?? body;
  }
  async start() { this.csrf = (await this.call('/api/csrf-cookie')).csrfToken; }
}

async function seed(locale) {
  const text = copy[locale];
  const password = randomUUID();
  const sessions = [];
  const newSession = async () => { const s = new Session(await request.newContext({ baseURL: api })); await s.start(); sessions.push(s); return s; };
  const owner = await newSession();
  const ownerEmail = `landing-owner-${randomUUID()}@example.test`;
  owner.identity = await owner.call('/api/auth/register', { email: ownerEmail, name: text.owner, password, workspaceName: text.workspace });
  if (owner.identity.verificationRequired) owner.identity = await owner.call('/api/auth/verify-email', { code: 'local666' });
  await owner.start();
  const projects = [];
  for (const [name, code] of text.projects) projects.push(await owner.call('/api/rpc/createProject', { name, code, requestId: randomUUID() }));
  const people = [{ session: owner, email: ownerEmail, id: owner.identity.user.id }];
  for (const name of text.members) {
    const email = `landing-member-${randomUUID()}@example.test`;
    const invite = await owner.call('/api/rpc/inviteMember', { email, requestId: randomUUID() });
    const member = await newSession();
    member.identity = await member.call('/api/auth/register', { name, email, password, inviteToken: invite.token });
    await member.start();
    await member.call('/api/auth/acceptWorkspaceInvite', { inviteToken: invite.token, requestId: randomUUID() });
    await member.start();
    for (const project of projects) {
      const result = await owner.call('/api/rpc/setProjectMember', { projectId: project.id, userId: member.identity.user.id, roleId: 'maintainer', expectedRevision: project.memberRevision || 0, requestId: randomUUID() });
      project.memberRevision = result.revision ?? (project.memberRevision || 0) + 1;
    }
    people.push({ session: member, email, id: member.identity.user.id });
  }
  for (const person of people) {
    const token = await person.session.call('/api/v1/personal-tokens', { name: text.tokens[2], abilities: ['write'], requestId: randomUUID() });
    person.pat = await request.newContext({ baseURL: api, extraHTTPHeaders: { Authorization: `Bearer ${token.plainTextToken}`, Accept: 'application/json' } });
  }
  for (const name of text.tokens.slice(0, 2)) await owner.call('/api/v1/personal-tokens', { name, abilities: [name === text.tokens[1] ? 'read' : 'write'], requestId: randomUUID() });
  const pub = async (person, method, path, data) => {
    const options = { method, ...(data ? { data: { requestId: randomUUID(), ...data } } : {}) };
    let response = await person.pat.fetch(path, options);
    // 公開 API 有每分鐘上限；同一 requestId 重送不會重複寫入。
    for (let wait = 5; response.status() === 429 && wait <= 80; wait *= 2) {
      await new Promise(resolve => setTimeout(resolve, wait * 1000));
      response = await person.pat.fetch(path, options);
    }
    const body = await response.json().catch(() => ({}));
    if (!response.ok()) throw new Error(`${method} ${path} ${response.status()} ${JSON.stringify(body).slice(0, 300)}`);
    return body.data ?? body;
  };
  // 預設狀態名稱存在資料裡, 英文工作空間改成英文, 畫面才不會中英混雜。
  if (locale === 'en') {
    const labels = { open: 'To do', inprogress: 'In progress', closed: 'Closed' };
    for (const project of projects) {
      const { items } = await pub(people[0], 'GET', `/api/public/v1/projects/${project.id}/statuses`);
      const { revision } = await pub(people[0], 'GET', `/api/public/v1/projects/${project.id}`);
      await owner.call('/api/rpc/updateStatuses', { projectId: project.id, expectedRevision: revision, requestId: randomUUID(), statuses: items.map(({ id, label, color }) => ({ id, label: labels[id] || label, color })) });
    }
  }
  const [web, app] = projects;
  const p = `/api/public/v1/projects/${web.id}`;
  const tags = [];
  for (const [name, color] of text.tags) tags.push({ name, id: (await pub(people[0], 'POST', `${p}/tags`, { name, color })).id });
  const tagId = name => tags.find(tag => tag.name === name).id;
  const board = await pub(people[0], 'POST', `${p}/boards`, { name: text.board[0], description: text.board[1] });
  const epic = await pub(people[0], 'POST', p + '/issues', { title: text.epic, type: 'epic', assignee: people[0].id, dueDate: '2026-11-30', boardIds: [board.id] });
  const issues = [];
  for (const [title, type, state, who, tagNames, description] of text.issues) {
    const issue = await pub(people[0], 'POST', p + '/issues', { title, type, description, assignee: people[who].id, epicId: epic.id, dueDate: '2026-11-28', estimateMinutes: 240, priority: who === 1 ? 'high' : 'normal', boardIds: [board.id] });
    const tagged = await pub(people[0], 'PATCH', `${p}/issues/${issue.id}/tags`, { expectedRevision: issue.revision, addTagIds: tagNames.map(tagId) });
    if (state !== 'open') await pub(people[0], 'PATCH', `${p}/issues/${issue.id}`, { state, expectedRevision: tagged.revision });
    issues.push(issue);
  }
  for (const [title, type, state, who] of text.appIssues) {
    const issue = await pub(people[0], 'POST', `/api/public/v1/projects/${app.id}/issues`, { title, type, assignee: people[who].id, estimateMinutes: 180 });
    if (state !== 'open') await pub(people[0], 'PATCH', `/api/public/v1/projects/${app.id}/issues/${issue.id}`, { state, expectedRevision: issue.revision });
    issues.push({ ...issue, project: app.id });
  }
  const focus = issues[1];
  for (const [index, content] of text.comments.entries()) {
    const current = await pub(people[0], 'GET', `${p}/issues/${focus.id}`);
    await pub(people[index === 0 ? 1 : 0], 'POST', `${p}/issues/${focus.id}/comments`, { content, expectedRevision: current.revision });
  }
  // 本月每位成員都有幾天填報, 分析頁才看得出分布；日期用台北時間。
  const today = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Taipei' }));
  const day = offset => { const d = new Date(today); d.setDate(d.getDate() - offset); return d.toLocaleDateString('sv-SE'); };
  const plan = [[1, [0, 1, 2, 4, 5, 7, 8, 11], [120, 90, 150, 60, 180, 90, 120, 60]], [0, [0, 2, 3, 6, 9, 12], [60, 120, 45, 90, 60, 120]], [2, [1, 3, 4, 8, 10], [90, 60, 120, 150, 90]]];
  for (const [who, offsets, minutes] of plan) {
    for (const [index, offset] of offsets.entries()) {
      if (offset >= today.getDate()) continue;
      const issue = who === 2 && index % 2 ? issues[6 + (index % 2)] : issues[[1, 0, 2][who] + (index % 2 ? 2 : 0)];
      const project = issue.project || web.id;
      await pub(people[who], 'POST', `/api/public/v1/projects/${project}/time-entries`, { issueId: issue.id, workDate: day(offset), minutes: minutes[index], note: text.notes[index % text.notes.length] });
    }
  }
  const [todoTitle, todoContent, reply, followUp] = text.todo;
  const todo = await pub(people[0], 'POST', '/api/public/v1/todos', { title: todoTitle, content: todoContent, assigneeId: people[1].id });
  let current = await pub(people[1], 'GET', `/api/public/v1/todos/${todo.id}`);
  await pub(people[1], 'POST', `/api/public/v1/todos/${todo.id}/comments`, { content: reply, expectedRevision: current.revision });
  current = await pub(people[1], 'GET', `/api/public/v1/todos/${todo.id}`);
  await pub(people[1], 'POST', `/api/public/v1/todos/${todo.id}/comments`, { content: followUp, expectedRevision: current.revision });
  const second = await pub(people[0], 'POST', '/api/public/v1/todos', { title: text.convert, content: todoContent, assigneeId: people[1].id });
  current = await pub(people[1], 'GET', `/api/public/v1/todos/${second.id}`);
  const converted = await people[1].session.call('/api/rpc/convertTodo', { id: second.id, expectedRevision: current.revision, requestId: randomUUID(), issue: { projectId: web.id, title: text.convert, description: todoContent, type: 'task', assignee: people[1].id } });
  const found = await pub(people[0], 'GET', `${p}/issues?perPage=100`);
  const convertedIssue = found.items.find(issue => issue.title === text.convert);
  assert.ok(convertedIssue && converted, '紙條轉卡後要讀得到新卡');
  for (const s of sessions) await s.context.dispose();
  for (const person of people) await person.pat.dispose();
  return { password, people: people.map(({ email, id }) => ({ email, id })), web: web.id, board: board.id, focus: focus.id, converted: convertedIssue.id, todo: todo.id };
}

async function signIn(browser, locale, email, password, viewport, scale) {
  const context = await browser.newContext({ locale: locale === 'en' ? 'en-US' : 'zh-TW', viewport, deviceScaleFactor: scale, reducedMotion: 'reduce', timezoneId: 'Asia/Taipei' });
  await context.addInitScript(value => { try { localStorage.setItem('tasktracker:locale', value); } catch { /* ignore */ } }, locale);
  const session = new Session(context.request);
  session.context = { get: (path, options) => context.request.get(origin + path, options), post: (path, options) => context.request.post(origin + path, options) };
  await session.start();
  await session.call('/api/auth/login', { email, password });
  return context;
}

// 手機版捲到該畫面的重點, 不讓頂部工具列佔掉整張圖。
const focus = selector => async (page, size) => {
  if (size !== 'mobile') return;
  await page.locator(selector).first().evaluate(element => { element.scrollIntoView({ block: 'start' }); window.scrollBy(0, -64); document.querySelector('main')?.scrollBy?.(0, -64); });
  await page.waitForTimeout(300);
};
const both = (...steps) => async (page, size) => { for (const step of steps) await step(page, size); };
const flatBoard = async page => { await page.locator('select:has(option[value="none"])').first().selectOption('none'); await page.waitForTimeout(400); };
const shots = [
  // name, who, hash, prepare
  ['board', 0, s => `#/projects/${s.web}/board/${s.board}`, both(flatBoard, focus('.board-lane'))],
  ['issues', 0, s => `#/projects/${s.web}`, focus('main h1, main h2')],
  ['issue', 0, s => `#/projects/${s.web}/issues/${s.focus}?detail=1`],
  ['notes', 1, () => '#/notes', async page => { await page.locator('main').getByRole('button').filter({ hasText: /更多|More/ }).first().click(); await page.waitForTimeout(600); }],
  ['time-members', 0, () => '#/time-analysis/members', focus('.analysis-totals')],
  ['time-trend', 0, () => '#/time-analysis/trend', focus('.analysis-totals')],
  ['time-calendar', 1, s => `#/projects/${s.web}/time`],
  ['boards', 0, s => `#/projects/${s.web}/boards`],
  ['converted', 1, s => `#/projects/${s.web}/issues/${s.converted}?detail=1`],
  ['tokens', 0, () => '#/', async page => { await page.getByRole('button', { name: /帳號|Account/ }).first().click(); await page.getByRole('menuitem', { name: /API Token/i }).or(page.getByRole('button', { name: /API Token/i })).first().click(); await page.getByRole('dialog').waitFor(); await page.waitForTimeout(400); }],
];

mkdirSync(raw, { recursive: true });
let server, browser;
try {
  server = await preview({ preview: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': api } } });
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  for (const locale of process.env.LOCALES ? process.env.LOCALES.split(',') : ['zh-TW', 'en']) {
    const data = await seed(locale);
    mkdirSync(`public-assets/screens/${locale}`, { recursive: true });
    for (const [size, viewport, scale] of [['desktop', { width: 1280, height: 800 }, 1], ['mobile', { width: 390, height: 760 }, 2]]) {
      const contexts = {};
      for (const [name, who, hash, prepare] of shots) {
        if (only && !only.includes(name)) continue;
        contexts[who] ||= await signIn(browser, locale, data.people[who].email, data.password, viewport, scale);
        const page = await contexts[who].newPage();
        const errors = []; page.on('pageerror', error => errors.push(error.message));
        await page.goto(`${origin}/app/${hash(data)}`);
        await page.locator('main').waitFor();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(500);
        if (prepare) await prepare(page, size).catch(async error => { await page.screenshot({ path: `${raw}/failed-${locale}-${name}-${size}.png` }); throw error; });
        await page.evaluate(() => document.fonts.ready);
        // 遮住工作區之外可能出現的 email（帳號選單等）；畫面本身只有合成姓名。
        const file = `${raw}/${locale}-${name}-${size}.png`;
        await page.screenshot({ path: file, animations: 'disabled', mask: [page.locator('text=/@example\\.test/')] });
        execFileSync('cwebp', ['-quiet', '-q', '82', '-m', '6', file, '-o', `public-assets/screens/${locale}/${name}-${size}.webp`]);
        assert.deepEqual(errors, [], `${locale} ${name} ${size}`);
        await page.close();
      }
      for (const context of Object.values(contexts)) await context.close();
    }
    console.log(`captured ${locale}`);
  }
} finally { await browser?.close(); await server?.close(); }
