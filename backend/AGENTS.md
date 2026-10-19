# TaskTracker Laravel 後端指引

沿用根 [AGENTS.md](../AGENTS.md) 與[README](../README.md)。後端專案是 Laravel JSON API, 產品頁面只在 `../frontend`, 不加入 Inertia、Blade 產品頁面或後端 npm 工具鏈

- 以 Composer lockfile 鎖定依賴, 容器提供 PHP 8.5。主機 PHP 不可用時使用 Docker, 不自動修改主機 PHP 安裝
- `docker compose exec backend php artisan test` 使用隔離記憶體 SQLite, 不改使用者資料
- 新授權以有效權限、Workspace／Project scope 與資源條件判斷；roleId 僅在角色映射中使用, 不在 controller 或 service 比較名稱放行
- RecordStore 的 Workspace namespace 是既有租戶邊界。寫入前在交易內重查成員資格與 revision, requestId 結果也須先授權
- 目前仍沿用空間內共享專案, Project ACL／完整矩陣尚待實作；不可將共享原型誤當成正式規格
- SMTP 預設 preview, 只能 verify, 不寄真實郵件。Reverb 目前只提供連線基礎, 不新增未授權公開業務頻道
- Reverb／Queue 是長駐程序, 程式修改後重啟；秘密來自環境或忽略的 volume, 不寫進 image、Git、前端及測試輸出
- 匯入只允許空目標, 不改來源, 測試使用人造資料與暫存 SQLite
- 不自動安裝額外 agent 工具或修改全域環境；新增套件須與當次任務相關
