import assert from 'node:assert/strict';

// Explicitly complete the local/testing verification step for existing synthetic fixtures.
export async function fixtureResponse(request, response) {
  const result = await response.json();
  if (!result.data?.verificationRequired) return result;
  const url = new URL('/api/auth/verify-email', response.url()).href;
  const csrf = await (await request.get(new URL('/api/csrf-cookie', url).href)).json();
  const send = () => request.post(url, { headers: { 'X-CSRF-TOKEN': csrf.csrfToken }, data: { code: 'local666' } });
  let verified = await send();
  if (verified.status() === 429) {
    const seconds = Number(verified.headers()['retry-after']);
    assert.ok(Number.isFinite(seconds) && seconds >= 0 && seconds <= 60, 'Bounded Retry-After required');
    await new Promise(resolve => setTimeout(resolve, seconds * 1000 + 250));
    verified = await send();
  }
  assert.equal(verified.status(), 200, await verified.text());
  return verified.json();
}

// Business fixtures use the isolated shortcut; email-verification-check covers real activation links.
export async function verifyFixtureInBrowser(page) {
  assert.equal(process.env.E2E_ISOLATED, '1', 'Browser verification requires explicit isolated fixtures');
  await page.getByRole('heading', { name: '驗證信箱', exact: true }).waitFor();
  const send = () => page.evaluate(async () => {
    const csrf = await (await fetch('/api/csrf-cookie')).json();
    const result = await fetch('/api/auth/verify-email', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken }, body: JSON.stringify({ code: 'local666' }) });
    return { status: result.status, retryAfter: result.headers.get('Retry-After') };
  });
  let response = await send();
  if (response.status === 429) {
    const seconds = Number(response.retryAfter);
    assert.ok(response.retryAfter !== null && Number.isFinite(seconds) && seconds >= 0 && seconds <= 60, 'Bounded Retry-After required');
    await new Promise(resolve => setTimeout(resolve, seconds * 1000 + 250));
    response = await send();
  }
  assert.equal(response.status, 200, 'Isolated fixture verification failed');
  await page.reload();
  await page.locator('.app-shell').waitFor();
}
