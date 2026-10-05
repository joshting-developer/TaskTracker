import { createServer } from 'node:http';

const OPERATIONS = ['getWorkspace', 'createProject', 'createIssue', 'updateIssue', 'addComment', 'loadDemo'];
const STATUS = { INVALID_INPUT: 400, NOT_FOUND: 404, REVISION_CONFLICT: 409, REQUEST_CONFLICT: 409, DUPLICATE_CODE: 409, DEMO_EXISTS: 409 };
export function createApiServer(service, { allowedOrigins = ['http://127.0.0.1:5174', 'http://localhost:5174', 'http://127.0.0.1:4174', 'http://localhost:4174', 'http://127.0.0.1:3002'] } = {}) {
  return createServer(async (req, res) => {
    res.setHeader('Content-Type', 'application/json; charset=utf-8');
    res.setHeader('Cache-Control', 'no-store');
    res.setHeader('X-Content-Type-Options', 'nosniff');
    const respond = (status, data) => { res.statusCode = status; res.end(JSON.stringify(data)); };
    try {
      const host = (req.headers.host || '').split(':')[0];
      if (!['127.0.0.1', 'localhost'].includes(host)) return respond(403, { ok: false, error: { code: 'FORBIDDEN', message: '原型僅供本機使用' } });
      if (req.method === 'GET' && req.url === '/api/health') return respond(200, { ok: true });
      const operation = req.url?.match(/^\/api\/rpc\/([A-Za-z]+)$/)?.[1];
      if (!OPERATIONS.includes(operation)) return respond(404, { ok: false, error: { code: 'NOT_FOUND', message: '找不到這個操作' } });
      if (req.method !== 'POST') return respond(405, { ok: false, error: { code: 'METHOD_NOT_ALLOWED', message: '請使用 POST' } });
      if (!req.headers['content-type']?.startsWith('application/json')) return respond(415, { ok: false, error: { code: 'INVALID_INPUT', message: '需要 JSON 資料' } });
      if (req.headers.origin && !allowedOrigins.includes(req.headers.origin)) {
        return respond(403, { ok: false, error: { code: 'FORBIDDEN', message: '不允許這個請求來源' } });
      }
      if (req.headers['sec-fetch-site'] === 'cross-site') return respond(403, { ok: false, error: { code: 'FORBIDDEN', message: '不允許跨網站操作' } });
      let body = '';
      for await (const chunk of req) {
        body += chunk;
        if (Buffer.byteLength(body) > 50000) return respond(413, { ok: false, error: { code: 'INVALID_INPUT', message: '資料太大' } });
      }
      let input;
      try { input = JSON.parse(body); } catch { return respond(400, { ok: false, error: { code: 'INVALID_INPUT', message: 'JSON 格式不正確' } }); }
      respond(200, { ok: true, data: service[operation](input) });
    } catch (error) {
      if (!error.code) console.error('API operation failed:', error.message);
      respond(STATUS[error.code] || 500, { ok: false, error: { code: error.code || 'INTERNAL_ERROR', message: error.code ? error.message : '操作失敗, 請稍後再試' } });
    }
  });
}
