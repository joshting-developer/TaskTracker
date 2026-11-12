# API責任與背景請求效能門檻（TT-226，2026-11-12）

兩支可重跑的回歸門檻：前端Network（實際頁面發出的請求）與後端資料成長（SQL次數／回應大小）。門檻來自隔離環境實測再留餘裕，**不是正式SLO**；正式runtime負載屬TT-184，長期觀察屬TT-163。

## 前端：`frontend/tools/api-budget-check.js`

隔離MySQL建立500張卡（5個Epic）、12則留言、60筆跨月工時，逐頁量測初次載入（`BUDGET_CARDS`可調卡數，`BUDGET_MEASURE=1`只量測不斷言）。

| 頁面 | 允許的endpoint | 實測（500卡） | 門檻 |
|---|---|---|---|
| 首頁 | personal-version、todos、todos/counts、todo-notifications(/unread)、todo-assignees、project-options | 6次／720 B | 8次／4 KB |
| 任務列表 | projects/{p}、projects/{p}/issues（20筆）、member／issue-options | 2次／7.9 KB | 4次／15 KB |
| 任務完整頁 | 單卡、Project、留言（預設分頁）、選項 | 3次／3.7 KB | 5次／10 KB |
| 看板 | version、看板屬性、Project、卡片頁（每頁100）、realtime config | 8次／310 KB（約620 B／卡） | `4＋卡數/100`次／`700 B×卡數＋10 KB` |
| 工時分析 | getTimeReport | 1次／1 KB | 2次／3 KB |

60卡時除看板外數值相同，表示這些頁面不隨卡數成長。所有頁面都禁止`getWorkspace`／`getTodos`，出現白名單外的endpoint即失敗。

其他斷言：

- 完整頁未切到「異動紀錄」不讀歷史，切換後才讀。
- 分頁隱藏180秒：0請求。
- 可見但版本不變：只送`version`／`personal-version`，不重讀卡片（閒置時已延長到120秒）。
- 20次`focus`＋5次隱藏／顯示的事件風暴：0～4次請求（實測0，受最短間隔與不重疊保護）。

## 後端：`backend/tests/Feature/ApiGrowthBudgetTest.php`

memory資料庫，先量16個一般頁面讀取端點，再加入150卡、40名Project成員、120筆跨10個月工時、30則留言後重量：

- 每個端點SQL次數不得增加（實測全部相同，例如列表12、看板頁13、看板版本10、月曆10）。
- 固定欄位端點（Project、單卡、看板屬性／版本、個人版本）回應增加不超過400 B。
- 分頁端點（列表、看板頁、留言、歷史、成員／卡片選項、當日工時、ACL、待辦、Project列表）回應不超過20 KB；月曆只回聚合，不超過8 KB。
- 基準先放一則留言：空結果會略過Eloquent關聯查詢，不能誤判為逐筆成長。

## 觀察與限制

- 首頁一進入就讀`todo-assignees`與`project-options`（有界選項），可改為打開指派／轉卡時才讀，列為後續最佳化，不影響門檻。
- 看板回應隨卡數線性成長（必須完整呈現所有卡）；初次渲染長任務屬TT-256。
- 未量測p95：本機單次耗時受Docker／memory SQLite影響，不代表正式延遲。docs/data-access-review-2026-11-12.md記錄的2.3～2.6秒是遠端SQL單次觀察，不是p95。遠端SQL RTT與正式拓樸的可重跑量測留給TT-184。
