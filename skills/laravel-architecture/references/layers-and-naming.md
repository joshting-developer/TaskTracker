# 層次、回傳與命名

## 職責與依賴

| 層 | 必須負責 | 不可負責 |
| --- | --- | --- |
| Controller | 選擇用例、傳入可信actor與驗證結果、HTTP status／Resource | 查DB、拼query、商業規則、交易流程、手動大量序列化 |
| FormRequest | 輸入格式／型別／範圍與allowlist；結構化輸入轉typed Data DTO | 信任前端身分／角色、用全域exists洩露其他租戶資源、以驗證取代交易內重查 |
| Service | 領域流程協調、跨步驟規則、授權檢查時機、交易／重試與副作用順序 | 讀HTTP request／session、組HTTP response、包含無關領域操作的萬用入口 |
| Action | 明確的單一用例，公開handle；只接業務參數／DTO，可被Controller、Job或Service共用 | 為每個Service方法複製一個原樣轉呼叫類別、反向呼叫協調自己的Service |
| Repository | 有範圍的查詢、保存、刪除、鎖定、批次載入、聚合；明確回傳型別 | 判斷可否結案／轉卡／授權、取得Session、決定跨Repository交易、組Response、寄信 |
| Query（選用） | 組合可重用查詢條件與排序；最後由受範圍限制的資料入口執行 | 當成另一個Service、掩藏資料寫入或授權決策 |
| Model | Eloquent關聯、casts、scopes及實體自身局部不變條件 | HTTP依賴、跨租戶流程、跨實體大型協調或在accessor中偷發外部請求 |
| Resource／Collection | 對已授權資料明確列欄位、格式與已載入關聯 | 查DB／lazy loading、重新判權、交易、載入未授權關聯 |

依賴往資料與領域內部走，不反向依賴HTTP。Service／Action互用須形成單向無環圖；不要先規定所有流程必經Service與Action。相依預設constructor injection；只有實際存在多實作、外部系統邊界或明確替換需求才建立interface並在Provider綁定，不為可mock單一class而建interface。

DI細節遵循[依賴注入與生命週期](../../tasktracker-development-standards/references/dependency-injection.md)，包含Context方法參數、Factory邊界、singleton／scoped限制及合理new例外；不在此重複維護。

## 簡單CRUD與Repository邊界

只有同時滿足「單一Model、固定workspace scope、簡單條件、沒有重複的複雜查詢／聚合、沒有既有適用Repository」時，Service／Action可直接使用Eloquent。權限、版本、防重複提交與交易仍須經既有共同入口，不因CRUD略過。條件不成立就使用或新增有實際用途的Repository／Query，不允許在Controller直接操作Model作例外。

Repository的workspace／project where、複合key與鎖定是資料隔離約束，不代表已通過permission。Service／Policy依可信actor與有效permission決定資格，Repository不得比較roleId／名稱放行。選出「未逾期」或「某狀態」是查詢；決定「這個人能不能關閉Epic」是業務規則。

## Repository回傳契約

- 單筆：明確的`Project`、`?Project`；查不到時採nullable或固定NotFound契約，名稱／型別須一致，不混用false／空array／Model。
- 多筆：Eloquent查詢回`Illuminate\Database\Eloquent\Collection<int, Project>`；分頁回`LengthAwarePaginator`（需要總數）或`Paginator`（不需要總數），同一方法不可依參數切成不同形狀。
- 聚合／跨來源讀取：回具名、不可變typed DTO，例如`MemberTimeTotalsData`；必要時`Collection<int, MemberTimeTotalsData>`。這是資料投影，不含API的ok/data、翻譯、權限決策或顯示格式。
- 寫入：create／update回已保存Model；delete回void或明確數量／bool並固定語意。方法宣告回傳型別，以PHPDoc補Collection泛型。
- 不把尚未執行的Builder傳給Controller或Resource。Query內部可組Builder，Service需要資料時由Repository／Query明確執行。
- 既有RecordStore回array是遷移相容邊界，不是新Repository的預設；不可趁新skill安裝批次改DTO或換資料儲存。

## Request、DTO與Resource

有body／query業務輸入的新端點使用專用FormRequest，`validated()` allowlist後以`toDto()`轉readonly Data DTO；純路徑ID／無輸入端點可沿用共用傳輸驗證，不建空Request。可信actor由後端取得並分開傳入，不接受DTO中的actorId／workspaceId作授權依據。

多欄位結構化業務輸入用typed DTO；單ID查詢可用明確scalar參數。DTO不接Request／Session、不查DB，不為同一欄位在每層重造一份DTO。不要在Service公開方法接整個Request或無定義的任意payload。

所有業務JSON成功回應經JsonResource／ResourceCollection或現有ApiResponse，禁止直接return Model／Collection自動序列化。既有`{ok,data}` envelope由ApiResponse維持；複雜實體輸出使用專用Resource，不強迫每個純ack產生新Resource。health、CSRF、204、stream／download、websocket auth按協定回應，是明確例外；錯誤由集中例外處理，不用Resource包exception。

Resource只能呈現已載入且已授權的資料，使用whenLoaded等機制避免N+1；缺欄位／null／空陣列／分頁meta必須穩定。不能直接抄ECC示例的success/error/meta取代現有ok/data。

## 命名與程式風格

- PHP類別／檔案PascalCase，方法／變數camelCase，常數UPPER_SNAKE_CASE；DB table／column為snake_case，table預設複數，migration為timestamp＋snake_case意圖並採anonymous class（ECC）。
- 新資源Controller使用複數，例如`ProjectsController`，方法採index／store／show／update／destroy；既有RPC operation與既有class不為改名而破壞契約。
- FormRequest：`StoreProjectRequest`／`UpdateProjectRequest`；Service：`ProjectService`；Action：`CreateProjectAction::handle`；Model：`Project`；Repository：`ProjectRepository`；Resource：`ProjectResource`／`ProjectCollection`；DTO：`CreateProjectData`；Query：`ProjectQuery`。
- 既有API欄位camelCase、DB snake_case，映射集中於資料／輸出邊界；不把ECC範例snake_case輸入當成改API指令。
- 明確type hints／return types，依ECC使用Laravel慣例與constructor injection；保留4格縮排與Pint可接受風格。不為安裝skill加formatter／套件或整庫格式化。
- 複雜條件用語意變數或private方法，展開流程；避免密集單行、無意義縮寫及需要心算的布林技巧。
- 自有後端class／function註解遵循[PHPDoc規範](../../tasktracker-development-standards/references/phpdoc.md)：繁中具體用途、param／return與實際簽章及精確型別一致。全面整理依明確任務範圍執行，不因讀取skill擴大改動。
- PHPUnit沿用專案`test_snake_case_describes_behavior`及`*Test.php`；Unit置`tests/Unit`，HTTP／DB整合置`tests/Feature`。不沿用舊規範`action_Result_Test_case`硬性格式，不為風格重命名既有測試。
