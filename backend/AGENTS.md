# TaskTracker後端指引

先讀根AGENTS、README與docs/project-engineering-bindings.md。按Side Project共用baseline載入工程規範、ECC Laravel、Laravel Architecture及相關references；產品現況／環境沿革按需查Memory.md，不在本文件追加任務成果。

- Laravel只提供JSON API；前端Vue＋Vuetify在frontend，不加入Inertia或Blade產品介面。
- Controller → Service → Repository嚴格分層，穩定依賴用建構子注入。Repository只處理存取，規則／交易協調歸Service；具體命名、回傳、Resource、DI／PHPDoc及例外以共用Skill為準。
- 保持有效permission＋Workspace／Project及資源條件、Session／CSRF、PAT Scope、revision／requestId與receipt重播前授權。資料由伺服器選scope，不能以角色名稱放行。
- 遵守根AGENTS資料存取八項審查；契約異動同卡同步前端、正式OpenAPI、文件與測試，不保留整包租戶快照作預設。
- 預設 `docker compose exec backend php artisan test --do-not-cache-result` 為強制memory隔離，不能改接主庫。目標MySQL鎖／並行／migration另用獨立測試schema及Compose，不使用現用業務DB。
- 不以範例擅改PHP或資料庫安裝；依Composer lockfile與既有Docker工具鏈。Queue／Reverb修改後需重啟受影響程序，秘密不入image或輸出。
- Demo僅由database/seeders/DemoSeeder.php建立，入口只允許local／testing；不得以--force或測試規範繞過現用資料保護。
- SMTP依consumer現有preview／verify限制，不發真信；即時事件保持私有授權及最小訊號，cache不取代當下資格重驗。
