# 後端嚴格分層與建構子注入盤點（TT-262）

2026-11-12。使用者先要求盤點Controller DI，討論後確認擴大為嚴格 **Controller → Service → Repository**，包含簡單CRUD。這輪只建卡／掃描／補skill，沒有修改產品程式。TT-170後的AI/API開發仍暫停。

## 已確認規範與缺口

原DI reference允許HTTP適合時method injection，Architecture還允許簡單CRUD由Service直接用Eloquent；原規範不能保證使用者這次要求的一致性。已更新既有TaskTracker Development Standards與Laravel Architecture（專案本地skill），新增Architecture references/strict-layering.md作嚴格分層單一來源，不再造重複skill或修改vendor。

Controller穩定Service／邊界、Service穩定Repository由建構子注入；Request／路由／當次Context屬方法輸入。Repository只做資料存取，不替Service判商業規則或角色權限；可依領域承擔多個實體，不要求每張表配空殼Repository。交易邊界／Queue序列化／CLI例外有明確限制。

## Controller掃描

掃描28個Controller，21個有穩定服務方法參數，共84個公開action。以下列實際action與協作者；原本由Container提供，屬風格／分責新標準的待遷移，不描述成完全沒有DI。

| Controller | action數 | 方法參數中的穩定協作者 |
| --- | ---: | --- |
| AccountSettingsController.php | 1 | WorkspaceRpc（1） |
| ApiController.php | 1 | WorkspaceRpc（1） |
| AuthController.php | 5 | AccountSessionBoundary（5） |
| BoardController.php | 3 | WorkspaceRpc（3） |
| Integrations/PublicQueriesController.php | 8 | ProjectReadService（4）、IssueReadService（3）、IssueChangeService（1） |
| IssueController.php | 3 | WorkspaceRpc（3） |
| ProjectController.php | 3 | WorkspaceRpc（3） |
| ProjectMemberController.php | 3 | WorkspaceRpc（3） |
| Queries/BoardsController.php | 4 | WorkspaceTransaction（4）、BoardReadService（4） |
| Queries/IssuesController.php | 8 | WorkspaceTransaction（8）、IssueReadService（8） |
| Queries/ProjectMembersController.php | 4 | WorkspaceTransaction（4）、ProjectAclReadService（4） |
| Queries/ProjectNotificationsController.php | 2 | WorkspaceTransaction（2）、ProjectNotificationReadService（2） |
| Queries/ProjectTimeController.php | 2 | WorkspaceTransaction（2）、ProjectTimeReadService（2） |
| Queries/ProjectsController.php | 6 | WorkspaceTransaction（6）、ProjectReadService（6） |
| Queries/TodosController.php | 8 | WorkspaceTransaction（8）、TodoReadService（8） |
| Queries/WorkspaceSettingsController.php | 3 | WorkspaceTransaction（3）、WorkspaceSettingsReadService（3） |
| RealtimeController.php | 2 | RealtimeSession（2） |
| SmtpController.php | 2 | WorkspaceRpc（2） |
| TimeEntryController.php | 4 | WorkspaceRpc（4） |
| TodoController.php | 9 | WorkspaceRpc（9） |
| WorkspaceController.php | 3 | WorkspaceRpc（3） |

Queries/IssuesController八個action重複WorkspaceTransaction＋IssueReadService，適合constructor；PublicQueriesController混合Project／Issue／Changes依賴，應先評估依用例拆Controller，保留原路由與契約，不為統一DI無條件初始化所有服務。

## Service與其他入口

掃描34個Service PHP檔，靜態檢索並閱讀以下5個直接Model／DB存取候選；這不是完整AST或所有instance／lazy query證明，不能說剩下29個一定合規。

| 檔案 | 可重查的直接存取位置 | 分工 |
| --- | --- | --- |
| backend/app/Services/AccountService.php | 57、58、89、115、151、194 | 正常應用流程，資料查詢／保存待移至Repository並建構子注入 |
| backend/app/Services/Integrations/IntegrationAudits.php | 24、25、42、43 | 正常應用流程，資料查詢／保存待移至Repository並建構子注入 |
| backend/app/Services/Integrations/PersonalTokens.php | 50、59、62、104、105 | 正常應用流程，資料查詢／保存待移至Repository並建構子注入 |
| backend/app/Services/ProjectSignals.php | 47 | 正常應用流程，資料查詢／保存待移至Repository並建構子注入 |
| backend/app/Services/RelationalDataMigration.php | 19、62、64、77、92、98 | 歷史匯入／資料升級工具，需明確入口與例外，不當正常Service範本 |

具體例：AccountService直接查／建User與Workspace資格；PersonalTokens自行查PAT／User／Workspace並save；IntegrationAudits直接create及組查詢；ProjectSignals直接查Project。後續還須查`$model->save/update/delete/refresh`、relationship lazy load、Builder外洩與HTTP認證adapter的自有表查詢，不能只移除Model imports。

另擴掃app目錄193個PHP檔的公開非建構子參數，除Controller外還見：

- ImportLegacy.handle(RecordStoreFactory)、ReceiptCodecConvert.handle(ReceiptCodec)：Artisan框架方法注入，依例外記錄與測試，不機械搬動。
- RequestReplay.execute(RecordStore)：Factory產生的當次Workspace資料入口，不是無租戶狀態的穩定依賴；後續核對scope與Repository使用契約，不能固定到共享建構子。
- Queue Job須避免把服務序列化進建構子；框架支援的handle注入可保留，不能以此豁免一般HTTP action。

## 追蹤與驗收

TT-262收於效能改進（架構品質），open未指派；已記完整範圍、穩定依賴／執行期輸入界線、公開API與交易安全保留、架構防退化及容器／MySQL／HTTP測試要求。新規範不是TT-234／236／249重新開發的假完成，也不是自動開始TT-262。

靜態快照僅為本次盤點：識別namespace/import/public method參數及Service明確DB／Model呼叫，再人工核對代表性入口；未解析任意trait／動態呼叫／執行期框架注入，行號會隨開發改變。既有工作區有他人的Controller／WorkspaceService／DTO／測試變更，這輪保留，正式施工前需重掃並協調，不覆蓋或收進本輪提交。

## 本輪驗證

只檢查skill frontmatter／symlink／本地相對連結、取消舊寬泛例外及簡單CRUD跳層條文、盤點檔案／方法數與卡片分類狀態、git diff／staged範圍。無產品程式修改，不跑業務測試、不重啟Docker、不seed／reset資料。
