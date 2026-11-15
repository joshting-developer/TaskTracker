# 執行紀錄

- 已領取 TT-368, 與現有 dueDate 分開處理, 不搬移舊值

- Web／PAT 共用日期驗證、DTO allowlist、Repository 映射與詳細 Resource 完成；DATE migration 已在 Codex 與全新 local-dev 專用庫執行
- 詳細頁與編輯器新增兩個有標籤的日期欄位, 不增加列表投影與查詢
- SQLite／MySQL 與公開 API 回歸 28 項／1064 assertions 通過, 包含新日期、空值、順序、閏年、清除、重播、read scope 與其他租戶
- 原 HTTP 程式範例保留；正式 OpenAPI 0.23.0 與離線 Schema 檢查通過
- 真實瀏覽器修改／清除／重新讀取及三尺寸完成, build 與冒煙通過
- 第一次畫面比對發現標題 autofocus 的邊框差異, 補等待字型／雙幀與有限動畫後重拍, 未放寬像素門檻或加遮罩

- 六張候選已依 Josh 持續授權保存為核准基準, 獨立重拍六張比較通過（threshold 0.02／maxDiffPixels 0, 無遮罩）
- 只修改既有欄位區, 維持品牌、布局與可見 label, 使用原生 date；UI 搜尋規則對應 Input Labels／Date Formatting／Input Types
- 前端 domain 日期檢查、Pint、公開 Schema 與 Skill 來源完整性通過；未部署正式站

- 目視檢查發現手機截圖含側欄收合過場殘影, 已等待 sidebar 真正離開畫面後重拍修前、修後與比較, 六張最終比較通過
- Skill 證據檢查通過, 910 組雙語 key／參數與錯誤內容邊界檢查通過
