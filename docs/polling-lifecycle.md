# 前端輪詢生命週期（TT-146，2026-11-12）

來源：[低成本部署與同步重構計畫](planning/TaskTracker_低成本部署與同步重構計畫-2026-11-11.md) §3.1–3.2。

## 現況

`frontend/src/composables/usePolling.js` 是共用輪詢生命週期，取代原本四處各自管理的 `setTimeout`／`visibilitychange`：

| 使用處 | 背景任務 | 暫停時 |
|---|---|---|
| `usePersonalSync` | 個人版本＋版本變更時的未讀數 | 增加世代並中止請求 |
| `useProjectTaskQueries` | 目前Project的列表／看板／詳細背景重讀 | 只中止請求 |
| `NotificationPreview` | 通知目前頁 | 中止列表與內文請求 |
| `TimeCalendar` | 月份摘要＋當日明細（填報面板開啟時略過） | 中止兩個請求 |

## 契約

- 只在「業務啟用（`enabled`）＋分頁可見＋`navigator.onLine`」時排程；隱藏、`offline`、`enabled`轉false或scope卸載即清除計時器並呼叫`onPause`。
- 背景輪次不重疊：上一輪未完成時，計時器到期或前景事件都不再送新一輪。
- 回到前景、`online`或`focus`：距上次背景輪次開始超過`minGap`（預設5秒）才立即補查，否則只重新排程。
- `restart()`用於範圍變更（帳號／Workspace切換）；`enabled`由false轉true只恢復排程，避免與呼叫端的範圍變更重複送出。
- 暫停時多數呼叫端只中止請求，不增加世代，讓同一世代的`finally`收回loading；`usePersonalSync`沒有loading狀態，維持原本世代＋中止。
- 間隔、退避與jitter仍由呼叫端`delay()`決定，數值維持原狀（30秒起、失敗倍增、上限120秒；`useProjectTaskQueries`原本無jitter）。請求世代、AbortController與401／403處理也留在呼叫端。

## 尚未處理（另卡）

- TT-147：可配置間隔（看板約30秒、通知約60秒）、統一jitter、5xx／429退避、401停止、403撤權清理及閒置延長。
- TT-148：有權限保護的輕量變更檢查端點。
- TT-149：拖曳、未儲存草稿與寫入衝突時的輪詢保護。
- 看板Reverb（`ui/boardRealtime.js`）的重連計時另屬即時通道，本卡未改。

## 驗證

- `node frontend/tools/polling-check.js`：週期執行、慢請求不重疊、隱藏停止、最短間隔去重、焦點去重、離線停止／恢復、停用／重新啟用、卸載移除監聽。
- 隔離MySQL堆疊：`browser-check`、`board-query-check`、`project-utility-check`、`time-calendar-check`通過；`personal-query-check`在未改動的基準程式同樣逾時，屬既有問題，不由本卡宣稱修復。
