# 嚴格分層與資料存取（2026-11-12）

使用者已確認採嚴格版：**Controller → Service → Repository → Model／資料來源**。簡單CRUD也適用，不再保留「Service可直接使用Eloquent」的例外。這是本專案在ECC Laravel基礎上的明確選擇，不修改vendor原文；規範成立不表示舊程式已全部遷移。

## 各層與依賴方向

| 層 | 職責 | 禁止 |
| --- | --- | --- |
| Controller | Request／路由輸入、呼叫Service用例、Resource及HTTP回應 | 直接注入／呼叫Repository、Model查詢、SQL、商業規則 |
| Service | 協調規則、permission與scope、交易時機、revision／requestId、Repository及副作用 | Eloquent查詢／保存／刪除、DB資料查詢、向Controller或Request取得可信身分 |
| Repository | 用可信scope查詢、鎖列、保存、刪除、批次／分頁與聚合，回明確Model／Collection／DTO | 以角色決定能否操作、商業狀態流程、組HTTP回應、寄信、任意跨用例commit |
| Model | 關聯、casts、必要scopes與不發I/O的實體行為；由Repository使用 | HTTP／Session、跨實體大型流程、偷偷查其他資源或開交易 |

- Controller透過建構子注入Service；Service透過建構子注入Repository。兩層的穩定Factory／基礎設施協作者亦由建構子提供，不用方法參數、`new`、`app()`／`resolve()`或萬用Factory隱藏依賴。
- Request／FormRequest、路由ID、可信WorkspaceContext、readonly DTO、已載入Model及當次scope不是穩定協作者，按其用途傳入方法。當次身分必須在正確交易內重驗，不提前固定在建構子。
- Service可以讀已載入Model屬性、Collection／DTO並做純計算；不得呼叫`Model::query/where/find/create`、`$model->save/update/delete/refresh`或未載入relationship導致lazy query，也不得接Repository回傳的Builder再組SQL／執行。保存欄位由Service依規則算出，再交Repository處理。
- 純計算、cursor編碼、格式轉換等不做資料存取的Service不新增空Repository；一旦讀寫業務資料就必經Repository，包含簡單CRUD。
- Repository命名依領域／資源／查詢用例，不強制每個Model或每張表都有一個Repository；可由既有Repository承擔多個有關聯實體的資料存取。不因「只是一行CRUD」跳層，也不建立沒有用途的interface／Action／轉呼叫包裝層。
- Query物件只組可重用條件，交Repository套scope並執行；Controller／Service不能繞Repository直接執行Query。Action為Service的可選內部分工，不取代Controller必經的Service入口；有資料存取時仍constructor-inject Repository，不另開Model直通。
- Model型別可出現在Service的參數／回傳或PHPDoc，不等於違規查DB；掃描必須區分型別／純值方法與實際I/O，不以`use App\\Models`數量當違規數。

## 交易與框架邊界

- 一個用例只有一個明確transaction owner，沿既有Application Service或HTTP交易邊界協調，不讓每個Repository自行包跨步驟交易。Service使用交易／afterCommit基礎設施不等於直接查業務表；讀寫／鎖列的SQL本身仍經資料入口。完整規則見[交易與測試](contracts-and-testing.md)。
- WorkspaceTransaction、Bearer認證、Session lease、User→Workspace鎖、receipt重播前授權、rollback／afterCommit不能為符合箭頭而刪掉。HTTP／Auth adapter可以接框架認證能力；其中自有業務表查詢仍列入遷移，不能把SQL搬到adapter或Factory當完成分層。
- Queue的`handle(Service $service)`、Artisan框架handle、Middleware／Event輸入等生命週期例外依[DI規範](../../tasktracker-development-standards/references/dependency-injection.md)。尤其不可為統一constructor而將Service或DB連線序列化到Job。
- migration／seeder／歷史匯入／診斷等非正常HTTP業務流程可有工具專屬資料存取，但需具體記錄入口、隔離與理由。放在Services目錄不會自動成為例外；可遷至明確的工具／migration資料入口，不用這個理由放寬一般Service。
- `RecordStore`等既有Repository相容邊界仍須有scope及明確來源；Factory只組裝當次scope，不承擔查詢或業務流程。帶Workspace狀態物件的既有方法參數須逐項設計，不機械塞入共享建構子。新功能不複製相容債務。

## 新增／修改與驗收

1. 先列Route → Controller → Service → Repository的實際呼叫與交易owner，分類穩定依賴與當次資料；不得只檢查類別名稱。
2. 新增／修改用例依本規範；舊碼全面遷移依TT-262逐階段處理。本輪補skill／建卡不代表功能已重構。
3. 盤點Service的靜態查詢、DB呼叫、instance寫入、relationship lazy load與Builder外洩；Controller的穩定服務方法參數、Repository直通或locator均須檢查。
4. 架構防退化檢查需能擋新違規並列舊債清單；既有規則未全遷移時不得將檢查結果假稱全庫合規。不能只比SQL次數、回傳值或一個regex。
5. 容器解析／替換Repository、相同HTTP契約、交易原子性、permission／scope、撤權、版本／冪等、資料範圍與初始化成本按風險驗證；真MySQL與既有公開API／瀏覽器流程保留。Repository真實SQL用隔離DB測試，不mock Builder驗自己。
