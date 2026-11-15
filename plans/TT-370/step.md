# 執行紀錄

- 確認目前無 5174／3002 服務, 原 tasktracker MySQL 不重啟、不寫入
- 採獨立 docker-compose.local-dev.yml, 避免原 volume 或根目錄 .env 誤接正式服務

- 新環境已啟動, 合成帳號與密碼只寫入 .local/local-dev-credentials.json（0600）
- 解析 Compose 實測：MySQL／Redis 只連容器服務, 無正式 R2／SMTP 與舊備份掛載, 所有 volume 前綴獨立
- 真實瀏覽器登入、專案列表、卡片編輯後還原、看板、紙條與三尺寸通過
- 真 HTTP 附件上傳／下載／綁定／刪除、MIME、安全標頭、10MiB 邊界與跨租戶拒絕通過
- down 再 up 後沿用原憑證與合成資料, 重跑瀏覽器通過；Codex 5199／13199 冒煙另行通過
- 前端來源分別掛載, node_modules 使用新映像, 避免重建套件後仍被舊 volume 遮住
- 推送 TT-369 遇遠端提交而被拒絕, 已依規範停下 Git 同步並請求授權；未清除本地改動
