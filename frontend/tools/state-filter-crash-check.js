import { verifyFixtureInBrowser } from './fixtures/verification.js';
// TT-258：狀態篩選勾選選項不得使頁面崩潰；在任務列表、我的任務與看板逐一勾選／取消／清除。
import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { openProjectList } from './navigation.js';
const e2ePort = Number(process.env.E2E_FRONTEND_PORT || 5175); // TT-335：Claude 5198、Codex 5199, 見.agents/skills/tasktracker-test-environments

const apiTarget = process.env.E2E_API_URL;
if (!apiTarget) throw new Error('請設定 E2E_API_URL 指向隔離 Laravel 測試服務');
let vite, browser;
const failures = [];
try {
  vite = await createServer({ logLevel: 'error', server: { host: '127.0.0.1', port: e2ePort, strictPort: true, proxy: { '/api': apiTarget } } });
  await vite.listen();
  browser = await chromium.launch({ headless: !process.env.E2E_HEADED, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  page.setDefaultTimeout(12000);
  page.on('pageerror', error => failures.push(error.message));
  page.on('crash', () => failures.push('PAGE_CRASH'));
  const token = crypto.randomUUID();
  await page.goto(`http://127.0.0.1:${e2ePort}/login`);
  await page.getByRole('button', { name: '第一次使用？建立工作空間' }).click();
  await page.getByLabel('你的名字').fill('Filter Owner');
  await page.getByLabel('Email', { exact: true }).fill(`filter-${token}@example.test`);
  await page.getByLabel('密碼', { exact: true }).fill('filter-test-password');
  await page.getByLabel('工作空間名稱', { exact: true }).fill('狀態篩選回驗');
  await page.getByRole('button', { name: '建立工作空間', exact: true }).click();
  await verifyFixtureInBrowser(page);
  await page.getByRole('heading', { name: '小待辦', exact: true }).waitFor();
  await page.evaluate(async () => {
    const { api } = await import('/src/api/index.js');
    const ws = await api.getWorkspace();
    let project = await api.createProject({ name: '篩選 first', code: 'FLT', requestId: crypto.randomUUID() });
    project = await api.project(project.id);
    await api.createIssue({ projectId: project.id, title: '待規劃卡', assignee: ws.meta.userId, requestId: crypto.randomUUID() });
    await api.createIssue({ projectId: project.id, title: '進行卡', state: 'inprogress', assignee: ws.meta.userId, requestId: crypto.randomUUID() });
  });
  await page.reload();
  await openProjectList(page);
  await page.getByRole('button', { name: '進入專案：篩選 first', exact: true }).click();

  async function exercise(where) {
    const toggle = page.getByRole('button', { name: '任務篩選', exact: true });
    await toggle.waitFor();
    if (await toggle.getAttribute('aria-expanded') === 'false') await toggle.click();
    const picker = page.locator('.status-filter details');
    if (!await picker.evaluate(el => el.open)) await page.getByLabel('篩選狀態', { exact: true }).click();
    // 以真實滑鼠點選項文字（非程式化check）；TT-258前Chrome 157會在此崩潰。
    const pick = label => page.locator('.status-filter-options label').filter({ hasText: label }).click({ position: { x: 60, y: 10 } });
    await pick('待規劃');
    await page.waitForTimeout(500);
    assert.ok(!failures.length, `${where} 點選項文字後：${failures.join('；')}`);
    assert.equal(await page.getByLabel('篩選狀態 待規劃', { exact: true }).isChecked(), true, `${where} 勾選保留`);
    assert.equal(await picker.evaluate(el => el.open), true, `${where} 多選時保持展開`);
    await pick('進行中');
    await pick('待規劃');
    assert.deepEqual(await page.locator('.status-filter-options input:checked').evaluateAll(els => els.map(el => el.value)), ['inprogress']);
    await page.getByRole('button', { name: '清除狀態', exact: true }).click();
    assert.equal(await page.locator('.status-filter-options input:checked').count(), 0);
    // 關閉方式：點外部、Esc、鍵盤Tab移出。
    await page.mouse.click(5, 880);
    assert.equal(await picker.evaluate(el => el.open), false, `${where} 點外部關閉`);
    await page.getByLabel('篩選狀態', { exact: true }).click();
    await page.keyboard.press('Escape');
    assert.equal(await picker.evaluate(el => el.open), false, `${where} Esc關閉`);
    await page.getByLabel('篩選狀態', { exact: true }).click();
    const options = await page.locator('.status-filter-options input').count();
    for (let i = 0; i <= options; i++) await page.keyboard.press('Tab');
    assert.equal(await picker.evaluate(el => el.open), false, `${where} Tab移出關閉`);
    await page.waitForTimeout(300);
    assert.ok(!failures.length, `${where}：${failures.join('；')}`);
    console.log('PASS', where);
  }
  await page.getByRole('link', { name: '任務列表', exact: true }).click();
  await exercise('任務列表');
  await page.getByRole('link', { name: /^我的任務/ }).click();
  await exercise('我的任務');
  await page.getByRole('link', { name: '任務看板', exact: true }).click();
  await page.getByRole('button', { name: '開啟看板 Default', exact: true }).click();
  await exercise('看板');
  assert.deepEqual(failures, []);
  console.log('PASS state filter crash check');
} finally {
  await browser?.close();
  await vite?.close();
}
