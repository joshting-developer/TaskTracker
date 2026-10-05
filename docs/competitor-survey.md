# 專案管理平台競品調研

調研日期：2026-10-05（Asia/Taipei）

本次以官方產品及功能文件為來源, 屬於文件調研, 尚未登入各產品進行操作測試。功能事實、產品取捨與 TaskTracker 建議分開記錄；方案與價格可能變動, 不使用過去印象填入目前定價

## 產品方向

使用者以前用過 YouTrack 且喜歡, 因此建議 TaskTracker 以「任務清單＋搜尋＋任務詳細內容」為主軸, 看板作為同一批任務的另一個視圖

這是根據使用者偏好與文件做出的產品提案, 尚未確認使用者最喜歡的是搜尋、看板、自訂欄位還是 Sprint

## 競品比較

| 產品 | 官方文件可確認的核心方式 | 對 TaskTracker 的參考價值（本次判斷） | 初期成本／取捨（本次判斷） |
| --- | --- | --- | --- |
| YouTrack | Issue、屬性搜尋、儲存搜尋、專案自訂欄位、敏捷看板、可程式化 workflow | 最符合既有使用經驗, 優先研究找到任務與快速修改的流程 | 完整查詢語法、任意欄位與 workflow 引擎會放大實作範圍 |
| OpenProject | Work package、任務階層、甘特圖、專案模組與成員角色, Basic／Action boards | 參考父子任務、相依關係、期限及專案設定的組織方式 | 完整排程、預算與工時管理牽涉較多規則 |
| Linear | Issue、Project、Cycle, Issue 可設定負責人、標籤與優先級 | 參考集中處理任務、減少切換頁面的流程 | Cycle 與 Project 的語意需要先定義, 不宜直接套用所有術語 |
| Trello | Board、List、Card, 卡片集中承載工作內容 | 參考看板易理解的狀態操作 | 僅以卡片排列建模較難表達跨專案任務與複雜關聯 |

表內功能來源：YouTrack 的[搜尋](https://www.jetbrains.com/help/youtrack/cloud/search-and-command-attributes.html)、[儲存搜尋](https://www.jetbrains.com/help/youtrack/cloud/saved-search.html)、[自訂欄位](https://www.jetbrains.com/help/youtrack/cloud/manage-custom-fields-per-project.html)、[敏捷看板](https://www.jetbrains.com/help/youtrack/cloud/configure-agile-board.html)、[Workflow 類型](https://www.jetbrains.com/help/youtrack/cloud/workflow-rules.html)；OpenProject 的[產品介紹](https://www.openproject.org/docs/getting-started/openproject-introduction/)、[Work packages](https://www.openproject.org/docs/user-guide/work-packages/)、[Boards](https://www.openproject.org/docs/user-guide/agile-boards/)；Linear 的[概念模型](https://linear.app/docs/conceptual-model)、[Projects](https://linear.app/docs/projects)、[Cycles](https://linear.app/docs/use-cycles)；Trello 的[卡片與清單文件](https://support.atlassian.com/trello/docs/add-and-customize-cards-and-lists/)

## YouTrack：主要參考

### 搜尋與常用視圖

YouTrack 支援 `attribute: value` 的屬性搜尋, 儲存搜尋可重用與分享, 也能用於通知訂閱及 backlog。這讓「指派給我」「未完成」「未指派」可直接成為常用入口, 不必每次重設條件。[搜尋參考](https://www.jetbrains.com/help/youtrack/cloud/search-and-command-attributes.html)、[儲存搜尋](https://www.jetbrains.com/help/youtrack/cloud/saved-search.html)

TaskTracker 建議先做文字搜尋＋篩選器, 保存結構化條件；先提供「我的任務」與「未完成」視圖。後續若確認需要鍵盤操作與查詢語法, 再加有限的 parser, 例如 `project: TT state: Open`, 不在第一版承諾相容 YouTrack 完整語法

### 自訂欄位與工作流程

YouTrack 可在專案管理欄位及列舉值, 並提供條件式欄位。Workflow 支援變更觸發、排程、狀態機及 action rules, 也能以 JavaScript 編寫。[專案自訂欄位](https://www.jetbrains.com/help/youtrack/cloud/manage-custom-fields-per-project.html)、[Workflow 類型](https://www.jetbrains.com/help/youtrack/cloud/workflow-rules.html)、[JavaScript 編輯器](https://www.jetbrains.com/help/youtrack/cloud/web-based-workflow-editor.html)

TaskTracker 建議先固定 Type、State、Priority、Assignee 等核心欄位, 保留日後以 ID 管理設定的空間。第一版可定義完成狀態的驗證規則, 任意自訂欄位與 workflow 編輯器列為後續功能

### 看板

YouTrack 的看板可以設定欄位對應的欄、swimlane 及 Sprint 行為, 不只是卡片排列。[看板設定](https://www.jetbrains.com/help/youtrack/cloud/configure-agile-board.html)

TaskTracker 建議先採 State 欄位對應欄位的 Kanban, 拖曳與詳細頁編輯呼叫同一個任務更新服務。清單與看板共用查詢條件, 第一版先不加入 Sprint、swimlane 與燃盡圖

## OpenProject：任務結構與排程參考

OpenProject 以 Work package 組織工作, 提供表格、任務階層、時程與專案模組, 也涵蓋工時、成本及知識文件。[Work packages](https://www.openproject.org/docs/user-guide/work-packages/)、[產品介紹](https://www.openproject.org/docs/getting-started/openproject-introduction/)

值得注意的是 Basic board 移動卡片不改變 work package 本身, Action board 則會依目標欄更新屬性。不能把「產品提供看板」直接等同「拖曳就會修改狀態」。實際方案可用性需搭配當下方案比較表確認。[Boards](https://www.openproject.org/docs/user-guide/agile-boards/)、[方案比較](https://www.openproject.org/pricing/)

TaskTracker 建議先提供一層父子任務與到期日, 後續再做阻擋／相依關係。甘特圖、相依排程、資源配置、工時與預算放到使用需求確定之後；這些功能對 Sheets 的查詢與跨資料寫入要求較高

## Linear、Trello：輕量流程對照

Linear 的 Project 集中相關 Issue, Cycle 是重複的工作期間且不等同 release。這可作為後續里程碑與迭代的對照, TaskTracker 第一版先保持 Project 與 Issue 兩個主要層級。[Projects](https://linear.app/docs/projects)、[Cycles](https://linear.app/docs/use-cycles)

Trello 的 Board／List／Card 模型可以作為看板說明方式, TaskTracker 可採「欄代表狀態、卡片代表任務」, 同時讓任務保留獨立 ID 與清單視圖。[卡片與清單](https://support.atlassian.com/trello/docs/add-and-customize-cards-and-lists/)

## 部署與方案觀察

- YouTrack 有 Cloud 與 Server, 目前兩者提供最多 10 users／3 helpdesk agents 的免費授權；付費規則依產品與現行方案確認。[Cloud 授權](https://www.jetbrains.com/help/youtrack/cloud/switching-subscription-plans.html)、[Server 授權](https://www.jetbrains.com/help/youtrack/server/manage-youtrack-server-license.html)
- OpenProject Community 可免費自架且不限 users／projects, Enterprise cloud／on-premises 提供不同 add-ons 與支援。[Community](https://www.openproject.org/community-edition/)、[版本說明](https://www.openproject.org/docs/getting-started/openproject-introduction/)
- 本次目的是參考產品流程, 並不選購競品；TaskTracker 採用 GAS 不代表沒有容量、配額或帳號方案成本, 需以實際使用量評估

## 第一版功能建議（未定案）

| 優先級 | 建議功能 | 可檢查的完成行為 |
| --- | --- | --- |
| P0 | 專案與成員權限 | 成員只能讀寫所屬專案, 修改權限由後端檢查 |
| P0 | 任務新增／修改、詳細頁 | 保存標題、描述、狀態、類型、優先級、負責人與到期日, 重新載入後仍存在 |
| P0 | 任務清單、文字搜尋、篩選 | 可依專案、狀態、負責人找出任務, 支援分頁 |
| P0 | State 看板 | 拖曳成功後清單與詳細頁顯示相同狀態, 衝突時提示重新載入 |
| P0 | 留言與異動紀錄 | 可查誰在何時修改哪些欄位, 重試不重複新增留言 |
| P1 | 儲存視圖、父子任務、標籤 | 保存常用查詢, 查到同一主鍵關聯的子任務 |
| P2 | 自訂欄位、Sprint、相依關係、工時、通知 | 需求確認後逐項定義, 先不建完整引擎 |
| 暫緩 | 甘特排程引擎、workflow 編輯器、Helpdesk、AI 功能、競品匯入 | 不放入第一版 |

建議使用流程：建立專案 → 建立任務 → 在清單搜尋／篩選 → 開詳細內容修改 → 在看板變更狀態 → 查看留言與異動

## 下一階段需要收斂

- 過去最喜歡 YouTrack 的哪幾個操作, 是清單搜尋、看板、快捷鍵還是自訂欄位
- 個人使用或多人協作, 大概有多少人、多少專案與任務
- 是否需要 Sprint、工時與通知, 或先以任務追蹤為主
- 登入是否限定同一 Google Workspace 網域, 外部協作者如何加入

上述問題記錄供下一階段討論, 不影響本次文件調研完成
