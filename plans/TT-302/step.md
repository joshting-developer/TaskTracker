# TT-302 步驟

- [x] 後端：Issue.creator關聯、詳細查詢加建卡者與最後異動（JSON取值、limit 1）、Resource與OpenAPI 0.18.0
- [x] 前端：屬性欄「建立」「最後異動」（時間放第二行）；異動紀錄新的在上
- [x] TT-317的活動測試改成只允許最後操作者名字和時間出現, 子查詢只能取actor／at且限一筆
- [x] 截圖fixture固定卡片建立時間和異動時間, 不改排序
- [x] 真瀏覽器檢查 frontend/tools/issue-history-order-check.js：詳細頁和快速編輯排序、載入更多方向、1440／768／390沒有橫向捲動
- [ ] 截圖基準：07-detail會因本卡改變；目前工作區有Codex TT-293還沒提交的「圖片」按鈕, 等它提交後再拍候選更新基準, 避免把別人沒完成的畫面存成基準

## 驗證結果

- IssueActivityCounts、PublicApiContract、PublicQuer* SQLite通過；MySqlIssueActivityCounts／MySqlPublicQueries／MySqlApiTest／MySqlPublicNotification 26支通過
- check_openapi通過
