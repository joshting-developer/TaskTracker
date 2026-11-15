# v0.4.0 正式發布驗證

2026-11-15, 正式站 https://task-tracker.josh-ting.com

## 發布內容

- 介面繁體中文與英文切換, 語言偏好依瀏覽器內的帳號保存。使用者輸入、自訂狀態與既有資料不翻譯
- 只發布v0.4.0, tag固定ba48d05, 多語系實作123f8a9。發布編排修正ff26ad6, 不移動tag
- 私有Fly映像registry.fly.io/tasktracker-josh:v0.4.0, digest `sha256:f6593b37e10c5343495ffeea14fe4c035306590223da7a8d1121e80f98d6bd9e`
- GitHub Actions run 43693179529第二次執行成功, validate、source、contract、image及deploy全通過。保留單台nrt、512MB, 沒有新增付費資源

## 實測

- 本地隔離資料完成中英文、權限、工時、附件與狀態等回歸；162張畫面通過核准基準比較, 後端492項通過、1項條件略過, 詳見plans/TT-308與artifacts/tt308
- 正式瀏覽器以Codex本人登入, 確認英文登入、既有TT-308詳情、中文切換及重新載入後保存, 1440／768／390寬度沒有頁面橫向溢出
- 登入Cookie為Secure及HttpOnly, 私有API為private／no-store, 帶hash靜態檔為immutable, HTML不長期快取
- 首頁、登入、工作頁、robots、sitemap、OpenAPI與健康檢查200；about／app／login斜線版本回相對301, 不存在路徑404, OG圖200, 登入與工作頁帶noindex
- 沒有頁面JavaScript錯誤, 沒有退回getWorkspace／getTodos整包讀取
- 正式migration共35項全部套用, 發布前保存完整MySQL備份。未seed／reset／還原正式資料, 沒有用正式庫跑人造功能測試

## CI修正與限制

第一次run已成功部署, 但最後Python匿名註冊政策檢查收到403, 因此當次CI仍判失敗。加入具名TaskTrackerRelease/1.0及JSON Accept後實測200；同tag續跑只重用image ID與原驗證artifact相同的映像, 不覆寫版本。12項發布相關測試通過, 包含不同映像與registry拒絕時停止

Discord預覽沿用TT-208已確認結果；2026-11-15 Josh明確取消LINE實際貼文預覽驗收, TT-208已結案, 不宣稱LINE實測通過。本次冷啟動量測仍未重測, TT-155保留該未驗項, 不以部署成功當作全部整合驗收完成。沒有重新建立真實邀請或上傳人造附件

## 回退

上一版v0.3.0 digest為`sha256:f5824ccd684cbe53121e9bec09daccb25bd43c55852e58d9d9e538f1315ef8a3`。回退程式不還原資料庫, 不覆蓋發布後的新資料
