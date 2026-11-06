# 工作空間工時分析

更新：2026-11-06（Asia/Taipei）

## 本輪範圍

使用者要求Owner／Manager側欄有工時分析，先選分析方式再進入報表，並授權本輪挑選三個角度。實作選擇如下，未另做績效、產能或估時準確度推論：

| 入口 | 回答的問題 | 呈現 |
| --- | --- | --- |
| 成員投入 | 誰在這段期間填報多少？參與幾個專案？ | 跨專案堆疊長條、實際時數／占比、可聚焦查專案；保留明細 |
| 專案投入 | 團隊時間分布在哪些專案？ | 專案投入比較長條、時數／占比、可聚焦成員；保留明細 |
| 時間趨勢 | 每日填報量如何變化？ | 每日折線與刻度、可選日期值、最高投入日，含零日期的明細 |

工作空間側欄與專案側欄都可進入工時分析，分析跨目前工作空間中有權讀取的專案。`#/time-analysis`提供三個入口；`/members`、`/projects`、`/trend`各自可深連結、重新整理及返回列表。不是專案內的工時填報月曆；原填報入口保留。

## 統計口徑

- 預設本月1日至台北今天，起訖日都包含，最多366日；用填報的`workDate`，不以建立或修改時間替代。
- 日期、專案、成員篩選為交集。先調整再按「套用範圍」，報表標示已套用日期；尚未套用的修改有提示，背景重讀沿用已套用條件。
- 只計未作廢的原始`timeEntries`。Epic直接填報、所屬Task／Bug、普通父子任務各自的一筆紀錄只計一次，不再把Epic彙總加一次。
- 已結案／取消卡片的有效投入仍計入；歷史或停用成員保留有效紀錄。未填報的日期補零，不等同請假或未工作。
- 各成員、專案、日期加總都等於同一筆總投入。分鐘保持整數，前端顯示至小數兩位小時；占比使用原始分鐘，四捨五入後相加可能差0.1%。
- 參與專案／投入成員數是範圍內有有效填報的不同ID數，不是ACL人數。無符合資料明確顯示空狀態，不畫假資料。
- 工時是填報投入，不代表出勤、績效、實際交付百分比或長期專案完成率。

## API與授權

`POST /api/rpc/getTimeReport`，使用既有Session／CSRF／JSON錯誤契約。輸入：

```json
{"startDate":"2026-11-01","endDate":"2026-11-06","projectId":"","userId":""}
```

輸出固定包含`range`、`summary`（`totalMinutes`、`entryCount`、`activeDays`）、`members`、`projects`、`days`、`allocations`（userId／memberName／projectId／projectName／minutes交叉聚合）、`options`。空資料集合保持陣列，日期序列仍補零。只回分析所需名稱、ID及加總，不回留言、說明、信箱、憑證或原始工時內容。

新增有效permission `workspace.timeReports.read`，目前固定映射Owner／Manager（含舊admin別名）。前後端均檢查permission，不比較身份名稱或roleId。這是工作空間權限，不因擔任專案管理者／開發者而取得。

後端由AccountService重建目前資格，在同一讀交易中透過WorkspaceService／WorkspaceTimeReport查詢；RecordStore限定Session的Workspace，再逐專案確認`project.read`及`timeEntry.readTeam`。報表入口permission不跳過Project scope，指定無權或外租戶的專案／成員回404，無報表權限回403。前端傳`workspaceId`或角色不改變查詢資格。此唯讀API不使用寫入重試快取、不寄信、不廣播，不新增資料表。

角色撤銷後，下次重讀會移除側欄入口與報表並回首頁；手動請求仍由後端拒絕。報表未寫入localStorage，離頁與新查詢透過generation捨棄過期回應。連線錯誤不把舊表格留著冒充新結果，可重試。

## 目前限制

這是本機版本，查詢沿用目前RecordStore批次讀取後彙整，尚未改成資料庫分頁／SQL聚合、匯出、計費或排程寄送。366日上限限制輸出日期數，不代表已針對大量歷史工時完成壓測。導覽回分析列表保留同一元件篩選；重新整理回預設本月，不提供儲存視圖。

## 驗證方式

`TimeReportTest`使用隔離記憶體SQLite，驗證三組加總、Epic不重複、作廢／範圍排除、零日、交集、外租戶ID、偽造角色、manager升降、逐專案資格與366日邊界。`frontend/tools/time-analysis-check.js`使用隔離Laravel API及編譯版，驗證三入口／篩選／錯誤／權限撤回與1440、768、390尺寸；測試不使用本機業務資料。

2026-11-06實用性review、圖表互動及seed規則見[工時分析review](time-analysis-review-2026-11-06.md)。新增聚合只在原已授權／未作廢／範圍內迴圈加總，不另讀更多資料，不含原始工時說明。
