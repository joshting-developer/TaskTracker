import { requestImage } from './http.js';

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
  if (!uuid.test(projectId) || !uuid.test(attachmentId)) return Promise.reject(new Error('圖片參照不正確'));
  return new Promise((resolve, reject) => {
    queue.push({ path: `/api/v1/projects/${projectId}/attachments/${attachmentId}/content`, signal, resolve, reject });
    drain();
  });
}
