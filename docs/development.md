# 開發與測試

前端使用 Vue／Vuetify／Vite, 後端是 Laravel JSON API。執行環境由根目錄 docker-compose.yml 定義, Fly 的公開發布範本放 fly.toml.example 與 docker/production, 真正 fly.toml 不版控

## 獨立模式

```sh
docker compose --profile standalone up -d --build
docker compose --profile standalone exec backend php artisan test --do-not-cache-result
docker compose --profile standalone down
```

預設網站埠 5174、API 3002、WebSocket 8080、Mailpit UI 8025, 均只綁本機 loopback。可用 TASKTRACKER_PORT、TASKTRACKER_API_PORT、TASKTRACKER_WS_PORT、TASKTRACKER_MAIL_UI_PORT 調整；同時開多套時另外設定不同的 TASKTRACKER_DATA_NETWORK, 避免資料網路共用

啟動會初始化本機隨機密碼、SMTP 憑證、應用程式金鑰與資料表, 不自動建立示範帳號。正常註冊後從 Mailpit 開啟啟用連結, 信件不向外轉送；down 保留 volume, 刪除資料前先自行備份

## 已有共用資料服務

在被 Git 忽略的連線設定檔填入自己的 DB_HOST、DB_DATABASE、DB_USERNAME、DB_PASSWORD、REDIS_HOST、REDIS_USERNAME、REDIS_PASSWORD、REDIS_PREFIX、MAIL_HOST。DB_PASSWORD_FILE 設為空字串, 使用明確提供的資料庫密碼

另外設定 TASKTRACKER_DATA_NETWORK 為既有 Docker network 名稱、TASKTRACKER_DATA_NETWORK_EXTERNAL=true, TASKTRACKER_MAIL_CA_VOLUME 為只放公開 cert.pem 的目錄。保留 SMTP 的 TLS 與憑證／主機名稱驗證, 不掛 SMTP 私鑰到應用程式

```sh
docker compose --env-file .env.shared --profile shared up -d --build
docker compose --env-file .env.shared --profile shared down
```

shared 模式只啟動應用程式服務, 資料庫、Redis 與 Mailpit 由既有環境提供。不同專案／測試實例使用不同資料庫與帳號, Redis key 和 channel 使用專用 prefix；不要在共用伺服器執行全庫清空

## 隔離測試實例

```sh
python3 tools/test-environment.py up --name tasktracker-tests --port 13029
# 預設 artisan test 強制 memory SQLite, 不重設日常開發庫
python3 tools/test-environment.py down --name tasktracker-tests
```

API 埠範圍為 13000–13999, MySQL 測試可在該實例執行 `docker compose -p tasktracker-tests --profile standalone exec -T backend php -d memory_limit=512M artisan test --filter MySql --do-not-cache-result`；包含另開 PHP 子程序的大套件需將 512M 寫入該測試容器的臨時 PHP ini

這個工具使用同一份 root Compose 建立獨立測試實例, MySQL 測試只允許專用 test／import schema 與測試旗標。瀏覽器檢查透過 E2E_ISOLATED=1、E2E_API_URL 與 E2E_FRONTEND_PORT 指向這個實例, 不使用正式站

公開 API 規格與範例在 /api/public/docs 和 /api/public/openapi.json, 命令列範例及驗收工具位於 tools。UI 視覺回歸另見[視覺回歸](visual-regression.md), 發布與回退見[Fly 部署](fly-deployment.md)

## 協作者與個人工作流

repository 保留產品程式、公開規格、測試與發布工具。個人的 Agent 設定、Skill 安裝入口、工作計畫與 step 是本機配置, 不是啟動或協同開發的必要檔案；需要 AI 輔助開發時可自行安裝 README 列出的外部 Skills

本機寄信驗收可指定 E2E_FRONTEND_URL 與 E2E_MAILBOX_URL 執行 `node frontend/tools/local-registration-probe.js`, 只接受 loopback 網址, 會建立合成帳號並從假信箱開啟真實啟用連結
