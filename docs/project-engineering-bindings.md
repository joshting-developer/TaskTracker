# TaskTracker工程綁定

2026-11-12／TT-268。共用規則在Side Project Skill庫，本文只定義TaskTracker的框架與產品契約，不是另一份Skill。

- 前端Vue 3＋Vuetify＋Vite，既有JavaScript與Composition API／script setup；後端Laravel JSON API，不使用Inertia或Blade產品介面。
- Controller → Service → Repository嚴格分層；穩定依賴用建構子注入，純計算不造空Repository。分工／命名／回傳以共用Laravel Architecture為準。
- Session／CSRF與公開Bearer PAT各有入口；沿用ok-data／DomainError、revision／requestId、receipt及重播前授權。契約可按資料存取審查改版，同卡同步呼叫端、OpenAPI、文件與測試。
- 有效permission＋Workspace／Project scope及資源條件決定授權，角色只作映射。租戶上下文由可信伺服器認證選定；不得以角色名稱、前端ID或快取取代重驗。
- 現用主庫為Fly連線的正式MySQL 8.4／InnoDB（task_tracker）與TLS Redis, 原本本機來源唯讀保留，SQLite memory只作快速隔離測試。寫入先鎖User→Workspace穩定資料列，再驗資格／版本；跨程序狀態不存長駐Singleton。禁止主庫seed／reset／壓測及共享Redis全庫flush。
- 工時工作日Asia/Taipei、每人每日24小時上限，只改本人有效紀錄。Epic允許直接填報，匯總去重；狀態open／closed必備，只有closed算結案，類型Epic／Task／Bug。
- 產品UI保留紫色品牌與既有流程，只留必要操作與事實文案。UI審查用真實隔離Laravel流程及瀏覽器證據，不能用編譯成功代替。
- 預設artisan test強制隔離memory；並行、鎖與引擎行為另用獨立MySQL Compose。秘密、PAT、Cookie、資料庫、截圖與build產物不進Git。
- 卡片追蹤、獨立Agent身分、領取／狀態／留言、逐階段commit及push授權依根AGENTS，不由共用庫替其他專案決定。

現況及歷史見根AGENTS、backend/AGENTS與各功能文件；共用規範的載入方式見[Skill入口](../.agents/skills/README.md)。
