# TT-302 步驟

- [x] 後端：Issue.creator關聯、詳細查詢加建卡者與最後異動（JSON取值、limit 1）、Resource與OpenAPI 0.18.0
- [x] 前端：屬性欄「建立」「最後異動」（時間放第二行）；異動紀錄新的在上
- [x] TT-317的活動測試改成只允許最後操作者名字和時間出現, 子查詢只能取actor／at且限一筆
- [x] 截圖fixture固定卡片建立時間和異動時間, 不改排序
- [x] 真瀏覽器檢查 frontend/tools/issue-history-order-check.js：詳細頁和快速編輯排序、載入更多方向、1440／768／390沒有橫向捲動
- [x] 截圖基準：Codex在cefa62a（TT-293）依持續授權一起收了本卡的07-detail, 備註寫明「TT-302建立／異動欄位, 已檢視三尺寸」；之後各卡正式比較都通過。2026-11-14重新檢視核准基準桌面與手機07-detail, 建立／最後異動顯示正確（手機收在「任務資訊」展開區）

## 驗證結果

- IssueActivityCounts、PublicApiContract、PublicQuer* SQLite通過；MySqlIssueActivityCounts／MySqlPublicQueries／MySqlApiTest／MySqlPublicNotification 26支通過
- check_openapi通過

- 2026-11-14 TT-336完整回歸（後端HEAD映像）issue-history-order-check通過
- 待Josh確認：右側「建立」「最後異動」的位置和樣子
