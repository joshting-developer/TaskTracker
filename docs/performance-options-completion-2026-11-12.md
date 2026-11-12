# TT-240 有界選項接線完成

2026-11-12。先驗證之前分階段的接線，再補剩餘問題；沒有新增第二套後端端點或恢復Workspace快照。

| 用途 | 現行資料來源 | 已選值與範圍 |
| --- | --- | --- |
| 卡片負責人／列表篩選 | Project member-options | 30筆候選；現有assigneeName及使用者選過的選項保留 |
| Epic／父卡／關聯 | Project issue-options，kind/exclude | 30筆；詳細頁references＋當頁＋已選候選，活動另讀 |
| 看板收錄 | Project issue-options | 本輪從較重issues列表改成最小候選；30筆，原selectedIssueIds不受當頁或搜尋覆蓋 |
| Project負責人 | project-lead-options | 建立／編輯用途資格，保留Project leadId／leadName |
| Todo承辦 | todo-assignees | 當次Workspace有效成員，30筆搜尋／分頁 |
| Todo轉卡Project／負責人／關係 | project-options、單Project、member-options／issue-options | 先限定可寫Project；切Project取消舊資料／選項，寫入再驗資格 |
| 月曆工時任務／成員 | Project issue-options／member-options | 當月摘要不作全候選；編輯用當日entry.issue保留已選值 |
| 單卡詳細頁填工時 | 目前卡片固定參照 | 鎖定此卡，不需要列全Project或全部工時 |

## 本輪修正

- BoardDialog改共用usePagedLookup，候選不再讀有工時／估時投影的一般任務列表，失敗有重試、載入中停用翻頁，不把空候選當成取消原收錄。
- 第二頁輸入新搜尋時，重設頁碼原本會觸發立即查詢並取消250ms debounce；現在保留搜尋等待，真正翻頁仍能立即載入。新的搜尋會取消舊transport，generation拒絕取消後仍回傳的舊資料；卸載清timer與請求。
- 子任務編輯時，詳細頁既有父項reference只含顯示欄位，沒有epicId；原watch會把正確Epic覆寫成undefined。現在僅完整新候選明確帶epicId時才同步歸屬，既有父項保留原卡片值。伺服器仍驗一層父子與Epic一致性，沒有放寬驗證。
- API、permission／scope、revision／requestId不變；現有後端候選已具SQL範圍、穩定排序、最大100／UI30，不為本次修復另開通用大接口。

## 資料存取審查

正常路由的選擇器只載當頁顯示欄位，選取後保留必要參照；不以每次候選回應覆寫原form關聯／基準revision。Project不可讀／撤權時現有access流程清空工作區內容；停用／舊指派值保留既有顯示與後端寫入規則，不能從候選快取放行。選項不是編輯詳細快照。

BoardDialog每次30筆，搜尋按SQL且有250ms debounce／取消；CSS與元件仍沿既有UI，未為效能改導航。元件內兼容舊props陣列的支線仍存在，舊Workspace入口退役由TT-180追蹤；這不等於正常選項仍依賴全量資料。其他P1／全域輪詢卡未結案，不宣稱全站效能通過。

## 驗證

- 正式build Chrome：145卡、第一頁30筆且無description/history/comments/spentMinutes/childCount；perPage101返回400。父項OPT-9／Epic OPT-8均在30筆之外，搜尋並編輯OPT-10後保留兩個ID。
- 同腳本看板跨搜尋選父卡／Epic，換搜尋並翻頁後保存仍為原兩個ID；Network只走issue-options，沒有候選issues/getWorkspace/getTodos；1440／768／390px無橫向溢位與pageerror。
- 真實月曆腳本：查詢摘要972B、bulkReads=0、按月／日／候選／填報流程通過。
- 真實personal-query：25待辦20＋5、原稿／歷史按需、轉卡Project／承辦候選、通知定位與版本同步通過，bulkReads=0。
- 受影響API隔離測試11 tests／921 assertions通過（含原生MySQL board投影與memory的scope／撤權／候選）；未把整批稱全部原生MySQL。使用獨立tasktracker-perf-test／tasktracker_test；無業務庫fixture。
- build、API client、composables（含第二頁搜尋debounce及慢回應拒絕）通過。

重跑新腳本：`E2E_API_URL=http://127.0.0.1:13029 PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' node frontend/tools/lookup-options-check.js`。截圖在忽略的frontend/artifacts/lookup-options。
