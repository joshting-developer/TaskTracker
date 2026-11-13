let workspaceId = '', generation = 0, csrfToken = '', csrfPending;
const active = new Set();

export function setWorkspaceContext(id) {
  if (workspaceId === id) return;
  workspaceId = id;
  generation++;
  globalThis.window?.dispatchEvent(new Event('tasktracker:workspace-changed'));
  for (const controller of active) controller.abort();
}

function failure(code, message, status) {
  return Object.assign(new Error(message), { code, status });
}

function accessChanged(code, method) {
  // 撤權Project的寫入回NOT_FOUND以免洩漏存在性；只對寫入廣播，GET 404不觸發全域重驗。
  if (['WORKSPACE_CHANGED', 'UNAUTHENTICATED', 'FORBIDDEN'].includes(code) || (code === 'NOT_FOUND' && method !== 'GET')) {
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
export async function requestJson(path, { method = 'GET', input, scoped = true, signal, timeoutMs = 20000, retryCsrf = true, notifyAccess = true, binary = false } = {}) {
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
    const response = await fetch(path, { method, credentials: 'include', headers, signal: controller.signal, ...(binary ? { redirect: 'error' } : {}), ...(input === undefined ? {} : { body: JSON.stringify(input) }) });
    const rotated = response.headers.get('X-CSRF-TOKEN');
    if (rotated) csrfToken = rotated;
    if (scoped && captured !== generation) throw failure('STALE_RESPONSE', '工作空間已切換，已捨棄舊回應');
    if (response.status === 419 && method !== 'GET' && retryCsrf) {
      csrfToken = '';
      return await requestJson(path, { method, input, scoped, signal: controller.signal, timeoutMs, retryCsrf: false, notifyAccess });
    }
    if (binary && response.ok) {
      const mime = response.headers.get('Content-Type')?.split(';')[0];
      const length = Number(response.headers.get('Content-Length'));
      const label = binary === 'attachment' ? '附件' : '圖片', errorCode = binary === 'attachment' ? 'INVALID_ATTACHMENT' : 'INVALID_IMAGE';
      const allowed = binary === 'attachment' ? ['image/png', 'image/jpeg', 'image/webp', 'application/pdf', 'application/zip'] : ['image/png', 'image/jpeg', 'image/webp'];
      if (!allowed.includes(mime) || !length || length > 10 * 1024 * 1024) throw failure(errorCode, `${label}格式或大小不正確`);
      const blob = await response.blob();
      if (scoped && captured !== generation) throw failure('STALE_RESPONSE', '工作空間已切換');
      if (blob.size !== length) throw failure(errorCode, `${label}未完整載入`);
      const disposition = response.headers.get('Content-Disposition') || '';
      if (binary === 'attachment' && !/^attachment(?:;|$)/i.test(disposition)) throw failure('INVALID_ATTACHMENT', '附件下載回應不正確');
      let name = label;
      try { name = decodeURIComponent(disposition.match(/filename\*=utf-8''([^;]+)/i)?.[1] || disposition.match(/filename="([^"]+)"/)?.[1] || name); } catch { /* Use the safe fallback label. */ }
      return { blob, name, bytes: length };
    }
    let body;
    try { body = await response.json(); }
    catch { throw failure('INVALID_RESPONSE', '伺服器回應格式不正確', response.status); }
    if (scoped && captured !== generation) throw failure('STALE_RESPONSE', '工作空間已切換，已捨棄舊回應');
    if (!body || typeof body !== 'object' || Array.isArray(body)) throw failure('INVALID_RESPONSE', '伺服器回應格式不正確', response.status);
    if (!response.ok || body.ok === false) {
      const code = body.error?.code || ({ 401: 'UNAUTHENTICATED', 403: 'FORBIDDEN', 404: 'NOT_FOUND', 409: 'CONFLICT', 419: 'CSRF_EXPIRED', 429: 'RATE_LIMITED' }[response.status]) || 'HTTP_ERROR';
      if (notifyAccess && scoped && workspaceId) accessChanged(code, method);
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

/** Private images share tenant cancellation and current authorization with JSON reads. */
export function requestImage(path, options = {}) { return requestJson(path, { ...options, binary: true }); }

/** Download-only files retain tenant cancellation and strict binary size/type checks. */
export function requestAttachment(path, options = {}) { return requestJson(path, { ...options, binary: 'attachment' }); }

/** Multipart keeps the same tenant/CSRF boundary, with actual upload progress. */
export async function requestUpload(path, form, { signal, onProgress } = {}) {
  const captured = generation, controller = new AbortController();
  const abort = () => controller.abort();
  signal?.addEventListener('abort', abort, { once: true });
  if (signal?.aborted) abort();
  active.add(controller);
  try {
    const token = await waitForToken(controller.signal);
    if (captured !== generation) throw failure('STALE_RESPONSE', '工作空間已切換');
    return await new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      const cancel = () => xhr.abort();
      controller.signal.addEventListener('abort', cancel, { once: true });
      const cleanup = () => controller.signal.removeEventListener('abort', cancel);
      xhr.open('POST', path); xhr.withCredentials = true; xhr.timeout = 30000;
      xhr.setRequestHeader('Accept', 'application/json'); xhr.setRequestHeader('X-CSRF-TOKEN', token);
      if (workspaceId) xhr.setRequestHeader('X-Workspace-ID', workspaceId);
      xhr.upload.onprogress = event => { if (event.lengthComputable) onProgress?.(Math.round(event.loaded / event.total * 100)); };
      xhr.onload = () => {
        cleanup();
        if (captured !== generation) return reject(failure('STALE_RESPONSE', '工作空間已切換'));
        try {
          const body = JSON.parse(xhr.responseText);
          if (xhr.status === 419) csrfToken = '';
          if (xhr.status !== 200 || body.ok !== true) {
            const code = body.error?.code || 'HTTP_ERROR'; accessChanged(code, 'POST');
            return reject(failure(code, body.error?.message || '圖片上傳失敗', xhr.status));
          }
          resolve(body.data);
        } catch { reject(failure('INVALID_RESPONSE', '圖片上傳回應不正確', xhr.status)); }
      };
      xhr.onerror = () => { cleanup(); reject(failure('NETWORK_ERROR', '圖片上傳失敗, 可重試')); };
      xhr.ontimeout = () => { cleanup(); reject(failure('TIMEOUT', '圖片上傳逾時, 可重試')); };
      xhr.onabort = () => { cleanup(); reject(failure('ABORTED', '圖片上傳已取消')); };
      if (controller.signal.aborted) { cleanup(); return reject(failure('ABORTED', '圖片上傳已取消')); }
      xhr.send(form);
    });
  } finally { active.delete(controller); signal?.removeEventListener('abort', abort); }
}
