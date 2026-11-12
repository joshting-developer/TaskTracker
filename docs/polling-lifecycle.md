# 前端輪詢生命週期（TT-146／TT-147／TT-149，2026-11-12）

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
- 請求世代、AbortController與畫面清理（撤權時清空資料、通知App重驗）留在呼叫端。

## 間隔、jitter與退避（TT-147）

間隔集中於`frontend/src/ui/pollingConfig.js`，建置時可用環境變數覆寫；低於5秒或非數字忽略：

| 用途 | 預設 | 覆寫 |
|---|---|---|
| 任務列表／看板／詳細背景重讀 | 30秒 | `VITE_POLL_BOARD_MS` |
| 個人版本與未讀通知、通知預覽 | 60秒 | `VITE_POLL_NOTIFICATIONS_MS` |
| 工時月曆 | 30秒 | `VITE_POLL_TIME_MS` |

- 每輪加±10% jitter（看板原本沒有）。
- 呼叫端以`report(failure|null)`回報每次載入（背景或前景）：
  - 成功：重設退避；若先前停止則恢復排程。
  - 5xx、429、逾時、網路與其他錯誤：間隔依連續失敗次數加倍，上限5分鐘（原為120秒）；前景失敗也會把已排定的下一輪改為退避間隔。
  - 401：停止輪詢，前景事件不會恢復；重新登入時由`restart`／`start`解除。
  - 403／404／`WORKSPACE_CHANGED`：停止輪詢，呼叫端照舊清除不可見資料並通知App重驗；範圍變更或之後成功載入才恢復。
  - `ABORTED`／`STALE_RESPONSE`：不計失敗。
- 閒置：5分鐘內沒有`pointerdown`／`keydown`／`wheel`／`touchstart`時，間隔延長為`max(基準, 120秒)`；恢復操作立即補查（仍受最短間隔限制）並回到基準。
- 行為變更：個人版本輪詢由30秒改為60秒，符合計畫「未讀通知約60秒」。

後端429目前不帶`Retry-After`（例外轉換不保留標頭），前端只做指數退避；之後若對輪詢端點加throttle再一併處理。

## 拖曳、草稿與寫入衝突（TT-149）

- 草稿：編輯表單或詳細頁有未儲存內容／送出中（`dirty`／`busy`）時，背景重讀只更新Project，不換列也不換表單基準revision（既有行為，`browser-check`涵蓋）。
- 拖曳：放下即在畫面把卡片移到目標欄並標示送出中；寫入仍帶原`expectedRevision`與同簽章重試共用的`requestId`。
  - 成功：由寫入後重讀交回伺服器資料。
  - 失敗：還原到原欄並提示；重試沿用同一`requestId`。
  - `REVISION_CONFLICT`：先還原再重讀，顯示他人版本，不靜默覆寫。
  - 還原只在列仍是本次套用的物件時進行，避免蓋掉之後的伺服器資料。
- 暫停背景換列：拖曳中（dragstart→drop／dragend／看板卸載）或移動送出中，輪詢與Reverb觸發的背景重讀延後；拖曳開始時中止進行中的背景請求。解除後補一次；若期間已有前景重讀（寫入成功）則不重複。
- 切租戶／範圍：移動世代失效、舊失敗不還原到新畫面；拖曳狀態重設、列清空。

## 尚未處理（另卡）

- TT-148：有權限保護的輕量變更檢查端點。
- 看板Reverb（`ui/boardRealtime.js`）的重連計時另屬即時通道，本卡未改。

## 驗證

- `node frontend/tools/polling-check.js`：週期執行、慢請求不重疊、隱藏停止、最短間隔去重、焦點去重、離線停止／恢復、停用／重新啟用、卸載移除監聽；TT-147另含jitter／上限計算、5xx與429退避後回基準、401／403／404／換Workspace停止與恢復、取消不計失敗及閒置延長。
- 隔離MySQL堆疊：`browser-check`、`board-query-check`、`project-utility-check`、`time-calendar-check`通過。
- TT-147修正兩個測試：`personal-query-check`原本在頁面排好計時器後才裝假時鐘，fastForward觸發不到背景計時器而逾時，改為載入前安裝並快轉70秒；`browser-check`看板背景刷新快轉由31秒改34秒以涵蓋jitter。修正後兩者皆通過。
- browser-check四次中一次在專案成員頁斷言失敗（`1 !== 0`），當時工作區含其他Agent未提交且持續變動的後端Session修改，未能重現，原因未確認。
- TT-149：`refactor-composables-check`加入立即套用／失敗與衝突還原／成功保留／跨範圍不還原；新增`poll-drafts-check`（看板輪詢以`VITE_POLL_BOARD_MS=10000`縮短，驗證送出中不背景重讀、失敗回原欄、衝突保留他人版本、解除後恢復輪詢）。
- 因卡片會先移到目標欄，`moveBoardCard`與三處直接`dragTo`的測試改為再等送出完成；另修兩個既有時序測試：成員移除後等清單列消失（版本與分頁清單分開重讀）、工時衝突後等列表出現新版本再編輯。
- `browser-check`五次中四次全過；一次在任務詳細頁異動紀錄分頁逾時，與拖曳／輪詢無關，未能重現。
