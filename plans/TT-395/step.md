# 執行紀錄

- 已核對 preview 與空 SMTP 配置, 正式應用仍要求 SMTP TLS；本次以本地捕捉信箱驗正常啟用流程, 不使用 local666 捷徑
- 取得修前註冊未交付狀態, 加入固定版本 Mailpit、本機專用憑證與 PHP 信任；保留 SMTP 強制 TLS／憑證檢查
- SMTP DSN 使用明確的 smtp://mailpit:1025, 避免 Laravel 把空 MAIL_URL 當成空 transport
- 專用 local-dev 已重開並套用未執行的加法 migration, 原合成帳號、待驗證 Session、資料與附件保留；沒有 seed 或重啟舊備份
- 實際重寄、Mailpit 收信、從信件啟用與原分頁恢復已通過；全程未用 local666, 未寄到外部信箱
- 本次沒有修改前端版型；既有核准註冊畫面另外重拍比對, 本地錯誤／成功狀態另留三尺寸診斷截圖
- 新註冊直接收信與既有 pending 帳號重寄都通過, probe 可重跑且不清資料
- 額外實測明文 SMTP 在 DATA 前被拒絕、沒有本地 CA 時憑證驗證失敗、指定 CA 可正常連線；沒有關閉驗證
- 現行核准註冊版型六張重拍比對與隔離冒煙通過, 本次未修改前端版型
