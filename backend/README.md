# TaskTracker Backend

Laravel JSON API, 搭配前端 Vue SPA, 不使用 Blade／Inertia 渲染產品畫面

[啟動與測試](../docs/development.md)、[資料搬移](../docs/mysql-transfer-runbook.md)與[正式 OpenAPI](resources/openapi.json)提供協作者操作入口, 開發者指南由網站 `/api/public/docs` 提供

- routes 與 Http：HTTP、Session／CSRF、PAT、輸入驗證與回應格式
- Services 與 Repositories：業務、交易與租戶範圍內的資料存取
- Models 與 database：關聯、migration 與僅供本地／隔離測試的示範資料
- tests：全部使用隔離 MySQL, 未提供專用測試連線即拒絕執行

版本依 composer.lock 與 Dockerfile, 不對正式庫 seed／reset 或使用正式資料執行測試。產品設計與歷次研究放 idea, 本機掃描及驗收報告不版控
