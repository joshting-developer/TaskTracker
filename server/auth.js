import { randomBytes, randomUUID, scryptSync, timingSafeEqual, createHash } from 'node:crypto';
import { fail, textField } from '../src/domain/model.js';

const hash = (value) => createHash('sha256').update(value).digest('hex');
const emailField = (value) => {
  const email = textField(value, 'Email', 254, true).toLowerCase();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) fail('INVALID_INPUT', 'Email 格式不正確');
  return email;
};
const publicUser = (user) => ({ id: user.id, name: user.name, email: user.email });
export function tenantStore(store, workspaceId) {
  return { transaction: (work) => store.transaction((tx) => work({
    get: (kind, id) => tx.get(`w:${workspaceId}:${kind}`, id),
    list: (kind) => tx.list(`w:${workspaceId}:${kind}`),
    put: (kind, id, value) => tx.put(`w:${workspaceId}:${kind}`, id, value),
  })) };
}
export function createAuth(store, { now = () => new Date().toISOString() } = {}) {
  const failures = new Map();
  function session(tx, user, workspace) {
    const token = randomBytes(32).toString('hex');
    tx.put('sessions', hash(token), { userId: user.id, workspaceId: workspace.id, expiresAt: new Date(Date.parse(now()) + 8 * 3600000).toISOString() });
    return { token, data: { user: publicUser(user), workspace: { id: workspace.id, name: workspace.name } } };
  }
  function resolve(token) {
    return store.transaction((tx) => {
      const current = token && tx.get('sessions', hash(token));
      if (!current || current.expiresAt <= now()) fail('UNAUTHENTICATED', '請先登入');
      const user = tx.get('accounts', current.userId);
      const workspace = tx.get('workspaces', current.workspaceId);
      const member = tx.get(`w:${current.workspaceId}:members`, current.userId);
      if (!user || !workspace || !member) fail('UNAUTHENTICATED', '登入已失效, 請重新登入');
      return { user: publicUser(user), workspace: { id: workspace.id, name: workspace.name }, role: member.role };
    });
  }
  return {
    resolve,
    register(input) {
      const email = emailField(input.email);
      const name = textField(input.name, '姓名', 60, true);
      const password = textField(input.password, '密碼', 128, true);
      if (password.length < 10) fail('INVALID_INPUT', '密碼至少 10 字');
      return store.transaction((tx) => {
        if (tx.list('accounts').some((user) => user.email === email)) fail('CONFLICT', '此 Email 已註冊, 請登入');
        const salt = randomBytes(16).toString('hex');
        const user = { id: randomUUID(), email, name, salt, passwordHash: scryptSync(password, salt, 64).toString('hex'), createdAt: now() };
        let workspace, role = 'admin';
        if (input.inviteToken) {
          const invite = tx.get('invites', hash(textField(input.inviteToken, '邀請碼', 100, true)));
          if (!invite || invite.consumedAt || invite.expiresAt <= now() || invite.email !== email) fail('INVALID_INPUT', '邀請已失效或 Email 不符');
          workspace = tx.get('workspaces', invite.workspaceId);
          role = 'member';
          tx.put('invites', invite.id, { ...invite, consumedAt: now() });
        } else {
          workspace = { id: randomUUID(), name: textField(input.workspaceName, '工作空間名稱', 60, true), createdAt: now() };
          const firstWorkspace = tx.list('workspaces').length === 0;
          tx.put('workspaces', workspace.id, workspace);
          if (firstWorkspace) {
            for (const kind of ['projects', 'issues', 'requests']) for (const record of tx.list(kind)) {
              const migrated = kind === 'issues' && record.assignee === '我' ? { ...record, assignee: user.id } : record;
              tx.put(`w:${workspace.id}:${kind}`, record.id, migrated);
            }
          }
        }
        tx.put('accounts', user.id, user);
        tx.put(`w:${workspace.id}:members`, user.id, { ...publicUser(user), role, joinedAt: now() });
        return session(tx, user, workspace);
      });
    },
    login(input) {
      const email = emailField(input.email);
      const password = textField(input.password, '密碼', 128, true);
      const recent = failures.get(email);
      if (recent && recent.count >= 5 && recent.until > Date.now()) fail('RATE_LIMIT', '嘗試次數過多, 請 5 分鐘後再試');
      return store.transaction((tx) => {
        const user = tx.list('accounts').find((account) => account.email === email);
        const salt = user?.salt || 'invalid-user-salt';
        const candidate = scryptSync(password, salt, 64);
        if (!user || !timingSafeEqual(candidate, Buffer.from(user.passwordHash, 'hex'))) {
          failures.set(email, { count: recent && recent.until > Date.now() ? recent.count + 1 : 1, until: Date.now() + 300000 });
          fail('UNAUTHENTICATED', 'Email 或密碼不正確');
        }
        failures.delete(email);
        const workspace = tx.list('workspaces').find((item) => tx.get(`w:${item.id}:members`, user.id));
        if (!workspace) fail('UNAUTHENTICATED', '找不到工作空間成員資格');
        return session(tx, user, workspace);
      });
    },
    logout(token) {
      store.transaction((tx) => { const record = token && tx.get('sessions', hash(token)); if (record) tx.put('sessions', hash(token), { ...record, expiresAt: now() }); });
      return null;
    },
    invite(context, input) {
      if (context.role !== 'admin') fail('FORBIDDEN', '只有工作空間管理員可以邀請成員');
      const email = emailField(input.email);
      return store.transaction((tx) => {
        if (tx.list(`w:${context.workspace.id}:members`).some((member) => member.email === email)) fail('CONFLICT', '這位成員已在工作空間');
        const token = randomBytes(32).toString('hex');
        const id = hash(token);
        const invite = { id, email, workspaceId: context.workspace.id, expiresAt: new Date(Date.parse(now()) + 7 * 86400000).toISOString(), createdAt: now() };
        tx.put('invites', id, invite);
        return { token, email, expiresAt: invite.expiresAt };
      });
    },
  };
}
