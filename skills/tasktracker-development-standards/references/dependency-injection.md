# 後端DI與生命週期

適用Service、Repository、Action、Query、Authorization、Controller、Job、Listener及其他自有協作物件。依[Laravel Architecture](../../laravel-architecture/SKILL.md)決定分層，本文件規定依賴如何組裝，不另創一套Service／Action／Repository層次。

## 穩定依賴與執行期資料

- 穩定協作者預設Laravel Container constructor injection；HTTP入口適合時可method injection。Controller只協調Request／用例／Resource；Repository不取得HTTP Session、不替Service決定業務與授權流程。
- 不在業務流程散落new Service／Repository、app()／resolve()或手動組裝相同協作者。可以自動解析的concrete class直接type-hint，不必每個類別都Provider bind。
- 有多實作、外部郵件／通知／遠端API／Clock等明確替換邊界才引入interface／adapter，不為可mock單一類別而製造interface，不為每個Model配空Repository／Action。
- Actor、Workspace／Project、permission等執行期資料不是穩定依賴。優先以型別化可信WorkspaceContext作方法參數；context來源必須在伺服器交易內重驗，不能由前端任意指定。
- 需要按Workspace建立Store或其他帶scope物件時，用責任明確的StoreFactory／ServiceFactory集中在組裝邊界處理；Factory不得成為可任意取服務的第二個Service Locator，歷史Store模式不可誤用於一般寫入。
- 不把帶actor／Workspace狀態的Service註冊為singleton。不濫用scoped；確實需要時記錄生命週期，驗證HTTP、Queue／Reverb連續任務不保留前一個操作者或租戶。

## 合理例外

純Value Object、DTO、Entity、Exception、Query Builder值物件及明確Factory內的new可以保留。框架bootstrap、特殊CLI／測試若確需app()／resolve()，記錄位置、原因、生命週期及測試；「原本就這樣」不是理由。不要只把new搬到另一個業務類別就稱完成DI。

## 依賴圖與驗收

- 先畫出當前Route→Controller→Application Service／ApiExecution→領域Service→Repository的實際依賴，辨識循環；不能用Service Locator隱藏A→B→A。
- 單一操作按需解析必要服務，比較前後初始化數，不為更新一張卡無條件初始化Project／Time／Board全部服務。
- transaction owner、RequestReplay、User→Workspace鎖、rollback、afterCommit、Queue／Reverb時序不變；DI不新增巢狀交易或打散原子性。
- 測試容器能解析真正入口、替換collaborator不用改被測原始碼；驗證scalar context／interface binding與scope生命週期，以及不同Session／Actor／Workspace／Project、長駐任務連續執行的隔離。
- 全庫任務須盤點所有自有PHP：app、seeders、Console、tools、tests支援類別及其他自有程式。Migration／config／測試既有不一致註解亦須檢視，vendor／generated／單純宣告不造無意義docblock。保留檔案清單、new／locator數量、已修與合理例外；不得只處理核心鏈就宣稱全庫完成。

此規範不自動授權全庫修改；明確執行全面重構時，施工階段與交付數據依[原任務規格](../../../docs/backend-di-phpdoc-refactor-plan.md)。
