# 效能改善紀錄

日期：2026-11-09。依[複掃報告](performance-scan-2026-11-09.md)分階段改善，保留產品及API契約。

## 階段一：唯讀資格批次化

ProjectAccess提供只在單次唯讀用例內使用的快照，WorkspaceQuery與WorkspaceTimeReport每次讀取重新建立；沿用同一permissions判斷，沒有Redis ACL快取或寫入授權捷徑。Project／Member只讀一次，Default看板共用已讀Project；issue ID用Set式索引，避免工時逐筆線性搜尋。

五輪同工具、同資料、同query log配置比較：

| 場景 | 原SQL | 改善SQL | 改善中位ms |
| --- | ---: | ---: | ---: |
| seed.workspace | 3635 | 55 | 10.97 |
| seed.report2025 | 71 | 22 | 4.16 |
| plus200.workspace | 6435 | 55 | 12.96 |
| plus200.report2025 | 71 | 22 | 4.31 |
| plus200.24members.workspace | 7235 | 55 | 13.63 |

資料回傳JSON bytes維持原基準；SQL數不再隨工時／成員數增加。這是領域服務證據，HTTP及看板會於後續階段用相同隔離流程重測。原始統計見[改善基準](../.ecc/benchmarks/performance-improvements-2026-11-09.json)。

驗證：原61項／2416 assertions baseline通過；新增兩項驗證四角色與既有逐筆權限等價、重用Query後移除／停用資格生效、Owner／Developer增加200筆工時仍回完整資料且SQL不成長。完整63項／2458 assertions通過，修改檔Pint格式檢查。既有跨租戶、撤權重試、交易、Seed／匯入回歸保留。

## 階段二：刷新合併與看板索引

同scope的手動／定時刷新共用進行中的讀取；寫入或即時事件發生時保留一輪尾端重讀。切換工作區／登出／本地保存會失效舊generation，舊請求不阻塞新scope。只合併讀取，不合併寫入或重試識別。

看板共用Issue／成員／工時與關聯索引，泳道及狀態欄一次分組，不再逐卡掃描全Project；保持原有順序、Epic、關聯、工時及拖曳語意。

固定FIRST 500張卡、共507 issues／374 entries，隔離tmpfs API、production build及相同Chrome，HTTP暖身3次後30次序列取樣、瀏覽器3輪：

| 指標 | 複掃基準 | 本輪 |
| --- | ---: | ---: |
| Owner getWorkspace中位 | 370.39ms | 40.21ms |
| Owner getWorkspace p95 | 420.03ms | 46.41ms |
| 看板就緒中位 | 583ms | 167ms |
| 快速兩次刷新Workspace請求 | 2 | 1 |
| DOM元素／卡片 | 12250／500 | 12250／500 |

HTTP及頁面就緒主要受階段一SQL減量影響，不能歸因為前端索引單獨提升。密度切換仍約83～118ms，與原始分布接近；索引沒有解決大量DOM及layout成本，不宣稱此互動已加速。本輪沒有虛擬化或減少顯示資料。JS gzip由182738增至183418 bytes，CSS大小持平；bundle拆分仍待處理。

驗證：build、composable刷新競態／scope隔離／索引與舊彙總等價測試、500卡量測（含雙刷新請求斷言）、既有看板三尺寸操作、工作區切換／邀請／另一分頁409，以及完整瀏覽器業務回歸均通過。無主資料寫入、瀏覽器錯誤或API契約變更。

## 階段三：明確唯讀SQLite交易

AppServiceProvider註冊Laravel SQLiteConnection子類，保留框架transaction／savepoint／rollback／afterCommit生命週期。ApiExecution只對`getWorkspace`與`getTimeReport`啟用DEFERRED及`PRAGMA query_only=ON`，身分、permission與資料在同一讀取快照驗證；離開後於finally還原query_only。寫入與其他操作仍使用原本IMMEDIATE，沒有全庫改DEFERRED、放寬Session blocking、CSRF或跨分頁Workspace檢查。

新增三項Feature測試：實際API讀取啟用query_only、後續建立Project可保存；唯讀誤寫拒絕／例外傳遞／巢狀還原；獨立磁碟WAL雙PDO讀取快照期間另一writer成功、既有快照不漂移，而一般交易仍令另一writer得到SQLITE_BUSY，結束後可正常寫入。tempnam資料庫及WAL／SHM皆清理，不使用業務DB。

完整後端66 tests／2483 assertions通過；最終例外斷言補強後三項交易測試再次通過。真實隔離API的三種分析／撤權與成員兩層分頁／Epic工時／XSS／三尺寸回歸通過。上表HTTP與500卡數據於階段二完成後取得；階段三驗證鎖定機制，未另宣稱提高HTTP吞吐量。測試服務採序列PHP serve，不能用來推論正式多人容量。

## 仍有的限制

- 全量getWorkspace仍帶所有可讀Project／Issue／工時，首頁和分析仍會取整包；拆身分／導覽／目前Project API需另做契約與撤權清除設計。
- 500卡仍全量DOM；密度切換成本尚在，後續虛擬化需保留拖曳、泳道及鍵盤焦點。
- 工時報表仍在PHP聚合，群組分頁及日期SQL／索引候選尚未遷移；不能把查詢次數減量當作已完成資料量上限治理。
- 初始JS／Vuetify與MDI CSS尚未拆分。未新增Redis業務或ACL快取，既有Queue／Cache用途保留。
- 僅local合成人造資料證據；沒有真人RUM、正式多worker負載或新SLO承諾。

交付不修改schema、不清空使用者資料、不push。長駐Queue／Reverb在完成後重啟載入程式，隔離量測容器移除。
