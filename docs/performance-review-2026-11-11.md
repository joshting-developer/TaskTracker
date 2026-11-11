# API責任與效能複審

日期：2026-11-11（Asia/Taipei）。使用者要求建立「效能改進」看板、重新審查並開好卡片。**本輪只審查／建卡，沒有修產品程式、拆API或停止現有刷新。**

## 結論與前次遺漏

getWorkspace是工作空間完整快照，並不適合承擔首頁初始化、專案列表、任務列表／詳情、看板、月曆與通知的共同資料來源。現在路由只需其中一部分，也會讀取其餘集合；同一刷新再串getTodos。這是責任與載入生命週期問題，不是單純Controller行數或JSON壓縮問題。

前次並非沒找到：[2026-11-09複掃](performance-scan-2026-11-09.md)已列全量bootstrap問題；[改善紀錄「仍有的限制」](performance-improvements-2026-11-09.md)也明確保留這項未完成工作。當時修掉唯讀授權N+1、合併同scope重複刷新、批次索引等，但刻意保留完整API契約，沒有拆讀取。已有TT-180待辦，這輪沿用。

交付上的問題是沒有把這個仍影響每頁的限制提升到明確的使用情境驗收：SQL查詢數下降，不等於首頁資料量合理，也不等於閒置頁面沒有昂貴背景請求。這次補入network／rows／bytes／分頁與閒置行為的回歸卡；不以「用過skill」或「已有Redis」宣稱整體效能合格。

## 範圍與證據限制

依專案內Addy performance-optimization診斷流程、ECC API量測格式、Laravel／Vue規範review。檢查現有查詢／DTO、API協調、寫入、前端刷新／事件／路由、Todo、工時、runtime與既有報告／卡片。

沒有對主庫壓測、植入測試資料、開query log或做EXPLAIN。靜態可證的全量get／循環／重寫行為與尚待量測的效能影響分開標示。沒有RUM、多worker容量或前後改善比較；只有以下單次正常使用的HTTP診斷。

## 一次實際HTTP樣本

本機PHP服務＋外部MySQL 8.0.46與外部Redis；操作者為TaskTracker專案Maintainer／工作區一般成員，只有一個可讀Project。以既有Session／CSRF登入後單次getWorkspace，Accept-Encoding=identity；不是Owner全空間基準，也不是HTTP p95。沒有清除共享cache，冷暖cache與背景探針／使用者流量未受控；時間僅為這次實際觀察。

- HTTP回應原始JSON：**437,772 bytes（約427.5 KiB）**；單次完成耗時**14.953s**。
- 內容：1個專案、212張卡、5個看板、2位可見成員；本帳號沒有可見工時／通知。
- Issue仍包含描述合計132,070個UTF-8 bytes、11則留言及234筆歷史，即使首頁只顯示小待辦。
- 原始回應大小包含JSON編碼／envelope，不能和重新序列化的單欄bytes直接相加比較。未切開SQL、Redis、Session等待與傳輸時間，因此不能說428KiB「造成全部15秒」，也不能推論所有使用者耗時相同。
- 另參考上一輪整庫遷移的停寫快照：sessions有11,176筆。這與AI可見Project的HTTP樣本是不同範圍，不能直接判定全由health產生；健康端點沿用web Session middleware的副作用仍應隔離驗證。
- 同scope請求已有single-flight；它只合併同時發生的讀取，不會消除相隔30秒的全量請求或事件後的整包刷新。

## 發現與對應卡片

P1／P2是本輪執行順序建議，不是聲稱所有分支已經量到慢。既有卡的標題／狀態／優先級／負責人保留，只補證據與分工；新P1卡設high、新P2設normal，全部新工作卡為open並指派AI。

### 沿用既有待辦

| 卡片 | 建議 | 本輪分工 |
| --- | --- | --- |
| TT-180 | P1 | 首頁bootstrap／身分導覽拆分，移除整包工作區依賴 |
| TT-146 | P1 | 智慧輪詢生命週期：可見性、離頁取消、scope及閒置策略 |
| TT-147 | P1 | 輪詢間隔、jitter、退避及失敗行為 |
| TT-148 | P1 | 輕量版本／變更檢查，未變不下載全量資料 |
| TT-149 | P1 | 局部刷新／輪詢下的草稿與revision保護 |
| TT-182 | P1 | 工時SQL彙總與資料庫分頁，避免全期間原始列載入PHP |
| TT-151 | P1 | 正式HTTP runtime，避免以artisan serve推論多人容量 |
| TT-184 | P1 | 正式多worker、遠端服務與弱網容量基準 |
| TT-163 | P2 | 請求／SQL／bytes／p95與遠端服務負載觀測 |
| TT-181 | P2 | 500卡DOM／看板視窗化評估，保留DnD與可存取性 |
| TT-183 | P2 | 前端路由／功能lazy loading與MDI資源拆分 |

TT-180承接首頁身分／導航及頁面載入協調，列表／詳情／Todo等由下方專用卡負責；TT-146／147／148／149承接排程、退避、版本檢查與草稿安全，不重複建四張「優化輪詢」。TT-168對外API可共用未來Application Service，但這次不啟動PAT／公開REST開發。

### 本輪補的具體缺口

| 卡片 | 建議 | 工作 | 證據／驗證性質 |
| --- | --- | --- | --- |
| TT-214 | P1 | 以專案為範圍提供精簡任務列表與伺服器分頁 | WorkspaceQuery::visibleIssues先all(issues)再PHP篩選；RelationalRecords::all先get全部及子表。App.vue傳pagedIssues、useIssueQuery每20筆前端切片，但API仍下載全部資料。 |
| TT-215 | P1 | 任務詳情按需讀取並分頁載入留言與異動紀錄 | RelationalChildren::prepare/read批次讀issue_comments與audit_events；WorkspaceQuery把完整Issue DTO帶進首頁／列表／刷新。 |
| TT-216 | P1 | 待辦依本人資格在SQL限縮並提供狀態分頁 | Todos::listing先all(todos)含歷史，再participant篩選；TodoHome的previewLimit只限制畫面，已完成集合仍全部載入。 |
| TT-217 | P1 | 將待辦通知摘要與收件匣從getTodos分離 | App.vue refreshWorkspace串接getTodos；Todos::listing同時回todos、全部本人通知與可指派成員，鈴鐺需求綁整份待辦。 |
| TT-218 | P2 | 批次查詢已轉卡待辦連結與有效專案資格 | Todos::listing在array_map內對convertedIssueId呼叫ProjectAccess::can與store->get(issues)，每筆get還會prepare留言／歷史。 |
| TT-219 | P1 | 依頁面與異動資源刷新以解除全Workspace重抓 | App.vue::toolUpdated／保存回呼與useBoardRealtime的project.changed都走refreshWorkspace；TimeAnalysis監看workspaceVersion再load報表。 |
| TT-220 | P1 | 任務異動改為差量子表寫入而非重建留言與歷史 | RelationalChildren::write對issue_comments／issue_relations及audit_events刪除後逐筆insert；addComment以store->put保存整個Issue。 |
| TT-221 | P1 | 任務關聯與Epic驗證改為限定資源查詢 | IssueService::validateLinks每次all(issues)，再查parent／related／epic；部分類型變更all(timeEntries)，updateIssue也掃子項。 |
| TT-222 | P1 | 填報工時以當人當日SQL加總取代全工作區掃描 | TimeEntryService::saveTime先all(timeEntries)再依userId／workDate／voidedAt及current排除，單筆填時讀整個工作區工時。 |
| TT-223 | P1 | 工時月曆依Project與月份取得必要紀錄 | TimeCalendar.vue用props.entries全部資料在前端filter月份；目前來源為getWorkspace的跨專案、跨期間timeEntries。 |
| TT-224 | P1 | 驗證慢API超過Session鎖租期與跨分頁並行風險 | routes/web.php對auth／rpc／realtime統一block(10,10)；本輪getWorkspace單次14.953s，已超過設定租期。 |
| TT-225 | P2 | 量測並合併請求內重複租戶與ID對照查詢 | RelationalRecords::query每次workspaceId都查Workspace；get／all清空identities與users快照，RecordStore建構也查Schema；跨網路往返成本放大。 |
| TT-226 | P1 | 建立API責任與背景請求效能驗收門檻 | 舊審查已列全量bootstrap未修，但既有回歸驗收主要涵蓋SQL數及功能；首頁仍可每30s讀完整Issue／history。 |
| TT-227 | P2 | 盤點防重複提交快照的儲存成長與保留契約 | RequestReplay把完整result寫operation_receipts；Issue result包含描述／留言／歷史，重複保存歷次整包快照。 |
| TT-228 | P1 | 健康檢查移除Session與業務資料庫寫入副作用 | /api/health位於routes/web.php，沿用web Session middleware；Compose的backend與frontend每5s呼叫，SESSION_DRIVER=database。StartSession會保存Session，健康檢查可能持續製造資料庫Session寫入與往返。 前輪遷移停寫快照有11,176筆sessions；這只是全庫數量，不能據此將來源全部歸因於health。 |
| TT-229 | P1 | 看板收錄以批次驗證與差量保存避免逐卡完整查詢 | ProjectBoards::fields對每個selectedIssueId呼叫store->require(issues)，每次還prepare留言／歷史；RelationalChildren::write對board_issues整批delete再逐筆insert。收錄上限1000，但驗證成本隨選取數成長。 |

各卡完整驗收與分工見[建卡清單](planning/performance-review-2026-11-11.json)。Session鎖卡是風險驗證：HTTP總時長不等同持鎖時長；目前沒有證明已發生跨空間錯寫，不能只拉長鎖或刪Session blocking當作修復。Todo轉卡連結、請求內重複查詢、receipt容量卡需要隔離量測後再決定實作，不預設加cache就有效。

## API職責邊界建議

這是後續實作方向，並非本輪已發布的新route／契約。已有POST /api/auth/current可重驗本人與工作區，bootstrap先評估沿用，不先新增同義身分端點。不是強迫每個欄位一隻API；同畫面、同授權與同更新週期的最小資料可一起回傳，避免變成HTTP N+1。

| 使用情境 | 應載入 | 不應附帶 |
| --- | --- | --- |
| 登入後應用殼 | 本人、目前空間、必要capabilities／版本 | 全部Project／Issue／Todo／工時／歷史 |
| 專案列表 | 有效可讀Project摘要與穩定分頁 | 每張卡、所有成員與狀態歷史 |
| 任務列表 | 目前Project的篩選／排序／分頁摘要 | 描述、留言／歷史全文及全期間工時 |
| 任務詳情 | 指定Issue詳細資料；留言／歷史按需分頁 | 其他Issue詳情與全部Project |
| 看板列表／目前看板 | 看板摘要／目前看板最小卡片DTO | 全空間Issue全文；看板列表不載全部卡片內容 |
| 個人待辦 | 本人承辦／交辦、狀態、分頁與操作能力 | 其他人的待辦、全部已完成歷史、鈴鐺所有收件內容 |
| 鈴鐺／收件匣 | 未讀摘要／開啟後分頁通知 | 全部Todo內容或getWorkspace |
| 工時月曆 | 目前Project與月份摘要、點日期後明細 | 全部專案與所有月份工時 |
| 工時分析 | 已授權期間的SQL聚合／群組分頁 | 全Workspace bootstrap與整個期間原始工時先進PHP |

目前任務列表已有每頁20筆的前端切片；缺的是伺服器分頁，不是列表DOM毫無上限。看板500卡DOM屬另一張既有待辦。

每次查詢在SQL先限制Workspace、已授權Project、當人／日期／IDs；用對應DTO而非通用實體完整序列化。現在PHP輸出篩選仍有授權，這輪沒有發現因此洩漏回應；問題是無需資料先進記憶體及資料庫往返。

## 程式證據入口

- [WorkspaceQuery](../backend/app/Services/WorkspaceQuery.php)、[RelationalRecords](../backend/app/Repositories/RelationalRecords.php)與[RelationalChildren](../backend/app/Repositories/RelationalChildren.php)：全量讀取、DTO子集合及重寫行為。
- [App](../frontend/src/App.vue)、[WorkspaceClock](../frontend/src/composables/useWorkspaceClock.js)、[RefreshQueue](../frontend/src/composables/useRefreshQueue.js)與[看板即時同步](../frontend/src/ui/boardRealtime.js)：固定排程、資料耦合及已有的single-flight。
- [Todos](../backend/app/Services/Todos.php)、[TimeEntryService](../backend/app/Services/TimeEntryService.php)、[TimeReportRepository](../backend/app/Repositories/TimeReportRepository.php)與[ProjectBoards](../backend/app/Services/ProjectBoards.php)：各領域查詢／寫入範圍。
- [路由](../backend/routes/web.php)、[bootstrap](../backend/bootstrap/app.php)與[外部Compose](../docker-compose.remote-db.yml)：Session鎖、健康檢查middleware與探針頻率。

## 執行順序與驗收

1. **先縮小責任與資料**：TT-180＋列表／詳情／Todo／月曆專用卡，讓首頁不再依賴完整Workspace。保留舊RPC相容期與深連結；tools/tasktracker.py等既有客戶端也列入遷移，不能直接刪API讓工具失效。
2. **再重整同步**：智慧輪詢生命週期＋版本檢查＋scope刷新。隱藏／離頁取消、閒置策略、重連補抓與錯誤退避明確；事件只標記相關資源失效，版本不變不下載資料。保留輕量撤權／資格重驗，不能因不抓整包而永久保留已撤權資料。
3. **處理寫入與報表量**：append歷史／留言、定向關聯驗證、當人當日SUM、SQL工時報表。不用減少驗證、刪歷史或略過交易換速度。
4. **補可重跑門檻**：隔離資料量階梯、登入後首頁／切頁／寫入／背景／雙分頁／撤權流程。驗證SQL數、讀／寫rows、payload、請求數、pending請求與正式runtime分位耗時；不要只驗畫面有資料。SLO待量測後議定。
5. 看板DOM與bundle獨立量測改善，不能讓列表分頁造成看板漏卡、Epic動態收錄失真或拖曳失效。

Redis key隔離與既有Queue成功不代表查詢已快取。整包Workspace快取會引入大量權限／失效風險，不作這輪首選；prefix或tags也不能修復無限增長的DTO。

## 交付範圍

- [效能改進看板](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/board/17e9af2b-4c24-42cb-af67-0a6337181e12)。
- 已沿用11張、補16張未完成工作卡，共27項改善皆open並指派AI；另1張審查交付卡TT-213已closed；只收錄明確卡片，不收跨主題Epic，以免動態展開混入其他工作。各主題看板可交叉收錄，沒有搬移或複製Issue。
- 審查交付卡完成時可closed，所有改善卡維持未完成；未做程式修改、migration、seed、重啟或壓測。
- 原始HTTP統計與操作前後快照留在忽略的.local，報告不提交cookie／密碼／任務全文。最終建卡對照及讀回結果已同步本文件與planning紀錄。

最終驗證：API確認28張看板收錄（27張open改善卡＋TT-213 closed）、16個新來源鍵唯一；既有卡標題／內容／狀態／指派／優先級／關聯保留，11張沿用卡各只新增一則審查留言，其餘201張原卡完整DTO不變；原有五個看板定義不變。真實Chrome登入AI後開啟效能改進看板，28張卡完整渲染、無pageerror；截圖只在忽略的frontend/artifacts/performance-review-2026-11-11。純審查／建卡不重跑build或功能測試，也不重啟服務。
