import assert from 'node:assert/strict';
import { effectScope, nextTick, shallowRef } from 'vue';

// 以EventTarget模擬瀏覽器可見性、連線與焦點事件；不連任何服務。
const doc = Object.assign(new EventTarget(), { hidden: false });
const win = new EventTarget();
let onLine = true;
globalThis.document = doc;
globalThis.window = win;
Object.defineProperty(globalThis, 'navigator', { configurable: true, get: () => ({ onLine }) });
const { usePolling, pollDelay } = await import('../src/composables/usePolling.js');
const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
const setHidden = value => { doc.hidden = value; doc.dispatchEvent(new Event('visibilitychange')); };

function harness({ taskMs = 0, delay = 20, minGap = 60, outcome = () => null, ...rest } = {}) {
  const scope = effectScope(), enabled = shallowRef(true), stats = { runs: 0, active: 0, maxActive: 0, pauses: 0, times: [] };
  const poller = scope.run(() => usePolling({
    enabled, interval: delay, minGap, ...rest,
    task: async () => { stats.runs++; stats.times.push(Date.now()); stats.active++; stats.maxActive = Math.max(stats.maxActive, stats.active); await wait(taskMs); stats.active--; poller.report(outcome(stats.runs)); },
    onPause: () => { stats.pauses++; },
  }));
  return { scope, enabled, stats, poller };
}

// 可見、在線且啟用才依間隔執行。
{
  const { scope, stats, poller } = harness();
  poller.start();
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
  poller.start();
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
  poller.start();
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
  poller.start();
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

// 間隔計算：±10% jitter，失敗加倍且有上限。
assert.equal(pollDelay(30000, 0, 300000, () => 0), 27000);
assert.equal(pollDelay(30000, 0, 300000, () => 1), 33000);
assert.equal(pollDelay(30000, 1, 300000, () => 0.5), 60000);
assert.equal(pollDelay(30000, 3, 300000, () => 0.5), 240000);
assert.equal(pollDelay(30000, 9, 300000, () => 0.5), 300000, '退避上限');

// 5xx／429／網路錯誤退避，成功後回到基準。
{
  const fail = { code: 'HTTP_ERROR', status: 503 };
  const { scope, stats, poller } = harness({ delay: 15, maxBackoff: 1000, outcome: run => run <= 2 ? (run === 1 ? fail : { code: 'RATE_LIMIT', status: 429 }) : null });
  poller.start();
  await wait(200);
  const gaps = stats.times.slice(1).map((time, index) => time - stats.times[index]);
  assert.ok(gaps[0] >= 25, `第一次失敗後至少約兩倍間隔：${gaps}`);
  assert.ok(gaps[1] >= 50, `429再加倍：${gaps}`);
  assert.ok(gaps.at(-1) < 30, `成功後回到基準：${gaps}`);
  scope.stop();
}

// 401停止；403／404／換Workspace停止，restart或成功回報後恢復。
for (const failure of [{ code: 'UNAUTHENTICATED', status: 401 }, { code: 'FORBIDDEN', status: 403 }, { code: 'NOT_FOUND', status: 404 }, { code: 'WORKSPACE_CHANGED', status: 409 }]) {
  const { scope, stats, poller } = harness({ delay: 10, minGap: 0, outcome: run => run === 1 ? failure : null });
  poller.start();
  await wait(60);
  assert.equal(stats.runs, 1, `${failure.code}後停止`);
  assert.ok(poller.halted.value, `${failure.code}標示停止`);
  setHidden(true); setHidden(false); win.dispatchEvent(new Event('online'));
  await wait(30);
  assert.equal(stats.runs, 1, `${failure.code}停止後前景事件不恢復`);
  await poller.restart();
  await wait(30);
  assert.ok(stats.runs >= 3, `${failure.code}restart後恢復`);
  scope.stop();
}
{
  const { scope, stats, poller } = harness({ delay: 10, outcome: () => ({ code: 'FORBIDDEN', status: 403 }) });
  poller.start();
  await wait(30);
  poller.report(null);
  assert.equal(poller.halted.value, '', '前景成功後解除停止');
  scope.stop();
  assert.equal(stats.runs >= 1, true);
}
// 取消與舊回應不計失敗。
{
  const { scope, stats, poller } = harness({ delay: 10, outcome: () => ({ code: 'ABORTED' }) });
  poller.start();
  await wait(60);
  assert.ok(stats.runs >= 3, '取消不退避');
  scope.stop();
}

// 閒置延長間隔，使用者操作後立即補查並回到基準。
{
  const { scope, stats, poller } = harness({ delay: 10, minGap: 0, idleAfter: 30, idleInterval: 1000 });
  poller.start();
  await wait(80);
  const settled = stats.runs;
  await wait(80);
  assert.equal(stats.runs, settled, `閒置後延長：${settled}`);
  win.dispatchEvent(new Event('pointerdown'));
  await wait(5);
  assert.equal(stats.runs, settled + 1, '恢復操作立即補查');
  await wait(25);
  assert.ok(stats.runs >= settled + 2, '恢復操作後回到基準');
  scope.stop();
}

console.log('polling lifecycle PASS');
