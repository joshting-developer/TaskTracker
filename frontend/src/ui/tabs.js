// 同一 tablist 內採自動啟用；Tab 只進入目前選取項目。
export function onTabKeydown(event) {
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
  const list = event.currentTarget;
  const tabs = [...list.querySelectorAll('[role="tab"]')].filter(tab => !tab.disabled && tab.getClientRects().length);
  const current = tabs.indexOf(document.activeElement);
  if (current < 0 || !tabs.length) return;
  event.preventDefault();
  const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (current + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
  tabs[next].focus();
  tabs[next].click();
}
