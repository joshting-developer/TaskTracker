import { t } from '../i18n/index.js';
import { requestImage } from './http.js';

// TT-294：紙條私有圖片以此範圍代替Project ID, 走紙條附件端點, 不借用Project權限。
export const TODO_ATTACHMENTS = 'todo';
/** 上傳／限制／讀圖的根路徑 */
export function attachmentRoot(scope) { return scope === TODO_ATTACHMENTS ? '/api/v1/todo-attachments' : `/api/v1/projects/${encodeURIComponent(scope)}/attachments`; }

let running = 0;
const queue = [];
function drain() {
  while (running < 2 && queue.length) {
    const job = queue.shift();
    if (job.signal.aborted) { job.reject(new DOMException('Canceled', 'AbortError')); continue; }
    running++;
    requestImage(job.path, { signal: job.signal }).then(job.resolve, job.reject).finally(() => { running--; drain(); });
  }
}

/** At most two image streams across previews and descriptions, no cross-session cache. */
export function loadAttachmentImage(projectId, attachmentId, signal) {
  const uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
  if ((projectId !== TODO_ATTACHMENTS && !uuid.test(projectId)) || !uuid.test(attachmentId)) return Promise.reject(new Error(t('圖片參照不正確')));
  return new Promise((resolve, reject) => {
    queue.push({ path: `${attachmentRoot(projectId)}/${attachmentId}/content`, signal, resolve, reject });
    drain();
  });
}
