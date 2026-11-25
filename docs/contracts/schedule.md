# 時程查詢

Session `GET /api/v1/projects/{project}/schedule` 供人員排程、週曆與甘特三種視圖共用, 需要該 Project 的 `issue.read`, 不提供 PAT 入口。身分與工作區沿用 Session／`X-Workspace-ID` 邊界, 每次查詢重新驗權

## 日期真值

- 條狀只看計畫開始日 `startDate` 與結束日 `endDate`, 到期日 `dueDate` 獨立顯示, 不參與排程
- 只填一端時視為單日；兩端皆空是未排程, 不回傳列, 只在 `unscheduled` 回同條件的卡片數
- 改期沿用既有 Issue 更新與 revision, 三視圖讀同一組欄位, 不另存排程副本

## Query

- `from`、`to` 必填, `YYYY-MM-DD`, 含首尾, `to` 不可早於 `from`, 最多 92 天
- `assignee` 選填, 承辦者 User ID
- `includeClosed` 選填, 預設不含已關閉卡片（TT-448）, `unscheduled` 計數也套用
- `page` 預設1、最多1000, `perPage` 預設1000、最多1000。三視圖一次讀完整期間不分頁；`total` 超過1000時畫面提示縮短期間或加篩選, 不呈現片段的條狀、負荷與 Epic 推算

卡片區間與 `[from, to]` 有任一天重疊即列入, 跨月、跨年都以日期比較。排序依開始日、結束日、卡號、公開ID, 換頁不重複不漏

## 回應

沿用 `ok/data` 及 `items/page/perPage/total/lastPage`, 另加 `unscheduled` 與 `epics`。單筆只有 `id/identifier/title/type/priority/state/stateLabel/assignee/assigneeName/assigneeDisabled/startDate/endDate/dueDate/epicId/revision`, 日期、承辦或 Epic 未填為空字串, 不含描述、留言或工時。`epics` 是當頁卡片引用的同 Project Epic 摘要 `id/identifier/title`, 依卡號排序, Epic 本身沒有日期也有名稱（TT-386）

查詢限單一 Project。單端日期以 stored generated column `schedule_start`／`schedule_end`（COALESCE 兩端）比較, 並以 `issues_schedule_state_range`／`issues_schedule_range` 範圍索引讀取, 不掃整個工作區（TT-449, 60k 張卡本週 p50 約 13–17ms）；跨 Project 的人員排程需要時另評估

## 未排程清單（TT-446）

Session `GET /api/v1/projects/{project}/schedule/unscheduled` 列出兩端日期皆空的卡片, 權限與工作區邊界同上。Query 只收 `assignee`、`includeClosed`、`page`（最多1000）與 `perPage`（預設20、最多50）, 不收日期區間；條件與 `unscheduled` 計數相同, 最近更新優先、公開ID次排序。單筆欄位與時程相同

三視圖底部的「N 張任務尚未排程」是按鈕, 打開共用對話框 `ScheduleUnscheduled.vue`, 沿用目前視圖的承辦人與「顯示已完成」。每列可填計畫開始／結束日（只填一端視為單日）, 存檔走既有 `updateIssue` 與 `expectedRevision`；revision 衝突時不覆蓋, 重讀最新內容請使用者確認後再存。存完重讀視圖, 卡片移出清單並出現在時程上。沒有 `issue.write` 只能查看。期間內沒有排程但有未排程卡片時, 空狀態會說明原因與補日期的入口。不做拖曳改期與多選批次, 檢查在 `frontend/tools/schedule-unscheduled-check.js`

## 前端共用

`frontend/src/domain/schedule.js` 提供日期位移、週一起算的週範圍、單端區間及不遮蓋的分列排版, 三視圖共用, 檢查在 `frontend/tools/schedule-domain-check.js`

## 週曆（TT-385）

專案側欄「週曆」, 網址 `#/projects/{project}/schedule`, 需要 `issue.read`。週一起算, 一次只讀當週, 切週、承辦人或頁碼才重讀, 不輪詢。跨週卡片以虛線邊標示延續；條狀左緣用專案狀態色, 計畫結束日早於台北今天且未關閉標為逾期, 「全部成員」時顯示承辦人縮寫；依賴箭頭與拖曳改期未授權, 三視圖都不提供；窄螢幕改逐日清單, 跨日卡片每天都列出。點卡片開既有詳情, 改期在卡片編輯完成。所選週與承辦人只存在目前分頁的 sessionStorage

## 人員排程（TT-384）

專案側欄「人員排程」, 網址 `#/projects/{project}/people`, 需要 `issue.read`。每位成員一列（成員選項每頁 100 人）, 沒有排程的成員也列出, 結果中不在分頁的承辦人（例如已停用）與未指派另成一列；篩選承辦人時只留該列。期間 2／4 週、週一起算, 同一天同時進行 3 張以上未關閉卡片標為負荷高, 已關閉卡片照常顯示但不計負荷（TT-445；schedule 回應不含估時, 以張數判斷）。4 週時兩天以內的條狀只留狀態色塊。窄螢幕改依成員分組清單。期間、週與承辦人只存在目前分頁的 sessionStorage；條狀樣式與週曆共用 `frontend/src/composables/useScheduleBars.js`

## 專案甘特（TT-386）

專案側欄「專案甘特」, 網址 `#/projects/{project}/gantt`, 需要 `issue.read`。Project → Epic → 卡片三層, 一張卡一列；Epic 有日期用自己的條狀, 沒有時依期間內子卡推算（虛線）, 沒有 Epic 的卡片放「未歸入 Epic」群組不隱藏。到期日以菱形標在該卡列上, 計畫結束日晚於到期日改警示色。期間 4／12 週（12 週 84 天, 在 92 天上限內）, 4 週逐日、12 週標每週一並另列月份。Epic 群組可收合, 期間、週與收合只存在目前分頁的 sessionStorage。窄螢幕改依 Epic 分組清單
