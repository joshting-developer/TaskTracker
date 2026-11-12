# HTTP操作紀錄

TT-260／2026-11-13。回答「誰在何時透過哪個操作、結果如何」的跨資源觀測紀錄；**不取代**各實體隨交易寫入的history／settingsHistory（audit_events），也不取代PAT來源稽核integration_audits（TT-166／176）。

## 路由命名

- 所有本專案API路由都有穩定名稱，作為操作識別：`rpc.{operation}`、`auth.{operation}`、`realtime.*`、`system.health／csrf`、`v1.<資源路徑>`（結尾為參數加`.show`、POST加`.store`）、`public.v1.*`、`public.docs.*`；既有`public.issues.*`保留。
- `HttpOperationLogTest::test_every_route_has_a_unique_name`阻擋未命名、重名或漏掛紀錄中介層的API路由。框架預設`/up`沒有使用者，已自bootstrap移除（開發／測試容器使用映像內bootstrap，重建映像後生效；非API路由不在檢查範圍）。

## 記錄範圍與欄位

- 記錄：所有非GET／HEAD／OPTIONS的具名路由，包含失敗（400／401／403／409／5xx）。不記：GET查詢與以POST呼叫的讀取型操作（`HttpOperationLogs::READ_ROUTES`：getWorkspace、getTodos、getTimeReport、getProjectMembers、smtpStatus、getProfile、auth.current／workspaces／invitation、realtime.*），避免輪詢淹沒紀錄。
- 欄位：workspace公開ID、userId、tokenId（PAT寫入）、route name、method、HTTP狀態、projectId（路由參數或輸入）、requestId、處理耗時ms（不含送出回應）、時間（UTC DATETIME(6)）。**不存**密碼、Token原文、Cookie、Session ID或request／response body；識別碼只接受安全字元格式，其他一律丟棄。
- 身分來源：Session請求取伺服器Session的登入者與workspace_id；PAT請求只在BearerTokenSession重驗Token／資格成功後放入請求範圍Context。未通過驗證的請求（如401）只記路由與狀態，不歸屬任何Workspace。登出成功後Session已清除，因此登出列沒有userId。

## 執行時機與失敗隔離

`RecordHttpOperation`為terminable middleware（routes/web.php統一掛到全部路由），在回應後同步insert一筆；任何例外只寫`HTTP操作紀錄寫入失敗`警告log（不含內容），不影響已送出的回應或業務交易。暫不改Queue：單筆insert成本低，Queue／Scheduler可靠性仍待部署看板TT-162決定；若正式runtime無法在送出回應後才執行terminate，再評估改dispatch。

## 讀取與保存

- `GET /api/v1/operation-logs?limit=50&before=<id>`：Session、需有效permission `workspace.operationLogs.read`（擁有者／管理者），只回目前Workspace，id倒序keyset分頁（limit 1–100，回`nextBefore`），不做COUNT／深OFFSET；索引`(workspace_public_id, id)`。目前沒有前端畫面。
- 保存90天：`HttpOperationLog`為Prunable，routes/console.php排程每日`model:prune`。需有scheduler執行者（TT-162）才會自動清除；未啟用前可手動`php artisan model:prune --model="App\Models\HttpOperationLog"`。

## 驗證

SQLite與獨立MySQL共用`HttpOperationLogCases`：路由命名、Session寫入／409失敗各一筆且讀取不記、欄位不含密碼與內容、資料表不存在時API仍成功、PAT寫入帶tokenId、無效Token的401不歸屬Workspace、跨Workspace不可見、非管理者403、游標分頁與參數上限。
