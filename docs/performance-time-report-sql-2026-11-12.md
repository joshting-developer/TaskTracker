# TT-182 工時SQL聚合與分頁

2026-11-12，套用本地TaskTracker／Laravel／Addy與ECC規範。

TimeReportRepository原先取回期間內全部有效工時，WorkspaceTimeReport與MemberTimeDetails才在PHP加總／排序／切頁。現在由Repository以相同租戶、有效專案、日期、作者及未作廢條件執行兩次SQL聚合：成員×專案配對、工作日。Service維持permission授權、名稱映射、零日期補齊與原JSON契約，不再建立每筆工時DTO。

成員明細以SQL GROUP BY、COUNT子查詢、穩定排序與LIMIT／OFFSET執行；群組與指定群組展開各20筆，超出頁碼仍收斂到最後頁。只有展開查詢讀note，不讀歷史。日期分組維持日期倒序／編號／卡片ID，卡片分組維持分鐘倒序／ID；原始紀錄維持日期倒序／公開ID。Epic及子卡的原始有效工時各計一次，不重新彙加Epic投入。

舊PHP分組器移至tests/Support/LegacyMemberTimeDetails作獨立結果對照；刪除不再使用的TimeReportEntryData。沒有更改HTTP輸入、輸出或Vue呼叫方式。

## 同條件量測

隔離Compose `tasktracker-perf-test`、MySQL 8.4.13、tasktracker_test schema，DatabaseTransactions回滾。10,000筆1分鐘原始工時、30卡（含Epic）、100工作日、1成員×專案配對。使用PHP Feature HTTP Kernel各5次連續取樣，第一筆程序冷、其餘較暖；不是網路HTTP p95、遠端拓樸或正式容量測試。

| 項目 | 原版 | SQL聚合 |
| --- | --- | --- |
| 摘要載入PHP列數 | 10,000原始紀錄 | 1配對＋100日聚合列 |
| 工時SQL次數 | 1 | 2 |
| 5次請求中位數 | 166.08 ms | 29.41 ms |
| 取樣範圍 | 144.19–499.42 ms | 28.69–43.71 ms |
| 明細原始列 | 先取整段再切頁 | 指定群組最多20筆 |

EXPLAIN顯示使用既有`workspace_id/member_id/work_date`索引。窄日期EXPLAIN ANALYZE在10,000筆中回傳100列、約0.9 ms的index condition lookup。這是該fixture的讀取節點結果，不代表儲存引擎只檢查100個index tuples；沒有強制索引或新增未證明收益的日期索引。完整統計與計畫見[去識別基準](../.ecc/benchmarks/time-report-sql-2026-11-12.json)。

## 八項資料存取審查

- 呼叫端只需分析期間內加總、名稱及當頁明細；不需要原始全部note／history。
- 日期上限366日，SQL WHERE沿用授權專案交集、作者及有效紀錄；明細SQL20筆，排序含唯一ID。
- 聚合資料列仍隨成員×專案配對數成長，options／授權目錄仍沿用現有Workspace成員與Project清單；未宣稱整個報表常數空間。大量組織目錄／圖表配對上限仍需按實際規模另設產品契約，既有TT-180／226尚未完成。
- 一次使用者分析請求，沒有新增輪詢或快取；兩次聚合增加一次SQL RTT，以減少原始列傳輸及PHP物件。遠端RTT已依使用者決策改成本地，未以本次數字代替遠端驗證。
- 明細／展開COUNT仍需掃描符合範圍的群組，深OFFSET不是cursor；資料量更大時仍有SQL計算成本。
- API envelope、選項、授權、作者及期間語意保持，無前端契約遷移。

## 驗證

- 變更前TimeReportTest：3／181。
- 變更後隔離memory：TimeReportTest＋舊純計算參考測試4／187；碰撞公開ID跨租戶、作廢、日期、作者與有效權限、空資料、翻頁／頁碼收斂、NOT_FOUND／輸入拒絕維持。
- 原生MySqlTimeReportTest：1／73，10,000筆總數、兩種群組與舊算法逐欄相等、群組20＋10不重複、展開limit20 offset20、原始note僅按需讀、窄日期100分鐘及EXPLAIN ANALYZE。
- 原生MySqlApiTest：17／510，含工時／撤權／版本／租戶及既有分責查詢。
- 真實Chrome＋隔離HTTP：member-time與time-analysis通過，涵蓋兩層分頁、Epic直接工時、三種分析加總、XSS、錯誤重試及三尺寸。
- 初次瀏覽器測試因常駐HTTP仍引用已移除舊class失敗；重啟隔離backend載入新程式後兩套通過，未刪測試斷言。
- Pint與diff check。主業務庫未seed／壓測，沒有新增migration或改資料。

### 格式驗證補充

TT-224交付期間確認原服務掛載唯讀，先前直接exec Pint未能完成格式寫入。已改一次性無網路可寫lint容器，補修Repository及兩個測試檔的PHPDoc／import格式，無業務變更；最後原生大資料與完整memory回歸通過。驗證以本次實際lint結果為準。
