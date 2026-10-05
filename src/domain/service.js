import { canonical, categoryFor, dateInTaipei, fail, integer, ISSUE_FIELDS, PROJECT_COLORS, statusesFor, STATES, textField, validDate, validateIssue } from './model.js';

export function createService(store, { uuid, now, actor = '我', userId = '', role = 'admin', workspace = null, mode = 'local' }) {
  function readRecord(tx, kind, id) {
    const record = tx.get(kind, id);
    if (!record) fail('NOT_FOUND', '找不到這筆資料, 請重新載入');
    return record;
  }
  function mutation(operation, input, work) {
    if (!input || typeof input !== 'object') fail('INVALID_INPUT', '請求格式不正確');
    const requestId = textField(input.requestId, '操作識別碼', 100, true);
    return store.transaction((tx) => {
      const signature = canonical({ operation, input });
      const requestKey = userId ? `${userId}:${requestId}` : requestId;
      const previous = tx.get('requests', requestKey);
      if (previous) {
        if (previous.signature !== signature) fail('REQUEST_CONFLICT', '操作識別碼已使用, 請重新送出');
        return previous.result;
      }
      const result = work(tx);
      tx.put('requests', requestKey, { id: requestKey, signature, result });
      return result;
    });
  }
  function addProject(tx, input) {
    const name = textField(input.name, '專案名稱', 60, true);
    const code = textField(input.code, '專案代碼', 8, true).toUpperCase();
    if (!/^[A-Z][A-Z0-9]{1,7}$/.test(code)) fail('INVALID_INPUT', '專案代碼需為 2–8 位英文字母或數字, 並以英文字母開頭');
    if (tx.list('projects').some((project) => project.code === code)) fail('DUPLICATE_CODE', '這個專案代碼已使用');
    const project = { id: uuid(), name, code, description: textField(input.description ?? '', '專案說明', 500), color: PROJECT_COLORS[tx.list('projects').length % PROJECT_COLORS.length], nextNumber: 1, revision: 1, statuses: STATES.map((state) => ({ ...state })), settingsHistory: [], createdAt: now() };
    tx.put('projects', project.id, project);
    return project;
  }
  function addIssue(tx, input) {
    const project = readRecord(tx, 'projects', input.projectId);
    const fields = validateIssue(input, statusesFor(project));
    validateLinks(tx, project, fields);
    const issue = {
      id: uuid(), projectId: project.id, identifier: `${project.code}-${project.nextNumber}`,
      ...fields, revision: 1, createdAt: now(), updatedAt: now(),
      comments: [], history: [{ id: uuid(), kind: 'created', actor, at: now(), changes: [] }],
    };
    tx.put('projects', project.id, { ...project, nextNumber: project.nextNumber + 1 });
    tx.put('issues', issue.id, issue);
    notify(tx, issue, '指派任務', `${actor} 將任務指派給你`, issue.assignee);
    return issue;
  }
  function validateLinks(tx, project, fields, current) {
    if (fields.epicId) {
      const parent = readRecord(tx, 'issues', fields.epicId);
      if (parent.projectId !== project.id || parent.type !== 'epic' || fields.type === 'epic' || parent.id === current?.id) fail('INVALID_INPUT', 'Epic 必須是同專案的父項目, 不可巢狀或自我連結');
    }
    if (userId && fields.assignee && fields.assignee !== current?.assignee && !tx.get('members', fields.assignee)) fail('INVALID_INPUT', '負責人必須是工作空間成員');
    if (fields.type === 'epic' && fields.estimateMinutes) fail('INVALID_INPUT', 'Epic 的預估工時由子任務加總, 請在子任務填寫');
    if (current) {
      const children = tx.list('issues').filter((issue) => issue.epicId === current.id);
      if (fields.type !== 'epic' && children.length) fail('INVALID_INPUT', '請先移轉子任務, 再變更 Epic 類型');
      if (fields.type === 'epic' && current.type !== 'epic' && tx.list('timeEntries').some((entry) => entry.issueId === current.id && !entry.voidedAt)) fail('INVALID_INPUT', '已有工時的任務不可改成 Epic');
      if (fields.type === 'epic' && fields.state !== current.state && categoryFor(fields, project) === 'done' && children.some((issue) => !['done', 'canceled'].includes(categoryFor(issue, project)))) fail('INVALID_INPUT', '還有未結案子任務, 請先完成或取消後再驗收 Epic');
    }
  }
  function notify(tx, issue, subject, body, recipientId) {
    const member = recipientId && tx.get('members', recipientId);
    if (!member || recipientId === userId) return;
    const notification = { id: uuid(), issueId: issue.id, projectId: issue.projectId, recipientId, email: member.email, subject: `[${issue.identifier}] ${subject}`, body: `${body}\n${issue.title}`, status: 'preview', createdAt: now() };
    tx.put('notifications', notification.id, notification);
  }
  function checkAdmin() { if (role !== 'admin') fail('FORBIDDEN', '只有工作空間管理員可以修改專案狀態'); }
  function saveTime(tx, input, current = null) {
    if (current && (current.userId !== userId || current.voidedAt)) fail('FORBIDDEN', '只能修改自己的有效工時');
    const issue = readRecord(tx, 'issues', current?.issueId || input.issueId);
    if (issue.type === 'epic') fail('INVALID_INPUT', '請將工時登記在 Epic 的子任務');
    const workDate = validDate(input.workDate);
    if (workDate > dateInTaipei()) fail('INVALID_INPUT', '不能填報未來日期的工時');
    const minutes = integer(input.minutes, '工時分鐘', 1, 1440);
    const daily = tx.list('timeEntries').filter((entry) => !entry.voidedAt && entry.userId === userId && entry.workDate === workDate && entry.id !== current?.id).reduce((sum, entry) => sum + entry.minutes, 0);
    if (daily + minutes > 1440) fail('INVALID_INPUT', '同一人同一天的工時不能超過 24 小時');
    const entry = { ...(current || { id: uuid(), issueId: issue.id, projectId: issue.projectId, userId, createdAt: now(), history: [] }), workDate, minutes, note: textField(input.note ?? '', '工時說明', 1000), revision: (current?.revision || 0) + 1, updatedAt: now() };
    entry.history = [...entry.history, { actor, at: now(), before: current ? { workDate: current.workDate, minutes: current.minutes, note: current.note } : null, after: { workDate, minutes, note: entry.note } }];
    tx.put('timeEntries', entry.id, entry);
    return entry;
  }
  return {
    getWorkspace() {
      return store.transaction((tx) => ({
        projects: tx.list('projects'),
        issues: tx.list('issues').sort((a, b) => b.updatedAt.localeCompare(a.updatedAt) || a.identifier.localeCompare(b.identifier)),
        members: tx.list('members'), timeEntries: tx.list('timeEntries'),
        notifications: tx.list('notifications').filter((entry) => role === 'admin' || entry.recipientId === userId),
        meta: { mode, actor, userId, role, workspace, singleUser: !userId },
      }));
    },
    createProject(input) {
      return mutation('createProject', input, (tx) => addProject(tx, input));
    },
    createIssue(input) {
      return mutation('createIssue', input, (tx) => addIssue(tx, input));
    },
    updateIssue(input) {
      return mutation('updateIssue', input, (tx) => {
        const current = readRecord(tx, 'issues', input.id);
        if (input.expectedRevision !== current.revision) fail('REVISION_CONFLICT', '任務已被更新, 請重新載入後再修改');
        const project = readRecord(tx, 'projects', current.projectId);
        const next = validateIssue({ ...current, ...input }, statusesFor(project));
        validateLinks(tx, project, next, current);
        const changes = ISSUE_FIELDS.filter((field) => current[field] !== next[field]).map((field) => ({ field, before: current[field], after: next[field] }));
        if (!changes.length) return current;
        const issue = { ...current, ...next, revision: current.revision + 1, updatedAt: now(), history: [...current.history, { id: uuid(), kind: 'updated', actor, at: now(), changes }] };
        tx.put('issues', issue.id, issue);
        if (issue.assignee !== current.assignee) notify(tx, issue, '指派任務', `${actor} 將任務指派給你`, issue.assignee);
        if (issue.epicId) {
          const parent = tx.get('issues', issue.epicId);
          if (categoryFor(parent, project) === 'done' && !['done', 'canceled'].includes(categoryFor(issue, project))) notify(tx, parent, 'Epic 需要重新確認', `${actor} 更新了未結案子任務 ${issue.identifier}`, parent.assignee);
        }
        return issue;
      });
    },
    addComment(input) {
      return mutation('addComment', input, (tx) => {
        const current = readRecord(tx, 'issues', input.id);
        if (input.expectedRevision !== current.revision) fail('REVISION_CONFLICT', '任務已被更新, 請重新載入後再留言');
        const comment = { id: uuid(), content: textField(input.content, '留言', 2000, true), author: actor, createdAt: now() };
        const issue = { ...current, revision: current.revision + 1, updatedAt: now(), comments: [...current.comments, comment] };
        tx.put('issues', issue.id, issue);
        notify(tx, issue, '新留言', `${actor}：${comment.content}`, issue.assignee);
        return issue;
      });
    },
    updateStatuses(input) {
      checkAdmin();
      return mutation('updateStatuses', input, (tx) => {
        const project = readRecord(tx, 'projects', input.projectId);
        if (input.expectedRevision !== (project.revision || 1)) fail('REVISION_CONFLICT', '專案設定已更新, 請重新載入');
        if (!Array.isArray(input.statuses) || input.statuses.length < 2 || input.statuses.length > 12) fail('INVALID_INPUT', '請設定 2–12 個狀態');
        const statuses = input.statuses.map((state) => {
          const id = textField(state.id, '狀態 ID', 100, true);
          if (!/^[A-Za-z0-9_-]+$/.test(id) || !['backlog', 'active', 'done', 'canceled'].includes(state.category) || !/^#[0-9a-f]{6}$/i.test(state.color)) fail('INVALID_INPUT', '狀態分類、顏色或 ID 不正確');
          return { id, label: textField(state.label, '狀態名稱', 30, true), color: state.color, category: state.category };
        });
        if (new Set(statuses.map((state) => state.id)).size !== statuses.length || new Set(statuses.map((state) => state.label)).size !== statuses.length) fail('INVALID_INPUT', '狀態 ID 與名稱不可重複');
        if (!statuses.some((state) => state.category === 'done') || !statuses.some((state) => ['active', 'backlog'].includes(state.category))) fail('INVALID_INPUT', '至少需要一個完成與一個未結案狀態');
        if (tx.list('issues').some((issue) => issue.projectId === project.id && !statuses.some((state) => state.id === issue.state))) fail('INVALID_INPUT', '使用中的狀態不能移除, 請先移轉任務');
        const next = { ...project, statuses, revision: (project.revision || 1) + 1, settingsHistory: [...(project.settingsHistory || []), { actor, at: now(), before: statusesFor(project), after: statuses }] };
        tx.put('projects', project.id, next);
        return next;
      });
    },
    logTime(input) { return mutation('logTime', input, (tx) => saveTime(tx, input)); },
    updateTime(input) {
      return mutation('updateTime', input, (tx) => {
        const current = readRecord(tx, 'timeEntries', input.id);
        if (input.expectedRevision !== current.revision) fail('REVISION_CONFLICT', '工時已更新, 請重新載入');
        return saveTime(tx, input, current);
      });
    },
    voidTime(input) {
      return mutation('voidTime', input, (tx) => {
        const current = readRecord(tx, 'timeEntries', input.id);
        if (current.userId !== userId) fail('FORBIDDEN', '只能作廢自己的工時');
        if (input.expectedRevision !== current.revision) fail('REVISION_CONFLICT', '工時已更新, 請重新載入');
        if (current.voidedAt) return current;
        const next = { ...current, voidedAt: now(), updatedAt: now(), revision: current.revision + 1, history: [...current.history, { actor, at: now(), kind: 'voided' }] };
        tx.put('timeEntries', next.id, next);
        return next;
      });
    },
    loadDemo(input) {
      return mutation('loadDemo', input, (tx) => {
        if (tx.list('projects').some((project) => project.code === 'DEMO')) fail('DEMO_EXISTS', '示範專案已存在, 不會重複載入');
        const project = addProject(tx, { name: '產品第一版 · 示範', code: 'DEMO', description: '用這個示範專案試試清單、看板與任務修改, 內容不代表正式需求' });
        const demo = [
          ['整理第一版需求與使用流程', 'done', 'high', 'task', '我', '先把最常用的操作整理成一個短清單, 每個功能都要有明確的完成條件'],
          ['建立專案與任務資料結構', 'done', 'normal', 'task', '我', '任務需要有固定編號、狀態、負責人與修改紀錄'],
          ['設計任務清單的搜尋與篩選', 'in_progress', 'high', 'feature', '我', '可以用關鍵字找到任務, 再用狀態和優先級縮小範圍'],
          ['讓看板拖曳與任務狀態同步', 'in_progress', 'normal', 'feature', '我', '把卡片移到另一欄, 詳細內容與清單的狀態也要一起更新'],
          ['手機上的表單需要更好操作', 'todo', 'high', 'bug', '', '檢查按鈕大小與欄位間距, 小螢幕也能新增和修改任務'],
          ['補上任務留言與異動紀錄', 'todo', 'normal', 'feature', '我', '在詳細內容中留下討論, 並查看每次狀態或負責人變更'],
          ['確認 Google Apps Script 部署方式', 'todo', 'normal', 'task', '', '先使用獨立測試試算表, 確認真實讀寫與身分限制'],
          ['討論下一版的常用視圖', 'backlog', 'low', 'feature', '', '等使用一段時間後, 再決定需要哪些儲存搜尋與常用入口'],
          ['研究適合團隊的登入流程', 'backlog', 'normal', 'task', '', '用 SaaS 工作空間邀請協作者, 不限定 Google 群組'],
        ];
        demo.forEach(([title, state, priority, type, assignee, description]) => addIssue(tx, { projectId: project.id, title, state, priority, type, assignee: assignee === '我' && userId ? userId : assignee, description }));
        return { projectId: project.id };
      });
    },
  };
}
