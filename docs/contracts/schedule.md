# 時程查詢

Session `GET /api/v1/projects/{project}/schedule` 供人員排程、週曆與甘特三種視圖共用, 需要該 Project 的 `issue.read`, 不提供 PAT 入口。身分與工作區沿用 Session／`X-Workspace-ID` 邊界, 每次查詢重新驗權

## 日期真值

- 條狀只看計畫開始日 `startDate` 與結束日 `endDate`, 到期日 `dueDate` 獨立顯示, 不參與排程
- 只填一端時視為單日；兩端皆空是未排程, 不回傳列, 只在 `unscheduled` 回同條件的卡片數
- 改期沿用既有 Issue 更新與 revision, 三視圖讀同一組欄位, 不另存排程副本

## Query

- `from`、`to` 必填, `YYYY-MM-DD`, 含首尾, `to` 不可早於 `from`, 最多 92 天
- `assignee` 選填, 承辦者 User ID
- `page` 預設1、最多1000, `perPage` 預設100、最多200

卡片區間與 `[from, to]` 有任一天重疊即列入, 跨月、跨年都以日期比較。排序依開始日、結束日、卡號、公開ID, 換頁不重複不漏

## 回應

沿用 `ok/data` 及 `items/page/perPage/total/lastPage`, 另加 `unscheduled`。單筆只有 `id/identifier/title/type/priority/state/stateLabel/assignee/assigneeName/assigneeDisabled/startDate/endDate/dueDate/revision`, 日期或承辦未填為空字串, 不含描述、留言或工時

查詢限單一 Project, issues 沒有日期索引, 依 Project 範圍掃描；跨 Project 的人員排程需要時另評估

## 前端共用

`frontend/src/domain/schedule.js` 提供日期位移、週一起算的週範圍、單端區間及不遮蓋的分列排版, 三視圖共用, 檢查在 `frontend/tools/schedule-domain-check.js`
