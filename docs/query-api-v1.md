# 分責查詢 API v1

2026-11-11。先以相同Laravel Session與CSRF可信邊界使用；尚未提供PAT／第三方認證。服務不依賴HTTP，可供後續Token能力交集的適配器沿用，不能僅加入Token就繞過Project權限。舊RPC相容保留，前端將逐頁遷移，不能把新增端點當作已移除全量刷新。

所有回應沿用`{ok,data}`，工作區由Session選定，建議帶`X-Workspace-ID`以檢查跨分頁切換。不相信query/body自稱的actor／workspace／role。

| GET | 職責 |
| --- | --- |
| `/api/v1/projects` | 已授權Project摘要，搜尋／分頁，不帶卡片或歷史 |
| `/api/v1/projects/{project}` | 單Project屬性／狀態及本人的有效capabilities |
| `/api/v1/projects/{project}/issues` | SQL條件、排序及有界列表摘要，不帶描述／留言／歷史 |
| `/api/v1/projects/{project}/issues/{issue}` | 單卡描述與屬性，不自動附帶活動全集 |
| `/api/v1/projects/{project}/issues/{issue}/comments` | 指定卡片留言分頁 |
| `/api/v1/projects/{project}/issues/{issue}/history` | 指定卡片異動分頁 |
| `/api/v1/projects/{project}/issues/{issue}/time` | 有效工時分頁；Epic含本身與所屬卡，按本人／團隊資格限定 |
| `/api/v1/projects/{project}/boards` | 看板摘要搜尋分頁及獨立defaultBoard，不附卡片內容 |
| `/api/v1/projects/{project}/boards/{board}` | 單看板屬性及明確收錄ID，上限1000；default為虛擬看板 |
| `/api/v1/projects/{project}/boards/{board}/issues` | SQL套看板範圍＋Epic動態展開去重後的卡片摘要分頁 |
| `/api/v1/projects/{project}/member-options` | 同Project有效成員id/name選項，分頁／搜尋，不含email或歷史 |

登入身分沿用`POST /api/auth/current`，不是另做一個大型bootstrap。專案與任務查詢在相同交易內重新驗證帳號、Workspace與permission＋scope；不可見資源404，已知資源缺少操作權403，工作區變更409。Resource僅序列化已預載欄位，不發lazy query。

列表輸入：`page`（1起）、`perPage`（預設20，上限100）、`search`（最多200字）。任務另支援精準`identifier`（AI／工具不必模糊搜尋編號）、`state[]`、`priority`、`type`、`assignee`、`epicId`（含unlinked）、`scope=all|mine`、`condition=overdue|unassigned`與`sort=updated|priority|due`。搜尋字詞中的`%`／`_`以文字處理，排序有穩定tie-breaker。

分頁形狀：`data.items`、`page`、`perPage`、`total`、`lastPage`。允許頁碼超出最後一頁回空items；不把新查詢任意改寫為未授權的全域exists檢查。

寫入暫沿用相容RPC及原有Form／revision／requestId契約；本輪不複製第二套業務實作。列表摘要不得直接當編輯基準，開編輯器前須讀單卡詳細及最新revision；留言／歷史另行分頁載入。

本機tools/tasktracker.py的list改逐頁摘要，status／comment用identifier精準定位取得ID與revision，不為寫入載入任務描述／活動；只有初次import／歷史verify保留明確使用相容Workspace快照，不能以初始manifest蓋掉後來進度。

2026-11-12契約修正：使用者已授權調整原API資料契約。舊RPC僅是前端／工具移轉中的暫時接口，不承諾永久保留整包Workspace；移轉後須退役過量入口並修改依賴它的測試，不可只新增v1而放任舊預設載入。安全與版本／冪等語意維持。資料存取審查見[審查紀錄](data-access-review-2026-11-12.md)。

Project授權僅讀指定ID或當頁ID（最多100筆），可見範圍在SQL分頁前依有效permission映射篩選。單Project回應不附全員memberIds；成員選項由獨立member-options端點提供，不能宣稱全部頁面已完整取代舊getWorkspace。

## 舊讀取契約移轉狀態（2026-11-12）

| 舊預設 | 新責任邊界 | 當前狀態／退役條件 |
| --- | --- | --- |
| getWorkspace取得全部Projects／Issues與活動 | 身分current、Project列表／屬性、Project Issue列表／單卡／活動分頁 | v1已建立，CLI一般讀取已切換；App看板列表／內容／其內詳細與編輯已切換；其他頁面仍屬P1。須完成側欄、選項、看板及草稿依賴後移除一般頁面快照入口。 |
| getTodos同時取待辦、全通知與成員選項 | 個人待辦分頁、單待辦內容、通知摘要／分頁、承辦者搜尋 | 尚未實作；不承諾保留舊組合回應。 |
| 全Workspace輪詢／事件後全量重讀 | 依目前頁面與Project的局部同步／授權重驗 | 看板已局部刷新、隱藏停止與草稿保護；全站尚未完成；需含斷線備援、隱藏分頁、撤權與草稿測試。 |

寫入的既有Application Service與集中授權可重用，但回傳不得為了沿用讀取端而附帶整個Workspace。遷移不是直接刪除所有RPC；尚有呼叫端時先分階段接線，最後刪除舊依賴與相應過量契約測試。歷史import／verify若仍需全量資料，須設計明確管理用途與有界批次，不成為SPA預設讀取例外。


## 看板呼叫端（2026-11-12）

看板列表每頁20個摘要，Default獨立於自訂列表分頁；卡數由SQL COUNT／EXISTS計算，不載入卡片全文。指定看板以每HTTP頁100張摘要依序取得所有頁，完整成功才交換畫面，避免只顯示第一頁。後續頁總數或去重結果與初始總數不符時拒絕交換並提示重讀；這不是跨HTTP的資料庫快照，不宣稱解決所有並行變更（版本同步仍另追蹤）。

編輯先讀單卡詳細及revision，再按需讀選項；留言／歷史／工時各20筆、按tab開啟分頁。摘要不含描述與活動全文；只在有工時資格時輸出spentMinutes，Epic估時及有效投入由SQL聚合。相關卡片只有識別／標題參照，不能拿mini reference當完整編輯資料。明確關聯ID保留於詳細避免修改時覆蓋既有連結。

看板route不預先呼叫getWorkspace/getTodos，也不沿用全站bulk輪詢；私有事件、可見性重連與備援只重讀當前Project／Board。未儲存或送出中的表單不被刷新更換revision，仍重驗Project資格，撤權時清除內容。成員選項、其他頁面遷移、DOM上限與全站版本同步仍有獨立待辦。
