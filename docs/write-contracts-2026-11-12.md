# 卡片／工時寫入的專用Request與有界結果（TT-178，2026-11-12）

承接[TT-227 receipt容量分析](performance-receipt-retention-2026-11-12.md)的根本解法：寫入結果不再回整張卡片的描述、留言與歷史。

## 改變

| RPC | Request | 回傳（也是receipt保存的內容） |
|---|---|---|
| `createIssue`、`updateIssue`、`addComment` | `Http/Requests/Writes/IssueWriteRequest` → `Data/Writes/IssueWriteData` | `{id, projectId, identifier, revision}`（與公開API寫入相同） |
| `logTime`、`updateTime`、`voidTime` | `Http/Requests/Writes/TimeEntryWriteRequest` → `Data/Writes/TimeEntryWriteData` | `{id, projectId, issueId, workDate, minutes, revision}` |

- 有界結果在`WorkspaceService::write`集中產生（`Data/Writes/WriteResults`），Web RPC、待辦轉卡與Seed共用。最新內容由既有GET按需讀取（單卡、留言、歷史、工時）。
- Request只檢查JSON物件與欄位型別，型別錯誤回`INVALID_INPUT`。
- 下列規則不變：
  - **必填、長度、格式、狀態、關聯與每日工時上限**：仍由`Fields`／領域服務在授權之後判斷，原錯誤訊息不變。對不可見的卡片，即使缺欄位也先回404，不洩漏存在與否。
  - **未知欄位**：不拒絕，原始輸入完整保留給`RequestReplay`算簽章。同`requestId`（含額外欄位）重試仍回原結果，改內容仍回409。
  - **其他語意**：`revision`、`requestId`、先授權再重播、rollback與afterCommit訊號都不變。
- 舊receipt的完整結果原樣保留，重播仍回當時結果。前端只用`id`／`revision`／`identifier`／`workDate`，新舊格式都相容。

## 呼叫端

- 前端`IssuePanel`、`IssueDetail`、`TimeEntryDrawer`、`TimeCalendar`、看板移動原本就只用`revision`、`id`或`workDate`，寫入後再以GET重讀，沒有改產品程式。
- `tools/tasktracker.py import`改為印出輸入卡片的狀態。
- 測試：
  - 需要整張卡片的測試改用`Tests\Concerns\StoredWriteResults`，從資料入口讀回完整紀錄。
  - 前端檢查工具建卡後合併輸入欄位，再做後續斷言。
  - 修改的檢查工具：browser-check、issue-time-check、member-time-details-check。

## 量測（隔離MySQL，`ReceiptGrowthTest`：同一卡64輪改標題＋留言，129筆receipt）

| | TT-227（之前） | TT-178後 |
|---|---:|---:|
| receipt JSON_STORAGE_SIZE合計 | 4,287,524 B | **18,318 B**（−99.6%） |
| 單筆最大 | 64,744 B（隨歷史成長） | **142 B**（固定） |
| 32→64輪成長 | 約3.76倍（超線性） | 約2倍（線性） |

TT-255的gzip-v1編碼仍保留，現在主要用於舊的完整receipt；`ReceiptCodecMySqlTest`改為先模擬舊式完整receipt再量測。

## 範圍外

Project、看板、成員、待辦與設定的寫入仍回原本結果，由TT-246承接。
