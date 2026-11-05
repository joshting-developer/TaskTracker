// 明確收錄與 Epic 動態展開分開保存, 同一 Issue 只出現一次
export function includedIssues(board, issues) {
  if (!board) return [];
  const candidates = issues.filter(issue => issue.projectId === board.projectId);
  if (board.isDefault) return candidates;
  const selected = new Set(board.selectedIssueIds);
  const epics = new Set(candidates.filter(issue => issue.type === 'epic' && selected.has(issue.id)).map(issue => issue.id));
  return candidates.filter(issue => selected.has(issue.id) || (issue.type !== 'epic' && epics.has(issue.epicId)));
}
export function boardCount(board, issues) {
  return includedIssues(board, issues).length;
}
