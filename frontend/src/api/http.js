let workspaceId = '', generation = 0, csrfToken = '', csrfPending;
const active = new Set();

export function setWorkspaceContext(id) {
  if (workspaceId === id) return;
  workspaceId = id;
  generation++;
  for (const controller of active) controller.abort();
}

function failure(code, message, status) {
  return Object.assign(new Error(message), { code, status });
}

function accessChanged(code) {
  if (['WORKSPACE_CHANGED', 'UNAUTHENTICATED', 'FORBIDDEN'].includes(code)) {
    globalThis.window?.dispatchEvent(new Event('tasktracker:access-changed'));
  }
}

async function csrf() {
  if (csrfToken) return csrfToken;
  if (!csrfPending) {
    csrfPending = requestJson('/api/csrf-cookie', { scoped: false }).then(data => {
      if (typeof data.csrfToken !== 'string') throw failure('INVALID_RESPONSE', '無法取得驗證資訊');
      csrfToken = data.csrfToken;
      return csrfToken;
    }).finally(() => { csrfPending = undefined; });
  }
  return csrfPending;
}

function waitForToken(signal) {
  return new Promise((resolve, reject) => {
    const canceled = () => reject(failure('ABORTED', '請求已取消'));
    if (signal.aborted) return canceled();
    signal.addEventListener('abort', canceled, { once: true });
    csrf().then(resolve, reject).finally(() => signal.removeEventListener('abort', canceled));
  });
}

/** HTTP傳輸：租戶generation、取消與逾時不與DomainError混為連線失敗。 */
export async function requestJson(path, { method = 'GET', input, scoped = true, signal, timeoutMs = 20000, retryCsrf = true, notifyAccess = true } = {}) {
  const captured = generation;
  const controller = new AbortController();
  let timedOut = false;
  const abort = () => controller.abort();
  if (signal?.aborted) abort();
  signal?.addEventListener('abort', abort, { once: true });
  if (scoped) active.add(controller);
  const timer = setTimeout(() => { timedOut = true; controller.abort(); }, timeoutMs);
  try {
    const headers = { Accept: 'application/json' };
    if (method !== 'GET') {
      headers['Content-Type'] = 'application/json';
      headers['X-CSRF-TOKEN'] = await waitForToken(controller.signal);
    }
    if (scoped && captured !== generation) throw failure('STALE_RESPONSE', '工作空間已切換');
    if (scoped && workspaceId) headers['X-Workspace-ID'] = workspaceId;
    const response = await fetch(path, { method, credentials: 'include', headers, signal: controller.signal, ...(input === undefined ? {} : { body: JSON.stringify(input) }) });
    const rotated = response.headers.get('X-CSRF-TOKEN');
    if (rotated) csrfToken = rotated;
    if (scoped && captured !== generation) throw failure('STALE_RESPONSE', '工作空間已切換，已捨棄舊回應');
    if (response.status === 419 && method !== 'GET' && retryCsrf) {
      csrfToken = '';
      return await requestJson(path, { method, input, scoped, signal: controller.signal, timeoutMs, retryCsrf: false, notifyAccess });
    }
    let body;
    try { body = await response.json(); }
    catch { throw failure('INVALID_RESPONSE', '伺服器回應格式不正確', response.status); }
    if (scoped && captured !== generation) throw failure('STALE_RESPONSE', '工作空間已切換，已捨棄舊回應');
    if (!body || typeof body !== 'object' || Array.isArray(body)) throw failure('INVALID_RESPONSE', '伺服器回應格式不正確', response.status);
    if (!response.ok || body.ok === false) {
      const code = body.error?.code || ({ 401: 'UNAUTHENTICATED', 403: 'FORBIDDEN', 404: 'NOT_FOUND', 409: 'CONFLICT', 419: 'CSRF_EXPIRED', 429: 'RATE_LIMITED' }[response.status]) || 'HTTP_ERROR';
      if (notifyAccess && scoped && workspaceId) accessChanged(code);
      throw failure(code, body.error?.message || '請求失敗', response.status);
    }
    return body.ok === true ? body.data : body;
  } catch (error) {
    if (scoped && captured !== generation) throw failure('STALE_RESPONSE', '工作空間已切換，已捨棄舊回應');
    if (timedOut) throw failure('TIMEOUT', '請求逾時，請稍後重試');
    if (controller.signal.aborted) throw failure('ABORTED', '請求已取消');
    if (typeof error.code === 'string') throw error;
    throw failure('NETWORK_ERROR', '暫時無法連線，請稍後重試');
  } finally {
    clearTimeout(timer);
    signal?.removeEventListener('abort', abort);
    active.delete(controller);
  }
}

/** 陣列使用Laravel的key[]語法，忽略未設定的可選欄位。 */
export function queryString(input = {}) {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(input)) {
    if (value === undefined || value === null || value === '') continue;
    if (Array.isArray(value)) value.forEach(item => params.append(`${key}[]`, item));
    else params.set(key, String(value));
  }
  return params.toString();
}

export function queryApi(path, filters = {}, options = {}) {
  const query = queryString(filters);
  return requestJson(`/api/v1/${path}${query ? `?${query}` : ''}`, options);
}
