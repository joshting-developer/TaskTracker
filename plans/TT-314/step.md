# TT-314 步驟

- [x] migration：issues.created_by_id（含外鍵）、issue_notifications.kind, 舊卡保守補建卡者
- [x] 退回再重建關聯表時, 從records備份補回這兩個欄位；SQLite測試不重建issues表, 避免連帶刪掉留言等子表
- [x] IssueService：建卡記建卡者, 狀態改變時發通知, 各種通知帶kind
- [x] 通知列表與單筆回傳kind
- [x] 通知預覽的重要標示
- [x] 測試：SQLite全套、MySQL全套、MySQL migration退回重上、真瀏覽器檢查 `frontend/tools/status-notification-check.js`
- [x] 文件：產品討論紀錄、query-api-v1
- [x] 收尾（Josh 11/13）：側邊欄通知預覽只在local／testing顯示, 由 `/api/auth/current` 的 `features.notificationPreview` 決定, 直接開網址也回任務列表

## 驗證結果

- SQLite全套：201 passed, 2 failed都是Codex進行中的工作（ProjectAclTest讀工時、R2ConfigurationTest）
- MySQL全套：只剩MySqlIssueChangesTest的索引選擇, 拿掉本卡外鍵仍失敗, 不是本卡造成；MySqlTimeReportTest在整套一起跑時記憶體不足, 單獨跑會過
- 隔離MySQL實際rollback兩步再migrate：欄位與外鍵正確移除、重建
- 視覺比對：通知頁沒有差異；卡片詳細頁07-detail的差異在活動數字, 是TT-317的改動, 本卡不核准新基準

## 收尾驗證（11/13）

- 新增後端測試：testing環境旗標為true、production為false；SQLite與隔離MySQL都過
- SQLite全套：219 passed, 1 failed是R2ConfigurationTest（Codex工作, 本卡之前就失敗）
- 真瀏覽器 `status-notification-check.js` 補正式環境情境：旗標false時側邊欄沒有通知預覽, 直接開網址也不顯示
- Pint、前端build通過；local／testing畫面不變, 沒有新的視覺差異
