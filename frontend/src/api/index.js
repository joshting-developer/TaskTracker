let csrfToken = '';
async function csrf() {
  if (!csrfToken) {
    const response = await fetch('/api/csrf-cookie', { credentials: 'include', headers: { Accept: 'application/json' } });
    csrfToken = (await response.json()).csrfToken;
  }
  return csrfToken;
}

export async function callApi(operation, input = {}, group = 'rpc', retry = true) {
  let result;
  try {
    const response = await fetch(`/api/${group}/${operation}`, { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': await csrf() }, body: JSON.stringify(input) });
    const rotatedToken = response.headers.get('X-CSRF-TOKEN');
    if (rotatedToken) csrfToken = rotatedToken;
    if (response.status === 419 && retry) { csrfToken = ''; return callApi(operation, input, group, false); }
    result = await response.json();
  } catch {
    throw Object.assign(new Error('暫時無法連線, 請確認服務已啟動後重試'), { code: 'NETWORK_ERROR' });
  }
  if (!result.ok) {
    if ((result.error?.code === 'UNAUTHENTICATED' && operation !== 'getWorkspace') || (!['getWorkspace', 'getTodos'].includes(operation) && ['FORBIDDEN', 'NOT_FOUND'].includes(result.error?.code))) window.dispatchEvent(new Event('tasktracker:access-changed'));
    throw Object.assign(new Error(result.error?.message || '操作失敗'), { code: result.error?.code });
  }
  return result.data;
}

export const api = {
  register: (input) => callApi('register', input, 'auth'),
  login: (input) => callApi('login', input, 'auth'),
  logout: () => callApi('logout', {}, 'auth'),
  getWorkspace: () => callApi('getWorkspace'),
  realtimeConfig: () => callApi('config', {}, 'realtime'),
  authorizeRealtime: input => callApi('authorize', input, 'realtime'),
  getProfile: () => callApi('getProfile'),
  updateProfile: input => callApi('updateProfile', input),
  getWorkspaceSettings: () => callApi('getWorkspaceSettings'),
  updateWorkspace: input => callApi('updateWorkspace', input),
  setWorkspaceMemberRole: input => callApi('setWorkspaceMemberRole', input),
  getTodos: () => callApi('getTodos'),
  createTodo: input => callApi('createTodo', input),
  updateTodo: input => callApi('updateTodo', input),
  completeTodo: input => callApi('completeTodo', input),
  reopenTodo: input => callApi('reopenTodo', input),
  returnTodo: input => callApi('returnTodo', input),
  cancelTodo: input => callApi('cancelTodo', input),
  convertTodo: input => callApi('convertTodo', input),
  readTodoNotification: input => callApi('readTodoNotification', input),
  getProjectMembers: (input) => callApi('getProjectMembers', input),
  setProjectMember: (input) => callApi('setProjectMember', input),
  removeProjectMember: (input) => callApi('removeProjectMember', input),
  createBoard: (input) => callApi('createBoard', input),
  updateBoard: (input) => callApi('updateBoard', input),
  archiveBoard: (input) => callApi('archiveBoard', input),
  createProject: (input) => callApi('createProject', input),
  updateProject: (input) => callApi('updateProject', input),
  createIssue: (input) => callApi('createIssue', input),
  updateIssue: (input) => callApi('updateIssue', input),
  addComment: (input) => callApi('addComment', input),
  loadDemo: (input) => callApi('loadDemo', input),
  updateStatuses: (input) => callApi('updateStatuses', input),
  logTime: (input) => callApi('logTime', input),
  updateTime: (input) => callApi('updateTime', input),
  voidTime: (input) => callApi('voidTime', input),
  inviteMember: (input) => callApi('inviteMember', input),
  smtpStatus: () => callApi('smtpStatus'),
  verifySmtp: () => callApi('verifySmtp'),
};
