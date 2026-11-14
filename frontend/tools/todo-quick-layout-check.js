import assert from 'node:assert/strict';
import { app as inspectApp } from '../../.agents/skills/design-taste-frontend/scripts/lib/probe.mjs';
import { judgeApp } from '../../.agents/skills/design-taste-frontend/scripts/lib/rules.mjs';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createServer } from 'vite';
import { chromium } from 'playwright';
import { fixtureResponse } from './fixtures/verification.js';
const port = Number(process.env.E2E_FRONTEND_PORT || 5175), target = process.env.E2E_API_URL;
if (process.env.E2E_ISOLATED !== '1' || !['http://127.0.0.1:13199', 'http://127.0.0.1:13198', 'http://127.0.0.1:13079'].includes(target)) throw Error('Owned isolated note layout required');
let server, browser;
const measurements = [];
mkdirSync('../artifacts/tt331/layout', { recursive: true });
try {
  server = await createServer({ server: { host: '127.0.0.1', port, strictPort: true, proxy: { '/api': target } } }); await server.listen();
  browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const origin = `http://127.0.0.1:${port}`; await page.goto(origin + '/login');
  const csrf = await (await page.request.get(origin + '/api/csrf-cookie')).json();
  const result = await page.request.post(origin + '/api/auth/register', { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { name: '紙條排版測試', email: `layout-${crypto.randomUUID()}@example.test`, password: 'isolated-notes-layout', workspaceName: '排版測試' } });
  await fixtureResponse(page.request, result); await page.goto(origin + '/app/#/notes');
  await page.getByRole('tab', { name: '建立', exact: true }).click();
  await page.getByLabel('紙條內容', { exact: true }).fill('排版測試紙條');
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    for (const expanded of [false, true]) {
      if (expanded) await page.getByRole('button', { name: '搜尋交給誰選項', exact: true }).click();
      const field = await page.getByLabel('交給誰', { exact: true }).boundingBox(), button = await page.getByRole('button', { name: '建立紙條', exact: true }).boundingBox();
      assert.ok(field.height >= 44 && field.height <= 46); assert.ok(button.height >= 44 && button.height <= 46);
      assert.ok(width >= 480 ? Math.abs(field.y - button.y) <= 1 : button.y >= field.y + field.height);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
      const design = await page.evaluate(inspectApp);
      measurements.push({ width, expanded, field, button, designFindings: judgeApp(design) });
      await page.screenshot({ path: `../artifacts/tt331/layout/create-${width}-${expanded}.png` });
      if (expanded) await page.getByRole('button', { name: '搜尋交給誰選項', exact: true }).click();
    }
  }
  await page.getByRole('button', { name: '建立紙條', exact: true }).click(); await page.getByRole('button', { name: '更多：排版測試紙條', exact: true }).click();
  const note = page.getByRole('dialog', { name: '紙條', exact: true });
  const before = await note.boundingBox(); await note.getByLabel('紙條操作').click(); await note.getByRole('button', { name: '編輯', exact: true }).waitFor();
  const after = await note.boundingBox(); assert.equal(before.height, after.height);
  writeFileSync('../artifacts/tt331/layout/measurements.json', JSON.stringify(measurements, null, 2));
  console.log('PASS: notes create/select/search 44px alignment, expanded search, 3 widths and floating drawer menu does not stretch layout');
} finally { await browser?.close(); await server?.close(); }
