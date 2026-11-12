# TT-227 重播快照容量與保留契約

2026-11-12。**本卡交付容量與契約分析，未更換儲存格式、刪除receipt或設定TTL。** 現有同requestId原結果、輸入簽章及重播前授權契約維持。以下「現況／必要不變條件」與「後續建議」分開；保留天數、過期API及壓縮上線尚未由使用者採納或實作。

## 現況盤點

- `RequestReplay::execute`：先由業務入口驗證當次資格、permission／scope／作者條件，再以Workspace＋userId/requestId找receipt；signature為operation及完整輸入的canonical SHA-256。原輸入不另存，成功result與signature同交易保存。
- 同key同輸入回**原始結果**，即使Model已有更新；改輸入回409 `REQUEST_CONFLICT`。資格失效時先拒絕，不回舊result。不能用重新讀Model代替原快照，也不能略過重播前授權。
- `operation_receipts`／`todo_operation_receipts`：id、public_id（userId:requestId）、workspace_id、signature、JSON result、legacy_attributes。唯一鍵為workspace_id/public_id；目前沒有operation／resource的獨立欄位，也沒有created_at／expires_at或清理命令，因此現況**無到期政策**。
- 一般卡片create/update/addComment回完整卡片，包含描述、全部comments／history；每次成功都保存當次完整結果。工時、Project、ACL／設定結果依操作而異，不把所有receipt都等同Issue大小。
- Todo建立／編輯已只回id/revision；它的業務歷史保存在原資料，不在receipt複製全文。轉卡會另外進入Issue寫入及其receipt，不能只看Todo表就說全流程精簡。
- 失敗／rollback未產生成功receipt；同key是否已commit須靠保留的紀錄判定，網路逾時重送應沿用key。Redis cache、TTL或eviction不能取代業務庫receipt。

## 隔離MySQL量測

MySQL 8.4、獨立tasktracker-perf-test／tasktracker_test；一張卡，每輪改一次標題並新增一則留言。描述為100段固定中文詞組，留言為40段中文詞組與回合號。從真實HTTP執行64輪，共129個成功結果；另有一筆Project建立receipt排除於本數據。

| 回合 | receipt筆數 | JSON文字bytes（MySQL CAST） | native JSON_STORAGE_SIZE合計 | 最大單筆native JSON |
| ---: | ---: | ---: | ---: | ---: |
| 0 | 1 | 1,898 | 1,923 | 1,923 |
| 8 | 17 | 95,972 | 97,883 | 9,755 |
| 16 | 33 | 312,837 | 319,316 | 17,608 |
| 32 | 65 | 1,115,589 | 1,139,268 | 33,320 |
| 64 | 129 | 4,197,219 | 4,287,524 | 64,744 |

最後卡片的應用JSON只有61,858 B，其原始Issue audit JSON合計20,362 B；後者不含comments與主列，**不當作整張卡片的磁碟占用**。32→64輪的receipt合計約3.76倍：每次結果隨歷史／留言線性增長，累積複製接近平方成長，不是每次固定幾KB。100張各自達到此人造工作量的卡片，僅JSON payload就約429 MB，尚不含索引、InnoDB頁面與備份；不是實際流量／容量預測。

對照Todo：1次建立＋32次改內容，33筆result各66 B，合計2,178 B；證明精簡寫入結果可讓receipt結果本身接近按操作次數線性成長，但業務歷史儲存成本仍存在。

原始去識別數據：[receipt-growth benchmark](../.ecc/benchmarks/receipt-growth-2026-11-12.json)。JSON_STORAGE_SIZE是binary JSON payload，不是`DATA_LENGTH`／配置磁碟頁數；不以混有其他fixture的table統計當精確容量。不含HTTP response gzip、DB壓縮、binlog與備份成本。

### 無損壓縮實驗，尚未上線

逐筆獨立gzip level6，再逐byte解碼比對；129筆JSON文字4,197,219 B→381,359 B，約減90.9%。單次本機總encode25,573μs、decode5,400μs，只含CPU、不含HTTP／DB IO；不可據此推論上線延遲或p95。

壓縮不消除重複歷史的成長型態，也不代表新schema的實際磁碟收益；還要計codec／checksum／metadata與遷移雙份儲存。66 B的Todo結果可能壓縮後更大，不能全表一律壓縮。重跑得到gzip382,055 B、encode20,556μs／decode5,276μs；UUID與時間戳不同會造成小幅差異。兩次同條件JSON容量一致，壓縮及CPU數值只是實驗樣本。此實驗只讀隔離fixture，沒有把結果寫回receipt。

## 必須維持的重播／稽核不變條件

| 情況 | 必要行為 |
| --- | --- |
| 原key、原輸入、仍有資格 | 回原結果，不能偷換目前revision或重新寫入 |
| 原key、不同operation或輸入 | signature不同，409衝突，不執行業務 |
| 相同key但已撤權／跨Workspace／作者不符 | 先驗目前資格，拒絕讀result；不得以壓縮或cache放行 |
| 寫入或receipt保存失敗 | 同交易rollback；不留半筆業務成功或缺receipt窗口 |
| 不認識的codec／損壞結果 | 保留key並明確失敗，不能視作cache miss再執行 |
| 成功receipt恢復／備份 | 必須與業務資料保持一致時間點；不能只還原業務表而丟掉防重複紀錄 |

receipt不是業務異動歷史的替代品，業務history也不能重建任意舊response。單靠signature無法還原回應，因此不能刪掉result後仍聲稱「同key同結果」。

## 後續建議與分工

### 1. 限縮新增寫入回應，保留既有相容

在TT-178／TT-246／TT-169的專用Request／DTO與公開寫入契約中，定義有界結果（id、revision、必要定位／異動摘要），活動用按需GET。修改API時同步Web／工具／文件與測試；不把舊receipt的完整原結果改寫成新形狀，不建立第二套業務。

這是驗收補充，三張既有卡仍open，不代表本輪已改API。精簡新回應能處理長期成長根源，舊快照另以透明編碼降低儲存成本。

### 2. 相容且可回退的無損codec

已建後續卡 **TT-255「以版本化無損編碼精簡重播快照並驗證雙讀回退」**，open、指派taskTrackerAI，分類效能改進＋AI整合/API。先量測小／大結果門檻、真實IO／CPU；需版本化envelope、舊JSON雙讀、逐批有界轉換與checksum、新舊worker／回退驗收。不能原地把JSON換BLOB而讓舊程式當result不存在。

此卡不含TTL，不在本輪提前實作。壓縮仍必須先授權、比對signature，再回原payload。

### 3. 保留期／過期狀態機，僅提案

- 現況繼續保留，不自動過期。未有使用者採納的保留天數；不能把PAT期限當receipt期限。
- 若未來要釋放payload，至少需`replayable`與永久或有明確保證期間的`result_expired`墓碑：保留唯一key、signature、actor／Workspace、operation／資源定位及狀態。**payload到期不等於key可重用**。
- 原key同輸入遇到墓碑應回明確的結果已過期錯誤（例如409 `IDEMPOTENCY_RESULT_EXPIRED`，僅建議、目前未實作），絕不重新執行。不同輸入仍回`REQUEST_CONFLICT`；在這之前照常先驗權限。
- 不接受任意「存30天後DELETE所有receipt」。若連墓碑也要刪除，必須先選定可驗證requestId的最長接受窗口／簽發時間與舊key拒絕方式，否則無法保證不重複建卡。
- 現有receipt沒有時間欄位，migration不可偽造舊紀錄的建立時間。可增`recorded_at`／`payload_expires_at`供新紀錄；舊資料時間未知時先不列入自動清理。operation／resource metadata可支援統計與將來資源刪除語意，但不是授權快照。
- 過期政策、客戶端處理、稽核／刪除／備份與新舊API版本相容必須一起決策後另建實作卡，本卡不把提案當確認需求。

## 資料存取審查與驗證

- 只在隔離test fixture透過HTTP寫1張卡及1個Todo；統計按本人固定request前綴限定，5個checkpoints以SQL SUM／COUNT／MAX聚合，不下載全Workspace。壓縮候選按id cursor串流讀指定fixture；不在產品頁加新報表或輪詢。
- `ReceiptGrowthTest`沿MySqlTestCase三重限制（旗標／test帳號／schema）及DatabaseTransactions，完成自動rollback；預設memory不執行此原生引擎測例。主業務庫無fixture與清理。
- 最後原生MySQL 1 test／308 assertions通過：64輪累積、Todo精簡、129個payload無損往返、同requestId歷史原結果與筆數不增、改payload409、原Issuerevision不變、停用Workspace資格後拒絕重播。
- 加跑既有冪等／資格失效回歸，共3 tests／335 assertions（1原生MySQL容量案例、2 memory既有API案例）通過。Pint單檔通過。沒有產品API／前端契約修改，不以build或UI測試充當容量證據；本卡分析結案不代表快照儲存改善已上線。TT-255及原寫入DTO卡仍未完成。

重跑：`docker compose -p tasktracker-perf-test -f docker-compose.mysql-test.yml exec -T backend php artisan test --filter=ReceiptGrowthTest --do-not-cache-result`，只允許獨立測試Compose。
