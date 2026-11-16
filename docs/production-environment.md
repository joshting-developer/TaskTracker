# 正式環境設定

`docker/production/runtime.env.example` 是通用範例, `fly.toml.example` 是 Fly 部署範本。填妥的正式設定只留本機私有檔與部署平台, 不提交資料服務位址、資料庫名稱、帳號或平台專案 ID

- HTTPS origin、Session secure／HttpOnly／SameSite、允許的 origin 依自己的部署設定, 不放寬到任意來源
- MySQL 使用 runtime DML 帳號, migration 另用限該 schema 的必要 DDL 帳號；帳密不放映像或命令列
- MySQL 與 Redis 皆驗證 CA 與主機名稱, CA 可掛檔, 不掛伺服器私鑰；Redis 使用環境專用 prefix, 不全庫 flush
- 現有同步部署模式使用 QUEUE_CONNECTION=sync、MAIL_DELIVERY_MODE=inline、BROADCAST_CONNECTION=null；改成背景服務時要另配置與驗證 worker／WebSocket
- 附件採私有持久儲存, 不依賴 Machine 暫存磁碟；R2 bucket、endpoint 與憑證由私有部署設定提供
- Fly Secrets 保存 APP_KEY、DB／Redis 帳密、SMTP、R2 存取金鑰與必要 CA；GitHub production Environment 另外保存部署設定及 migration 資格, 見 [Fly 部署](fly-deployment.md)

只移轉必要設定, 不把開發 .env 整份上傳。啟動服務、migration、資料移轉與正式發布分開驗證；資料操作方式見 [搬移手冊](mysql-transfer-runbook.md)與[受控遷移](controlled-migrations.md)
