# TT-315 步驟

- [x] Repository：有效成員身分、依收件人遞增讀通知（走 (workspace_id, recipient_id) 外鍵索引）
- [x] Service：重驗專案issue.read、算nextAfter／hasMore
- [x] Controller、Resource、路由
- [x] OpenAPI 0.17.0（listMyNotifications、MyNotification、MyNotificationPage、Notifications tag）
- [x] 測試：SQLite／MySQL共用的PAT流程測試（被指派、留言、狀態改已關閉讀到三則；讀不到別人的；limit=1續讀不重複不漏；撤專案權限讀不到；錯誤參數400；沒Token 401）, 真實回應比對正式Schema
- [x] 文件：HTTP契約、Agent指南、api-first-platform、產品討論紀錄
- [x] 共用Skill：開工步驟加讀通知, CLI `notifications` 存位置接著讀（skills庫 7cb7199）

## 驗證結果

- SQLite全套：204 passed, 1 failed是Codex的R2ConfigurationTest
- MySQL：MySqlPublic*／MySqlIssueStatus*／MySqlApiTest共46支都過
- check_openapi.py通過；TokenSpecificationTest（路由和規格一致）通過
- 共用Skill validate_library通過、單元測試9項通過
- 限制：本機實際在用的TaskTracker服務還沒部署這版, Skill CLI的 `notifications` 要等部署後才能對真服務用
