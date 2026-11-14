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

// Use the visible form so browser fixtures also cover the pending-account boundary.
export async function verifyFixtureInBrowser(page) {
  assert.equal(process.env.E2E_ISOLATED, '1', 'Browser verification requires explicit isolated fixtures');
  await page.getByLabel('驗證碼', { exact: true }).fill('local666');
  const submit = async () => {
    const [response] = await Promise.all([
      page.waitForResponse(value => value.url().endsWith('/api/auth/verify-email') && value.request().method() === 'POST'),
      page.getByRole('button', { name: '驗證並登入', exact: true }).click(),
    ]);
    return response;
  };
  let response = await submit();
  if (response.status() === 429) {
    const seconds = Number(response.headers()['retry-after']);
    assert.ok(Number.isFinite(seconds) && seconds >= 0 && seconds <= 60, 'Bounded Retry-After required');
    await page.waitForTimeout(seconds * 1000 + 250);
    response = await submit();
  }
  assert.equal(response.status(), 200, await response.text());
  await page.locator('.app-shell').waitFor();
}
