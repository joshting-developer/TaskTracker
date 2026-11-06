# TaskTracker Backend

Laravel 13 JSON API, 獨立於 `../frontend` 的 Vue SPA, 不使用 Blade／Inertia 渲染產品畫面

安裝、Compose、舊資料匯入與完整驗證方式見[根 README](../README.md)

- `routes/web.php`：JSON API 路由, 使用 Laravel session／CSRF middleware
- `app/Http/Controllers`：依專案、任務、工時、看板、成員、待辦分開的HTTP入口
- `app/Http/Requests`、`Resources`：共用JSON傳輸驗證與固定回應格式
- `app/Services`：Project／Issue／TimeEntry等領域服務、查詢彙整、重試與應用交易
- `app/Models`：Workspace／Project／Issue／Board／TimeEntry／Todo 等 Eloquent 模型及關聯；StoredRecord 僅映射歷史封存
- `app/Authorization`：角色映射權限與Project範圍授權，包含撤權及重試前檢查
- `app/Repositories`：Workspace 限定的關聯資料存取及既有 DTO 映射；LegacyRecordStore 僅供遷移／回退／匯入
- `app/Console/Commands`：舊版 SQLite 匯入、Redis／Queue／WebSocket 檢查
- `tests/Feature`：以隔離 SQLite 驗證 API 與資料遷移

目前使用獨立關聯式業務表、外鍵及索引，保留既有 JSON API DTO；舊 records 保留封存，正常業務不雙寫。Project ACL 已落地。Reverb 尚未接上任務推播, Queue 不寄送真實郵件

PHP：Composer最低需求8.3，Docker指定8.5.12；目前以Docker為驗證環境，未宣稱8.3／8.4均已測試。版本更新紀錄及檢查結果見[版本報告](../docs/前端後續改善與PHP版本檢查-2026-11-04.md)。

開發規範：先讀 [AGENTS.md](AGENTS.md)、[ECC laravel-patterns](../skills/vendor/laravel-patterns/SKILL.md) 與 [Laravel Architecture](../skills/laravel-architecture/SKILL.md)。後者補充分層／命名／回傳／交易／測試；舊後端skill已移除。2026-11-06本輪只配置skills，使用者已暫緩重構，既有程式不宣稱全面符合新規範。詳見[配置與衝突處理](../docs/development-skills.md)。

閱讀入口：見[後端程式導覽](../docs/backend-development.md)，包含請求流程、各class責任、相容邊界及驗證紀錄。

資料入口：見[關聯式資料設計](../docs/database-design.md)，包含 ER 圖、資料表用途、外鍵／索引、JSON 邊界及升級／回退驗證。
