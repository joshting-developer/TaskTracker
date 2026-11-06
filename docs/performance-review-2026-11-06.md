# 架構、效能與 Redis review

日期：2026-11-06，基準：`1be16cc`。本輪完成程式盤點、隔離量測與基礎設施驗證；下述重構與新快取為建議，尚未修改業務實作。

## 結論

最值得優先處理的是工作區讀取的授權查詢放大、全量資料回傳，以及唯讀請求取得 SQLite 寫入交易。工時報表已有租戶／期間限定查詢，值得保留；剩餘問題是授權重查、PHP 全量聚合與分頁前仍載入全部符合範圍的紀錄。

Redis 已存在且正常運作，不需要再加入第二個相同服務。現用於 Queue、Cache、Session blocking 與限流；Session 本體仍在 SQLite。先改善查詢，再評估報表聚合快取與非同步匯出。

## 可重現量測

執行根目錄的命令：

```sh
docker compose exec -T backend php < backend/tools/profile-reads.php
```

工具在 framework bootstrap 前覆寫環境，啟動後再次指定並檢查 SQLite `:memory:` 與空檔案路徑，才 migration／DemoSeeder；Queue sync、Broadcast null、Cache array，不使用業務資料庫或 Redis。測試密碼隨機產生，不列印。資料只存在該 PHP 程序記憶體。

使用正式 Repository／ProjectAccess／WorkspaceQuery／WorkspaceTimeReport 程式。基準為 2 個專案、4 名成員、14 張卡片、174 筆工時；增加的人造工時各為2025年不同工作日的15分鐘，不與seed的2026年紀錄重疊。額外成員沒有專案資格。

| 場景 | 工時筆數 | 成員數 | SQL 次數 | 單次耗時 ms | JSON bytes |
| --- | ---: | ---: | ---: | ---: | ---: |
| Workspace 基準 | 174 | 4 | 3,635 | 208.95 | 125,134 |
| Workspace 增加50筆 | 224 | 4 | 4,335 | 242.78 | 141,674 |
| Workspace 增加200筆 | 374 | 4 | 6,435 | 357.92 | 191,424 |
| Workspace 再增加20人 | 374 | 24 | 7,235 | 524.40 | 194,034 |
| 2025全年報表／無紀錄 | 174 | 4 | 71 | 7.23 | 18,524 |
| 2025全年報表／50筆 | 224 | 4 | 71 | 6.02 | 18,973 |
| 2025全年報表／200筆 | 374 | 4 | 71 | 7.49 | 19,131 |
| 2025全年報表／200筆／24人 | 374 | 24 | 71 | 8.20 | 19,991 |

另外只讀 `RecordStore::all('issues')`：14張卡與114張卡均為14次SQL；增加100張任務沒有出現逐任務關聯查詢成長。

重要差異：新增50筆工時多700次SQL，新增200筆多2,800次，正好每筆增加14次。基準中相同Workspace內部ID查詢1,297次、同型單筆audit預載511次、成員ID列表及全體User讀取各262次。是目前實作的重複查詢證據，與網路延遲無關。

限制：這是單程序、記憶體SQLite的領域服務取樣，啟用query log會增加開銷；耗時只供本機取樣參考，不是HTTP延遲、磁碟SQLite表現、p95或併發容量。JSON是`json_encode`後的服務資料大小，不含HTTP envelope、壓縮與傳輸。資料量仍小；未測100專案、萬筆卡片、多人同時寫入，也未做瀏覽器CPU profiler。兩輪取樣的SQL次數一致；未宣稱已完成加速。

## 問題與處理順序

### P1：授權熱路徑產生查詢放大（已量測）

位置：`backend/app/Authorization/ProjectAccess.php` 的 `permissions／can／eligibleMembers／forUser`、`backend/app/Services/WorkspaceQuery.php` 的 `getWorkspace／visibleTimeEntries／visibleNotifications`。

每次 `can` 都重新讀目前成員、專案與資格；`eligibleMembers` 在每個專案迴圈逐成員建立入口並再查。工時逐筆再次檢查同一Project的同一permission。`RelationalRecords::get` 每次清除內部識別映射，成員序列化又取得全空間成員ID與User資料，因此單次權限判斷不只一條SQL。

建議：建立**唯讀用例內**的授權快照，一次查目前成員、必要Project與ProjectMember映射；依相同permission ID規則計算。先按Project算出團隊／本人工時可讀集合，再篩工時，不在每筆紀錄重查。`eligibleMembers` 應一次載入有效成員與專案資格，避免P×M次DB查詢。

不要把快照做成Singleton或跨請求Redis ACL快取。寫入與requestId重試仍必須在交易內重新授權；若同一流程修改資格，不能繼續用修改前快照。下一請求撤權立即生效。新測試應比較「增加200筆同專案工時」前後SQL數接近固定，並保留停用／移除／降權／跨租戶測試。

### P1：工作區全量資料回傳（程式確認，大小已取樣）

位置：`WorkspaceQuery`、`frontend/src/App.vue::refresh`、`useWorkspaceClock.js`、`frontend/src/ui/boardRealtime.js`。

目前首頁、專案列表、工時分析都先取全部可見Project、Issue、工時、通知，包含卡片描述／留言／歷史。伺服器先讀整個租戶，再以PHP篩可讀範圍；沒有跨租戶讀取問題，但讀了不必要的同租戶內容。前端每30秒、保存後、看板訊號與重連又觸發全量刷新，接著取小待辦。

建議順序：

1. Workspace bootstrap只回必要身分、導覽及能力；專案列表有獨立摘要查詢。
2. 任務列表由後端依Project／篩選／穩定排序分頁；詳細頁才載入描述、留言、歷史。
3. 月曆只查當月範圍，通知及待辦只查本人可見內容並分頁；保留待辦轉卡所需的專案選單。
4. 看板訊號重查目前Project資料；能力更新／撤權仍有明確檢查，不能只憑WebSocket連線活著就信任權限。

現在列表每頁20筆只限制DOM，不限制API／DB工作量。這項需要前後端契約共同調整，不能單獨砍掉 `getWorkspace` 欄位。

### P1：唯讀也持有 SQLite IMMEDIATE 交易（程式確認，未併發壓測）

位置：`Http/ApiExecution::inWorkspace／authenticate`、`config/database.php`、`routes/web.php`；已核對安裝版本的 `vendor/laravel/framework/src/Illuminate/Database/SQLiteConnection.php`。

HTTP用例全部包在 `DB::transaction`。PHP8.5下這個Laravel實作採設定的 `BEGIN IMMEDIATE`；SQLite的IMMEDIATE會預先取得寫入交易。因此長工作區讀取、報表、SMTP連線驗證也可能延長其他Session寫入等待。Session route blocking只隔離同一Session，不能解決不同使用者共用SQLite的寫入競爭。

建議：明確區分讀／寫用例；純讀在必要時採DEFERRED一致性快照，寫入維持IMMEDIATE及同交易授權／revision／receipt。不要直接全庫改DEFERRED或移除交易。SMTP等外部IO不要持有業務DB交易。另以**獨立磁碟SQLite**測報表讀取與另一連線寫入、撤權競態、每日24h防超填及Session切換；記憶體單連線測試不足以驗證此項。

### P2：工時分析與成員明細仍是全量PHP聚合（程式確認）

位置：`TimeReportRepository::entries`、`WorkspaceTimeReport::get`、`MemberTimeDetails::get`。

優點：已限定租戶、有效entry、授權Project、日期與可選成員；一次join投影，174→374筆時查詢數不成長。71次中仍有授權及Project／Member序列化開銷，不能把它說成「報表只要1次SQL」。

剩餘：`get()`把符合範圍的原始紀錄全部轉Model／DTO，PHP再group／sort／slice。明細每頁20筆限制的是回傳；展開一組時目前也會先讀該成員整個期間所有note，再只回所選頁。

建議：Repository新增SQL `SUM／COUNT／GROUP BY`投影；日／人／專案聚合與篩選選項分開。明細群組SQL分頁，展開紀錄才以群組條件和穩定排序查20筆note；維持完整範圍總計、零日期補齊、每筆entry只計一次、同租戶複合join與撤權。先修P1再評估查詢計畫；現有 `workspace_id,member_id,work_date` 索引不是所有期間查詢的萬用索引。`workspace_id,work_date`或帶`voided_at`的複合／部分索引是候選，需EXPLAIN和較大量資料比較後才加。

### P2：每次填報工時掃描全空間歷史（程式確認）

位置：`Services/TimeEntryService::saveTime`。

每日上限驗證用 `all('timeEntries')` 讀所有資料及歷史，再按本人／日期／有效狀態篩選。長期專案每次新增工時都愈來愈貴。

建議：同交易查目前空間／成員／工作日的有效分鐘 `SUM`，排除目前entry。沿用每日上限與作者限制，並做兩連線同日填報競態驗證。上限驗證不能使用有TTL的Redis統計值。

### P2：子集合寫入每次刪除重建（程式確認）

位置：`Repositories/RelationalChildren::write`。

改任務狀態會重建既有留言／關聯，audit每個stream刪除後逐筆重插；歷史越長單次寫入SQL越多，並延長SQLite寫鎖。Board收錄1000項也逐項寫入，`ProjectBoards::fields`逐項 `require('issues')` 驗證。

建議：按用例差異更新；歷史採append並驗證前綴／position；留言及關聯不變時不要動。看板用租戶／Project限定的 `whereIn` 一次驗證，再分批bulk insert；超大參數集合需chunk。保持整筆交易／revision／重試及外鍵，不能單純跳過刪除而留下舊關聯。

### P2：小待辦與邀請資料的讀取範圍（程式確認）

位置：`Services/Todos::listing`、`Repositories/RelationalRecords::serialize`。

待辦／站內通知先讀同租戶全部資料再按參與人／收件者過濾；已完成／取消／轉卡只在UI收合，資料仍傳回。這是過量讀取，不等於已發現他人資料外洩。應用SQL限定本人、獨立未讀數、有效／歷史分頁；現有assignee索引不能自動涵蓋createdBy的OR條件，需要實際plan。

`workspace_ref`型別序列化會逐列 `Workspace::findOrFail`，全域邀請集合有另一個明確N+1候選；單一邀請讀取不是問題。宜按本次rows預載Workspace公開ID；避免為讀一個邀請預載所有租戶。此輪沒有大量邀請量測。

### P2：刷新缺少全域合併（程式確認）

`App.refresh`用generation丟棄過期回應，但舊HTTP仍執行；看板有自己的200ms debounce和running／pending，不能合併同時到達的輪詢、保存或其他事件。工作區回應整物件更新也觸發分析與成員明細重新查詢。

建議：以user／Workspace scope建立single-flight＋dirty再跑一次，切換或登出立即失效；業務寫入請求不能合併。後續若在隱藏分頁降頻，需要visibility恢復時補抓及保存前重新驗權，不犧牲撤權與草稿保護。AbortController只減少客戶端等待，不保證伺服器已停止SQL。

### P2：看板渲染與計算隨卡片全量增加（程式確認，未做瀏覽器壓測）

`ProjectBoard.vue` 的泳道／欄位在template重複filter，卡片反覆find成員；每張詳細卡的 `IssueContext` 各自掃Issues／Entries；Epic `epicSummary` 還有entries×children.some。列表 `filterIssues` 即使搜尋空白也先對每張卡find成員。看板所有卡片都進DOM，沒有虛擬化。

建議：目前Project建立共享computed的issueById、memberById、childrenByParent、minutesByIssue及lane/state矩陣；把索引或已算值傳入卡片。先做Map／Set和單次group，真實幾百／幾千卡profile後再選每欄虛擬化或分批顯示，保留拖曳及泳道位置。不能因為用了computed就假設多個卡片元件會共享同一次全表掃描。

### P3：可維護性與交付模式

- `App.vue`仍有563行，承擔hash導覽、工作區切換、草稿與跨功能協調；下一步按「工作區資料生命週期／導航」抽出，不按行數機械拆函式。
- 既有寫入仍走通用JsonOperationRequest／array／RecordStore；優先遷移任務及工時寫入的專用Request／DTO／Repository，保留receipt原始輸入簽章，避免只是把array轉移到另一個class。
- 安裝的Eloquent模型不是問題本身，不能為了少檔案刪除關聯／安全邊界。`preventLazyLoading`可作local／testing護欄，但無法抓到此輪大量的顯式store.get；需要SQL成長測試。
- Compose現在是開發用Vite及`artisan serve`，正式多人效能不能依此推斷；正式建置的靜態檔、PHP服務程序與queue監控應另規劃。既有build有chunk size advisory，路由／重型Markdown／圖表等按實際bundle分析安排lazy import；沒有這輪瀏覽器載入時間量測，不列成首要瓶頸。

## Redis 現況與放置位置

### 已存在，且已驗證

| 用途 | 現況／證據 |
| --- | --- |
| Docker Redis | `redis:8-alpine`、AOF、redis-data volume、healthcheck；無主機公開6379 |
| Queue | Compose與執行中 `QUEUE_CONNECTION=redis`，預設Redis DB0；探針dispatch並由worker消費成功 |
| Cache | `CACHE_STORE=redis`，cache connection DB1；探針以Cache讀到worker結果 |
| Session blocking | routes的block＋Laravel預設cache store，鎖連線default／DB0；不是Session資料搬到Redis |
| 登入／註冊／訂閱等限流 | 路由已有throttle，cache.limiter未另指定，執行中使用預設Redis cache |
| Session本體 | 執行中仍為database；本輪未改driver |
| Reverb | 已完成101 Upgrade及connection_established；ProjectSignals在commit後派Queue無內容訊號 |

以既有 `php artisan tasktracker:check-infrastructure`驗證，探針只產生臨時key／Job並清理，沒有寄信或發布業務事件。Compose既有各服務運作正常，沒有新增重複服務或清除volume。

### 新用途建議

| 順序 | 放置位置 | 方式與限制 |
| --- | --- | --- |
| 先保留 | 廣播Queue、限流、Session鎖 | 已用上Redis，優先修查詢與交易持有時間 |
| SQL改善之後 | WorkspaceTimeReport與Repository之間的聚合讀取服務 | 可短期快取聚合DTO；先新鮮授權，再讀正確scope的key；不快取HTTP整包回應或完整Eloquent物件 |
| 有大型報表時 | 新增匯出Job | Queue產檔，回傳工作ID；下載時再次驗權，不把同步HTTP改成長時間等待Queue |
| 量測Session寫入爭用後 | Laravel Session store | 可評估Redis driver，但需處理既有登入遷移／失效、cookie／CSRF、Session blocking、多分頁切換與備援；本輪先維持database |
| 多Reverb節點時 | Reverb scaling pub/sub | 單節點現在不需要額外開啟scale或再放一份業務事件資料 |

報表快取必要設計：

- Key至少包含版本、Workspace、操作者／有效授權scope指紋、起訖日期及成員／專案篩選；標題／姓名等投影依賴也要納入失效考慮。建議第一版只快取聚合數值，不快取成員明細note。
- 工時建立／修改／作廢、資料匯入／seed／reset及資格變動需更新資料版本。資料版本應與DB寫入一致地改變，再於commit後失效；單純Redis increment＋TTL不能保證故障／競態時不回舊資料。
- 查詢版本→查DB→寫cache可能遇到並行寫入；使用版本化key、必要時重查版本，避免舊結果污染新版key。stampede鎖需有限等待，錯誤釋放，不能把大查詢藏在全空間共用鎖裡。
- 聚合cache故障可受控回源，須監測回源洪峰；Session／授權鎖與限流不能一律套同樣的fail-open策略。
- Queue DB0／Cache DB1是邏輯區分，仍共用記憶體與服務故障域。業務快取大幅增長時再考慮獨立cache instance與eviction政策；不要在共用Queue／鎖實例直接用allkeys淘汰。
- 不將operation_receipts改成有TTL的唯一Redis紀錄；過期或淘汰會破壞防重複提交。SQLite仍為業務真實來源。

本輪沒有新增報表Cache，原因是尚未選擇可接受的資料延遲／失效契約，且查詢放大比重複報表聚合更優先。這些是明確設計建議，不代表已實作。

## 建議階段與驗收

1. **唯讀授權批次化＋查詢成長測試**：先切斷每工時14條SQL與P×M資格重查；對照本工具，輸出／ACL完全相同，新增SQL budget測試，不把數字鎖死為當前3635。
2. **查詢API與唯讀交易拆分**：Workspace bootstrap、Project tasks、calendar／todos／notifications各自限定範圍；合併刷新。兩連線SQLite驗證read/write與撤權／切換。
3. **工時SUM／GROUP BY／明細SQL分頁＋寫入增量化**：保持總計與歷史，EXPLAIN／較大資料量驗證索引和分頁。
4. **Vue索引與看板渲染量測**：真實大資料操作、拖曳與背景更新量測，再判斷虛擬化。
5. **報表Redis快取／匯出**：前四階段後量測仍有需要時加入版本化聚合快取，驗證租戶／撤權／rollback／Redis故障／seed失效。

## 本輪交付與限制

新增可重現隔離取樣工具及本報告，兩輪結果確認查詢成長；Redis／Cache／Queue／WebSocket實際探針通過，執行中driver確認與PHP語法檢查通過。本輪未修改產品前後端或schema、未搬移Session、未更動使用者資料，故未重跑整套功能測試或UI build，也不宣稱發現項目已修復。

## 官方來源

查閱：2026-11-06。程式實況與官方原則分開；以本專案安裝版本及配置為準。

- Laravel [Eager loading與preventLazyLoading](https://laravel.com/framework/docs/13.x/eloquent-relationships)：預載可避免關聯N+1；顯式重複查詢仍需另外量測。
- Laravel [Cache與atomic locks](https://laravel.com/framework/docs/cache)：Redis cache及鎖的使用依據。
- Laravel [Session blocking](https://laravel.com/framework/docs/session)：Session資料driver與blocking所用的atomic cache lock是不同責任。
- SQLite [Transaction](https://www.sqlite.org/lang_transaction.html)：IMMEDIATE寫入交易與SQLITE_BUSY風險。
