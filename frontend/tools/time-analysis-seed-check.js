import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { preview } from 'vite';
import { chromium } from 'playwright';
const e2ePort = Number(process.env.E2E_FRONTEND_PORT || 5175); // TT-335：Claude 5198、Codex 5199, 見.agents/skills/tasktracker-test-environments
if (!process.env.E2E_API_URL || !process.env.E2E_SEED_PASSWORD) throw new Error('請指定隔離seed API與測試密碼，不使用業務DB');
let server, browser;
try {
  server = await preview({ preview: { host: '127.0.0.1', port: e2ePort, strictPort: true, proxy: { '/api': process.env.E2E_API_URL } } });
  browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
  mkdirSync('artifacts/time-analysis-seed', { recursive: true });
  for (const role of ['owner', 'manager']) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    await page.goto(`http://127.0.0.1:${e2ePort}/login`);
    await page.getByLabel('Email', { exact: true }).fill(`${role}@example.test`);
    await page.getByLabel('密碼', { exact: true }).fill(process.env.E2E_SEED_PASSWORD);
    await page.getByRole('button', { name: '登入', exact: true }).click();
    await page.getByRole('link', { name: '工時分析', exact: true }).click();
    const region = page.getByRole('region', { name: '工時分析', exact: true });
    for (const [mode, title] of [['members', '成員投入'], ['projects', '專案投入'], ['trend', '時間趨勢']]) {
      await region.getByRole('button', { name: new RegExp(`^${title}`) }).click();
      await region.getByRole('button', { name: '近 28 天', exact: true }).click();
      await region.locator('.analysis-table tbody tr').first().waitFor();
      const rows = await region.locator('.analysis-table tbody tr').count();
      assert.equal(rows, mode === 'members' ? 4 : mode === 'projects' ? 2 : 20);
      if (mode === 'members') {
        assert.equal(await region.locator('.segment').count(), 7);
        const totals = await region.locator('.row-value').allTextContents();
        assert.ok(new Set(totals).size > 1, '成員有不同投入');
      }
      if (mode === 'projects') assert.equal(await region.locator('.chart-row').count(), 2);
      if (mode === 'trend') {
        const date = region.getByLabel('圖表工作日期', { exact: true });
        const value = await date.locator('option').nth(2).getAttribute('value');
        await date.selectOption(value);
        await region.locator('output').filter({ hasText: value }).waitFor();
        assert.ok(await region.locator('tbody tr').filter({ hasText: '0 h' }).count() > 0, '保留零日期');
        assert.equal((await region.locator('.plot-line').getAttribute('points')).split(' ').length, 28, '圖表保留完整期間');
        const totalText = await region.locator('.analysis-totals').textContent();
        const pagination = region.getByRole('navigation', { name: '分析明細分頁', exact: true });
        await pagination.getByRole('button', { name: '下一頁', exact: true }).click();
        assert.equal(await region.locator('tbody tr').count(), 8);
        assert.equal(await region.locator('.analysis-totals').textContent(), totalText, '翻頁不改完整期間總計');
        await pagination.getByRole('button', { name: '上一頁', exact: true }).click();
        await region.locator('.trend-plot svg').scrollIntoViewIfNeeded();
        const bounds = await region.locator('.trend-plot svg').boundingBox();
        await page.mouse.move(bounds.x + bounds.width / 2, bounds.y + 100);
        const middle = await date.locator('option').nth(14).getAttribute('value');
        await region.locator('output').filter({ hasText: middle }).waitFor();
        await date.selectOption(value);

      }
      if (role === 'owner') {
        const snapshot = await page.evaluate(() => {
          const root = document.documentElement.cloneNode(true);
          root.querySelectorAll('script, link[rel="stylesheet"]').forEach(node => node.remove());
          const style = document.createElement('style');
          style.textContent = [...document.styleSheets].flatMap(sheet => { try { return [...sheet.cssRules].map(rule => rule.cssText); } catch { return []; } }).join('\n');
          root.querySelector('head').appendChild(style);
          const originals = [...document.querySelectorAll('input, select')];
          root.querySelectorAll('input, select').forEach((node, index) => {
            if (node.tagName === 'INPUT') node.setAttribute('value', originals[index].value);
            else [...node.options].forEach(option => { if (option.value === originals[index].value) option.setAttribute('selected', ''); else option.removeAttribute('selected'); });
          });
          return '<!doctype html>' + root.outerHTML;
        });
        writeFileSync(`artifacts/time-analysis-seed/snapshot-${mode}.html`, snapshot);
      }
      for (const width of [1440, 768, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.evaluate(() => window.scrollTo(0, 0));
        if (width === 390) await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        await page.screenshot({ path: `artifacts/time-analysis-seed/${role}-${mode}-${width}.png`, fullPage: true });
      }
      await page.setViewportSize({ width: 1440, height: 1000 });
      if (mode === 'projects') {
        const column = region.locator('.project-column').first();
        const projectName = await column.locator('.project-name').textContent();
        await column.click();
        await region.getByRole('heading', { name: '成員投入', exact: true }).waitFor();
        await region.locator('.analysis-table tbody tr').first().waitFor();
        assert.equal(await region.getByLabel('分析專案').locator('option:checked').textContent(), projectName);
        await region.getByLabel('分析專案').selectOption('');
        await region.getByRole('button', { name: '套用範圍', exact: true }).click();
        await region.locator('.analysis-table tbody tr').first().waitFor();
      }
      await region.getByRole('button', { name: '返回工時分析', exact: true }).click();
    }
    assert.deepEqual(errors, []);
    await context.close();
  }
  console.log('PASS: seed Owner／Manager登入、四成員／兩專案／28天差異、7組成員專案分配、日期選值／游標、零日期、20筆分頁及完整期間圖表／總計、三尺寸');
} finally { await browser?.close(); await new Promise(resolve => server?.httpServer.close(resolve)); }
