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

登入身分沿用`POST /api/auth/current`，不是另做一個大型bootstrap。專案與任務查詢在相同交易內重新驗證帳號、Workspace與permission＋scope；不可見資源404，已知資源缺少操作權403，工作區變更409。Resource僅序列化已預載欄位，不發lazy query。

列表輸入：`page`（1起）、`perPage`（預設20，上限100）、`search`（最多200字）。任務另支援精準`identifier`（AI／工具不必模糊搜尋編號）、`state[]`、`priority`、`type`、`assignee`、`epicId`（含unlinked）、`scope=all|mine`、`condition=overdue|unassigned`與`sort=updated|priority|due`。搜尋字詞中的`%`／`_`以文字處理，排序有穩定tie-breaker。

分頁形狀：`data.items`、`page`、`perPage`、`total`、`lastPage`。允許頁碼超出最後一頁回空items；不把新查詢任意改寫為未授權的全域exists檢查。

寫入暫沿用相容RPC及原有Form／revision／requestId契約；本輪不複製第二套業務實作。列表摘要不得直接當編輯基準，開編輯器前須讀單卡詳細及最新revision；留言／歷史另行分頁載入。

本機tools/tasktracker.py的list改逐頁摘要，status／comment用identifier精準定位取得ID與revision，不為寫入載入任務描述／活動；只有初次import／歷史verify保留明確使用相容Workspace快照，不能以初始manifest蓋掉後來進度。
