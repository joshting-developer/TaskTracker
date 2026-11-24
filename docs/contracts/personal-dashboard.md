# 個人工作摘要

Session `GET /api/v1/personal-dashboard` 只讀目前工作區內本人被指派且具有 `issue.read` 的任務, 不提供 PAT 入口。身分與工作區由既有 Session／`X-Workspace-ID` 邊界驗證, 每次查詢重新驗權

必要 query `kind`：

- `mine`：未結案本人任務, 最近更新優先
- `due`：未結案且到期日不晚於台北今日加七天, 包含逾期, 最早到期優先
- `starting`：未結案且計畫開始日在台北今日至七天後（含首尾）, 最早開始優先
- `recent`：本人任務最近更新, 包含已結案；是卡片更新摘要, 不是完整活動歷史

所有排序以公開ID作穩定次排序。`page` 預設1、最多501, `perPage` 預設20、最多20。回應沿用 `ok/data` 及 `items/page/perPage/total/lastPage`, 單筆只有 `id/projectId/projectName/identifier/title/state/stateLabel/dueDate/startDate/updatedAt`, 日期未填為空字串

前端各區每頁5筆, 切頁或手動重新整理才重讀, 不新增輪詢。切工作區或離頁取消查詢及清除摘要, 失敗區塊可獨立重試。紙條摘要沿用 `/api/v1/todos?scope=mine&state=open`, 不使用 Project 讀權開放私人紙條

## 首頁小工具偏好（TT-186）

Session `GET`／`PUT /api/v1/personal-dashboard/preferences`, 以工作區成員保存, 換工作區或重新登入都能還原；不提供 PAT。白名單 `mine/due/starting/recent/notes`（`notes` 需 `todo.read`）, 只存順序與顯示, 不存資料。回應 `widgets`（顯示順序）、`available`（目前可用）、`revision`；沒有紀錄時預設全部顯示、revision 0。PUT 需 `widgets`（不重複、最多 5）與 `expectedRevision`, 版本不符且內容不同回 `REVISION_CONFLICT`, 同內容重送視為成功。前端「自訂」可上移、下移、隱藏、加入、取消與恢復預設；讀取失敗時仍以預設顯示摘要
