export async function callApi(operation, input = {}, group = 'rpc') {
  let result;
  try {
    const response = await fetch(`/api/${group}/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(input) });
    result = await response.json();
  } catch {
    throw Object.assign(new Error('暫時無法連線, 請確認服務已啟動後重試'), { code: 'NETWORK_ERROR' });
  }
  if (!result.ok) throw Object.assign(new Error(result.error?.message || '操作失敗'), { code: result.error?.code });
  return result.data;
}

export const api = {
  register: (input) => callApi('register', input, 'auth'),
  login: (input) => callApi('login', input, 'auth'),
  logout: () => callApi('logout', {}, 'auth'),
  getWorkspace: () => callApi('getWorkspace'),
  createProject: (input) => callApi('createProject', input),
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
  createSavedView: (input) => callApi('createSavedView', input),
  updateSavedView: (input) => callApi('updateSavedView', input),
  deleteSavedView: (input) => callApi('deleteSavedView', input),
};
