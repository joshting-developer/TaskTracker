# 正式HTTP runtime映像（TT-151，2026-11-12）

依[低成本部署與同步重構計畫](planning/TaskTracker_低成本部署與同步重構計畫-2026-11-11.md)§4.1–4.2，以及最新「前後端共同封裝至單一App」規劃。本卡只建立並在本機驗證映像，**沒有建立`fly.toml`、雲端build或部署指令**，也沒有部署。

## 組成

`docker/production/Dockerfile`（build context為repo根目錄）：

1. `node:24-bookworm-slim`：`npm ci`後`vite build`，只取`dist`。
2. `php:8.5.12-fpm-bookworm`：`composer install --no-dev`並`dump-autoload --classmap-authoritative`，裝Nginx；映像內不含Composer、tests、tools、`.env`、資料庫或秘密。
3. 執行期為Nginx＋PHP-FPM，不是`artisan serve`：
   - Nginx監聽`PORT`（預設8080），`/api/`以FastCGI交給Laravel，其餘路徑提供Vite靜態檔，SPA深連結回`index.html`。
   - PHP-FPM為`pm=dynamic`，`PHP_FPM_MAX_CHILDREN`預設6，`max_requests=500`；OPcache開啟且不檢查時間戳。
   - 全部以`www-data`執行。

快取標頭：`/assets/*`（檔名含雜湊）為`public, max-age=31536000, immutable`；`index.html`與SPA路徑為`no-cache`；`/api/*`沿用Laravel的`no-store, private`，私有資料不進CDN。

## 啟動、健康檢查與關機

- 啟動（`entrypoint.sh`）：
  - 缺`APP_KEY`即失敗。
  - 依執行期環境`config:cache`與`route:cache`後，啟動FPM與Nginx。
  - **不執行migration或seed**。
- 健康檢查：Docker `HEALTHCHECK`每30秒打`/api/health`（同一容器內，不是外部Ping，不會讓平台的auto-stop失效）。
- 關機：`SIGTERM`時Nginx `quit`、FPM `QUIT`，`process_control_timeout=30s`讓進行中的請求完成。任一程序結束即整個容器結束，交給平台重啟。
- 發布時另外執行migration：`docker run --rm <env> tasktracker:prod php artisan migrate --force`。

## 必要環境變數（以Secret或平台設定提供，不進映像）

`APP_KEY`、`APP_URL`、`ALLOWED_ORIGINS`、`DB_CONNECTION=mysql`、`DB_HOST`、`DB_DATABASE`、`DB_USERNAME`、`DB_PASSWORD`或`DB_PASSWORD_FILE`。

無狀態設定：`SESSION_DRIVER=database`、`CACHE_STORE=database`（或Redis）。`QUEUE_CONNECTION`與`BROADCAST_CONNECTION`依TT-142～145的決策；本次驗證用`sync`／`null`，前端退回輪詢。映像預設`APP_ENV=production`、`APP_DEBUG=false`、`LOG_CHANNEL=stderr`。

## 本機驗證（隔離MySQL `tasktracker-claude-test`，主機埠18081）

| 項目 | 結果 |
|---|---|
| 建置 | 成功，PHP 8.5.12、OPcache啟用；映像619 MB（含PHP擴充的建置依賴，尚未瘦身） |
| 發布步驟migration | 以同一映像`migrate:fresh --force`成功（隔離庫） |
| 啟動到health OK | 1.09秒 |
| 快取標頭 | assets immutable（單一Cache-Control）、index no-cache、API no-store, private |
| 頁面 | `production-smoke-check.js`：直接開容器（無Vite），註冊後看板與完整頁正常；0次整包讀取、無pageerror |
| 並行 | 6個列表請求並行119 ms、依序153 ms，全部成功（FPM多worker） |
| 優雅關機 | 30個請求與`docker stop`同時送出，全部200；1.72秒結束、exit 0、FPM正常結束 |

重跑：

```sh
docker build -f docker/production/Dockerfile -t tasktracker:prod-local .
# 以隔離測試資料庫的env啟動並對外開18081後：
PROD_BASE_URL=http://127.0.0.1:18081 node frontend/tools/production-smoke-check.js
```

## 限制與後續

- 映像619 MB，主要來自留著的`-dev`套件；可改為多階段只複製`.so`以縮小，尚未處理。
- 不含Reverb、Queue worker或Scheduler，相關決策屬TT-142～145、162。
- 公開入口路由、Fly App設定與冷啟動實測屬TT-154／155；多worker負載與弱網容量屬TT-184。
