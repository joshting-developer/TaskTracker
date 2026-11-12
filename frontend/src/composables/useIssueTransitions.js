import { onScopeDispose, readonly, shallowRef, watch } from 'vue';
import { api } from '../api/index.js';

// 卡片移動的重試識別、送出中狀態與過期回應同屬一個生命週期。
// apply(issue, fields)可先在畫面套用移動並回傳還原函式；失敗時還原，成功則等saved重讀後交回伺服器資料。
export function useIssueTransitions({ canWrite, contextKey, saved, refresh, onError, update = api.updateIssue, apply }) {
  const moving = shallowRef('');
  const requests = new Map();
  let generation = 0;
  function invalidate() { generation++; moving.value = ''; requests.clear(); }
  watch(contextKey, invalidate, { flush: 'sync' });
  onScopeDispose(invalidate);

  async function moveIssue(issue, fields) {
    if (!canWrite.value || moving.value || Object.entries(fields).every(([key, value]) => issue[key] === value)) return;
    const current = generation;
    const signature = `${issue.id}:${issue.revision}:${JSON.stringify(fields)}`;
    if (!requests.has(signature)) requests.set(signature, crypto.randomUUID());
    moving.value = issue.id;
    const rollback = apply?.(issue, fields);
    try {
      const result = await update({ id: issue.id, ...fields, expectedRevision: issue.revision, requestId: requests.get(signature) });
      if (current !== generation) return;
      await saved(result);
      requests.delete(signature);
    } catch (failure) {
      // 換租戶／範圍後舊列已清空，不再還原到新畫面。
      if (current !== generation) return;
      rollback?.();
      if (failure.code === 'REVISION_CONFLICT') {
        await refresh();
        if (current === generation) onError(`${failure.message}, 清單已更新`);
      } else onError(failure.message);
    } finally {
      if (current === generation) moving.value = '';
    }
  }
  return { moving: readonly(moving), moveIssue };
}
