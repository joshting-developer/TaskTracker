# 正式環境設定（TT-321）

## 一般設定

以`docker/production/runtime.env.example`為可公開清單, Fly的`[env]`使用相同值。建置參數另設`VITE_SITE_ORIGIN=https://task-tracker.josh-ting.com`, 不能只改執行期APP_URL

- Session放database, secure／HttpOnly／SameSite=lax；不設SESSION_DOMAIN, 保持host-only Cookie, 不授權其他子網域
- Redis供cache／限流／鎖, prefix為`tasktracker:production:`, CACHE_PREFIX為`cache:`；不使用cache:clear／FLUSHDB／FLUSHALL
- 正式QUEUE_CONNECTION=sync、MAIL_DELIVERY_MODE=inline、BROADCAST_CONNECTION=null, 不啟Reverb或常駐mail worker；定時維護依TT-162另外驗收
- 公開註冊關閉, 既有帳號由TT-341搬移；不得用正式seed建立測試Owner
- 附件使用私有`r2-attachments` disk, 不寫Fly Machine的暫存磁碟；本地附件移轉依TT-292

## Secrets清單

| 名稱 | 來源／注意事項 |
| --- | --- |
| APP_KEY | 沿用現用本地主庫的實際金鑰, 不產生新值取代；只透過私有檔案或stdin傳輸 |
| DB_USERNAME、DB_PASSWORD | 正式task_tracker的應用帳號, 最小權限驗收另看TT-330 |
| REDIS_USERNAME、REDIS_PASSWORD | 既有TLS端點ACL, 不更動共享服務其他使用者 |
| MYSQL_CA_BASE64、REDIS_CA_BASE64 | 既有CA的base64內容, Fly files分別掛至範例設定的兩個pem路徑；不傳伺服器私鑰 |
| R2_ACCESS_KEY_ID、R2_SECRET_ACCESS_KEY | 私有Bucket存取憑證；R2_BUCKET、R2_ENDPOINT屬部署識別, 可一併留Secrets管理 |
| MAIL_HOST、MAIL_USERNAME、MAIL_PASSWORD、MAIL_FROM_ADDRESS | 沿用已驗證SMTP服務；MAIL_SCHEME及MAIL_PORT需符合實際服務, 範例為587 STARTTLS |

只傳清單中的設定, 不把整份開發.env送去Fly。不得帶入DB_PASSWORD_FILE／REDIS_PASSWORD_FILE、DB_URL／REDIS_URL或TASKTRACKER_ENV_PATH的本機覆寫。一般環境變數與*_PASSWORD_FILE目前皆支援, 同時設定時file優先

MySQL必須`MYSQL_SSL_ENABLED=true`與`MYSQL_SSL_VERIFY_SERVER_CERT=true`；Redis必須tls, Predis的peer及名稱驗證保持開啟。不能沿用測試時不驗證CA的例外。CA來源與輪替見[OCI TLS](oci-service-tls-2026-11-13.md)

Fly Secrets用私有0600檔案經stdin匯入, 不把值放命令列、卡片或log。先以stage匯入, 搭配部署一起生效；以官方當前CLI help確認參數。只有設定名稱／digest可以回報, 不讀出值

## 映像與發布入口

正式映像帶入前端app／landing／public-assets和後端resources, 包含郵件模板及公開API文件。建置context採allowlist, 排除.env、私有目錄、資料庫、node_modules與vendor宿主副本

entrypoint收到命令時直接exec, 因此release command可使用`php artisan migrate --force --no-interaction`並取得正確退出碼；未帶命令才啟動Nginx／FPM。migration前仍須備份, 不在一般HTTP啟動時自動執行

## 驗證範圍

本卡先用隨機金鑰、Codex隔離MySQL與專用Redis驗證正式映像設定、一次性migration、靜態入口與API。隔離測試的TLS與SMTP以明確測試覆寫停用／array mailer, 不發真信；不能當成Fly→OCI的TLS端到端已通過

Fly Secrets尚待TT-323實際套用, 正式登入與資料移轉尚未執行；首次發布的版本映像依TT-320, 實際部署依TT-323, 資料搬移依[TT-341操作手冊](mysql-transfer-runbook.md)
