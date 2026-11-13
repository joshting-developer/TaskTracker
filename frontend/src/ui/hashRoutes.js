// TT-285：工作空間hash網址的解析與組成（純函式, 不碰畫面狀態）；格式與既有深層連結完全相同。

/**
 * 解析 `location.hash`。
 *
 * @param {string} hash 例如 `#/projects/<id>/board/<boardId>/issues/<issueId>?detail=1`
 * @returns {{ project: string, issue: string, board: string, mode: 'list'|'board'|'boards', section: string, detail: boolean, landing: 'home'|'projects'|'settings'|'analysis', teamTab: 'general'|'members', analysis: 'index'|'members'|'projects'|'trend', invite: string, scope: ''|'mine', retiredEpics: boolean, retiredView: boolean, malformed: boolean }}
 */
export function parseHash(hash) {
  const [path, query = ''] = hash.split('?');
  const params = new URLSearchParams(query);
  const route = path.match(/^#\/projects\/([^/]+)(?:\/(board|boards)(?:\/((?!issues(?:\/|$))[^/]+))?)?(?:\/issues\/([^/]+))?$/);
  const featureRoute = path.match(/^#\/projects\/([^/]+)\/(epics|time|settings|members|notifications)$/);
  const legacyId = path.match(/^#\/issue\/([^/]+)$/)?.[1];
  let project = '', issue = '', board = 'default', malformed = false;
  try {
    project = route || featureRoute ? decodeURIComponent((route || featureRoute)[1]) : '';
    // 舊#/issue/<id>只有卡片id, 由App以issue-locations查出所屬專案。
    issue = route?.[4] ? decodeURIComponent(route[4]) : legacyId ? decodeURIComponent(legacyId) : '';
    if (route?.[2] === 'board' && route[3]) board = decodeURIComponent(route[3]);
  } catch {
    malformed = true;
  }
  const analysisRoute = path.match(/^#\/time-analysis(?:\/(members|projects|trend))?$/);
  const settingsRoute = path.match(/^#\/workspace\/settings(?:\/(general|members))?$/);
  const retiredEpics = featureRoute?.[2] === 'epics';
  return {
    project, issue, board, malformed,
    mode: route?.[2] || 'list',
    section: retiredEpics ? 'tasks' : featureRoute?.[2] || 'tasks',
    detail: Boolean(issue && params.get('detail') === '1'),
    landing: settingsRoute ? 'settings' : analysisRoute ? 'analysis' : path === '#/projects' || path === '#/projects/' ? 'projects' : 'home',
    teamTab: settingsRoute?.[1] || 'general',
    analysis: analysisRoute?.[1] || 'index',
    invite: path === '#/invite' ? params.get('token') || '' : '',
    // TT-329：「我的任務」的偏好只存在分頁的sessionStorage, 新分頁要靠網址帶?scope=mine
    scope: route && !route[2] && !issue && params.get('scope') === 'mine' ? 'mine' : '',
    retiredEpics,
    retiredView: Boolean(route && params.has('view')),
  };
}

/**
 * 組成hash；有專案時是專案內網址, 沒有時依landing回到首頁、專案列表、分析或設定。
 *
 * @param {{ project?: string, issue?: string, mode?: string, board?: string, detail?: boolean, section?: string, landing?: string, analysis?: string, teamTab?: string }} target 目標位置
 * @returns {string} 以 `#/` 開頭的hash
 */
export function buildHash({ project = '', issue = '', mode = 'list', board = 'default', detail = false, section = 'tasks', landing = 'home', analysis = 'index', teamTab = 'general' }) {
  if (project) {
    const target = issue ? 'tasks' : section;
    const feature = target !== 'tasks' ? `/${target}` : mode === 'boards' ? '/boards' : mode === 'board' ? `/board${board === 'default' ? '' : `/${encodeURIComponent(board)}`}` : '';
    return `#/projects/${encodeURIComponent(project)}${feature}${issue ? `/issues/${encodeURIComponent(issue)}${detail ? '?detail=1' : ''}` : ''}`;
  }
  if (landing === 'analysis') return `#/time-analysis${analysis === 'index' ? '' : `/${analysis}`}`;
  if (landing === 'settings') return settingsHash(teamTab);
  return landing === 'projects' ? '#/projects' : '#/';
}

/**
 * 工作空間設定頁的hash。
 *
 * @param {string} tab general或members
 * @returns {string} 設定頁hash
 */
export function settingsHash(tab = 'general') {
  return `#/workspace/settings${tab === 'members' ? '/members' : ''}`;
}
