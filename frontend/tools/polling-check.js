import assert from 'node:assert/strict';
import { effectScope, nextTick, shallowRef } from 'vue';

// 以EventTarget模擬瀏覽器可見性、連線與焦點事件；不連任何服務。
const doc = Object.assign(new EventTarget(), { hidden: false });
const win = new EventTarget();
let onLine = true;
globalThis.document = doc;
globalThis.window = win;
Object.defineProperty(globalThis, 'navigator', { configurable: true, get: () => ({ onLine }) });
const { usePolling } = await import('../src/composables/usePolling.js');
const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
const setHidden = value => { doc.hidden = value; doc.dispatchEvent(new Event('visibilitychange')); };

function harness({ taskMs = 0, delay = 20, minGap = 60 } = {}) {
  const scope = effectScope(), enabled = shallowRef(true), stats = { runs: 0, active: 0, maxActive: 0, pauses: 0 };
  const poller = scope.run(() => usePolling({
    enabled, delay: () => delay, minGap,
    task: async () => { stats.runs++; stats.active++; stats.maxActive = Math.max(stats.maxActive, stats.active); await wait(taskMs); stats.active--; },
    onPause: () => { stats.pauses++; },
  }));
  return { scope, enabled, stats, poller };
}

// 可見、在線且啟用才依間隔執行。
{
  const { scope, stats, poller } = harness();
  poller.schedule();
  await wait(75);
  assert.ok(stats.runs >= 2, `應定期執行：${stats.runs}`);
  scope.stop();
  const after = stats.runs;
  await wait(60);
  assert.equal(stats.runs, after, '卸載後不再執行');
  assert.ok(stats.pauses >= 1, '卸載時通知取消請求');
}

// 慢請求不重疊。
{
  const { scope, stats, poller } = harness({ taskMs: 50, delay: 5 });
  poller.schedule();
  win.dispatchEvent(new Event('focus'));
  await wait(160);
  assert.equal(stats.maxActive, 1, '同時最多一輪');
  scope.stop();
}

// 隱藏停止；最短間隔內回到前景不立即補查，超過才補查。
{
  const { scope, stats, poller } = harness({ delay: 1000, minGap: 60 });
  await poller.restart();
  assert.equal(stats.runs, 1);
  setHidden(true);
  assert.equal(stats.pauses, 1, '隱藏時取消');
  assert.equal(poller.visible.value, false);
  setHidden(false);
  await wait(5);
  assert.equal(stats.runs, 1, '最短間隔內不重查');
  setHidden(true);
  await wait(70);
  setHidden(false);
  await wait(5);
  assert.equal(stats.runs, 2, '超過最短間隔立即補查');
  win.dispatchEvent(new Event('focus'));
  await wait(5);
  assert.equal(stats.runs, 2, '焦點事件同樣去重');
  scope.stop();
}

// 隱藏期間計時器不觸發。
{
  const { scope, stats, poller } = harness({ delay: 20 });
  poller.schedule();
  setHidden(true);
  await wait(60);
  assert.equal(stats.runs, 0, '隱藏時不執行');
  setHidden(false);
  scope.stop();
}

// 離線停止，恢復連線補查。
{
  const { scope, stats, poller } = harness({ delay: 1000, minGap: 0 });
  await poller.restart();
  onLine = false; win.dispatchEvent(new Event('offline'));
  assert.equal(stats.pauses, 1, '離線時取消');
  assert.equal(await poller.restart().then(() => stats.runs), 1, '離線時restart也不送出');
  onLine = true; win.dispatchEvent(new Event('online'));
  await wait(5);
  assert.equal(stats.runs, 2, '恢復連線後補查');
  scope.stop();
}

// 停用（登出／失去權限）停止，重新啟用只恢復排程。
{
  const { scope, stats, enabled, poller } = harness({ delay: 20 });
  poller.schedule();
  enabled.value = false; await nextTick();
  assert.equal(stats.pauses, 1, '停用時取消');
  await wait(50);
  assert.equal(stats.runs, 0, '停用後不執行');
  enabled.value = true; await nextTick();
  assert.equal(stats.runs, 0, '重新啟用不重複立即送出');
  await wait(35);
  assert.equal(stats.runs, 1, '重新啟用後恢復排程');
  scope.stop();
}

// 卸載後移除全域監聽。
{
  const { scope, stats } = harness({ minGap: 0 });
  scope.stop();
  setHidden(true); setHidden(false);
  win.dispatchEvent(new Event('online'));
  win.dispatchEvent(new Event('focus'));
  await wait(10);
  assert.equal(stats.runs, 0, '卸載後事件不再觸發');
}

console.log('polling lifecycle PASS');
