# TT-246 其餘領域寫入契約

2026-11-12。前置 TT-262 已交付建構子 DI 與嚴格資料層；本卡接續 TT-178 的 Issue／Time 寫入邊界，處理其他領域。沒有新增一套公開 API，也沒有搬動現用資料庫。

## 逐端點移轉矩陣

以下都是 POST；`/api/rpc/` 或 `/api/auth/` 加上表內操作。Controller → 專用 FormRequest → readonly Data → 共用 WorkspaceRpc／AccountSessionBoundary → 原有 Application Service／Repository。穩定協作者沿建構子注入。

| 前綴／操作 | Request／Data | 新結果與最新內容取得 |
| --- | --- | --- |
| rpc/createProject、updateProject、updateStatuses | ProjectWriteRequest／ProjectWriteData | `{id, revision}`；GET `/api/v1/projects/{id}` |
| rpc/createBoard、updateBoard、archiveBoard、restoreBoard | BoardWriteRequest／BoardWriteData | `{id, projectId, revision}`；GET `/api/v1/projects/{project}/boards/{id}`；封存後仍可唯讀查閱, restoreBoard（board.manage、expectedRevision、requestId）還原（TT-283）；create／update可帶 `description`（最多300字）, 更新時沒帶就保留原值（TT-309） |
| rpc/createTodo、updateTodo、completeTodo、reopenTodo、returnTodo、cancelTodo、convertTodo | TodoWriteRequest／TodoWriteData | 保留原有 `{id, revision}`；GET `/api/v1/todos/{id}`／列表分頁。轉卡依可見性從查詢取得連結 |
| rpc/readTodoNotification | TodoWriteRequest／TodoWriteData | 保留 `{id}`；未讀及列表按需重讀 |
| rpc/setProjectMember、removeProjectMember | ProjectMemberWriteRequest／ProjectMemberWriteData | 保留 `{projectId, revision}`；ACL metadata／成員分頁分責重讀 |
| rpc/updateProfile、updateWorkspace、setWorkspaceMemberRole | SettingsWriteRequest／SettingsWriteData | 保留 `{id, revision}`；本人資料或工作區設定／成員分頁重讀 |
| rpc/getProfile（相容讀取） | SettingsWriteRequest／SettingsWriteData | 既有本人 id／name／email／revision；雖與設定共用入口，不當成寫入或新增鎖 |
| rpc/inviteMember | WorkspaceWriteRequest／WorkspaceWriteData | 既有有界邀請資訊、一次性 token／連結；不擴大內容或新增假冪等承諾 |
| rpc/loadDemo | WorkspaceWriteRequest／WorkspaceWriteData | 原有 `{projectId}`；仍只限 local／testing，不回整個示範專案 |
| auth/register、login | AccountWriteRequest／AccountWriteData | 原有認證身分 DTO／Session 語意，不以資源寫入投影截斷登入資訊 |
| auth/workspaces、switchWorkspace、acceptWorkspaceInvite、invitation | AccountWriteRequest／AccountWriteData | 原有工作區清單／身分／邀請預覽；沿用資格與 Session lease 邊界，GET／切換並非普通資源寫入 |

28 個路由由同一組矩陣測試核對專用 Request。既有 Issue／Time 專用 Request／DTO、PAT 管理、公開 Bearer API 不重複改寫；current／logout、唯讀查詢、SMTP verify 與未知／退役 RPC 保留各自既有邊界，未把所有 POST 當成寫資料。

## 輸入與重播

- Request 共用受限 JSON 物件解析、大小限制與型別驗證；錯型別維持 `INVALID_INPUT`／DomainError、ok/data 與 HTTP 形狀。不是 Laravel 預設 422 驗證頁。
- 必填、格式、列存在與 permission、可授角色、商業狀態與 revision 仍由交易內原有用例判斷；不可見資源仍 404。傳輸型別檢查先於用例授權，與既有 Issue／Time 邊界一致。
- readonly Data 有明確領域欄位；原始 payload 完整保留，包含未知欄位、空白、null／未提供差異、陣列順序。型別化視圖不拿來改寫簽章；舊領域服務仍在明確的相容入口接原始 array，不宣稱全庫方法都已改成 DTO 參數。
- 不用 `validated()` 丟掉未知欄位，不擴大 mass assignment allowlist。body 的 operation／actor／role 不取代固定路由與伺服器身分。
- 新 Project／Board 結果在第一次寫入 closure 內投影，再由 RequestReplay 保存。舊 receipt 的原結果不重寫、不補成新資源、不在重播時重跑業務。每次重播仍先重新授權，不能因 receipt 存在跳過撤權。
- 確認成功後目前畫面按需重讀；API client 回原寫入結果，不隱藏自動 GET 或以最新 revision 偷換未保存草稿基準。ProjectDialog 額外傳遞已送出的名稱，只供成功訊息；App 不把部分結果塞回 Project／Board read model。

## 資料存取審查

| 要求 | 本卡範圍 |
| --- | --- |
| 欄位／scope | 寫入只回定位與 revision；認證及邀請保留必要固定資訊，不含歷史、留言或全集 |
| 上限／排序／SQL | 不新增列表、資料查詢或輪詢；後續 GET 沿既有 SQL 範圍、穩定排序／分頁／Project ACL |
| Workspace 全量 | 正常表單不因此改回 getWorkspace；原始測試 fixture 使用明確 GET 取得資源，不偽造完整寫入回應 |
| 按需資料 | 設定歷史、描述與看板收錄清單不存進新寫入結果；最新屬性用單資源 GET |
| 成長／頻率 | Project 歷史／Board 收錄增加時，新 HTTP／receipt result 固定大小；無新增背景請求。領域保存成本仍依既有 Repository，沒有宣稱整個寫入都變 O(1) |
| 範圍證據 | 12張卡的看板、8次Project修改、新result小於120 bytes；驗單資源GET、舊receipt不覆蓋最新值及撤權403 |
| 限制 | 不據此宣稱其他效能待辦完成、遠端p95或全服務DTO化；既有舊receipt可能仍大，保留原結果是相容要求 |

## 驗證

使用獨立 `tasktracker-codex-refactor`／13069／tasktracker_test；現用資料庫未 seed／reset。記錄 memory、原生 MySQL、瀏覽器與前端測試於下方完成紀錄。公開 OpenAPI 0.9.0 的既有19個 operation 未變，沒有把 Session RPC 誤加成純 Bearer。

### 完成紀錄

- memory：155 passed、66 skipped、7,855 assertions，含路由矩陣／DomainError／DTO原始值／新結果容量／舊receipt／撤權。skip包含隔離MySQL與外部環境條件，未算成memory通過。
- 原生 MySQL 8.4.13：65項全部完成驗證。首次完整執行64項通過、1項報表測試仍把createProject結果當完整metadata；修正fixture於量測前明確GET後，單獨重測該項通過（74 assertions）。新28路由矩陣及寫入邊界三項在兩引擎皆通過。512MB測試限制，未拿memory結果取代MySQL。
- 完整真Laravel瀏覽器流程通過，另以正式build補驗帳號／工作區設定、改名／revision衝突／草稿、固定角色、已註冊帳號邀請、多工作區切換與另一分頁舊請求409。舊設定測試對帳號選單及設定入口的假設已依現有PAT／側欄入口更新，未更改產品選單。
- `npm run build`、`test:api`與`test:composables`通過；API client斷言固定寫入結果、不隱藏GET且requestId／原始payload不改寫。
- PHPDoc required 258檔／250類別／846方法、0個缺漏檔案；既有reference歷史缺漏不當成全面清零。Pint限定異動檔與diff／新文件連結檢查通過。
- 截圖與build只在忽略目錄，未提交、未正式部署、未推遠端；獨立測試服務用畢清理，不影響現用Compose或其他Agent的測試環境。
