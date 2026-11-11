# TaskTracker Laravel 後端指引

沿用根 [AGENTS.md](../AGENTS.md) 與[README](../README.md)。後端專案是 Laravel JSON API, 產品頁面只在 `../frontend`, 不加入 Inertia、Blade 產品頁面或後端 npm 工具鏈

2026-11-09：主庫改MySQL 8.4 LTS／InnoDB，PHP同時提供pdo_mysql與pdo_sqlite。SQLite僅保留歷史與快速隔離回歸。MySQL並行與資料遷移需在獨立測試Compose實測；不可把預設TestCase改成使用主資料庫。見[MySQL切換](../docs/mysql-migration-2026-11-09.md)。

## 後端共用規範

後端程式開發、review及測試設計先讀 [ECC laravel-patterns](../skills/vendor/laravel-patterns/SKILL.md)，再讀 [Laravel Architecture](../skills/laravel-architecture/SKILL.md) 與按需references。均由專案`.agents/skills`提供入口；來源及配置見[開發skills配置](../docs/development-skills.md)。舊backend-development-standards已移除。

- 架構／慣例以ECC為準，本地Architecture補足其未規定處；使用者需求、產品契約與下方資料／授權安全限制優先。
- Controller只處理HTTP協調，輸入由FormRequest驗證；Service協調流程、Action是有實際用途的單一用例；Repository只做資料存取。簡單CRUD可由Service直接用Eloquent，不讓Controller查DB，不為每個Model建立空Repository／Action。
- 命名、Repository回傳、Resource使用、交易owner及Unit／Feature必要條件依Architecture兩份references，不再沿用舊測試命名硬性格式或CLM專用例子。
- 保留既有Session／CSRF、RPC／ok-data／DomainError、revision／requestId與permission＋scope；不抄ECC範例改API、資料庫或登入方式。
- 2026-11-06安裝階段曾暫緩；後續使用者已恢復重構，依本輪計畫分階段執行。skills安裝不代表全庫已合規，不授權無關的批次格式化或改依賴。

## 專案限制與驗證

- 以 Composer lockfile 鎖定依賴, 容器提供 PHP 8.5.12。主機 PHP 不可用時使用 Docker, 不自動修改主機 PHP 安裝
- `docker compose exec backend php artisan test --do-not-cache-result` 使用隔離記憶體 SQLite, 不改使用者資料
- 新授權以有效權限、Workspace／Project scope 與資源條件判斷；roleId 僅在角色映射中使用, 不在 controller 或 service 比較名稱放行
- RecordStore／RelationalRecords 由伺服器選定 Workspace；核心欄位、子集合與關聯寫入明確業務表，複合FK限制Workspace／Project。LegacyRecordStore／StoredRecord僅供歷史migration、匯入staging與回退，不回到records正常雙寫。寫入前仍在交易內重查資格與revision，requestId結果也須先授權
- Project ACL與固定權限矩陣已落地；逐Project過濾任務、看板、通知及工時。保留撤權清除與重試前授權，不能把加入Workspace視為可讀全部Project
- SMTP 預設 preview, 只能 verify, 不寄真實郵件。Reverb已接看板私有Project頻道，commit後Queue只發無內容訊號，訂閱與API重讀依有效permission／scope授權；不新增公開業務頻道，見[同步契約](../docs/board-realtime.md)
- Reverb／Queue 是長駐程序, 程式修改後重啟；秘密來自環境或忽略的 volume, 不寫進 image、Git、前端及測試輸出
- 匯入只允許空目標, 不改來源, 測試使用人造資料與暫存 SQLite
- 不自動安裝額外 agent 工具或修改全域環境；新增套件須與當次任務相關

- 2026-10-20 補強：TestCase 在 bootstrap 前覆寫程序環境並強制 memory config, 避免 Compose 環境令 RefreshDatabase 清空本機資料；隔離證據須驗證外部 sentinel SQLite 完全未改
- 所有demo資料建立在database/seeders/DemoSeeder.php，DatabaseSeeder／db:seed與相容tasktracker:seed-demo共用；入口只允許local／testing，不因--force放寬。工時分析seed按月與工作日標記冪等，不覆寫舊工時。
- `tasktracker:seed-demo` 建指定 Jack／f-ws／first／second, 密碼僅由忽略的 TASKTRACKER_SEED_PASSWORD 設定；預設冪等, --reset 只重設此 seed namespace, 不改其他 Workspace

- 2026-11-04儲存更新：22張關聯式表及Eloquent模型已落地，資料設計／ER／遷移保護見[database-design.md](../docs/database-design.md)。JSON只用於事件／重試快照及未映射歷史欄位；新業務欄位須新增明確欄位／migration。

2026-11-06後續更新：使用者已明確恢復前後端架構重構，先前暫緩為歷史決策。依本專案Laravel／Vue skills分階段實作及驗證，保留API、資料、授權及產品操作契約，見`plans/main/plan.md`與`docs/產品討論紀錄.md`。

2026-11-11：目前本機依使用者要求連外部MySQL 8.0.46／Redis，根.env選docker-compose.remote-db.yml；原本機8.4及Redis volume保留但已停止。所有測試隔離原則不變，禁止對遠端主庫seed／RefreshDatabase或共享Redis全庫flush。連線、TLS測試例外及prefix見[外部服務切換](../docs/remote-services-migration-2026-11-11.md)。
