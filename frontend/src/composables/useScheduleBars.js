import { computed } from 'vue';
import { t } from '../i18n/index.js';
import { dateInTaipei, isClosed, statusesFor } from '../domain/model.js';
import { scheduleSpan } from '../domain/schedule.js';

// TT-384：週曆、人員排程與甘特共用的條狀呈現；狀態色取專案設定, 逾期以台北今天判斷。

/**
 * 時程條狀的樣式、讀屏文字與逾期判斷。
 *
 * @param {() => object} project 目前專案（含狀態設定）
 * @returns {{today: string, overdue: Function, barClass: Function, barStyle: Function, barLabel: Function, initials: Function}} 條狀呈現工具
 */
export function useScheduleBars(project) {
  const today = dateInTaipei();
  const statuses = computed(() => new Map(statusesFor(project()).map(status => [status.id, status])));
  // 計畫結束日已過仍未關閉視為逾期, 和工具列「今天」同一個台北日期。
  const overdue = issue => !isClosed(issue) && scheduleSpan(issue).end < today;
  const stateLabel = issue => issue.stateLabel || statuses.value.get(issue.state)?.label || issue.state;
  const barClass = (bar, continued = true) => ({ closed: isClosed(bar.issue), overdue: overdue(bar.issue), before: continued && bar.continuesBefore, after: continued && bar.continuesAfter });
  const barStyle = issue => ({ '--status-color': statuses.value.get(issue.state)?.color || '#9a9da9' });
  const initials = name => Array.from(name.trim())[0]?.toUpperCase() || '';
  function barLabel(bar) {
    const { issue } = bar, range = issue.startDate && issue.endDate && issue.startDate !== issue.endDate ? `${issue.startDate} – ${issue.endDate}` : issue.startDate || issue.endDate;
    const state = overdue(issue) ? `${stateLabel(issue)}, ${t('已逾期')}` : stateLabel(issue);
    return t('{0} {1}, {2}, {3}, {4}', [issue.identifier, issue.title, state, range, issue.assigneeName || t('未指派')]);
  }
  return { today, overdue, barClass, barStyle, barLabel, initials };
}
