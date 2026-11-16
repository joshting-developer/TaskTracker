// Explicit loopback origins only; creates a synthetic account and captures local mail.
import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { writeFileSync, mkdirSync } from 'node:fs';

const base = process.env.E2E_FRONTEND_URL, mailbox = process.env.E2E_MAILBOX_URL;
for (const value of [base, mailbox]) {
  const url = new URL(value);
  assert.equal(url.protocol, 'http:');
  assert.equal(url.hostname, '127.0.0.1');
  assert.equal(url.origin, value);
}
const folder = new URL('../../artifacts/registration-probe/', import.meta.url);
mkdirSync(folder, { recursive: true });
const browser = await chromium.launch({ headless: true });
try {
  const context = await browser.newContext({ locale: 'zh-TW', viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage(), errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto(base + '/login?mode=register');
  const email = `local-mail-${crypto.randomUUID()}@example.test`, password = crypto.randomUUID() + '-local';
  for (const [label, value] of [['你的名字', '本機驗證流程'], ['Email', email], ['密碼', password], ['確認密碼', password], ['工作空間名稱', '本機驗證流程']]) {
    await page.getByLabel(label, { exact: true }).fill(value);
  }
  const response = page.waitForResponse(result => new URL(result.url()).pathname === '/api/auth/register');
  await page.getByRole('button', { name: '建立工作空間', exact: true }).click();
  const registered = await response;
  assert.equal(registered.status(), 200);
  const data = (await registered.json()).data;
  assert.equal(data.verificationRequired, true);
  assert.equal(data.deliveryStatus, 'sent');
  await page.getByRole('heading', { name: '驗證信箱', exact: true }).waitFor();
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 1000 });
    await page.evaluate(() => document.fonts.ready);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.screenshot({ path: new URL(`verification-${width}.png`, folder).pathname, fullPage: true, animations: 'disabled', caret: 'hide' });
  }
  const messages = await (await context.request.get(mailbox + '/api/v1/messages')).json();
  const mail = messages.messages.find(message => message.To.some(to => to.Address === email));
  assert(mail, 'Captured verification mail must exist');
  const message = await (await context.request.get(mailbox + '/api/v1/message/' + mail.ID)).json();
  const match = message.Text.match(/https?:\/\/[^\s<>]+\/app\/#\/activate\/[a-f0-9]{64}/);
  assert(match, 'Local activation URL required');
  assert.equal(new URL(match[0]).origin, base);
  const activation = await context.newPage();
  await activation.goto(match[0]);
  await activation.getByRole('button', { name: '啟用帳號', exact: true }).click();
  await activation.locator('.app-shell').waitFor();
  await page.locator('.app-shell').waitFor();
  await page.reload();
  await page.locator('.app-shell').waitFor();
  assert.deepEqual(errors, []);
  writeFileSync(new URL('result.json', folder), JSON.stringify({ screenshots: 3, receivedLocally: true, activated: true, errors }));
  console.log('PASS local registration, captured mail and real activation link');
} finally {
  await browser.close();
}
