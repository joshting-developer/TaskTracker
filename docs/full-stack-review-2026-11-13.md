# 前後端複審與待辦分類 — 2026-11-13

本輪 TT-275 只審查、查重、建卡與更新追蹤規範；**沒有實作產品修正、沒有新增索引、沒有封存現用看板**。P1 尚未處理，不能宣稱整體效能驗收通過。

## 方法與範圍

使用共用 side-project-development-standards（engineering／data-access／verification）、Laravel Patterns＋Architecture、Vue Best Practices、UI UX Pro Max、Design Taste app-screen、Ponytail lite、performance-optimization＋benchmark，以及 task-tracker-usage。矩陣選用輸出本機 `/tmp/tt-audit-selection.json`；規範正文透過專案symlink指向Side Project共用庫。Skill只作審查依據，沒有啟用plugin／hook或更換框架。

- 讀HTTP邊界、Request、Service／Repository、Resource、migration與正式OpenAPI；列主要列表、看板、工時分析、寫入子卡與資料成長路徑。
- 獨立 `tasktracker-codex-audit` Compose／MySQL **8.4.13**、`tasktracker_test` schema、專用秘密volume／tmpfs。10000張人造Task、20000筆人造來源稽核，另加100個不同承辦者與1000筆專案歷史；未讀寫現用業務DB、未操作共享Redis。測試案例使用自己的隔離fixture。
- `EXPLAIN ANALYZE`、實際 `SHOW INDEX`、Repository query log；只作單次查詢形狀／掃描量觀察，不當p95、RUM、正式容量或優化前後倍率。
- 前端讀目前元件及Markdown renderer，查看使用者截圖與既有核准圖：desktop/05-issues、07-detail、09-board及mobile/05-issues、08-editor。這是視覺／可用性專家走查，**本輪未重跑新瀏覽器操作或正式48圖比較**；產品修復卡仍需新行為與核准視覺驗證。

## 後端發現

| 卡片 | 等級 | 證據與影響 |
| --- | --- | --- |
| TT-276 | P1 | `IssueReadRepository::timeProjection`對一般Task也加Epic估時子查詢。本組20卡看板每卡`epic_child table scan rows=10000 loops=20`，即便HTTP SQL次數固定，DB內部仍逐列重複工作。先消除無需的投影，再驗真正Epic。 |
| TT-277 | P1 | `WorkspaceTimeReport::get`先`all(projects)`／`all(members)`及權限成員全集，再套分析條件；`RelationalChildren::prepare`帶audit全集。合成單專案metadata讀回1000筆detailsHistory。`TimeReportRepository::summary`的allocations及options也未限制成員×專案成長。這不否定TT-182已做的SQL工時聚合，是另外殘留路徑。 |
| TT-278 | P2 | 一般搜尋在title／identifier／description使用LOWER＋`%term%`與assignee EXISTS，COUNT及data本組各掃10000列。精準identifier已有唯一索引且本組命中1卡，應先設計卡號／文字不同路徑；中文全文／短詞／標點語意不能隨意犧牲。 |
| TT-279 | P2 | 增量索引`(workspace_id,project_id,updated_at,id)`並不覆蓋列表`updated_at DESC,identifier,public_id`的混合排序；20卡仍排序10000輸入列。page400 OFFSET7980；Request可接受到1000000頁。應先按查詢形狀驗索引、CASE排序與深分頁，不盲目全表加索引。 |
| TT-280 | P2 | `IssueService::updateIssue`換Epic時逐子卡require／writeIssue；完整讀會帶所有留言／歷史。只重現require部分，2／20張不同承辦子卡分別14／122次SQL，證明讀取線性成長，**未將此數值冒稱完整mutation成本**；寫入、鎖、rollback及歷史成長續卡驗。 |

單次Repository觀察（未清OS／DB cache，順序取樣，不能比較成加速倍率）：

| 情境 | 列數 | SQL次數 | 觀察耗時 ms |
| --- | ---: | ---: | ---: |
| 列表第一頁 | 20 | 4 | 16.56 |
| 列表第一頁 | 100 | 4 | 27.28 |
| 搜尋Synthetic | 20 | 4 | 28.07 |
| 精準AUD-5000 | 1 | 4 | 1.13 |
| 列表第400頁 | 20 | 4 | 62.18 |
| 看板投影（無工時權限） | 20 | 4 | 42.65 |

不同資料／語意的耗時只供定位，不能直接當exact搜尋對全文搜尋的提升倍率。授權、HTTP、Resource bytes、並行p95及真實大量工時沒有混入這組數值。

### 已有保護與避免誤報

- 100個不同承辦者時，列表20／100卡皆 **6次SQL**，最小關聯預載有效；這條一般列表路徑未重現應用層N+1。不能由此推論全部Repository都無N+1。
- 實際`SHOW INDEX`確認MySQL為FK建立的epic／parent複合索引存在；不是「migration沒有index()就全部缺索引」。仍可能因查詢形狀／選擇性讓最佳化器選掃描。
- `integration_audit_resource(workspace_id,resource_id,resource_revision)`已存在；20000筆來源metadata以該索引查到1列，沒有另開缺索引卡。
- 搜尋已參數化且跳脫`!%_`；本輪沒有發現可重現SQL注入，不把慢搜尋寫成注入漏洞。
- Resource使用`getRelation`與明確投影，列表／詳細／活動分責大多已落地。JsonOperationRequest有50000-byte上限，PageQueryRequest限制perPage≤100，PAT有效權限與Scope交集、receipt重播前授權仍有既有測試。
- 選定安全回歸 **33 tests／1186 assertions通過**：MySQL＋memory的TokenAuthorization、PublicQueries、PublicWrites及memory ProjectAclRead。第一輪filter中兩個舊測試名無匹配，實際只跑TokenAuthorization兩類；第二輪已用正確類名補公開查寫。這是回歸子集，**不是滲透測試、依賴CVE掃描或整個服務無弱點的保證**。

## 前端與產品流程

| 卡片 | 發現 | 建議／驗收方向 |
| --- | --- | --- |
| TT-281 | 每列紫色底線「快速編輯」與完整頁箭頭並列，重複文字搶注意；列表手機版與看板鉛筆又不同。 | 使用一致、安靜且可辨識的編輯按鈕；tooltip／aria-label／44px觸控及鍵盤。標題閱讀、動作編輯；double-click/F2只能輔助，不能作唯一入口。 |
| TT-191（原卡補範圍） | 附件／圖片原候選已存在，不能再開一張相同儲存基建。 | 提升open未指派，預估360分鐘；補私有儲存、租戶／參與者授權、MIME／配額／撤權／孤兒清理。R2是候選，不當已決定的硬依賴。 |
| TT-282 | `MarkdownEditor.vue`無paste／drop／file；`ui/markdown.js`刻意將圖片轉連結且sanitize不准img。光改textarea或放寬img無法完成貼圖。 | 依賴TT-191；共用編輯器貼上blob→上傳→游標插入→授權預覽，文字貼上／IME／undo／草稿不退化，失敗／重試／取消不遺失內容。 |
| TT-283 | 後端已有archiveBoard及archived_at，但前端叫移除，SQL只讀未封存板，無查閱／還原。 | 做active／archived列表、唯讀查閱及還原；Default不可封存。卡片／留言／工時保留；先明訂Epic動態收錄在封存時是否凍結。與TT-250卡片刪除、TT-195專案封存不同。 |

本輪保留品牌紫色、既有資訊架構與Markdown，不因審美Skill另造設計系統。沒有擴張成全站重設計。既有編輯器手機單欄與固定保存區可沿用，主要修操作層次與圖片流程。

## 看板應持續使用還是分階段封存

建議：長期收件匣可沿用，**有限範圍的交付階段板完成後封存，新階段以日期／目標另開**，例如「效能改進・查詢成本第二輪」。避免每年所有結構工作都塞在同一板，也不必每個小需求新建一板。

這是後續整理建議，不代表已取得自動封存／新建階段板的要求；本輪新卡仍收原有語意正確的板，未封存或重新命名現有板。封存UI尚缺；且舊效能板仍有TT-163部署觀察，結構板仍有工程工作，不能只看大部分closed就封存。封存是整理看板，不是關閉卡片或刪除內容。

封存時凍結「當下解析的卡片成員」可避免Epic日後新卡混入歷史板；**這只是建議，TT-283開工先確認成本與產品語意**。卡片本身後續狀態／工時仍是真實目前值，若另需歷史交付快照要獨立定義，不偷加完整版本快照系統。

## 查重與分類讀回

- 新建TT-276～280：效能改進（五張，改善度量／SQL與讀取範圍）。TT-281～283：實際功能（三張，介面／圖片／看板生命週期）。全數open、未指派，皆有預估、證據、驗收與限制。
- TT-191既有附件卡提升為已確認待辦，未複製新卡。TT-282依賴它；TT-195／250保持不同資源生命週期範圍。
- TT-275本輪review／Skill治理歸結構優化；TT-179的App職責拆分、TT-260內部HTTP操作紀錄也由實際功能移至結構優化，**未改它們的承辦人、狀態或內容**。其他承辦者仍繼續原任務。
- AI整合/API沒有加入任何內部優化或UI卡。TT-163既有部署＋效能雙板保留：驗收包含正式環境成本／冷啟動／p95／DB負載，有跨題理由。TT-266是同主題工程Epic，其動態收錄不需強拆；不將跨主題Epic整包塞板。
- 每次updateBoard先GET最新revision，保留原selectedIssueIds，追加／移除目標後GET確認集合。Default全收不能替代分類。

## 共用Skill與後續流程

`/Users/josh/workspace/side-project/skills/01-common/task-tracker-usage/references/classification-and-time.md`是唯一分類／工時正文，Skill入口引用；TaskTracker AGENTS只留指向。共用庫提交 **2c70629**。全14Skills／380 hash／358原vendor校驗通過。

本輪TT-275已實測：預估120分鐘、以Codex本人Session填報14分鐘正式工時並讀回（自建立追蹤卡起、按分鐘估計，未回補領卡前探索）。預估保留作偏差對照，不在結案時覆蓋成實際值。

新規範：開工前正式estimateMinutes、指派自己後inprogress；階段／交接／結案前填本人正式time entry，寫進度與偏差理由，讀回再結案。實際工時不以預估回填、不由留言冒充、不為舊closed卡補造；不能把等待與並行工作重複計費。公開PAT目前沒有工時接口，consumer已授權的Session或Web流程才填，不自行新增或繞過權限。

## 證據與限制

合成SQL計畫／索引、query counts及測試範圍見[evidence JSON](evidence/full-stack-review-mysql-2026-11-13.json)。本機原始PHP實驗與log在忽略artifacts/tt275，沒有帳密／PAT／業務資料；圖片／trace不進Git。重跑只能使用本文件的獨立testing Compose，PHP腳本會檢查testing與schema，不能移到主服務執行。

沒有實作產品、沒有性能改善前後結果、沒有正式環境壓測或新UI驗收；TT-276／277的P1仍未完成。下階段先做這兩張，再處理搜尋／索引與前端需求。
