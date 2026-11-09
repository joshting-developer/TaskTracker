import { isClosed } from './model.js';

// 一份Project資料只建立一次關聯／工時索引，所有看板卡片共享。
export function buildIssueIndex(issues, entries) {
  const byId = new Map(issues.map(issue => [issue.id, issue]));
  const children = new Map(), epicChildren = new Map(), spent = new Map(), summaries = new Map();
  const append = (map, key, issue) => { if (!map.has(key)) map.set(key, []); map.get(key).push(issue); };
  for (const issue of issues) {
    if (issue.parentIssueId) append(children, issue.parentIssueId, issue);
    if (issue.epicId) append(epicChildren, issue.epicId, issue);
  }
  for (const entry of entries) {
    if (!entry.voidedAt && byId.has(entry.issueId)) spent.set(entry.issueId, (spent.get(entry.issueId) || 0) + entry.minutes);
  }
  for (const epic of issues) {
    if (epic.type !== 'epic') continue;
    const items = epicChildren.get(epic.id) || [];
    const done = items.filter(isClosed).length, total = items.length;
    summaries.set(epic.id, {
      children: items, done, canceled: 0, total, percent: total ? Math.round(done / total * 100) : null,
      estimateMinutes: items.reduce((sum, issue) => sum + (issue.estimateMinutes || 0), 0),
      spentMinutes: (spent.get(epic.id) || 0) + items.reduce((sum, issue) => sum + (spent.get(issue.id) || 0), 0),
      needsReview: isClosed(epic) && done < total,
    });
  }
  return { byId, children, spent, summaries };
}
