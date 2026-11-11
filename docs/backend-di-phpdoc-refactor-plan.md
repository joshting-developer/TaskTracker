# TaskTracker 後端全面 DI 與 PHPDoc 重構規格

> 建立日期：2026-11-12（Asia/Taipei）
> 狀態：**待實作的重構需求／交付規格**，不是已完成報告。
> 專案：TaskTracker（Laravel 13、PHP 8.5、Vue SPA；現行業務資料庫為 MySQL）。
> 使用者決策：**整個自有後端都要處理；允許大範圍 Diff**。不要只整理碰巧修改到的幾個檔案，也不要為了減少 Diff 而保留明顯不一致的依賴寫法與註解格式。

## 1. 目標與非目標

### 必須達成

1. 自有後端的 Service、Repository、Action、Query、Authorization、Controller、Job、Listener 及相關協作物件，依賴關係原則上採 Laravel Container 管理的 **Constructor Injection**；適合框架方法注入的 HTTP 入口可使用 Method Injection。
2. 將散落在業務流程中的直接 new Service／Repository、app()／resolve() Service Locator，以及無必要的手動依賴組裝，改為清楚且容易替換／測試的依賴關係。
3. 將可信任的操作者、Workspace／Project scope、Permission 等「執行期資料」與「物件的穩定依賴」區分；不可因 DI 造成租戶狀態跨請求洩漏。
4. **全面盤點並統一專案自有後端 PHPDoc**：描述以繁體中文為主，依實際參數與型別提供標準 @param、@return，必要時補 @throws、泛型及 array shape。可接受大量文件與格式 Diff。
5. 保留對外行為與業務資料；以測試證明不是僅把 new 轉移到另一個檔案。
6. 完成清楚的 DI／PHPDoc 掃描清單、修改清單、例外及驗收紀錄。

### 本輪不應順便做

- 不因 DI 重構改掉 Laravel 版本、Vue、API 欄位／Envelope、MySQL Schema、Redis 配置、既有工時／任務語意。
- 不為每個 Service 或 Model 生造 interface、Repository、Action；**有多實作、外部系統邊界或明確替換理由才增加介面**。
- 不將所有類別註冊為 singleton，不為了好看引入跨請求全域容器狀態。
- 不把 getWorkspace 全量讀取、SQL 分頁、遠端 MySQL 網路延遲等效能重構混為 DI 完成條件。它們是**獨立且仍須追蹤的問題**；如本輪觸及讀取層，不能惡化現有 SQL／Payload。
- 不直接操作正式／遠端業務資料庫做 destructive test、seed、RefreshDatabase 或 shared Redis FLUSH。

## 2. 實際程式現況：應先處理的切入點

下列為 2026-11-12 查閱 GitHub main 時可見的實例，實作前仍應重新掃描最新 working tree：

| 檔案 | 現況 | 要求 |
| --- | --- | --- |
| backend/app/Http/ApiExecution.php | 已注入 AccountService、SmtpService、WorkspaceSession、TimeReportRepository，但仍 new WriteLocks、WorkspaceMemberships、AccountSettings、Todos、WorkspaceService、WorkspaceTimeReport、RecordStore | 注入穩定依賴；授權後的情境資料交給用例或明確 Factory |
| backend/app/Services/WorkspaceService.php | 建構子 new RecordStore、WorkspaceOperationAccess；write() 同時 new ProjectService、IssueService、TimeEntryService、ProjectBoards、ProjectMembers；execute() new WorkspaceQuery、RequestReplay | 拆出 Composition Root／Factory；不要讓 WorkspaceService 知道所有服務如何建構，也不要在每次操作時無條件初始化所有領域服務 |
| backend/app/Services/IssueService.php | Constructor 接受 RecordStore 與 workspaceSession array，內部 new ProjectAccess，建立 Issue 時另 new ProjectBoards | 將可重用協作者交由 DI；情境與租戶 scope 清楚傳遞 |
| backend/app/Services/ProjectService.php | 注入 RecordStore 與 workspaceSession，內部 new ProjectAccess | 改成注入可解析的授權協作者或明確按 scope 取得授權物件 |
| backend/app/Repositories/RecordStore.php | 自行 new RelationalRecords／LegacyRecordStore，forWorkspace() 再 new self | 考慮有明確責任的 workspace-scoped StoreFactory；歷史相容模式不得被一般寫入誤用 |
| backend/app/Providers/AppServiceProvider.php | 目前主要註冊 SQLite connection resolver | 只註冊無法自動解析、需特定生命週期、factory 或介面映射的項目；不必把所有 concrete class 逐一 bind |

**全面掃描範圍**（不可只看上表）：backend/app/**、backend/database/seeders/**、backend/app/Console/**（如有）、專案自行維護的 backend/tools/**、backend/tests/** 中的支援類別與需要修改的 docblock，以及其他自有 PHP 程式。Migration、config 和 PHPUnit 測試方法亦須檢視既有註解是否不一致，但不要為 Laravel generated/vendor 程式碼或單純宣告檔製造無意義的 PHPDoc。

## 3. DI 的具體設計原則

### 3.1 由 Laravel Container 管理穩定依賴

- Controller 只處理 Request／Resource／HTTP 邊界；可用建構子或 Method Injection。
- Service／Action／Query 預設 constructor-inject 所需協作服務或 Repository；不要在業務方法內到處 new 同類型物件。
- Repository 專注查詢與保存，不替 Service 決定授權／流程，也不承擔 HTTP Session。
- 對沒有狀態、可自動解析的具體類別，直接 type-hint 即可；不強制 Provider bind。
- Date／Clock、外部郵件、通知、遠端 API 等有替換需求的邊界，視需要引入介面或 Adapter。
- **純 Value Object、DTO、Entity、Exception、Query Builder 值物件及顯式 Factory 內的 new 是合理例外**；規則不是禁止 PHP 使用 new，而是禁止業務層自行組裝穩定的服務依賴。
- 避免用 app()／resolve() 掩蓋依賴；框架 Bootstrap、明確 Factory、特殊 CLI 測試等確有需要的地方，記錄理由。

### 3.2 Workspace／Actor 不能放成錯誤的共享狀態

目前 WorkspaceService 需要由 Session 驗證後才知道 Workspace ID 與 Actor；直接把帶此狀態的 Service 註冊成 singleton 是危險的。

**預設優先方案：**
- 將已驗證的 actor／workspace／permission 組成明確的 WorkspaceContext（型別化、不可由外部輸入任意指定）。
- 穩定依賴透過建構子注入；WorkspaceContext 優先當作執行用例的方法參數。
- 若現有用例需要每 Workspace 綁定的 RecordStore，可由明確的 StoreFactory／WorkspaceServiceFactory 在可信 context 建立，再注入目標 Service；集中組裝位置，不在多個業務 Service 重複組裝。
- 只有確實需要 request-scoped binding 時才使用 scoped，並驗證 Queue／Reverb 長駐進程不保留上一個操作的身份與 Workspace；不可濫用 singleton。
- 現有授權以有效 permission、Workspace／Project scope、resource／author 條件判定；不能因為改成 Context DTO 就省略交易內重新授權。

示意（最終以 Laravel 實際容器解析與現有相容契約決定，不要求照抄名稱）：

    final class IssueService
    {
        public function __construct(
            private IssueRepository $issues,
            private ProjectAccessFactory $access,
        ) {}

        /**
         * 更新指定任務，並依當次可信身分驗證存取資格及版本。
         *
         * @param WorkspaceContext $context 交易內驗證的操作者與工作空間
         * @param UpdateIssueData $input 已驗證的任務更新資料
         * @return IssueData 更新後的任務資料
         */
        public function update(WorkspaceContext $context, UpdateIssueData $input): IssueData
        {
            // 使用被注入的協作者，依本次 context 驗證權限與資料範圍。
        }
    }

### 3.3 先檢查依賴圖，不做表面 DI

- 畫出 Route → Controller → ApiExecution → WorkspaceService → 各領域 Service → Repository 的實際依賴圖。
- 排除 Service A → B → A 的循環；必要時按真正用例拆責任，不能把循環隱藏成 app()／resolve()。
- 比較單一操作前後初始化的 Service 數量；不應為更新一張卡片就強制建構所有 Project／Time／Board Service。
- 保留明確的 transaction owner、RequestReplay 與 afterCommit 副作用；DI 不應新增巢狀交易、打散原子性或提早觸發事件。
- 若需要 Factory，應只在**組裝邊界**建立物件；Factory 不得成為第二個萬用 Service Locator。

## 4. PHPDoc 與註解統一規範（適用全自有後端）

### 4.1 標準格式

    /**
     * 根據 Issue 識別碼取得標題。
     *
     * @param string $issue Issue 識別碼
     * @return string Issue 標題
     */
    public function getIssueTitle(string $issue): string
    {
        // ...
    }

- 使用真正的 /** ... */ PHPDoc，每行以 * 起始；不要把 Markdown 列表符號「-」當成 PHPDoc 基本格式。
- 說明內容使用**繁體中文**，首句具體描述業務用途／重要副作用；不寫只有「取得資料」「設定依賴」的空泛敘述。
- 說明段與 @param／@return 等標籤間保留一行空白。
- 每個參數的名稱必須有 $，型別與方法簽章一致；參數有預設值時不可捏造必填語意。
- 非建構子的方法依使用者要求原則上補齊 @param 與 @return（無參數可省略 @param，void 回傳使用 @return void）；若另有 PHPDoc 靜態分析工具的特殊限制，記錄例外。
- 有意義的 @throws 才標註；不要列出毫無機會向外傳遞的框架內部例外。
- 集合、分頁、nullable、union、callable、泛型及 array shape 優先精確表達：例如 array<string,mixed>、Collection<int, Issue>、array{projectId:string,revision:int}，不要把所有型別降級成 array 或 mixed。
- Class／Trait／Interface 也要有清楚用途註解；建構子不需為重複型別而寫冗長文字，但需要描述其注入依賴和非顯而易見的情境限制。
- 不改動原本有價值的 @template、@phpstan-type、@mixin 等工具註解；修正而非刪除靜態分析資訊。
- 刪除明顯過時、與實作矛盾、重複且無價值的既有註解；不要只做字串批次替換。
- PHP 的原生型別宣告優先且與 PHPDoc 一致，不能以註解隱藏真實 nullable 或可能失敗情境。
- **接受大 Diff；但不得為了追求格式一致而悄悄修改業務結果、例外語意或權限判斷。**

### 4.2 範例：陣列與例外

    /**
     * 依專案與有效權限列出可見任務摘要。
     *
     * @param WorkspaceContext $context 已驗證的工作空間及操作者
     * @param string $projectId 專案公開識別碼
     * @param int $perPage 每頁筆數，必須經過上限驗證
     * @return LengthAwarePaginator<int, IssueSummaryData> 已授權的分頁任務
     * @throws DomainError 專案不存在或操作者沒有讀取權限
     */

此為**目標規格示意**，不表示現有 API 已提供該分頁方法或 DomainError 實際以此方式拋出。實作時需以真實類別、throws 行為及可用的靜態分析語法修正。

## 5. 分階段施工，但全範圍必須清完

1. **盤點與基準**：掃描自有 PHP 類別、直接 new Service／Repository、app()／resolve()、建構子依賴、現有 PHPDoc；建立檔案清單與例外清單。讀 AGENTS、相關 Skills、現有本機修改；記錄基線測試結果。
2. **Context 與組裝邊界**：決定 WorkspaceContext 與工廠的責任／生命週期，確認 ApiExecution 在交易內驗證 Session 和 scope 的時序；先寫容器解析與跨 Workspace 隔離測試。
3. **核心依賴鏈**：處理 ApiExecution、WorkspaceService、WorkspaceQuery、IssueService、ProjectService、TimeEntryService、ProjectAccess、RecordStore／RelationalRecords／RelationalChildren；同階段維持 API 回應契約與回歸測試。
4. **其餘自有後端全面 DI**：逐一處理其他 Controllers、Services、Repositories、Authorization、Events／Jobs、Seeders／CLI 及相關輔助類別；清理無必要 Service Locator，寫出少數合理例外。
5. **全庫 PHPDoc 統一**：對所有自有後端目標檔案逐項修正 Class／Method 註解、參數及回傳型別，包含前面未碰到的檔案。可集中產生大 Diff，但逐檔確認實際語意，不以自動字串替換當完成。
6. **總驗收與文件**：完整 PHPUnit、MySQL 隔離整合、核心瀏覽器流程、Pint 與相關型別／語法檢查；輸出 DI 掃描前後差異、例外、PHPDoc 覆蓋與未完成項目。階段性本機 Commit；是否 push 依 root AGENTS 和使用者明確指示。

**不可把「核心鏈已處理」當成全庫完成，也不可把「註解格式已改」當成型別與依賴都已正確。**

## 6. 必要驗收與安全護欄

### DI／可測試性

- Controller → Application Service → Repository 由 Container 正常解析；按實際責任測試替換 collaborator，不需修改被測類別原始碼。
- 單一操作沒有不必要的其他領域服務 eager initialization；沒有循環依賴。
- 所有自有 Service／Repository 內直接 new 與 app()/resolve() 已逐一盤點：要麼替換為 DI／Factory，要麼附具體合理原因，不准以「沿用舊寫法」視為例外。
- 跨 Session、Workspace、Project、不同操作者，以及 Queue／Reverb 多次任務不會共用錯誤 actor／scope。
- Composer Container 不應因缺少 scalar context、無法解析的 interface 或 scoped 生命週期錯誤在執行時失敗。

### 行為與資料安全

- 保留 Session／CSRF、X-Workspace-ID、既有 RPC 路由、ok/data、DomainError、DTO 欄位及歷史。
- 保留租戶隔離、專案 ACL、撤權即生效、作者限制、revision、requestId 防重複及重放前重新授權。
- MySQL 寫入的 User → Workspace 鎖順序、交易擁有者、rollback、afterCommit／Reverb／Queue 語意不變。
- 預設測試仍使用隔離 SQLite；MySQL 交易、並行／死鎖與正式引擎行為使用獨立 MySQL 測試環境，不使用遠端現有業務 DB。
- 目前本機透過遠端 MySQL／Redis 運作；不清空共享 Redis、不打印帳密／Session、不在主要業務庫執行測試。
- DI 重構應保留或改善現有 SQL 次數，不得以更多查詢、更多物件建立與更大的 Workspace Payload 換取表面分層。getWorkspace 全量讀取問題仍**另開獨立效能工作**。

### PHPDoc

- 產生可稽核的全庫自有 PHP 檔案清單，統計應補註解的方法、已完成數、合理例外。
- 對照方法實際簽章檢查 @param 名稱／數量、@return 與 null／void／never 語意；泛型／array shape 不可憑空臆測。
- 中文說明可讀、反映現在行為；不保留過期註解、不單純複製函式名稱。
- 既有工具可用時執行 Pint、PHP 語法／型別檢查；若沒有 PHPStan 不要求為了單一重構任意安裝新的大型依賴，可先使用現有工具與測試。

## 7. 執行及交付要求（交給 Codex／Claude）

- 本文件是**完整實作需求**，不是已經完成的變更紀錄；使用者接受大範圍 Diff，但仍要求功能安全與測試證據。
- 依 TaskTracker 的 root AGENTS／backend AGENTS 規範，先查 TaskTracker 內既有相關工項；必要時建立或更新後端重構卡片，真實記錄範圍、狀態及驗收，不因單次 Commit 就把整件事結案。
- 先產生檔案／依賴掃描清單，再按上述階段實作；每階段保留可追蹤 Commit，不自行覆蓋使用者尚未提交的工作。
- 不要因大 Diff 就跳過 Review；至少讓另一個檢查流程核對 DI 生命週期、租戶隔離、例外、PHPDoc 型別與測試。使用者不常手動 Review 不等於可以忽略工程驗收。
- 最終報告需包含：實際修改檔案數、被替換的直接 new／Service Locator 數量、保留例外、PHPDoc 覆蓋比例、測試命令與結果、是否跑 MySQL 隔離測試、仍未處理的項目。
- 若實作中發現必要的 API／資料模型或授權契約變更，必須明確區分另行設計，不得暗中帶入本輪重構。

## 8. 既有文件參考

- AGENTS.md、backend/AGENTS.md
- skills/laravel-architecture/SKILL.md
- skills/laravel-architecture/references/layers-and-naming.md（現行規範已寫：依賴預設 Constructor Injection）
- skills/laravel-architecture/references/contracts-and-testing.md
- docs/development-skills.md、docs/backend-development.md、docs/refactoring-2026-11-06.md
- docs/remote-services-migration-2026-11-11.md
- docs/performance-improvements-2026-11-09.md（SQL 減量已完成，不代表 getWorkspace 全量設計已修復）

---

**完成定義：全自有後端 DI 與 PHPDoc 盤點清零（或具體列明少數合理例外），核心契約與真實引擎的安全回歸通過，且沒有把未完成項目誤報為完成。**
