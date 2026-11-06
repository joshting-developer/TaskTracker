# 工作空間工時分析

更新：2026-11-06（Asia/Taipei）

## 本輪範圍

使用者要求Owner／Manager側欄有工時分析，先選分析方式再進入報表，並授權本輪挑選三個角度。實作選擇如下，未另做績效、產能或估時準確度推論：

| 入口 | 回答的問題 | 呈現 |
| --- | --- | --- |
| 成員投入 | 誰在這段期間填報多少？參與幾個專案？ | 跨專案堆疊長條、實際時數／占比；點成員進日期／卡片工時明細 |
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

輸出固定包含`range`、`summary`（`totalMinutes`、`entryCount`、`activeDays`）、`members`、`projects`、`days`、`allocations`（userId／memberName／projectId／projectName／minutes交叉聚合）、`options`。空資料集合保持陣列，日期序列仍補零。一般總覽只回分析所需名稱、ID及加總，不回留言、填報說明、信箱、憑證或原始工時內容。明確請求成員明細時另依下方契約回傳。

新增有效permission `workspace.timeReports.read`，目前固定映射Owner／Manager（含舊admin別名）。前後端均檢查permission，不比較身份名稱或roleId。這是工作空間權限，不因擔任專案管理者／開發者而取得。

後端由AccountService重建目前資格，在同一讀交易中透過WorkspaceService／WorkspaceTimeReport查詢；RecordStore限定Session的Workspace，再逐專案確認`project.read`及`timeEntry.readTeam`。報表入口permission不跳過Project scope，指定無權或外租戶的專案／成員回404，無報表權限回403。前端傳`workspaceId`或角色不改變查詢資格。此唯讀API不使用寫入重試快取、不寄信、不廣播，不新增資料表。

角色撤銷後，下次重讀會移除側欄入口與報表並回首頁；手動請求仍由後端拒絕。報表未寫入localStorage，離頁與新查詢透過generation捨棄過期回應。連線錯誤不把舊表格留著冒充新結果，可重試。

## 成員工時明細（2026-11-06）

使用者已要求完成「哪一天做了什麼、哪些卡片花了多少時間」。成員圖表名稱與表格名稱改為明細入口，堆疊區段可將範圍縮到該專案；專案分析點專案仍進入成員分析。明細沿用總覽已套用的日期／專案範圍，不偷用表單草稿；返回時保留草稿並重讀原已套用總覽。

- 「依日期」以日期＋卡片分組，顯示當日合計、卡片編號／名稱／專案、時數與紀錄數；同日多卡不重複印日期標題，跨頁仍顯示所屬日期。
- 「依卡片」加總同卡跨日期投入；兩種方式都可展開各筆工作日期、分鐘換算時數及填報說明。說明以轉義文字顯示，不執行HTML。
- 群組每頁20筆，展開的原始填報也每頁20筆；兩層各自有上一頁／下一頁，總計始終是完整範圍。日期由新到舊，卡片以投入由多到少，最後以固定ID穩定排序。不是分頁後才加總。
- 背景重讀仍重新驗證資格，保留分組、頁碼與展開群組；請求失敗清除資料並可重試，離頁捨棄晚到回應。成員明細是分析元件內的子畫面，本輪未新增獨立深連結；重新整理回該分析總覽。

沿用`getTimeReport`，選定`userId`並加以下欄位才產生`details`：

```json
{"startDate":"2026-11-01","endDate":"2026-11-06","userId":"member-id","projectId":"","detailGroup":"dates","detailPage":1,"entryGroupKey":"","entryPage":1}
```

`detailGroup`只接受`dates`／`issues`，必須指定本工作空間成員；頁碼必須為1到1000000的整數，超過資料尾頁時收斂到最後頁。`details`含`groupBy`、`rows`、`page`、`perPage`、`total`、`lastPage`、`expanded`。空清單為`rows:[]`、`total:0`、`lastPage:1`。

群組列含`key`、`date`（卡片分組為null）、`dayMinutes`（卡片分組為null）、`issueId`、`identifier`、`title`、`projectId`、`projectName`、`minutes`、`entryCount`。直接Epic填報與子卡紀錄分開歸卡，不複製彙總。

將後端回傳的群組`key`放入`entryGroupKey`可取得`expanded`，其含`key`及相同分頁欄位，`rows`僅列`id`、`workDate`、`minutes`、`note`。未展開時為null，不預載所有說明；不存在／外租戶／無權群組統一404。明細除總覽的permission／scope外，逐專案再要求`issue.read`才可回卡片名稱及說明，總計也限於此可見範圍；角色名稱不作判斷。

`WorkspaceTimeReport`負責既有授權與範圍，`MemberTimeDetails`只整理已授權的單人成員有效紀錄並投影分頁，不讀Session或直接查DB；沿用既有RecordStore相容邊界與ApiResponse，不另開第二套資料入口。

## 目前限制

這是本機版本，查詢沿用目前RecordStore批次讀取後彙整，尚未改成資料庫分頁／SQL聚合、匯出、計費或排程寄送。366日上限限制輸出日期數，不代表已針對大量歷史工時完成壓測。導覽回分析列表保留同一元件篩選；重新整理回預設本月，不提供儲存視圖。

## 驗證方式

`TimeReportTest`使用隔離記憶體SQLite，驗證三組加總、Epic不重複、作廢／範圍排除、零日、交集、外租戶ID、偽造角色、manager升降、逐專案資格與366日邊界。`frontend/tools/time-analysis-check.js`使用隔離Laravel API及編譯版，驗證三入口／篩選／錯誤／權限撤回與1440、768、390尺寸；測試不使用本機業務資料。

2026-11-06實用性review、圖表互動及seed規則見[工時分析review](time-analysis-review-2026-11-06.md)。總覽交叉聚合只在原已授權／未作廢／範圍內迴圈加總，不含原始工時說明；本輪成員明細另外讀取同工作空間卡片索引，僅投影可讀範圍的卡片與明確展開的說明。

成員明細增加`MemberTimeDetailsTest`純聚合Unit與`TimeReportTest`分頁／展開／非法輸入／逐專案卡片資格驗證；`frontend/tools/member-time-details-check.js`以隔離API驗日期與卡片切換、兩層20筆分頁、完整總計、範圍及草稿保留、背景更新、文字安全、錯誤重試與1440／768／390畫面。既有分析瀏覽器測試同步更新成員入口與明細中撤權流程。一般總覽表格仍未分頁，本輪分頁限新增的成員工時明細。

專用瀏覽器入口：`cd frontend && E2E_API_URL=<隔離API網址> npm run test:member-time`，與既有`test:time-analysis`分開執行（同占5175）。使用已安裝Chrome時可指定`PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH`；不得指向業務資料庫，產物在忽略的`frontend/artifacts/member-time-details/`。
