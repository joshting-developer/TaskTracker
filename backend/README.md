# TaskTracker Backend

Laravel 13 JSON API, 獨立於 `../frontend` 的 Vue SPA, 不使用 Blade／Inertia 渲染產品畫面

安裝、Compose、舊資料匯入與完整驗證方式見[根 README](../README.md)

- `routes/web.php`：JSON API 路由, 使用 Laravel session／CSRF middleware
- `app/Services`：帳號、工作空間業務規則與 SMTP 連線驗證
- `app/Authorization`：角色映射權限與Project範圍授權，包含撤權及重試前檢查
- `app/Repositories`：Workspace namespace 限定的資料存取
- `app/Console/Commands`：舊版 SQLite 匯入、Redis／Queue／WebSocket 檢查
- `tests/Feature`：以隔離 SQLite 驗證 API 與資料遷移

目前使用 records payload 保留原型契約, Laravel migration 管理 schema；Project ACL已落地，完整關聯式業務表仍待後續重構。Reverb 尚未接上任務推播, Queue 不寄送真實郵件

PHP：Composer最低需求8.3，Docker指定8.5.12；目前以Docker為驗證環境，未宣稱8.3／8.4均已測試。版本更新紀錄及檢查結果見[版本報告](../docs/前端後續改善與PHP版本檢查-2026-11-04.md)。

開發規範：先讀 [AGENTS.md](AGENTS.md) 與 [backend-development-standards](../skills/backend-development-standards/SKILL.md)，涵蓋中文註解、可讀性、分層、API契約、併發與測試。原共用規範已搬入專案並適配；這不代表後端重構已完成。
