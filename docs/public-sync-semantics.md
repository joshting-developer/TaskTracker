# 同步語意：全量、增量、刪除與來源識別（TT-173）

2026-11-12。OpenAPI 0.8.0。使用規則以[公開契約](../backend/resources/public-api-contract.md)的「同步語意」為準。增量端點本身見[TT-170](public-issue-changes.md)，外部識別見[TT-171](public-external-ids.md)。

## 現況事實（查核程式）

- 產品沒有刪除或封存卡片、Project的API或UI。只有本機示範資料重設（local／testing）會刪除整個工作區。closed是狀態。
- 原本的syncUntil等於讀取當下時間。卡片的updated_at由應用程式在交易內寫入，所以時間戳早於水位、但較晚才提交的寫入，下一輪從水位開始讀時會被永遠漏掉。

## 決策（使用者授權先採Claude建議）

1. **水位緩衝**：本輪項目仍讀到當下為止，即時性不變。回傳的syncUntil往前退`tasktracker.sync.settle_seconds`（預設10秒，環境變數TASKTRACKER_SYNC_SETTLE_SECONDS），而且不會早於本輪的updatedSince。客戶端依id／revision去重（原本文件就有要求）。這是相容的語意收緊，欄位格式不變。
2. **來源識別**：issue-changes項目新增`externalRefs`。由Repository預載，增加一次限定scope的查詢，有界：每卡每source一筆。Web列表DTO不變（另建IssueChangeResource）。
3. **刪除語意**：本版明確聲明沒有tombstone，因為沒有刪除操作。日後新增刪除時，必須先提供刪除事件或tombstone。離開範圍（撤權、篩選）以不帶篩選或定期全量對帳偵測。
4. **回音辨識**：留給TT-176，本卡只文件化目前可用的revision與externalRefs。

## 驗證

- 新增案例在memory與真MySQL各跑一次：
  - 已綁定的卡帶externalRefs、未綁定為空陣列。
  - 水位早於本輪最新項目。
  - 模擬時間戳落在緩衝內的較晚提交，下一輪讀得到。
  - 緩衝內項目重讀。
- 既有IssueChangesCases（cursor穩定、同頁水位一致、包含邊界、EXPLAIN用issues_change_cursor）全部照常通過，issues查詢仍不含comments等欄位。
- 隔離MySQL完整209通過。

## 限制

- 緩衝只涵蓋提交延遲不超過settle秒數的交易。目前寫入都是短交易且Workspace鎖序列化（50項批次也在同一交易），仍建議外部系統定期全量對帳。
- 不是跨請求snapshot，也沒有刪除changefeed（因為目前沒有刪除）。
