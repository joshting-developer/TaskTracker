import { requestJson, queryString } from './http.js';

const options = { scoped: false, notifyAccess: false };
export const adminSession = signal => requestJson('/api/admin/session', { ...options, signal });
export const adminLogin = input => requestJson('/api/admin/session', { ...options, method: 'POST', input });
export const adminLogout = () => requestJson('/api/admin/session', { ...options, method: 'DELETE' });
export const adminStatistics = (days, signal) => requestJson(`/api/admin/statistics?${queryString({ days })}`, { ...options, signal });
export const adminWorkspaces = (before, signal) => requestJson(`/api/admin/workspaces?${queryString({ before })}`, { ...options, signal });
