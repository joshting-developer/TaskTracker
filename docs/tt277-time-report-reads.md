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
8. Josh已明確核准13張合成畫面, 同資料重播比較通過；不宣稱整體效能驗收完成, TT-280／278／279仍是後續指定卡片

## 驗證與量測

- 隔離Compose tasktracker-codex-perf, MySQL8.4.13, tasktracker_test, 不使用業務資料庫／共享Redis
- 同一份1001專案、1000筆專案歷史、10000張無關卡片, 指定一個專案及30天, 沒有符合期間的工時。舊新交錯各9次, 排除前2次暖機：服務中位數58.45→1.67ms, 查詢12→3, 歷史查詢3→0, 摘要JSON48638→1707 bytes。總計相同；這是無關metadata成本實驗, 不是大量有效工時或正式網路延遲
- 舊有一萬筆有效工時案例及權限／日期／Epic各算一次的測試保留, 相關後端28項／1037assertions通過。合成量測見[evidence](evidence/tt277-time-report-comparison.json)
- test:time-analysis驗三種分析、Owner／Manager與撤權、篩選、日期上限、空結果、錯誤重試, 三尺寸欄位不能被遮住；test:member-time驗日期／卡片分組、兩層分頁、XSS文字與三尺寸, 通過
- Design Taste app inspector檢查移除腳本、正確載入CSS的合成DOM, 三尺寸findings為空；不取代互動測試或人工基準核准
- 前端build、API client及composables通過；限定Pint、Skill integrity通過。原始log與合成截圖留忽略artifacts/tt277及frontend/artifacts, 不進Git
- 原生本機PHP缺少舊版ICU動態庫, 未用它驗證；PHP測試與格式化均使用專案容器, 不調整使用者PHP安裝

舊RPC保留第一頁100筆options供過渡, 新前端已改GET搜尋分頁。沒有新增PAT工時API、索引、Redis快取或部署改動

## 核准畫面比較

Josh回覆「核准這批畫面」, 核准紀錄在[approval](visual-baselines/tt277-time-analysis.json), 原圖雜湊與固定日期／測試來源在[manifest](evidence/tt277-visual-manifest.json)。圖檔只留忽略artifacts/tt277/approved, 換機缺原圖須還原已核准bundle, 不能自行重建冒充核准

三種分析共9張及成員明細4張, 沿用既有policy.json的threshold=0.02與maxDiffPixels=0, 不加遮罩或排除抗鋸齒像素。12張位元組相同；日期明細圖僅11個像素的單通道差1, 依既有YIQ容忍度為0個差異像素。比較器實測拒絕8400像素退化注入, 失敗會清掉舊成功產物, 還原後13張再次通過

分析fixture只在明確指定的隔離MySQL固定兩個合成專案ID, 避免圖表依隨機ID排序；成員明細用原核准帳號及資料重播, 避免隨機工時ID改變同日說明順序。私有fixture位置.local/tt277-reviewed-fixture.json, 不進Git。固定測試日期2026-11-13, 不改產品時鐘

重跑方式：

```sh
E2E_VISUAL_FIXTURE=1 E2E_COMPOSE_PROJECT=tasktracker-codex-perf E2E_API_URL=http://127.0.0.1:13089 E2E_FRONTEND_PORT=5291 PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' npm --prefix frontend run test:time-analysis
E2E_REVIEW_FIXTURE="$PWD/.local/tt277-reviewed-fixture.json" E2E_VISUAL_FIXTURE=1 E2E_API_URL=http://127.0.0.1:13089 E2E_FRONTEND_PORT=5291 PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' npm --prefix frontend run test:member-time
node frontend/tools/compare-reviewed-time-analysis.js
```

比較器使用鎖定版Playwright的PNG decoder, 依相同YIQ距離與原policy逐像素比對, 不新增依賴。這批為TT-277局部覆蓋, 不代表其他Agent正在修改的頁面已通過視覺驗收
