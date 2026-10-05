import { mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { randomUUID } from 'node:crypto';
import { createSqliteStore } from './repository.js';
import { createService } from '../src/domain/service.js';
import { createApiServer } from './app.js';
import { createAuth, tenantStore } from './auth.js';
import { createSmtp } from './smtp.js';

const filename = resolve(process.env.DATABASE_PATH || 'data/tasktracker.sqlite');
mkdirSync(dirname(filename), { recursive: true });
const store = createSqliteStore(filename);
const server = createApiServer(null, {
  auth: createAuth(store), smtp: createSmtp(),
  serviceFor: (context) => createService(tenantStore(store, context.workspace.id), { uuid: randomUUID, now: () => new Date().toISOString(), actor: context.user.name, userId: context.user.id, role: context.role, workspace: context.workspace }),
});
server.listen(3002, '127.0.0.1', () => console.log('TaskTracker API: http://127.0.0.1:3002'));
for (const signal of ['SIGTERM', 'SIGINT']) process.on(signal, () => server.close(() => { store.close(); process.exit(0); }));
