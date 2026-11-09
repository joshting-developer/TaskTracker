# Addy／ECC 效能複掃

完成：2026-11-09（Asia/Taipei）。SQL／HTTP／瀏覽器量測於2026-11-06取得，11-09補驗同容器PHP／SQLite雙連線鎖並整理；基準程式commit `0434591`，工作區沒有產品程式變更。本輪使用專案內Addy主診斷、ECC補量測，Laravel／Vue規範配合原始碼審查。

## 結論與優先順序

| 順序 | 已觀察問題 | 證據 | 建議改動 |
| --- | --- | --- | --- |
| P1 | 工作區讀取逐工時重查權限 | Owner 3635→6435 SQL；Developer 2729→6529 | 唯讀用例內批次授權、按Project算可讀集合 |
| P1 | 全量bootstrap拖累首頁與分析 | 增加200筆工時後Owner工作區HTTP中位203→351ms；分析刷新仍先查整個工作區 | 分離身分／導覽、目前Project與詳細資料查詢 |
| P1 | 純讀取取得SQLite寫鎖 | 獨立WAL雙連線，IMMEDIATE下寫入5/5鎖住；DEFERRED 5/5提交 | 區分唯讀／寫入交易；保留寫入原子授權與版本保護 |
| P2 | 刷新未合併 | 雙擊一次刷新控制，重現兩次getWorkspace | scope限定single-flight＋dirty後再跑；保留切換／撤權失效 |
| P2 | 大看板一次渲染所有卡片 | 500卡約12250元素、97ms長任務；密度切換中位84ms | 共享computed索引與lane/state矩陣，再評估分批／虛擬化 |
| P2 | 工時查詢日期條件未被用於索引搜尋 | EXPLAIN以workspace_id索引取得time_entries | SQL聚合／群組分頁與日期索引候選實測，不盲加索引 |
| P3 | 初始資源偏大 | 單JS 550750 bytes、CSS 694930 bytes | 先分析CSS／MDI用量與路由載入，再做必要拆包 |

這是**資料量成長比較**，不是優化前後比較；尚未修復任何業務實作，也沒有把較小資料集的結果當作加速成果。完整去識別數據見[基準JSON](../.ecc/benchmarks/performance-scan-2026-11-09.json)。

## 量測條件與限制

- 領域服務：既有`backend/tools/profile-reads.php`，5個獨立PHP程序，每輪全新`:memory:`SQLite、query log啟用。
- HTTP：另建`tasktracker-performance-scan`容器、loopback13029、獨立`/data` tmpfs SQLite；覆寫compose project／service標籤，不掛主資料卷。PHP8.5.12／SQLite3.40.1、`artisan serve`，database Session、file Cache、sync Queue、Broadcast null，沒有連主Redis。
- 每角色／端點3次暖身＋30次序列取樣；p95採nearest rank，即第29個排序值。這是小樣本的本機描述值，不是正式SLO或多人容量。
- 瀏覽器：production build＋Vite preview5175代理到隔離API，Chrome156.0.8078.80，1440×1000，無網路／CPU節流；Owner真實登入狀態。每輪新browser context，seed／expanded各5輪、cards3輪。資料ready與UI切換時間含Playwright定位、輪詢與兩次rAF，不等於INP。
- 初始資料2專案／4人／14卡／174工時；expanded只增加200筆2025年不同日期的15分鐘工時，卡片數不變；cards再只擴FIRST至500卡，總507卡／374工時。報表固定2025全年，所以seed為0筆、expanded與cards為200筆；瀏覽器分析預設本月，不與2025 HTTP報表混成同一用例。
- 沒有新做HTTP並發壓測、真人RUM、手機／弱網、PHP CPU flamegraph或Redis命中率測試；未計算正式Web Vitals p75。PerformanceObserver的事件與長任務是lab線索，不能據此宣稱INP達標。
- build通過，仍有既有500KB chunk advisory。沒有改產品程式，未重跑整套PHP功能測試；本輪工具的HTTP成功、瀏覽器runtime錯誤及資料就緒有明確檢查。

## 1. SQL成長：五輪結果一致

| 領域場景 | SQL數 | 時間中位ms | 最小–最大ms |
| --- | ---: | ---: | --- |
| Owner seed工作區 | 3635 | 208.49 | 206.52–216.38 |
| 增50筆工時 | 4335 | 241.14 | 239.16–256.21 |
| 增200筆工時 | 6435 | 359.29 | 356.21–362.34 |
| 再增20名無專案資格成員 | 7235 | 532.71 | 527.92–636.21 |
| seed期間報表 | 71 | 7.67 | 6.95–8.96 |
| 增200筆期間報表 | 71 | 6.75 | 6.74–7.82 |
| 單讀14張任務 | 14 | 1.17 | 1.11–1.27 |
| 單讀114張任務 | 14 | 4.00 | 3.94–4.25 |

報表seed與expanded的幾毫秒差不能解讀成資料增加反而加速；這只是小量資料下的查詢數與時間樣本。任務子表批次預載有效，沒有逐卡SQL成長；工作區的授權熱路徑才是主要放大來源。

補一輪角色對照：Manager與Owner同為3635→6435 SQL；Developer為2729→6529。`WorkspaceQuery::visibleTimeEntries`會分別呼叫team及本人讀取資格，而`ProjectAccess::permissions`每次重新讀成員／Project／資格。對本人的工時會進兩條判斷路徑，這組fixture中Developer新增每筆額外19次SQL、Owner14次，不能假設「低權限看得少一定比較快」。

具體位置：`backend/app/Services/WorkspaceQuery.php`、`backend/app/Authorization/ProjectAccess.php`、`backend/app/Repositories/RelationalRecords.php`。後者每次`get`重置映射；成員序列化取得全體User，讓重複授權的每一步更昂貴。

## 2. 登入後HTTP實測

時間單位ms；回應bytes包含實際JSON envelope，未壓縮。

| 角色／讀取 | seed中位／p95 | expanded中位／p95 | seed→expanded bytes |
| --- | --- | --- | ---: |
| Owner工作區 | 203.39／238.55 | 351.08／365.04 | 125153→191443 |
| Manager工作區 | 200.72／217.07 | 348.95／362.91 | 125121→191411 |
| Developer工作區 | 148.25／157.62 | 342.09／402.74 | 69032→135322 |
| Owner期間報表 | 12.69／13.88 | 15.28／15.97 | 18543→19150 |
| Manager期間報表 | 12.52／13.06 | 15.73／17.23 | 18543→19150 |

`getTodos`在這些沒有待辦的fixture約8–9ms，不能推論大量歷史待辦也同樣便宜。Developer不具workspace報表權限，沒有為湊表格越權量報表。

擴到500卡後Owner工作區中位370.39ms、p95 420.03ms、回應448956 bytes；報表仍約15.29ms。這個量級先處理工作區讀取比先快取報表更有根據。

## 3. 實際頁面請求與就緒

| Owner場景 | seed中位ms | expanded中位ms | cards中位ms |
| --- | ---: | ---: | ---: |
| 登入狀態首頁資料ready | 406.4 | 551.5 | 566.8 |
| 首頁→看板及資料刷新完成 | 267.0 | 416.2 | 583.3 |
| 看板手動刷新完成 | 317.2 | 466.7 | 533.6 |
| 分析頁手動刷新與報表ready | 317.0 | 466.3 | 483.8 |

各場景請求序列在三組fixture一致：

- 首頁：csrf-cookie→getWorkspace→getTodos。首頁只顯示小待辦，但仍取所有可讀任務與工時。
- 進看板：getWorkspace、realtime/config、getTodos。隔離服務關閉廣播，所以此處沒有WebSocket事件流量量測。
- 看板手動刷新：getWorkspace→getTodos。
- 雙擊刷新：getWorkspace、getWorkspace、getTodos；generation可丟舊回應，無法阻止兩個昂貴請求執行。每個phase第1輪驗這一情境，不宣稱做了高並行壓測。
- 分析頁刷新：getWorkspace、getTodos、getTimeReport；工作區整物件更新經workspaceVersion觸發報表重查。這是現有架構成本，不等同資料錯誤；權限改變仍應重查，優化不能直接停止刷新。

來源：`frontend/src/App.vue::refresh`、`useWorkspaceClock`、`TimeAnalysis.vue`的workspaceVersion watch；`useBoardRealtime`已有自己的debounce／running，但不涵蓋所有刷新來源。

## 4. 大看板與初始bundle

固定374筆工時，比較7卡與500卡：

| 實際畫面 | 卡片 | 卡片context區 | 全頁DOM元素 | 密度切換中位ms | 觀察到最大long task ms |
| --- | ---: | ---: | ---: | ---: | ---: |
| expanded | 7 | 7 | 418 | 33.2 | 50 |
| cards | 500 | 500 | 12250 | 83.7 | 97 |

切換樣本為每頁5次，expanded共25次、cards共15次，詳細／精簡交替；包含自動化等待，不是精準純Vue render時間。長任務是在整個測試流程收集，沒有完整CPU trace可把97ms全部歸給特定computed。

原始碼仍顯示每張`IssueContext`各自filter工時、find Epic／父項／關聯，`epicSummary`有entries×children.some；泳道及狀態欄也反覆filter。優先共享索引／單次group，再用同fixture量測。500卡全部進DOM已被實際確認，虛擬化若要做仍須保留拖曳、焦點與泳道收合。

build結果：

| 資源 | 原始bytes | gzip bytes |
| --- | ---: | ---: |
| 單一JS入口 | 550750 | 182738 |
| 單一CSS入口 | 694930 | 110835 |

`frontend/src/main.js`靜態引入所有App功能、Vuetify styles及`@mdi/font`完整CSS；MDI來源CSS約417931 bytes，但不能直接等同最終bundle可移除量。產物另有403KB woff2等字型檔；本次已登入頁面的resource紀錄只有JS／CSS，沒有觀察到字型下載，不把所有產物大小加成初次網路傳輸量。需先核對Auth表單圖示及Vuetify icon使用，不能只刪整份樣式。

## 5. SQLite交易與報表query plan

新工具[profile-sqlite-locks.php](../backend/tools/profile-sqlite-locks.php)以全新tempnam DB、兩個PDO連線、WAL／busy_timeout=200測試。2026-11-09容器PHP8.5.12／SQLite3.40.1結果：IMMEDIATE讀取未釋放時，另一連線寫入5/5 `locked`；DEFERRED 5/5 `committed`。時間完整存基準JSON。這驗證引擎機制，**不是兩個Laravel HTTP使用者的完整競態測試**；Session／ACL／revision仍需在正式改交易時共同驗證。不得為了通過效能測試全庫改DEFERRED。

實際報表SQL在memory fixture上：

```text
SEARCH workspaces USING COVERING INDEX workspaces_public_id_unique (public_id=?)
SEARCH time_entries USING INDEX time_entries_scope_unique (workspace_id=?)
SEARCH projects USING INTEGER PRIMARY KEY (rowid=?)
SEARCH workspace_members USING INTEGER PRIMARY KEY (rowid=?)
SEARCH issues USING INTEGER PRIMARY KEY (rowid=?)
```

不是跨租戶全表SCAN，但time_entries索引搜尋沒有使用work_date條件，會對租戶候選紀錄再篩日期。這是小資料plan，沒有加索引的前後實驗，不宣稱加某索引必然有效。報表仍`get()`後PHP彙總及分頁，SQL數固定不代表大量原始entry的CPU／記憶體成本固定。

## 重跑與交付

```sh
# 根目錄，僅memory／獨立暫存SQLite
 docker compose exec -T backend php < backend/tools/profile-reads.php
 docker compose exec -T backend php < backend/tools/profile-sqlite-locks.php

# frontend；必須先另建隔離seed API，禁止使用主3002／5174
 E2E_API_URL=http://127.0.0.1:13029 E2E_SEED_PASSWORD='<隔離測試密碼>' PERF_PHASE=seed node tools/performance-scan.js
```

`PERF_PHASE`可為seed／expanded／cards，**只是結果標籤，不會產生資料或修改卡片**。本輪expanded是在隔離DB複製一筆工時模板200次，公開ID固定不同、工作日2025-01-01起各日15分鐘；cards複製FIRST Task模板493次，清空parent、保留欄位／Epic，總500卡。外部fixture建立需先驗DB路徑及資料數，不能直接對不明API設phase就當成同樣條件。工具本身只登入、讀API、切換UI及截圖，結果寫忽略的`frontend/artifacts/performance-scan/`；正式基準只納去識別統計，不提交cookie、密碼、原始payload或截圖。

本輪完成兩工具語法／PHP格式、production build、三角色共627次HTTP暖身與取樣（兩組各264次＋cards99次；不含登入／CSRF及瀏覽器請求）、13輪真瀏覽器流程與零runtime錯誤、領域5輪、角色補量與query plan、原生PHP雙連線鎖測試。沒有新效能修正，因此沒有keep／revert實驗贏家，也不設定任意CI門檻。隔離容器及tmpfs於結束清理，主服務未重啟、業務資料未改。

建議下一階段先做P1授權批次化，保留本基準作對照；SQL成長應接近用例固定成本，再看HTTP／頁面就緒是否超過量測雜訊地改善。其他未量到的項目不可因本輪掃描而宣稱已排除。
