# TaskTracker

專案管理平台 Side Project, 以 Workspace → Project → Issue 組織多人協作

產品原始碼採前後端分離, 不使用 Inertia：

```text
frontend/            Vue 3＋Vuetify 4＋Vite, 獨立 SPA
backend/             Laravel 13 JSON API, PHP 8.5 容器
docker-compose.yml  本機前端／API／Queue／WebSocket／Redis
docs/               產品決策與設計紀錄
```

前端只透過 HTTP API 存取後端, 不直接存取資料庫或依賴 Laravel 頁面渲染。Node 僅用於前端工具鏈, 舊 Node API 已由 Laravel 取代。先前 GAS 架構保留在歷史文件, 目前不產 `.gs`

## Docker 開始試用

```sh
docker compose up -d --build
```

開啟 [TaskTracker](http://127.0.0.1:5174), 建立帳號及工作空間, 再建立／選擇專案。預設空資料, 可自行載入示範專案

專案首頁卡片顯示名稱、代碼、負責人與介紹, 提供進入專案及有權限者的編輯入口；累計完成率與任務數已移除。新專案負責人預設建立者, 舊資料未設定時會明確提示。負責人是責任資訊, 不會自動授予存取權；專案編輯依 project.update, 代碼建立後固定。分析頁的期間／交付範圍與指標仍在規劃

進入專案後, 側邊欄只顯示目前專案資訊與任務／Epic／工時等功能。點左上角 TaskTracker 返回首頁, 再選其他專案或管理目前工作空間成員；不在專案側欄列出其他專案。目前尚未提供多工作空間切換

專案內「任務列表」提供表格、篩選與分頁；「任務看板」是獨立功能, 用狀態欄與 Epic 泳道呈現, 支援收合、卡片密度、欄內新增及跨欄／泳道拖曳。兩邊共用同一批 Issue。側欄看板入口先開 `#/projects/<id>/boards` 看板列表；Default（`/board`）包含目前專案全部卡片, 自訂看板（`/board/<boardId>`）可收錄 Epic 或單卡, Epic 所屬卡片動態跟隨並去重。列表仍為 `#/projects/<id>`, 詳細頁保留來源看板。卡片密度／泳道偏好按看板保存在目前瀏覽器；收錄設定保存於伺服器, 不需重設資料

目前類型固定為 Epic、Task、Bug, 暫不提供自訂類型。既有 Feature 透過 migration 轉為 Task, 保留內容／關聯／工時並增加版本與歷史；舊版匯入也會轉換。升級前請備份 SQLite, 不需重設 Seed

可在看板列表新增／編輯收錄, 或在自訂看板內新增卡片（會自動收錄）。移除看板保留卡片、留言與工時。管理入口依 board.manage 權限, 原型仍沿用工作空間內共享專案, 完整 Project ACL 尚未完成。詳見[看板設計](docs/board-design.md)

任務可快速查看或開啟完整頁：點任務標題維持右側編輯面板, 面板的「開啟完整頁」或列表右側箭頭進入完整閱讀頁。上方狀態 tab 可直接更新此任務, 自動包含專案自訂狀態；中間顯示描述、子任務、關聯及留言／異動／工時, 右側顯示其他任務屬性；「編輯任務」沿用既有表單。完整頁在 Issue 網址加 `?detail=1`, 可分享與重新整理, 返回保留原列表篩選或來源看板；新開／重整後返回網址所屬列表或看板。未送留言與編輯草稿受離開保護, 衝突不清空內容

任務詳細內容提供「新增子任務」及「新增關聯」。子任務各有負責人、狀態與工時, 父項顯示完成數但不自動連動狀態；關聯為雙向追蹤。子任務跟隨父任務的 Epic, 工時按實際 Issue 各計一次。已移除前置任務與其結案阻擋

工時月曆點擊日期後從右側開啟填報面板, 自動帶入日期；修改自己的紀錄也使用同一面板。平常月曆滿寬, 手機面板滿寬。關閉／Escape／遮罩會保護未儲存內容, 儲存成功後更新明細

既有資料啟動時透過 Laravel migration 將前置連結轉成一般關聯並保留歷史；建議升級前備份 SQLite。細節見[日常流程](docs/workflow-design.md)

右側採平整全寬的資料工作區, 以分隔線區分工具列與內容, 提高文字大小與對比。表格第一欄明示「類型」及 Task／Bug 文字, 狀態另欄顯示。列表只保留目前篩選結果筆數, 移除總計／完成率與重複功能標題；手機在頂端顯示目前功能。狀態／優先級顏色保留於色點與圖示, 文字使用深色, 避免自訂淺色狀態難以辨識

任務頁常駐搜尋、排序與篩選入口, 其他條件按需展開；已套用條件即使收合仍可見、可逐項移除或清除。頁首不再重複返回連結與介紹, 可從左上角回首頁查看專案資訊

視圖／常用篩選保存功能已移除, 相關 API 停用；既有資料庫紀錄保留但不再回傳或使用。舊 ?view= 連結提示移除並回到路徑指定的專案／任務。未來首頁個人卡片篩選小清單僅記錄構想, 尚未實作, 詳見[產品討論紀錄](docs/產品討論紀錄.md)

| 服務 | 用途 | 本機接口 |
| --- | --- | --- |
| frontend | Vue 開發服務, `/api` 代理至 backend | `http://127.0.0.1:5174` |
| backend | Laravel JSON API, 自動執行 migration | `http://127.0.0.1:3002/api/health` |
| queue | Laravel Redis queue worker, 3 次嘗試、60 秒 timeout | 不對主機開放 |
| websocket | Laravel Reverb WebSocket | `ws://127.0.0.1:8080` |
| redis | Queue／Cache, AOF 持久化 | 只開放容器內網 `redis:6379` |

API 與 WebSocket 都只綁主機 loopback。這是本機開發設定, 正式交付仍需 HTTPS／WSS、反向代理、註冊政策及部署設計

```sh
docker compose ps
docker compose logs -f backend queue websocket
docker compose exec backend php artisan tasktracker:check-infrastructure
docker compose down
```

基礎設施檢查會實際送入／消費一筆 Redis 測試工作並完成 WebSocket 握手, 不寄信或發布任務事件。Reverb 接口已準備, Issue 即時推播與 Project 私有頻道授權尚未接上, 畫面仍每 30 秒更新

若本機埠被占用, 三個埠可分別調整；後續 Compose 指令須沿用相同設定：

```sh
TASKTRACKER_PORT=5176 TASKTRACKER_API_PORT=3004 TASKTRACKER_WS_PORT=8082 docker compose up -d --build
```

前端 `frontend/src/` 與後端 app／routes／config 掛載至容器。前端 index／Vite 設定、後端 bootstrap／Composer 與依賴變更後需重建；修改後端類別後重啟長駐的 queue／websocket 以載入新程式

## 本機示範 Seed

```sh
# 第一次建立；重跑保留目前資料
docker compose exec backend php artisan tasktracker:seed-demo
# 只還原這組 seed 的 f-ws, 會取代該空間內的修改
docker compose exec backend php artisan tasktracker:seed-demo --reset
```

工作空間 `f-ws`, 專案 `first`／`second`, 四組登入 Email：

| 帳號 | Email | 權限差異 |
| --- | --- | --- |
| owner | owner@example.test | 管理空間／專案／成員及 SMTP 驗證 |
| manager | manager@example.test | 管理空間／專案／成員, 無 SMTP 驗證 |
| maintainer | maintainer@example.test | 維護專案資訊／狀態／看板, 不可建專案或邀請 |
| developer | developer@example.test | 任務／留言／本人工時, 無管理操作 |

四帳號使用同一個你指定的本機密碼, 存在忽略的 volume 環境檔, 不進 Git。新環境先在 backend 的被忽略 `.env` 或容器環境設定 `TASKTRACKER_SEED_PASSWORD`（至少 10 字）。權限由後端 permission ID 檢查, 名稱不作放行依據；四者目前仍看得到空間內所有專案, **Project ACL 尚未實作**。owner／manager 可看團隊通知, 其他兩者只看本人；所有人都只能修改自己的工時

新建／重設時每個專案有 1 個 Epic、6 張一般卡片, 包含父子任務、雙向關聯、不同狀態／優先級、留言及按不同帳號填報的工時。Epic 指派 manager、父 Task 指派 maintainer、執行卡指派 developer。舊 Seed 重跑會非破壞地加入四帳號, **保留 Jack 與既有卡片、指派和工時**；所以既有資料的「我的任務」可能仍是空的, 可自行指派試用。v2 再重跑保留修改及帳密；明確 --reset 才重建此 seed 空間與四帳號密碼, 不修改其他空間。Email／ID 被外部帳號占用時拒絕覆蓋

Seed 只允許 local／testing。Laravel 的 `php artisan db:seed` 也呼叫相同非重設流程。完整權限矩陣及暫時限制見[產品討論紀錄](docs/產品討論紀錄.md#四帳號權限試用-seed2026-10-22)

## 資料與設定

- `laravel-data`：SQLite 與首次啟動產生的 APP_KEY／Reverb 金鑰, 不寫入 Git 或 image
- `laravel-storage`：Laravel 執行時資料與日誌
- `redis-data`：Redis AOF, Queue／Cache 的持久化資料
- `docker compose down` 保留 volume, `down -v` 會刪除上述資料及秘密
- 舊版 `data/` 與 `tasktracker-data` volume 保持原狀, 不會自動匯入或覆蓋

工作空間之間隔離, 空間內專案／工時暫沿用原型共享行為。權限入口已集中, 但文件約定的 Project 成員 ACL 與完整角色矩陣仍待實作, 不把原型視為正式 SaaS 授權完成

SMTP 預設 preview, 只有連線／TLS／登入驗證, 不寄真實郵件。可參考根目錄 `.env.example` 填寫被忽略的 `.env.local`, 再執行 `docker compose --env-file .env.local up -d --build`；SMTP 設定只供後端使用

### 匯入舊 Node SQLite

先備份來源, Laravel 目標必須沒有帳號及業務資料。根 `data/` 在容器內唯讀掛載為 `/legacy`, 執行：

```sh
docker compose exec backend php artisan tasktracker:import-legacy /legacy/tasktracker.sqlite
```

匯入保留帳號／Workspace／任務／工時 ID 及歷史, 不匯入舊 session 或 requestId 快取。來源不修改, 有既有目標資料時拒絕覆蓋。原 Node scrypt 帳密可繼續登入, 首次成功登入後升級 Laravel 雜湊, 使用者須重新登入

若舊資料位於 Docker `tasktracker-data` volume, 請先停寫並備份／複製到根 `data/` 後匯入, 不直接讓兩個版本共用可寫 SQLite

## 不使用 Docker 的開發方式

需要符合 `backend/composer.lock` 的 PHP 及 Composer（容器已驗證 PHP 8.5）, 前端使用 Node 22.18 以上的 22.x 或 Node 24.12 以上

```sh
cd backend
composer install
cp .env.example .env
php artisan key:generate
# 首次建立本機資料庫, 不覆寫既有檔案
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
php artisan serve --host=127.0.0.1 --port=3002
```

另開終端：

```sh
cd frontend
npm ci
npm run dev
```

本機 `.env.example` 預設使用資料庫 Queue／Cache, 不要求先裝 Redis。若要執行 Reverb 與 Redis 整合檢查, 建議使用 Compose 的完整服務配置；本機另啟 Reverb 時需自行產生 key／secret, 並明確設定可信 origin

## 驗證

```sh
docker compose exec backend php artisan test --do-not-cache-result
cd frontend
npm run build
```

Laravel 測試在 framework bootstrap 前強制 testing 環境及記憶體 SQLite, 覆寫 Compose 的程序環境並檢查隔離, 覆蓋租戶隔離、權限／作者限制、Epic、子任務／雙向關聯、狀態、工時、版本衝突、防重複提交、Seed 重跑／局部還原及舊資料匯入

瀏覽器回歸用 `frontend/tools/browser-check.js`, 須將 `E2E_API_URL` 指向**另一組空白、隔離的 Laravel 測試服務**, 不要指向使用者資料庫。會建立測試帳號、專案與工時, 前端使用 5175 埠：

```sh
cd frontend
npx playwright install chromium
E2E_API_URL=http://127.0.0.1:13004 npm run test:e2e
```

若隔離 API 已執行四帳號 Seed, 可加 `E2E_SEED_PASSWORD` 執行四角色登入／畫面權限比較, 僅使用隔離測試密碼。

可設定 `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` 使用現有 Chromium。回歸截圖位於忽略的 `frontend/artifacts/`, 不提交 Git

## 文件

- [產品討論紀錄](docs/產品討論紀錄.md)：已確認決策、待確認事項及選型沿革
- [協作與工時設計](docs/collaboration-design.md)：Epic、狀態、工時與通知
- [日常流程](docs/workflow-design.md)：子任務／關聯與常用視圖
- [架構提案](docs/architecture.md)：最新方向與 GuildManager／GAS 參考沿革
- [競品調研](docs/competitor-survey.md)：YouTrack、OpenProject 等比較
