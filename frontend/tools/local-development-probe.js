// Dedicated local-dev only; never point this probe at a configurable production URL.
import assert from 'node:assert/strict';
import { readFileSync, mkdirSync, writeFileSync } from 'node:fs';
import { chromium } from 'playwright';
const config = JSON.parse(readFileSync(new URL('../../.local/local-dev-credentials.json', import.meta.url)));
assert.equal(config.baseUrl, 'http://127.0.0.1:5174');
const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH });
const page = await browser.newPage({ locale: 'zh-TW', viewport: { width: 1440, height: 1000 } });
const errors = []; page.on('pageerror', error => errors.push(error.message));
const dir = new URL('../../artifacts/tt370/', import.meta.url); mkdirSync(dir, { recursive: true });
try {
  await page.goto(config.baseUrl + '/login');
  await page.getByLabel('Email', { exact: true }).fill(config.accounts[0]);
  await page.getByLabel('密碼', { exact: true }).fill(config.password);
  await page.getByRole('button', { name: '登入', exact: true }).last().click();
  await page.locator('.app-shell').waitFor();
  const result = await page.evaluate(async () => {
    const { api } = await import('/src/api/index.js');
    const projects = await api.projects({ perPage: 20 });
    const project = projects.items.find(item => item.name === 'first');
    if (!project) throw new Error('Missing synthetic first project');
    const issues = await api.issues(project.id, { perPage: 20 });
    const issue = await api.issue(project.id, issues.items[0].id);
    const before = issue.title;
    await api.updateIssue({ id: issue.id, projectId: project.id, expectedRevision: issue.revision, title: before + ' local probe', requestId: crypto.randomUUID() });
    const updated = await api.issue(project.id, issue.id);
    if (updated.title !== before + ' local probe') throw new Error('Edit did not persist');
    await api.updateIssue({ id: issue.id, projectId: project.id, expectedRevision: updated.revision, title: before, requestId: crypto.randomUUID() });
    const boards = await api.boards(project.id, { perPage: 20 });
    const todo = await api.createTodo({ content: 'Local development verification', requestId: crypto.randomUUID() });
    return { project: project.id, issue: issue.id, title: before, board: boards.items[0]?.id || 'default', todo: todo.id };
  });
  await page.goto(`${config.baseUrl}/app/#/projects/${result.project}`);
  await page.getByText(result.title, { exact: true }).first().waitFor();
  await page.goto(`${config.baseUrl}/app/#/projects/${result.project}/boards`);
  await page.getByText('Default', { exact: true }).waitFor();
  await page.goto(`${config.baseUrl}/app/#/notes`);
  await page.locator(`[data-todo-id="${result.todo}"]`).waitFor();
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1000 });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({ path: new URL(`local-notes-${width}.png`, dir).pathname });
  }
  assert.deepEqual(errors, []);
  writeFileSync(new URL('result.json', dir), JSON.stringify({ login: true, projects: true, issueEdit: true, boards: true, notes: true, errors }, null, 2));
  console.log('PASS local-dev: seeded login, project/list, persisted issue edit+restore, boards, notes and three viewport widths');
} finally { await browser.close(); }
