# TaskTracker Backend

Laravel 13 JSON API, 獨立於 `../frontend` 的 Vue SPA, 不使用 Blade／Inertia 渲染產品畫面

安裝、Compose、舊資料匯入與完整驗證方式見[根 README](../README.md)

- `routes/web.php`：JSON API 路由, 使用 Laravel session／CSRF middleware
- `app/Services`：帳號、工作空間業務規則與 SMTP 連線驗證
- `app/Authorization`：角色映射權限與集中檢查；Project ACL 仍待後續實作
- `app/Repositories`：Workspace namespace 限定的資料存取
- `app/Console/Commands`：舊版 SQLite 匯入、Redis／Queue／WebSocket 檢查
- `tests/Feature`：以隔離 SQLite 驗證 API 與資料遷移

目前使用 records payload 保留原型契約, Laravel migration 管理 schema；完整關聯式業務表與 Project ACL 分階段調整。Reverb 尚未接上任務推播, Queue 不寄送真實郵件
