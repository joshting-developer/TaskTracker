# 後端核心依賴鏈DI（TT-233，2026-11-12）

依[DI／PHPDoc重構規格](backend-di-phpdoc-refactor-plan.md)第3階段與[DI規範](../skills/tasktracker-development-standards/references/dependency-injection.md)。前置TT-232的WorkspaceContext與Factory邊界見[backend-context-boundaries](backend-context-boundaries-2026-11-12.md)。不改API路由、ok/data envelope、DomainError、DTO或資料模型。

## 依賴圖（之後）

```
Controller ──method injection──> ApiExecution
ApiExecution ──constructor──> AccountService, SmtpService, WorkspaceSession, AccountSettingsFactory,
                              WriteLocks, WorkspaceMemberships, WorkspaceService, WorkspaceServicesFactory, TodosFactory
WorkspaceService ──constructor──> WorkspaceServicesFactory, RequestReplay, DemoSeeder
TodosFactory ──constructor──> WorkspaceService          （Todos轉卡沿用RPC寫入入口）
DemoSeeder ──constructor──> WorkspaceServicesFactory     （loadProject）
WorkspaceServicesFactory ──constructor──> RecordStoreFactory, TimeReportRepository
WorkspaceServicesFactory::forSession(可信Session) ──> WorkspaceOperationServices（單次操作）
WorkspaceOperationServices ──按需建立──> ProjectAccess（共用一個）、WorkspaceOperationAccess、ProjectService、IssueService、
                                         TimeEntryService、ProjectBoards、ProjectMembers、WorkspaceQuery、WorkspaceTimeReport、ProjectSignals
```

建構子層級沒有循環。`DemoSeeder`的seed路徑需要`WorkspaceService`，而`WorkspaceService`建構時注入`DemoSeeder`（loadDemo），所以seed路徑在local／testing才延遲由Container取得（見例外）。

## 主要改變

- `WorkspaceService`改為無租戶狀態、可由Container解析：`execute(array $workspaceSession, string $operation, array $input)`，操作者與工作區是每次執行的參數，不放在建構子或singleton。
- `WorkspaceOperationServices`是單次操作的組裝邊界：
  - 同一操作共用一個限定Store與一個`ProjectAccess`（原本每個領域服務各new一個）。
  - 領域服務第一次使用才建立。`IssueService`只在帶`boardId`建卡時才建立`ProjectBoards`（以provider closure注入）。
  - 每次`forSession`都是新實例，不跨操作、請求或Queue任務保存。
- 領域服務（Project／Issue／TimeEntry／Boards／Members／WorkspaceQuery／WorkspaceOperationAccess）改由建構子接收`ProjectAccess`，不再自行new。
- `ApiExecution`的`WriteLocks`、`WorkspaceMemberships`、`WorkspaceService`、工時報表與Todos都改由注入或Factory取得。
- 交易擁有者（ApiExecution）、User→Workspace鎖順序、RequestReplay「先授權、比對簽章再重播」、rollback與afterCommit訊號都不變。

## 手動組裝數量（核心鏈）

計`new XxxService／Store／Access／…`與`app()`／`resolve()`服務定位：

| 檔案 | 之前 | 之後 |
|---|---:|---:|
| Http/ApiExecution | 7 | 0 |
| Services/WorkspaceService | 14 | 0 |
| IssueService／ProjectService／TimeEntryService／ProjectBoards／ProjectMembers | 6 | 0 |
| WorkspaceQuery／WorkspaceOperationAccess | 3 | 0（剩下1處是`WorkspacePermissions::resolve`靜態角色對照，不是Service Locator） |

建立動作集中在`app/Factories`（WorkspaceOperationServices、WorkspaceServicesFactory、TodosFactory、RecordStoreFactory、ProjectAccessFactory）。

## 保留例外

| 位置 | 原因 |
|---|---|
| `RecordStore`建構子new `RelationalRecords`／`LegacyRecordStore`，`forWorkspace()` new self | 兩者帶每個Store自己的資料列快取與workspace範圍，屬限定Store的內部組成；一般寫入只經RecordStoreFactory取得Store |
| `RelationalRecords`建構子new `RelationalChildren` | 同上，子表列快取依附單一Store |
| `DemoSeeder::actAs`延遲`app(WorkspaceService::class)` | 只在local／testing seed路徑；避免WorkspaceService⇄DemoSeeder建構子循環 |
| `RequestReplay`建構子預設`new ReceiptCodec` | 純設定值物件，Container解析時亦可替換 |

## 留給TT-234

`Todos`（自行new RecordStore／ProjectAccess×2／RequestReplay）、`AccountService`、`WorkspaceMemberships`、各升級／匯入服務與Console、`DemoSeeder`其餘new RecordStore／ProjectAccess，以及Query類別等其他協作者。

## 驗證

- `WorkspaceServiceContainerTest`（新增，4 tests）：
  - ApiExecution／WorkspaceService／TodosFactory／DemoSeeder可由Container解析且無循環，WorkspaceService不是共享實例。
  - 按需初始化：createProject只建`access, operationAccess, projects, signals`；建卡未帶看板不建`boards`，帶看板才建；工時只建`time`。
  - 以Container替換RequestReplay後生效，不改被測類別。
  - 不同使用者／工作區連續操作各得新服務組，Store工作區與身分正確，ProjectAccess不共用。
- 完整memory suite 100 passed（30個MySQL專用略過）；隔離MySQL完整suite 130 passed／5781 assertions。
- 隔離環境`tasktracker:seed-demo`成功；`browser-check`（11段）與`personal-query-check`（含待辦轉卡）通過。
