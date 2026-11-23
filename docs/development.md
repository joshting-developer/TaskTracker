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
# artisan test 使用隔離 MySQL 測試庫, 未配置專用連線即拒絕執行
python3 tools/test-environment.py down --name tasktracker-tests
```

API 埠範圍為 13000–13999, MySQL 測試可在該實例執行 `docker compose -p tasktracker-tests --profile standalone exec -T backend php -d memory_limit=512M artisan test --do-not-cache-result`；包含另開 PHP 子程序的大套件需先執行 `docker compose -p tasktracker-tests --profile standalone exec -T -u root backend sh -c 'echo memory_limit=512M > /usr/local/etc/php/conf.d/test-memory.ini'`

這個工具使用同一份 root Compose 建立獨立測試實例, MySQL 測試只允許專用 test／import schema 與測試旗標。瀏覽器檢查透過 E2E_ISOLATED=1、E2E_API_URL 與 E2E_FRONTEND_PORT 指向這個實例, 不使用正式站

公開 API 規格與範例在 /api/public/docs 和 /api/public/openapi.json, 命令列範例及驗收工具位於 tools。UI 視覺回歸另見[視覺回歸](visual-regression.md), 發布與回退見[Fly 部署](fly-deployment.md)

## 協作者與個人工作流

repository 保留產品程式、公開規格、測試與發布工具。個人的 Agent 設定、Skill 安裝入口、工作計畫與 step 是本機配置, 不是啟動或協同開發的必要檔案；需要 AI 輔助開發時可自行安裝 README 列出的外部 Skills

本機寄信驗收可指定 E2E_FRONTEND_URL 與 E2E_MAILBOX_URL 執行 `node frontend/tools/local-registration-probe.js`, 只接受 loopback 網址, 會建立合成帳號並從假信箱開啟真實啟用連結

## 分開建置介紹站與工作區

預設 `npm run build` 仍產生 `frontend/dist`, 同時包含介紹頁與 App, 沿用本機及既有合併部署。拆分模式需要明確指定兩站的公開 origin, 不要在設定值中加入路徑、帳密或 query

```sh
cd frontend
VITE_SITE_ORIGIN=https://www.example.com VITE_APP_ORIGIN=https://app.example.com npm run build:public
VITE_SITE_ORIGIN=https://www.example.com npm run build:app
```

| 目標 | 產物 | 使用方式 |
| --- | --- | --- |
| `public` | `dist-public` | 純靜態介紹頁與功能頁, 可放 Cloudflare Pages。沒有 App／管理後台入口或登入查詢, 不依賴 Laravel 運行 |
| `app` | `dist-app` | Vue 工作區與管理後台, 與 Laravel 同源提供 `/api`, 根路徑與 `/login`／`/app/` 保留登入及 hash 路由 |
| `combined` | `dist` | 既有合併模式, 維持目前預設與驗收工具 |

介紹站的登入、註冊及開發者文件連結會在初始 HTML 指向 `VITE_APP_ORIGIN`, App 的產品介紹連結則回 `VITE_SITE_ORIGIN`。公開頁不使用登入提示自動轉址, 但仍會將舊 `/#/…` 深層連結導向 App 的 `/app/#/…`。兩個不同 origin 的語言偏好各自保存, 不宣稱跨網域共用 localStorage

介紹站產物含 `404.html`, 避免未知路徑被當成工作區 SPA。`_redirects` 為舊的登入／工作區入口提供暫時轉址, 不代理 API 或搬動資料；永久舊公開網址遷移另由發布計畫處理。Cloudflare Pages 的輸出目錄選 `frontend/dist-public`（repository 根目錄作建置根時）, 或 `dist-public`（`frontend` 作建置根時）。規則格式依 [Cloudflare Pages redirects](https://developers.cloudflare.com/pages/configuration/redirects/)

介紹頁有中文 `/` 與英文 `/en/` 兩個靜態網址, 由建置時依 `frontend/landing/en.json` 產生, 各自帶 canonical、hreflang、OG 與結構化資料, sitemap 同時列出兩個網址。只有明確選過英文才會從 `/` 轉到 `/en/`, 不依瀏覽器語言自動轉址

App 建置的 Fly 映像會把舊公開網址（`/`、`/about`、`/features/*`、`/en/`、`/sitemap.xml`）301 到介紹站；`APP_URL` 網域的根路徑改 302 到 `/app/`, `/login`、`/app/`、`/admin/`、`/api/` 照常服務。規則由 `docker/production/public-routes.sh` 產生, 用 `sh tools/release-image-smoke.sh <image> <site origin>` 驗轉址表

Fly runtime 可選擇 App 建置, 不需要新增 Node 後端：

```sh
docker build -f docker/production/Dockerfile \
  --build-arg VITE_BUILD_TARGET=app \
  --build-arg VITE_SITE_ORIGIN=https://www.example.com \
  -t tasktracker:app .
```

Dockerfile 會把選定產物放入既有 `/srv/frontend`, 沿用 Nginx／PHP-FPM。省略 `VITE_BUILD_TARGET` 時維持合併建置。介紹站只上傳靜態目錄, 不把這個 PHP runtime 部署到 Pages

### 新網域切換前

先在對應平台確認 hostname／TLS, 保留舊網域與前一版映像, 再核對以下設定。這是切換清單, 不是建置或推送後自動執行的流程

介紹站與工作區網址集中在 `deploy/origins.json` 的 `site`／`app`, 發布流程、GPT Bridge 與部署前檢查都讀這份。兩者相同時發布合併建置, 不同時 Fly 只放工作區。私有 `fly.toml`（含 GitHub 的 `FLY_CONFIG_TOML`）的 `APP_URL` 須等於 `app`、`ALLOWED_ORIGINS` 須包含 `app`、`VITE_SITE_ORIGIN` 須等於 `site`, 不一致時 `tools/fly_config.py` 拒絕部署。換網域時先改這份檔案, 再同步私有設定與平台 hostname


- Fly 的 `APP_URL` 及啟用信／邀請／重設密碼等連結改用新的 App origin, 不指向介紹站
- 保持 App 與 `/api` 同源, 核對 `SESSION_DOMAIN`、Secure Cookie、Session／CSRF、WebSocket 與代理設定。介紹站不需要開放跨來源帶憑證存取 API
- 不為換網域重設資料庫、PAT、密碼或應用程式金鑰。不同 hostname 的既有登入 Cookie 不會自然搬到新站, 上線時需驗重新登入
- 確認介紹站在 App 休眠時仍可閱讀, App 登入／登出及舊 hash 連結正確, 所有新舊網址的 HTTPS 憑證有效
- 標準版 tag 發布流程仍使用合併目標。獨立 Pages 自動發布、兩站設定集中化及正式舊網址遷移, 由各自發布工作接續, 不因本地拆分通過而直接切正式站

在 `frontend` 執行 `tools/split-site-check.js` 可驗本地拆分流程, 須先依上列命令以 `http://127.0.0.1:5200`／`http://127.0.0.1:5199` 建置兩站, 並將 `E2E_ISOLATED=1`、`E2E_API_URL=http://127.0.0.1:13199` 指向專用隔離 Laravel。此檢查會建立合成帳號, 不得指向正式服務

PHP 映像沿用上游同版本的編譯選項, 建置時停用上游靜態內建的 SQLite drivers, 並檢查停用結果。開發與正式映像共用 `backend/docker/mysql-only-php.sh`, 不另下載 PHP 版本
