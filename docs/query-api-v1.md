# 分責查詢 API v1

2026-11-11。先以相同Laravel Session與CSRF可信邊界使用；尚未提供PAT／第三方認證。服務不依賴HTTP，可供後續Token能力交集的適配器沿用，不能僅加入Token就繞過Project權限。舊RPC相容保留，前端將逐頁遷移，不能把新增端點當作已移除全量刷新。

所有回應沿用`{ok,data}`，工作區由Session選定，建議帶`X-Workspace-ID`以檢查跨分頁切換。不相信query/body自稱的actor／workspace／role。

| GET | 職責 |
| --- | --- |
| `/api/v1/projects` | 已授權Project摘要，搜尋／分頁，不帶卡片或歷史 |
| `/api/v1/projects/{project}` | 單Project屬性／狀態及本人的有效capabilities |
| `/api/v1/projects/{project}/issues` | SQL條件、排序及有界列表摘要，不帶描述／留言／歷史 |
| `/api/v1/projects/{project}/issues/{issue}` | 單卡描述與屬性，不自動附帶活動全集 |
| `/api/v1/projects/{project}/issue-options` | 有界候選kind=issue／epic／parent、exclude與文字搜尋；最小參照，不含描述／活動／工時 |
| `/api/v1/projects/{project}/issues/{issue}/children` | Epic所屬卡或直接子任務的摘要分頁 |
| `/api/v1/projects/{project}/issues/{issue}/comments` | 指定卡片留言分頁 |
| `/api/v1/projects/{project}/issues/{issue}/history` | 指定卡片異動分頁 |
| `/api/v1/projects/{project}/issues/{issue}/time` | 有效工時分頁；Epic含本身與所屬卡，按本人／團隊資格限定 |
| `/api/v1/projects/{project}/boards` | 看板摘要搜尋分頁及獨立defaultBoard，不附卡片內容；每列`issueCount`與`closedCount`（狀態type＝closed）在同SQL以相同收錄／Epic展開去重計算（TT-248）。`archived=1` 改列已封存看板, 依封存時間新到舊；每列多回 `archivedAt`（使用中為null）（TT-283）及最多300字的 `description` 簡述（TT-309） |
| `/api/v1/projects/{project}/boards/{board}` | 單看板屬性（含`issueCount`／`closedCount`、`archivedAt`、`description`）及明確收錄ID，上限1000；default為虛擬看板。已封存看板也讀得到（唯讀）, `issues`／`version` 同樣可查（TT-283） |
| `/api/v1/projects/{project}/boards/{board}/issues` | SQL套看板範圍＋Epic動態展開去重後的卡片摘要分頁 |
| `/api/v1/projects/{project}/boards/{board}/version` | 看板輕量變更檢查：只回`{projectId, boardId, version}`不透明版本，見下節 |
| `/api/v1/projects/{project}/member-options` | 同Project有效成員id/name選項，分頁／搜尋，不含email或歷史 |

登入身分沿用`POST /api/auth/current`，不是另做一個大型bootstrap。專案與任務查詢在相同交易內重新驗證帳號、Workspace與permission＋scope；不可見資源404，已知資源缺少操作權403，工作區變更409。Resource僅序列化已預載欄位，不發lazy query。

列表輸入：`page`（1起）、`perPage`（預設20，上限100）、`search`（最多200字）。任務另支援精準`identifier`（AI／工具不必模糊搜尋編號）、`state[]`、`priority`、`type`、`assignee`、`epicId`（含unlinked）、`scope=all|mine`、`condition=overdue|unassigned`與`sort=updated|created|priority|due`與`direction=asc|desc`（TT-306, 省略方向時更新／建立新到舊、優先級緊急到低、到期日近到遠；到期日反向時沒填的排最前）。搜尋字詞中的`%`／`_`以文字處理，排序有穩定tie-breaker。

分頁形狀：`data.items`、`page`、`perPage`、`total`、`lastPage`。允許頁碼超出最後一頁回空items；不把新查詢任意改寫為未授權的全域exists檢查。

寫入暫沿用相容RPC及原有Form／revision／requestId契約；本輪不複製第二套業務實作。列表摘要不得直接當編輯基準，開編輯器前須讀單卡詳細及最新revision；留言／歷史另行分頁載入。

本機tools/tasktracker.py的list改逐頁摘要，status／comment用identifier精準定位取得ID與revision，不為寫入載入任務描述／活動；只有初次import／歷史verify保留明確使用相容Workspace快照，不能以初始manifest蓋掉後來進度。

2026-11-12契約修正：使用者已授權調整原API資料契約。舊RPC僅是前端／工具移轉中的暫時接口，不承諾永久保留整包Workspace；移轉後須退役過量入口並修改依賴它的測試，不可只新增v1而放任舊預設載入。安全與版本／冪等語意維持。資料存取審查見[審查紀錄](data-access-review-2026-11-12.md)。

Project授權僅讀指定ID或當頁ID（最多100筆），可見範圍在SQL分頁前依有效permission映射篩選。單Project回應不附全員memberIds；成員選項由獨立member-options端點提供，不能宣稱全部頁面已完整取代舊getWorkspace。

## 舊讀取契約移轉狀態（2026-11-12）

| 舊預設 | 新責任邊界 | 當前狀態／退役條件 |
| --- | --- | --- |
| getWorkspace取得全部Projects／Issues與活動 | 身分current、Project列表／屬性、Project Issue列表／單卡／活動分頁 | v1已建立，CLI一般讀取已切換；App任務列表／我的任務、看板列表／內容／其內詳細與編輯已切換；其他頁面仍屬P1。須完成側欄、選項、看板及草稿依賴後移除一般頁面快照入口。 |
| getTodos同時取待辦、全通知與成員選項 | 個人待辦分頁、單待辦內容、通知摘要／分頁、承辦者搜尋 | 尚未實作；不承諾保留舊組合回應。 |
| 全Workspace輪詢／事件後全量重讀 | 依目前頁面與Project的局部同步／授權重驗 | 看板已局部刷新、隱藏停止與草稿保護；全站尚未完成；需含斷線備援、隱藏分頁、撤權與草稿測試。 |

寫入的既有Application Service與集中授權可重用，但回傳不得為了沿用讀取端而附帶整個Workspace。遷移不是直接刪除所有RPC；尚有呼叫端時先分階段接線，最後刪除舊依賴與相應過量契約測試。歷史import／verify若仍需全量資料，須設計明確管理用途與有界批次，不成為SPA預設讀取例外。


## 看板呼叫端（2026-11-12）

看板列表每頁20個摘要，Default獨立於自訂列表分頁；卡數由SQL COUNT／EXISTS計算，不載入卡片全文。指定看板以每HTTP頁100張摘要依序取得所有頁，完整成功才交換畫面，避免只顯示第一頁。後續頁總數或去重結果與初始總數不符時拒絕交換並提示重讀；這不是跨HTTP的資料庫快照，不宣稱解決所有並行變更（版本同步仍另追蹤）。

編輯先讀單卡詳細及revision，再按需讀選項；留言／歷史／工時各20筆、按tab開啟分頁。摘要不含描述與活動全文；只在有工時資格時輸出spentMinutes，Epic估時及有效投入由SQL聚合。相關卡片只有識別／標題參照，不能拿mini reference當完整編輯資料。明確關聯ID保留於詳細避免修改時覆蓋既有連結。

看板route不預先呼叫getWorkspace/getTodos，也不沿用全站bulk輪詢；私有事件、可見性重連與備援只重讀當前Project／Board。未儲存或送出中的表單不被刷新更換revision，仍重驗Project資格，撤權時清除內容。成員選項、其他頁面遷移、DOM上限與全站版本同步仍有獨立待辦。


## 任務頁面接線（2026-11-12）

列表回應改為IssueListResource的純表格欄位，不附Epic／父卡參照、估時／工時SUM或活動；選項、看板摘要、詳細屬性各有不同明確投影。列表的filters／scope／page都送SQL，total／lastPage由後端決定，沒有把當頁再切片。詳細關聯最多200，查201作超限拒絕檢查；不靜默截斷後允許儲存。子卡／活動分頁最大100，預設20。選項UI每頁30，搜尋可達第一頁以外，已選值保留。

ProjectTasksWorkspace與useProjectTaskQueries已取代原BoardWorkspace／useBoardQueries；詳細頁不在背景載入列表或看板全卡，按需讀單卡及活動，未儲存／送出中只重驗資格。單卡工時按timeEntry.readOwn／readTeam限制SQL，明細附最小Issue參照讓Epic下編輯子卡紀錄時仍鎖原卡。完整審查與證據見[任務頁面資料分責](performance-task-pages-2026-11-12.md)。


## 2026-11-12 個人查詢與候選

- `GET /api/v1/todos`：本人scope=mine|sent、state=open|closed、搜尋／分頁摘要，不帶content／note／history。
- `GET /api/v1/todos/counts`：四項scope與狀態數量。
- `GET /api/v1/todos/{todo}`與`/{todo}/history`：本人參與的原稿及獨立歷史分頁。
- `GET /api/v1/todo-notifications/unread`與`/todo-notifications`：本人有效未讀數與收件匣分頁。
- `GET /api/v1/personal-version`：本人Todo及通知兩個無內容指紋；包含資格版本，不是完整bootstrap。
- `GET /api/v1/todo-assignees`：有效承辦候選；`/project-options`：可寫卡片Project候選；`/project-lead-options?project=<optional>`：建立／編輯用途的有效負責人候選。

上述沿用相同Session／可信Workspace／交易驗權與PageQuery上限，純Resource投影。實際接線、八項審查與限制見[個人頁面資料分責](performance-personal-pages-2026-11-12.md)。

## 2026-11-12 專案管理頁與月曆

- `GET /api/v1/projects/{project}/status-usage`（TT-243）：需`project.statuses.update`，回傳各狀態type的SQL卡數。
- `GET /api/v1/projects/{project}/notifications`與`/notifications/{id}`（TT-245）：團隊通知或本人收件，列表20筆摘要不含正文，單筆按需含正文。TT-314起每筆多`kind`：general、assigned、epic、comment、status、status_important（改成已關閉／待驗收）, 舊通知一律general。
- `GET /api/v1/issue-locations/{issue}`（TT-180）：舊版無Project深連結只回`{id, projectId}`，無權與不存在同為404。
- `GET /api/v1/projects/{project}/time-calendar?month=YYYY-MM&member=<optional>`（TT-223）：需`issue.read`及團隊／本人工時資格；回傳days／members／issues／epics SQL聚合與total，各清單上限100，不回原始工時列。Epic為本身＋所屬卡片各計一次，預估為所屬卡片加總。無團隊資格時伺服器固定為本人。
- `GET /api/v1/projects/{project}/time-entries?date=YYYY-MM-DD&member=&page=&perPage=`（TT-223）：當日有效工時分頁，欄位同單卡工時。

月曆八項審查與限制見[工時月曆查詢](performance-time-calendar-2026-11-12.md)。

## 2026-11-12 Project ACL管理查詢（TT-242）

`GET /api/v1/projects/{project}/acl`只回revision及可授予角色；`/acl/members`有效成員管理分頁、`/acl/candidates`候選搜尋分頁、`/acl/history`按需歷史分頁。四者均要求project.members.manage，與只回姓名的member-options不同；UI每頁20、最大100，source／canChange由當次permission判斷。細節與證據見[ACL讀取拆分](performance-project-acl-2026-11-12.md)。

## 2026-11-12 工作區設定（TT-241）

`GET /api/v1/workspace/settings`只回Workspace的id／name／revision，要求workspace.read；`/workspace/members`要求workspace.members.read，姓名／email SQL搜尋及管理摘要分頁；`/workspace/member-roles`要求workspace.members.manage，只回可授予非ownership角色id／label。成員20筆預設／最大100，canChange與寫入共用規則，前端Owner設定入口不變。

舊`POST /api/rpc/getWorkspaceSettings`整包讀取已退役，已登入且有效JSON請求返回404；前後端及測試已改分責GET，不保留全員名單相容支線。詳見[設定資料分責](performance-workspace-settings-2026-11-12.md)。

## 看板輕量變更檢查（TT-148，2026-11-12）

`GET /api/v1/projects/{project}/boards/{board}/version`（`board`可為`default`）

- 權限：每次重新驗證`issue.read`；封存看板、撤權、跨Workspace沿用`NOT_FOUND`／`FORBIDDEN`，不讓未授權者探測資源是否更新。`/api/*`一律`Cache-Control: no-store`（框架另加`private`），不進CDN／共用快取。
- 回應：`{ projectId, boardId, version }`，`version`為64字元sha256，只供比較，不含卡片、成員或工時內容。
- 版本來源（固定次數聚合查詢，不隨卡片數成長，不讀描述／說明／歷史）：
  - Project `revision`（名稱、狀態設定等）與自訂看板`revision`（屬性、收錄）。
  - 整個Project卡片的`COUNT`＋`SUM(revision)`：卡片只在整個Workspace重設時刪除，每次寫入revision+1，故只增不減；不依同秒時間字串。自訂看板採Project範圍，可能多觸發一次重讀，但不漏。
  - 卡片承辦者與Project負責人的`users.profile_revision`（改名不動Issue revision）。
  - 依本人可見範圍的工時`COUNT`＋`SUM(revision)`：`timeEntry.readTeam`看全Project、只有`readOwn`看本人、都沒有則不納入，避免以版本洩漏他人投入。
  - 本人在此Project的有效permission清單（權限變更即重讀）。
- 前端用法：先取版本再讀Project／看板／卡片，背景輪次版本相同就略過；期間有變更時下次版本必不同，最多多讀一次。寫入仍沿用`expectedRevision`／`requestId`與409衝突處理，這是最終一致性檢查，不取代寫入版本保護。
- 量測（隔離MySQL、3張卡）：版本回應168 bytes；同輪完整重讀為Project＋看板屬性＋卡片頁1404 bytes，卡片越多差距越大。前景載入多一個版本請求（board-query-check 45→47次）。


## 2026-11-12 TT-246 寫入結果收斂

Project建立／修改／狀態更新新結果為`{id, revision}`，Board建立／修改／移除為`{id, projectId, revision}`；不附描述、設定歷史或收錄清單。需要最新內容時使用上列單Project／Board查詢；寫入結果不得作完整read model或草稿基準。舊receipt仍回第一次保存的原結果，先重驗資格，不能為改契約重新執行業務。其餘領域專用Request／typed Data、前端與逐端點矩陣見[TT-246契約](other-write-contracts-2026-11-12.md)。

## 工時分析分責（TT-277）

- `GET /api/v1/time-report?startDate=YYYY-MM-DD&endDate=YYYY-MM-DD&projectId=&userId=`：統計摘要、成員／專案分布、日期及配對, 不附選項、卡片標題或填報說明
- `GET /api/v1/time-report/details`：相同期間／專案篩選, 必填`userId`及`detailGroup=dates|issues`；`detailPage`、`entryGroupKey`與`entryPage`沿用20筆雙層分頁, 只回`range/summary/details`, 不重算全部配對
- `GET /api/v1/time-report/options/{projects|members}?search=&page=1&perPage=30`：只回`id/name`及PageResource分頁, 每頁最多100筆；名稱搜尋採字面LIKE, 保留歷史成員
- 每次重驗workspace.read及workspace.timeReports.read, 專案需project.read與timeEntry.readTeam, 明細另需issue.read；Any能力及逐專案角色權限取交集, 外租戶與未授權專案不納入SQL
- 日期最多366天；摘要最多2000個成員×專案配對, 超過回400 INVALID_INPUT並要求縮小範圍, 不以截斷資料計算不完整總計。明細不受配對上限影響, 原始列只在展開時讀取
- 舊`POST /api/rpc/getTimeReport`僅保留相容, options只附第一頁各100筆, 不保證完整目錄；新前端使用上述GET介面。新整合應使用搜尋分頁, 不依賴舊options
- 這些為Session v1介面, 不宣稱公開PAT工時API已提供；公開機器契約不因本次內部拆分變更

## TT-278 卡號搜尋路徑

任務列表／增量與任務候選的search, 完整卡號自動轉大寫等值, 明確尾端*採卡號prefix。text:前綴強制既有字面contains, 其他文字維持中文／英文／標點與%_字面搜尋。看板本地篩選同步, 不以FULLTEXT丟棄短詞或改搜尋結果；Project／Workspace與其他filters仍交集

## TT-279 列表深分頁

任務列表的(page-1)×perPage最多10000, 超過回400 INVALID_INPUT且不執行Issue查詢。total與lastPage保留真實總數, maxOffset固定10000, accessibleLastPage是可翻閱上限；網頁按此上限停用下一頁並提示縮小篩選。一般PageQuery其他資源及看板分頁不套此任務列表專屬界限

完整第三方同步使用既有issue-changes cursor, 不靠遞增page匯出全部。原有穩定identifier／public_id次排序與changes keyset索引保留, CASE優先級與NULL到期日語意不變。新增倒序索引不代表其他排序或COUNT已變成固定成本

## TT-317 單卡活動總數與工時

Issue詳細新增activityCounts.comments／history／time, 為SQL總數, 不附活動內容；列表不增加活動欄位。每個活動仍按需讀每頁最多100筆（畫面20筆）, 頁籤數字採總數並用分頁total校正

2026-11-13 Josh確認：有issue.read的人可看該卡片所有成員的有效工時與筆數, Epic包含所屬卡片, 作廢紀錄不計。修改仍限本人且要timeEntry.writeOwn；月曆／工作區分析沿原權限, 不因能讀卡片就開放全部報表。跨Project／Workspace或撤權仍拒絕
