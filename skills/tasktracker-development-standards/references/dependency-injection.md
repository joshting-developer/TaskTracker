# 後端DI與生命週期

適用Service、Repository、Action、Query、Authorization、Controller、Job、Listener及其他自有協作物件。依[Laravel Architecture](../../laravel-architecture/SKILL.md)決定分層，本文件規定依賴如何組裝，不另創一套Service／Action／Repository層次。

## 穩定依賴與執行期資料

- 穩定協作者使用Laravel Container constructor injection。2026-11-12使用者進一步確認：**Controller的穩定協作者必須放建構子，HTTP action不得以method injection注入Service／Repository／Factory／交易邊界等服務**；即使只有單一action也相同。Request、路由值與當次Context等輸入依下節區分。Controller只協調Request／用例／Resource；Repository不取得HTTP Session、不替Service決定業務與授權流程。
- Service有資料存取需求時必須以建構子注入Repository，所有業務資料存取（含簡單CRUD）經Repository；不得在Service直接用Eloquent／DB查表或保存。責任與交易／框架例外以[嚴格分層](../../laravel-architecture/references/strict-layering.md)為單一來源，不強制每個Model一個Repository。
- 不在業務流程散落new Service／Repository、app()／resolve()或手動組裝相同協作者。可以自動解析的concrete class直接type-hint，不必每個類別都Provider bind。
- 有多實作、外部郵件／通知／遠端API／Clock等明確替換邊界才引入interface／adapter，不為可mock單一類別而製造interface，不為每個Model配空Repository／Action。
- Actor、Workspace／Project、permission等執行期資料不是穩定依賴。優先以型別化可信WorkspaceContext作方法參數；context來源必須在伺服器交易內重驗，不能由前端任意指定。
- 需要按Workspace建立Store或其他帶scope物件時，用責任明確的StoreFactory／ServiceFactory集中在組裝邊界處理；Factory不得成為可任意取服務的第二個Service Locator，歷史Store模式不可誤用於一般寫入。
- 不把帶actor／Workspace狀態的Service註冊為singleton。不濫用scoped；確實需要時記錄生命週期，驗證HTTP、Queue／Reverb連續任務不保留前一個操作者或租戶。

## Controller建構子注入（2026-11-12收斂）

| 參數種類 | 正確位置／理由 |
| --- | --- |
| Controller的Service／WorkspaceTransaction等HTTP邊界；Service的Repository／Factory | 各自類別的建構子，由Container提供；action不重複宣告協作者，Controller也不得直接注入Repository跳過Service |
| Request／FormRequest | action參數；每次HTTP輸入與驗證生命週期不放Controller屬性或建構子 |
| 路由ID、路由綁定Model／Enum | action參數；屬當次資源選擇，仍須驗permission及租戶範圍，不代表自動授權 |
| WorkspaceContext、已驗證DTO、按Workspace建立的Store | 用例方法參數／明確Factory組裝邊界；不是供Container跨請求保存的穩定身分依賴 |

- 同一協作者在多個action反覆出現時，統一移至建構子；不可只改第一個action或留下新舊混用。
- 同一Controller的各action依賴不同領域服務時，先檢查是否應依用例／資源拆Controller；保持原HTTP路由／method及契約。不得用「按需解析」保留action service injection，也不得一口氣把Project／Issue／Time／SMTP全部塞入同一建構子。
- 不以`app()`／`resolve()`、萬用Factory、Controller方法內new或全域靜態存取替代建構子注入。既有framework facade／純工具的使用依其原有規則審查，不因本節要求全庫改寫。
- 建構子只組裝穩定協作者，不讀Request／Session、不驗actor或租戶、不開始交易、不執行查詢或外部I/O。當次資格仍在交易邊界的方法內重驗，不能把WorkspaceContext固定在建構時。
- review逐項列Controller／action／依賴種類，排除Request、路由輸入及具體框架例外；不能用「已由Container解析」作HTTP方法注入的豁免。交付須有容器可解析／可替換依賴、HTTP授權與租戶隔離、初始化範圍、PHPDoc與架構防退化檢查。
- 規範生效不代表既有action已改寫；2026-11-12盤點及待實作追蹤為TT-262，見[盤點報告](../../../docs/controller-constructor-injection-review-2026-11-12.md)。

### 框架生命週期例外

- Laravel Queue Job的可序列化建構子只存工作資料；執行期服務使用框架支援的`handle(Service $service)`，避免把Service、連線或帶租戶狀態的物件序列化進工作。Artisan Command的`handle()`依Laravel框架方法注入亦可保留。需記錄具體位置、框架契約與可替換測試，不擴張為HTTP action的例外。
- Middleware的`handle(Request $request, Closure $next)`、Listener的當次Event參數屬框架輸入，不是可機械移至constructor的穩定協作者；其他服務仍依其實際框架生命週期組裝，不能假定任意方法都支援容器注入。
- `RequestReplay.execute(RecordStore $store, ...)`等接收當次Workspace限定物件，是明確的執行期scope參數；必須核對來源由Factory限定且不跨租戶保存，不因參數型別是Repository便移到共享建構子。

## 合理例外

純Value Object、DTO、Entity、Exception、Query Builder值物件及明確Factory內的new可以保留。框架bootstrap、特殊CLI／測試若確需app()／resolve()，記錄位置、原因、生命週期及測試；「原本就這樣」不是理由。不要只把new搬到另一個業務類別就稱完成DI。

## 依賴圖與驗收

- 先畫出當前Route→Controller→Application Service／ApiExecution→領域Service→Repository的實際依賴，辨識循環；不能用Service Locator隱藏A→B→A。
- 單一操作按需解析必要服務，比較前後初始化數，不為更新一張卡無條件初始化Project／Time／Board全部服務。
- transaction owner、RequestReplay、User→Workspace鎖、rollback、afterCommit、Queue／Reverb時序不變；DI不新增巢狀交易或打散原子性。
- 測試容器能解析真正入口、替換collaborator不用改被測原始碼；驗證scalar context／interface binding與scope生命週期，以及不同Session／Actor／Workspace／Project、長駐任務連續執行的隔離。
- 全庫任務須盤點所有自有PHP：app、seeders、Console、tools、tests支援類別及其他自有程式。Migration／config／測試既有不一致註解亦須檢視，vendor／generated／單純宣告不造無意義docblock。保留檔案清單、new／locator數量、已修與合理例外；不得只處理核心鏈就宣稱全庫完成。

此規範不自動授權全庫修改；明確執行全面重構時，施工階段與交付數據依[原任務規格](../../../docs/backend-di-phpdoc-refactor-plan.md)。
