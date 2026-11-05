# TaskTracker Laravel 後端指引

沿用根 [AGENTS.md](../AGENTS.md) 與[README](../README.md)。後端專案是 Laravel JSON API, 產品頁面只在 `../frontend`, 不加入 Inertia、Blade 產品頁面或後端 npm 工具鏈

## 後端共用規範

後端程式開發、review及測試設計先讀 [backend-development-standards](../skills/backend-development-standards/SKILL.md)，再按工作選讀六份references；專案入口亦在`.agents/skills/backend-development-standards`。來源與適配校驗見 [來源清單](../skills/backend-standards-sources.json)。

- 新增／修改的class、function使用中文docblock，說清責任、實際參數及回傳；展開流程，避免密集單行及需要心算的布林條件。
- 只建立有實際用途的Controller／Service／Repository／Resource或Transformer，流程要能從入口理解；不以新增空wrapper代替責任拆分。
- 規範自後續相關改動採用，未修改的既有原型不視為已合規；使用者於2026-11-04後續已要求調整，核心流程已分層；後續仍按明確範圍逐步整理，不為套規範改動無關工具或重命名舊測試。
- TaskTracker既有產品契約與下列授權／隔離規則優先；CLM的時間macro、Kibana、Report／PDF與安裝包只是來源案例，不自動加入本專案。

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
- `tasktracker:seed-demo` 建指定 Jack／f-ws／first／second, 密碼僅由忽略的 TASKTRACKER_SEED_PASSWORD 設定；預設冪等, --reset 只重設此 seed namespace, 不改其他 Workspace

- 2026-11-04儲存更新：22張關聯式表及Eloquent模型已落地，資料設計／ER／遷移保護見[database-design.md](../docs/database-design.md)。JSON只用於事件／重試快照及未映射歷史欄位；新業務欄位須新增明確欄位／migration。
