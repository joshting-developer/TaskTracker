export async function callApi(operation, input = {}) {
  let result;
  try {
    const response = await fetch(`/api/rpc/${operation}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(input) });
    result = await response.json();
  } catch {
    throw Object.assign(new Error('暫時無法連線, 請確認服務已啟動後重試'), { code: 'NETWORK_ERROR' });
  }
  if (!result.ok) throw Object.assign(new Error(result.error?.message || '操作失敗'), { code: result.error?.code });
  return result.data;
}

export const api = {
  getWorkspace: () => callApi('getWorkspace'),
  createProject: (input) => callApi('createProject', input),
  createIssue: (input) => callApi('createIssue', input),
  updateIssue: (input) => callApi('updateIssue', input),
  addComment: (input) => callApi('addComment', input),
  loadDemo: (input) => callApi('loadDemo', input),
};
