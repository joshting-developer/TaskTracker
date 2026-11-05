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

首頁 `#/` 目前只顯示小待辦，保留空間供未來個人小工具；專案搜尋、建立與進入改在獨立的「專案列表」`#/projects`。品牌回首頁，專案麵包屑返回列表，小工具配置功能尚未實作。

Owner／Manager可從側欄「工時分析」選擇成員投入、專案投入或時間趨勢；提供期間、專案、成員篩選，預設本月。權限由後端permission與專案scope判斷，Epic與子卡各筆工時不重複加總。詳見[工時分析](docs/time-analysis.md)。

## 資料儲存

業務資料使用關聯式 SQLite：Workspace／Member、Project／Status／ACL、Issue／Comment／Relation、Board／收錄、工時、Todo／通知各有獨立資料表，含外鍵與索引。Laravel Eloquent 模型提供關聯；歷史事件與重試結果保留快照 JSON。

舊 `records.payload` 已改為歷史封存，正常業務不再寫入。升級會保留現有帳號、公開 ID、revision 與內容；啟動前先備份 SQLite，無需重設 Seed。資料表、ER 圖及升級／回退方式見[關聯式資料設計](docs/database-design.md)。

## Docker 開始試用

```sh
docker compose up -d --build
```

開啟 [TaskTracker](http://127.0.0.1:5174), 建立帳號及工作空間, 再建立／選擇專案。預設空資料, 可自行載入示範專案

專案列表卡片顯示名稱、代碼、負責人與介紹, 提供進入專案及有權限者的編輯入口；累計完成率與任務數已移除。新專案負責人預設建立者, 舊資料未設定時會明確提示。負責人是責任資訊, 不會自動授予存取權；專案編輯依 project.update, 代碼建立後固定。分析頁的期間／交付範圍與指標仍在規劃

進入專案後, 側邊欄只顯示目前專案資訊與任務／看板／工時等功能。點左上角 TaskTracker 返回首頁，再從專案列表選其他專案或管理目前工作空間成員；不在專案側欄列出其他專案。目前尚未提供多工作空間切換

專案內「任務列表」提供表格、篩選與分頁；「任務看板」是獨立功能, 用狀態欄與 Epic 泳道呈現, 支援收合、卡片密度、欄內新增及跨欄／泳道拖曳。兩邊共用同一批 Issue。側欄看板入口先開 `#/projects/<id>/boards` 看板列表；Default（`/board`）包含目前專案全部卡片, 自訂看板（`/board/<boardId>`）可收錄 Epic 或單卡, Epic 所屬卡片動態跟隨並去重。列表仍為 `#/projects/<id>`, 詳細頁保留來源看板。卡片密度／泳道偏好按看板保存在目前瀏覽器；收錄設定保存於伺服器, 不需重設資料

目前類型固定為 Epic、Task、Bug, 暫不提供自訂類型。Epic是可選卡片類型，不另設概覽入口；三種類型共用任務列表與類型篩選，從「新增任務」選擇Epic即可建立，關聯在詳細頁查看。舊 `/epics` 網址返回同專案列表。既有 Feature 透過 migration 轉為 Task, 保留內容／關聯／工時並增加版本與歷史；舊版匯入也會轉換。升級前請備份 SQLite, 不需重設 Seed

可在看板列表新增／編輯收錄, 或在自訂看板內新增卡片（會自動收錄）。移除看板保留卡片、留言與工時。管理入口依目前專案的 board.manage 權限；看板不擴張卡片存取範圍。詳見[看板設計](docs/board-design.md)

看板預設不分組，沿用目前瀏覽器上次的泳道／密度選擇；Epic本身也會呈現卡片。頁面統一上下捲動，看板局部左右捲動。精簡只留主要資料，詳細增加描述、優先級、期限、留言及工時／關聯。卡片不再提供狀態選單，狀態由所在欄位呈現，拖曳換欄即可修改；手機與鍵盤可在完整頁變更狀態。

任務可快速查看或開啟完整頁：列表與看板點任務標題開啟完整閱讀頁；列表的「快速編輯」及看板雙擊卡片／F2／鉛筆圖示可開側邊表單。手機看板可點鉛筆，手機列表從閱讀頁的「編輯任務」操作。面板的「開啟完整頁」或列表右側箭頭也能進入閱讀頁。上方顯示「目前狀態」，透過「變更狀態」選單更新此任務，自動包含專案自訂狀態；中間顯示描述及留言／異動／工時；右側以「屬性／關聯」tab區分類型／負責人／期限／工時，以及所屬Epic、父子任務與關聯卡片。長清單可在側欄捲動，手機從「屬性與關聯」展開；「編輯任務」沿用既有表單。完整頁在 Issue 網址加 `?detail=1`, 可分享與重新整理, 返回保留原列表篩選或來源看板；新開／重整後返回網址所屬列表或看板。未送留言與編輯草稿受離開保護, 衝突不清空內容

任務詳細內容提供「新增子任務」及「新增關聯」。子任務各有負責人、狀態與工時, 父項顯示完成數但不自動連動狀態；關聯為雙向追蹤。子任務跟隨父任務的 Epic, 工時按實際 Issue 各計一次。已移除前置任務與其結案阻擋

任務完整頁的「工時」分頁可點「填報工時」，自動帶入目前Epic／Task／Bug，也可修改自己的紀錄。Epic總投入包含直接填報及所屬卡片的有效工時，各筆只計一次；估時仍由所屬卡片加總。保存後留在原頁，月曆同步顯示同一筆紀錄。

工時月曆點擊日期後從右側開啟填報面板, 自動帶入日期；修改自己的紀錄也使用同一面板。平常月曆滿寬, 手機面板滿寬。關閉／Escape／遮罩會保護未儲存內容, 儲存成功後更新明細

既有資料啟動時透過 Laravel migration 將前置連結轉成一般關聯並保留歷史；建議升級前備份 SQLite。細節見[日常流程](docs/workflow-design.md)

右側採平整全寬的資料工作區, 以分隔線區分工具列與內容, 提高文字大小與對比。表格第一欄明示「類型」及 Task／Bug 文字，狀態另欄顯示。任務欄只留標題與編號，Epic／父子任務／關聯／工時與留言留到詳細頁查看；專案名稱不在每列重複。列表只保留目前篩選結果筆數, 移除總計／完成率與重複功能標題；手機在頂端顯示目前功能。狀態／優先級顏色保留於色點與圖示, 文字使用深色, 避免自訂淺色狀態難以辨識

任務頁常駐搜尋、排序與篩選入口, 其他條件按需展開；已套用條件即使收合仍可見、可逐項移除或清除。頁首不再重複返回連結與介紹, 可從專案麵包屑前往專案列表查看資訊

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

基礎設施檢查會實際送入／消費一筆 Redis 測試工作並完成 WebSocket 握手, 不寄信或發布任務事件。看板已接上 Reverb 私有 Project 頻道及 Redis Queue，新增／編輯／狀態／看板設定等變更即時重讀 API；重連補抓並保留30秒輪詢備援。詳見[看板即時同步](docs/board-realtime.md)

若本機埠被占用, 三個埠可分別調整；後續 Compose 指令須沿用相同設定：

```sh
TASKTRACKER_PORT=5176 TASKTRACKER_API_PORT=3004 TASKTRACKER_WS_PORT=8082 docker compose up -d --build
```

前端 `frontend/src/` 與後端 app／routes／config 掛載至容器。前端 index／Vite 設定、後端 bootstrap／Composer 與依賴變更後需重建；修改後端類別後重啟長駐的 queue／websocket 以載入新程式

## 功能網址與操作

專案內工時、狀態設定、成員、通知預覽與 Epic 分別使用 `#/projects/<id>/time`、`/settings`、`/members`、`/notifications`、`/epics`, 可直接開啟或重整, 瀏覽器上一頁／下一頁可切換功能。既有 Project、board、Issue 網址相容；未授權功能回到任務列表, 專案不可見則回首頁。未保存表單離開前仍會確認。

首頁待辦、任務詳情及快速編輯的活動 tab 支援左右方向鍵與 Home／End；手機任務詳情優先描述, 點「任務屬性」才展開完整欄位。工時以整數分鐘計算, 例如 0.25 小時為 15 分鐘, 0.5 為 30 分鐘；錯誤會標出欄位並保留輸入。修正沿革見 [UI／UX 審查報告](docs/UIUX審查報告-2026-11-03.md)。

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
| maintainer | maintainer@example.test | first／second 的專案管理者, 可管理成員／資訊／狀態／看板, 不可建專案或邀請 |
| developer | developer@example.test | 只加入 first 為開發者, 可處理任務／留言／本人工時, 看不到 second |

四帳號使用同一個你指定的本機密碼, 存在忽略的 volume 環境檔, 不進 Git。新環境先在 backend 的被忽略 `.env` 或容器環境設定 `TASKTRACKER_SEED_PASSWORD`（至少 10 字）。權限由後端 permission ID 檢查, 名稱不作放行依據；owner／manager 可存取所有專案；maintainer／developer 的 Workspace role 是 user, 必須有各專案授權才看得到。owner／manager 及所屬專案的管理者可看團隊通知, 開發者／讀者只看本人；所有人都只能修改自己的工時

新建／重設時每個專案有 1 個 Epic、6 張一般卡片, 包含父子任務、雙向關聯、不同狀態／優先級、留言及按不同帳號填報的工時。Epic 指派 manager、父 Task 指派 maintainer；first 執行卡指派 developer, second 執行卡指派 maintainer。舊 Seed 重跑會非破壞地加入四帳號, **保留 Jack 與既有卡片、指派和工時**；所以既有資料的「我的任務」可能仍是空的, 可自行指派試用。v2 再重跑保留修改及帳密；明確 --reset 才重建此 seed 空間與四帳號密碼, 不修改其他空間。Email／ID 被外部帳號占用時拒絕覆蓋

Seed 只允許 local／testing。Laravel 的 `php artisan db:seed` 也呼叫相同非重設流程。目前權限矩陣及限制見[Project ACL 契約](docs/project-acl.md)；[四帳號舊紀錄](docs/產品討論紀錄.md#四帳號權限試用-seed2026-10-22)保留沿革

## 帳號與工作空間設定

右上角頭像可開啟帳號選單，查看姓名、Email與目前空間，前往個人資料／工作空間設定或登出；左下角保留身分資訊。個人資料可改顯示名稱，Email目前唯讀。

「工作空間設定 → 一般設定」可改空間名稱；「成員與權限」可邀請同伴及調整固定工作空間角色。Owner／Manager有空間管理權限，一般成員只能查看有權讀取的資料；角色操作仍由後端有效permission決定，這裡不提供ownership轉移或自訂角色。個別專案請進入專案側欄「成員與權限」設定負責人／專案管理者／開發者／讀者，與空間角色分開。

設定表單有版本衝突、重試及未保存確認，改名不改歷史內容。詳見 [帳號與工作空間設定](docs/account-workspace-settings.md)。專用瀏覽器驗證：在 `frontend/` 執行 `E2E_API_URL=http://127.0.0.1:13004 npm run test:settings`，必須使用隔離API，與其他瀏覽器腳本分開使用5175埠。

## 首頁小待辦

首頁提供不屬於 Project Issue 的輕量待辦：直接寫一整塊 Markdown 速記，Enter 換行，按「加入待辦」保存；選同空間成員即可直接交辦。首行自動產生清單摘要，展開可閱讀全文。所有有效 Workspace 成員都可互相交辦, 包含一般成員交給 Manager／Owner；收到後直接進「我的待辦」, 可完成、重新開啟或退回交辦者。「我交辦的」用來追蹤自己交出去的項目, 完成／取消／轉卡預設收合。首頁目前只顯示小待辦，專案入口位於獨立列表；未完成待辦預設預覽三筆, 可展開全部, 從通知定位時會自動顯示目標項目

小待辦、任務描述及留言共用 Markdown 編輯器：格式工具、編輯／預覽、粗體、清單、核取清單、連結與程式碼。核取清單在預覽中唯讀；HTML 顯示為文字，圖片語法先顯示連結。原稿完整保存，待辦／描述上限 6000 字、留言 2000 字；舊待辦仍可閱讀與修改。詳見 [Markdown 編輯與資料相容](docs/markdown-editing.md)。

待辦整筆完成請用「更多 → 標記為已完成」；展開已完成項目後，可用「更多 → 重新開啟」。列首不放選取框，已完成項目保留刪除線及狀態文字，操作仍限目前承辦者。

待辦僅交辦者及目前承辦者可讀, Workspace 管理權限不會打開他人的私人速記。首頁即使尚未加入任何 Project 也可使用小待辦；成員選單是獨立的同空間最小交辦目錄, 不改 Project ACL。建立者可修改未完成內容或取消, 承辦者可處理進度及退回, 退回後交辦者重新成為承辦者

待辦「更多 → 轉成正式卡片」開啟原建卡側欄, 帶入摘要標題及完整 Markdown 原稿, 選擇有任務編輯權的專案並補類型、狀態、負責人、Epic 等欄位。建立卡片和標記待辦轉卡在同交易完成, 重試不重複建卡；來源待辦保留, 卡片連結只對具該專案讀取資格的人顯示。只有目前承辦者可轉換, 本版沒有卡片轉回待辦接口

頂端「待辦通知」提供未讀數, 收到交辦、內容更新、完成／重開／退回／取消／轉卡會通知另一方, 點擊標已讀並回首頁定位。這是實際保存在伺服器的站內通知, 與原專案郵件 preview 分開；依現有 30 秒輪詢或手動刷新更新, 尚無即時推播／寄信。新增資料經 RecordStore 寫入獨立的待辦／通知表，無需重設 Seed。詳見 [小待辦設計](docs/todo-design.md)

## 專案成員與 ACL

Workspace owner／manager 以明列的全專案 permission 管理空間內所有 Project；一般 user 加入 Workspace 後預設看不到任何專案。管理者進入專案, 從側欄「專案成員」選擇既有 Workspace 成員及角色, 可加入、更換角色或移除, 並查看授權異動紀錄。成員表單有獨立版本檢查, 衝突時保留草稿再重新載入

固定 Project 角色為負責人、專案管理者、開發者、讀者；前兩者本版共用管理權限。讀者可查看任務完整頁及看板, 不能編輯、留言、拖曳或查看實際工時；開發者可查看團隊工時, 仍只能修改本人工時。專案負責人欄位僅作責任資訊, 不會自動給權限。所有授權依有效 permission＋Project scope＋資源條件判斷, 不以角色名稱放行

清單、深連結、Epic／關聯、看板、工時與通知都受相同後端範圍限制。撤權／降權於下個 API 請求生效, 舊 requestId 重試也重查權限；畫面於重新整理、拒絕回應或30秒輪詢更新；看板另以私有頻道訊號觸發重查與清除。撤去專案資格後清除該頁與編輯草稿, 已有任務指派、留言、工時及歷史保留

升級前備份 SQLite（含正在使用的 WAL, 建議 SQLite backup 或 VACUUM INTO）。啟動 migration 將舊 member／maintainer／developer 空間角色改為 user, **不把所有一般成員自動加入全部專案**；原 owner／manager 保有管理入口, 再逐案加入成員。Seed 只為明確示範 namespace 加入上述 first／second 資格, 不改既有卡片／工時／帳密；重跑不恢復已撤回的資格。無需 reset, 回舊版應還原備份。完整契約見 [Project ACL](docs/project-acl.md)

## 資料與設定

- `laravel-data`：SQLite 與首次啟動產生的 APP_KEY／Reverb 金鑰, 不寫入 Git 或 image
- `laravel-storage`：Laravel 執行時資料與日誌
- `redis-data`：Redis AOF, Queue／Cache 的持久化資料
- `docker compose down` 保留 volume, `down -v` 會刪除上述資料及秘密
- 舊版 `data/` 與 `tasktracker-data` volume 保持原狀, 不會自動匯入或覆蓋

工作空間及專案存取皆由後端授權, 目前提供固定角色與 Project scope ACL。正式 SaaS 的註冊政策、既有帳號加入多空間、管理角色任命／移轉、自訂角色及即時推播仍須分階段完成

SMTP 預設 preview, 只有連線／TLS／登入驗證, 不寄真實郵件。可參考根目錄 `.env.example` 填寫被忽略的 `.env.local`, 再執行 `docker compose --env-file .env.local up -d --build`；SMTP 設定只供後端使用

### 匯入舊 Node SQLite

先備份來源, Laravel 目標必須沒有帳號及業務資料。根 `data/` 在容器內唯讀掛載為 `/legacy`, 執行：

```sh
docker compose exec backend php artisan tasktracker:import-legacy /legacy/tasktracker.sqlite
```

匯入保留帳號／Workspace／任務／工時 ID 及歷史, 不匯入舊 session 或 requestId 快取。來源不修改, 有既有目標資料時拒絕覆蓋。原 Node scrypt 帳密可繼續登入, 首次成功登入後升級 Laravel 雜湊, 使用者須重新登入

若舊資料位於 Docker `tasktracker-data` volume, 請先停寫並備份／複製到根 `data/` 後匯入, 不直接讓兩個版本共用可寫 SQLite

## 不使用 Docker 的開發方式

需要符合 `backend/composer.lock` 的 PHP 及 Composer；Composer最低需求為PHP8.3，Docker指定PHP8.5.12安全修補版。前端使用 Node 22.18 以上的 22.x 或 Node 24.12 以上

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

Laravel 測試在 framework bootstrap 前強制 testing 環境及記憶體 SQLite, 覆寫 Compose 的程序環境並檢查隔離, 覆蓋租戶隔離、Project ACL／固定矩陣／撤權重試／授權衝突／作者限制、Epic、子任務／雙向關聯、狀態、工時、版本衝突、防重複提交、Seed 重跑／局部還原及舊資料匯入

瀏覽器回歸用 `frontend/tools/browser-check.js`, 須將 `E2E_API_URL` 指向**另一組空白、隔離的 Laravel 測試服務**, 不要指向使用者資料庫。會建立測試帳號、專案與工時, 前端使用 5175 埠：

```sh
cd frontend
npx playwright install chromium
E2E_API_URL=http://127.0.0.1:13004 npm run test:e2e
```

UI／UX 修正的針對性瀏覽器檢查也使用同一類隔離服務（同樣占用 5175, 請分開執行）：

```sh
cd frontend
E2E_API_URL=http://127.0.0.1:13004 node tools/uiux-check.js
```

涵蓋三筆待辦預覽／通知定位／背景更新草稿、tab 鍵盤、工時錯誤與保存、手機屬性與平板工具列、五種功能路由及 reader 深連結限制。若主機已有 Chromium, 可用 `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` 指定執行檔。


若隔離 API 已執行四帳號 Seed, 可加 `E2E_SEED_PASSWORD` 執行四角色登入／畫面權限比較, 僅使用隔離測試密碼。

可設定 `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` 使用現有 Chromium。回歸截圖位於忽略的 `frontend/artifacts/`, 不提交 Git


F01–F04 可用性修正另有 `E2E_API_URL=http://127.0.0.1:13004 node tools/usability-fixes-check.js`（在 `frontend/` 執行，同樣必須使用隔離 API），驗證跨 tab 草稿、查閱續作、手機速記及閱讀／快速編輯。

Markdown 專用檢查：在 `frontend/` 執行 `E2E_API_URL=http://127.0.0.1:13004 npm run test:markdown`，同樣使用隔離 API 及 5175 埠；涵蓋原稿保存／轉卡、格式／預覽／中文輸入、返回草稿焦點與不可信內容。

## 文件

- [使用者流程走查結果（2026-11-04）](docs/hci/usability-findings-2026-11-04.md)：雙 skill、八個任務／45份實際畫面, 四項改善與真人資料缺口
- [使用者流程／可用性驗證方案（2026-11-04）](docs/可用性驗證方案-2026-11-04.md)：兩個新 skill 評估、AI 任務走查與真人試用分工, 含後續走查報告入口
- [UI／UX skills 與來源](skills/README.md)：原有兩個已加入 `.agents/skills`；新增 cognitive-walkthrough／usability-testing 僅評估副本, 保留原文與介紹
- [UI／UX 審查報告（2026-11-03）](docs/UIUX審查報告-2026-11-03.md)：兩個 skill 搭配真實隔離 API、44 組畫面量測, 問題與改善優先級；本輪未修改 UI
- [競品使用流程與功能缺口研究（2026-10-30）](docs/競品使用流程與功能缺口研究-2026-10-30.md)：五家競品的日常流程、公開回饋及分階段功能建議
- [首頁小待辦設計](docs/todo-design.md)：速記、互相交辦、通知、隱私與單向轉卡
- [Project ACL 契約](docs/project-acl.md)：固定角色矩陣、permission／scope、成員 API 與資料升級
- [產品討論紀錄](docs/產品討論紀錄.md)：已確認決策、待確認事項及選型沿革
- [協作與工時設計](docs/collaboration-design.md)：Epic、狀態、工時與通知
- [日常流程](docs/workflow-design.md)：子任務／關聯與常用視圖
- [架構提案](docs/architecture.md)：最新方向與 GuildManager／GAS 參考沿革
- [競品調研](docs/competitor-survey.md)：YouTrack、OpenProject 等比較

查閱續作：任務範圍（全部／我的／已完成）與工時月份、成員、選取日，按帳號／工作空間／專案保存在目前分頁的 sessionStorage；重整保留，分享網址不包含這些條件。搜尋與其他即時篩選不保存，未恢復「存為視圖」。瀏覽器禁用儲存時仍可使用，但不保證重整續作。

手機／平板首頁首屏提供「記小事」，直接定位並聚焦待辦輸入欄；首頁內定位會保留既有速記內容，專案區排列維持不變。

版本與維護：見[前端後續改善與PHP版本檢查](docs/前端後續改善與PHP版本檢查-2026-11-04.md)。本機PHP若遇到Homebrew／ICU錯誤，先使用`docker compose exec backend php artisan ...`，本專案不要求修改主機共用PHP。PHP映像修補後需重建並重新建立backend／queue／websocket，單純restart不會換映像。

後端程式閱讀：2026-11-04已拆分明確HTTP入口、Project／Issue／TimeEntry服務及授權／重試邊界，見[Laravel後端程式導覽](docs/backend-development.md)。既有API與資料契約保留，尚未遷移完整關聯式業務表。
