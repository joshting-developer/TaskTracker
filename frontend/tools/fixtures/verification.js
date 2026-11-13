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
