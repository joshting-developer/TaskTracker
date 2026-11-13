# TT-277 工時分析讀取範圍

原本日期／專案篩選之前, 會先讀整個工作區的專案、成員、專案授權及歷史。這次讓Repository直接以SQL套租戶與有效permission範圍, 只在聚合結果附上需要的姓名與專案名稱

摘要、單人成員明細、專案／成員選項分成四個GET入口, 見[查詢契約](query-api-v1.md)。Service改成無狀態, Repository由建構子注入, 當次資格由交易邊界傳入。選項每頁最多100筆；成員明細和展開紀錄各20筆, 明細不再重算所有成員×專案配對。摘要超過2000個配對會要求縮小範圍, 不以截斷資料冒充完整總計

## 資料存取審查

1. 摘要只需日期總計與成員／專案配對, 不讀描述、留言、audit_events或原始工時說明；選項只回id/name
2. 選項最多100筆且以name/id穩定排序；摘要配對最多2000, 日期最多366天；明細維持穩定排序及雙層20筆分頁
3. 租戶、授權專案、期間、指定專案及成員先進SQL, 不在PHP全載後再篩選
4. 授權專案用EXISTS與子查詢, 不載Workspace快照或全部projectMembers
5. 卡片標題僅明細需要, 填報說明僅展開群組讀取；明細另需issue.read
6. 無定時輪詢, 進頁／套用／手動重整或展開才讀；選項有取消及過期回應保護。1001個專案的隔離MySQL服務量測由12查詢降到3查詢, 不代表正式HTTP p95
7. memory與MySQL驗105個無關專案＋1000筆歷史後回應逐值相同且小於6000 bytes, 不查audit_events／description；驗搜尋分頁、跨頁不重複、撤權、未授權專案、2100個配對拒絕及窄範圍仍正確
8. 本卡仍待人工畫面核准與正式視覺比較, 不宣稱整體效能或UI驗收完成；TT-280／278／279仍是後續指定卡片

## 驗證與量測

- 隔離Compose tasktracker-codex-perf, MySQL8.4.13, tasktracker_test, 不使用業務資料庫／共享Redis
- 同一份1001專案、1000筆專案歷史、10000張無關卡片, 指定一個專案及30天, 沒有符合期間的工時。舊新交錯各9次, 排除前2次暖機：服務中位數58.45→1.67ms, 查詢12→3, 歷史查詢3→0, 摘要JSON48638→1707 bytes。總計相同；這是無關metadata成本實驗, 不是大量有效工時或正式網路延遲
- 舊有一萬筆有效工時案例及權限／日期／Epic各算一次的測試保留, 相關後端28項／1037assertions通過。合成量測見[evidence](evidence/tt277-time-report-comparison.json)
- test:time-analysis驗三種分析、Owner／Manager與撤權、篩選、日期上限、空結果、錯誤重試, 三尺寸欄位不能被遮住；test:member-time驗日期／卡片分組、兩層分頁、XSS文字與三尺寸, 通過
- Design Taste app inspector檢查移除腳本、正確載入CSS的合成DOM, 三尺寸findings為空；不取代互動測試或人工基準核准
- 前端build、API client及composables通過；限定Pint、Skill integrity通過。原始log與合成截圖留忽略artifacts/tt277及frontend/artifacts, 不進Git
- 原生本機PHP缺少舊版ICU動態庫, 未用它驗證；PHP測試與格式化均使用專案容器, 不調整使用者PHP安裝

舊RPC保留第一頁100筆options供過渡, 新前端已改GET搜尋分頁。沒有新增PAT工時API、索引、Redis快取或部署改動
