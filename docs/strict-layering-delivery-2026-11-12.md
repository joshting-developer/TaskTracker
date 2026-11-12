# TT-262 嚴格分層與建構子注入交付

2026-11-12。接續[原盤點](controller-constructor-injection-review-2026-11-12.md)，本卡只遷移依賴與資料存取邊界。其餘領域寫入的 Request／DTO 與前端契約由 TT-246 接續，並非本卡已完成。

## 實際變更

- 21 個既有 Controller、84 個 action 的穩定協作者改為建構子注入；Request、FormRequest 與路由 ID 保留 action 輸入。WorkspaceTransaction 是交易／資格邊界，不是 SQLite 專屬元件，保留 User → Workspace 鎖與交易內資格檢查。
- 公開查詢拆成 PublicProjectsController、PublicQueriesController（Issue）及 PublicIssueChangesController；原 URL、權限、DTO 與公開規格保持一致。Project 入口不建立 IssueReadService／IssueChangeService。
- AccountService → AccountRepository；PersonalTokens → PersonalTokenRepository；IntegrationAudits → IntegrationAuditRepository；ProjectSignals → ProjectReadRepository。Bearer／Session 自有資料查詢經應用 Service 與 Repository，不把 SQL 移到 HTTP adapter。
- RequestReplay 的 ReceiptCodec 改為必要建構子依賴。未顯式覆寫的編碼設定在 encode 時讀取，避免重用協作者後凍結先前設定；保存結果的格式、簽章與原結果重播不變。
- RelationalDataMigration 及四個歷史 Upgrade 移至 `App\Database`，migration、匯入、seeder 與測試引用同步。只是工具位置及 namespace 遷移，不重跑現用庫或改寫歷史資料。

## 邊界與例外

現在 AST 掃描涵蓋 31 個 Controller（含基底）與 31 個 Service。保留已載入 Model 作為資料型別及純值運算；不以 import 數量當直接查詢。Factory 仍可組裝可信當次 context／scoped RecordStore，不能固定成跨請求 singleton；Artisan handle 的框架注入、migration／診斷等工具不假裝成一般業務 Service。

`LayeringInventory` 使用既有 PHP Parser AST，辨識別名、action 服務注入、Controller Repository 直通、Model 靜態呼叫、直接 SQL、常見 instance I/O、手動建立穩定協作者與 locator；負面 fixture 驗證檢查器本身。它不是完整 PHP 型別／任意動態程式分析，不能單憑綠燈保證所有 lazy load／反射／Builder 外洩不存在；第二輪人工檢查 Model 引用及查詢鏈，並搭配實際隔離測試。

Container 測試解析所有 Controller，斷言建立過程沒有 SQL、Project 不初始化其他領域、Repository 可替換。既有跨操作／租戶 scope、按需服務初始化及公開 API 契約測試一併保留。

## 資料存取八項審查

| 面向 | 本卡結果 |
| --- | --- |
| 呼叫需要 | 帳號單筆識別與最小資格；PAT／本人稽核 metadata；Project 訊號只取 public_id |
| 上限／排序 | PAT、稽核仍每頁 20 筆、id 倒序；單筆 find；訊號 cursor 依 id 順序 |
| SQL 過濾 | 本人、有效成員與 Workspace 條件仍在 Repository SQL；未先取全 Workspace 再過濾 |
| 範圍 | 不新增全 Workspace 查詢；Project 公開查詢不初始化 Issue 服務 |
| 活動 | 不把留言、歷史、工時併入列表；保留按需分頁 |
| 頻率／成本 | 路由、請求頻率、SQL 投影／次數、response 欄位及背景載入無新增；訊號枚舉仍為 O(Project 數)，本卡不宣稱改成固定成本 |
| 範圍測試 | 既有異租戶／無關 Project、撤權、分頁及資料讀取範圍回歸，另驗建構零 SQL |
| 限制 | 未對遠端拓樸做效能倍率／p95 宣稱；本卡不是所有效能待辦或全部 Request／DTO 完成 |

## 驗證

隔離 Compose `tasktracker-codex-refactor`、localhost:13069、獨立 tasktracker_test 帳號／schema；未 seed／reset 現用 tasktracker 庫，也未操作另一 Agent 的測試服務。

- memory 全量回歸及 PHPDoc／AST／Container：結果見下方完成紀錄。
- MySQL 8.4.13：62 tests、2,012 assertions 通過，包含並行、鎖後撤權、receipt、公開讀寫／外部同步、10,000 筆報表資料。大型報表測試需 512MB PHP 限制（實測峰值155MB）；128MB 原始執行曾耗盡，沒有把該次算通過。
- 瀏覽器使用真實隔離 Laravel，沒有業務 mock；結果見下方完成紀錄。
- PHPDoc 自動檢查與分層掃描是回歸門檻；公開 OpenAPI 與使用範例未新增端點／改契約。

### 完成紀錄

- memory：152 passed、63 skipped、7,718 assertions（skip為分開執行的MySQL／外部環境條件）。
- 全範圍PHPDoc：required 241檔／233類別／811方法，0個有缺漏的檔案；reference歷史測試／migration仍有既存缺漏，沒有宣稱整庫所有歷史檔案零缺漏。
- 真實瀏覽器完整流程通過：雙帳號／邀請、Project ACL、列表／看板／拖曳、詳細與編輯／衝突草稿、小待辦／轉卡、留言、工時月曆與mobile。截圖只留忽略的artifacts，不進Git。
- PHP Pint限定變更檔、git diff --check通過。
