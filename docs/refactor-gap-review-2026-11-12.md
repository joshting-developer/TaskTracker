# 效能與前後端重構缺口補卡

日期：2026-11-12（Asia/Taipei）。本輪僅程式／文件與真實卡片比對、查重、補卡與驗收範圍整理，沒有實作功能、改API或執行業務資料庫測試。

## 結果

新增15張未完成工作卡（12項P1、3項P2；屬審查建議順序），補充8張既有卡。新卡全部open並指派taskTrackerAI；6張後端架構／契約卡放AI整合/API板，9張資料存取／前端接線卡放效能改進板。另有審查交付卡，僅本輪補卡驗收後結案。

核心缺口是前端仍以getWorkspace／getTodos取得資料，新v1後端接口尚未接入；新增後端路由或完成skill不等於這個端到端問題已修。DI／PHPDoc與效能分別驗收。

## 新增工作

| 卡片 | 建議 | 工作 | 前置／協作 |
| --- | --- | --- | --- |
| [TT-232](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/aac17bef-ed4e-43be-b27f-d76c51a281a6?detail=1) | P1 | 盤點全後端依賴並建立可信Context與Factory組裝邊界 | 先盤點 |
| [TT-233](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/420eac4f-5cc0-4fee-beb6-30cb5a122960?detail=1) | P1 | 以Container與按需組裝重構後端核心依賴鏈 | TT-232 |
| [TT-234](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/27d65780-d081-48d0-9280-d0838ef1a7d0?detail=1) | P1 | 完成其餘後端協作者與長駐工作之DI遷移 | TT-232、TT-233 |
| [TT-235](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/5d90c544-090e-45b6-ba87-649d3b3705b9?detail=1) | P1 | 統一全自有後端PHPDoc並產生型別覆蓋與例外清單 | TT-232 |
| [TT-236](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/57c258fc-aff0-46fb-beea-af235b5414fc?detail=1) | P1 | 完成全面DI與PHPDoc的隔離整合與交付驗收 | TT-233、TT-234、TT-235 |
| [TT-237](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/5fbb2d64-95ca-4ab0-a1b9-b195b6155a7c?detail=1) | P1 | 前端API客戶端支援分責GET、取消與正確錯誤分類 | TT-180 |
| [TT-238](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/9a2b1fd5-2737-487b-a2f9-ff7cf96c6a68?detail=1) | P1 | 看板列表改為Project摘要API並接上前端 | TT-237、TT-180 |
| [TT-239](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/a48653db-282d-4829-80b2-4bb91c8baa80?detail=1) | P1 | 目前看板卡片改為有界查詢並維持泳道與拖曳 | TT-238、TT-214、TT-219 |
| [TT-240](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/220188a2-abbf-4639-a112-9293c6c9ff14?detail=1) | P1 | 建立有界選項查詢並移除表單對全量Issue與成員的依賴 | TT-237、TT-214、TT-215 |
| [TT-241](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/de9270d0-5691-48ca-9e26-2e80ea2156a2?detail=1) | P1 | 工作區設定與成員目錄拆分有界讀取並接線 | TT-237、TT-180 |
| [TT-242](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/8130656b-de6a-4653-90b2-31c2e2fc92fb?detail=1) | P1 | 專案成員ACL讀取改批次分頁並拆出候選與歷史 | TT-237、TT-240 |
| [TT-243](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/bf8e5a89-0a38-4393-84e1-33204bfeb890?detail=1) | P2 | 狀態設定以Project狀態使用摘要取代完整卡片掃描 | TT-237、TT-178 |
| [TT-244](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/ccb196b0-b4b7-4e98-9b55-b124677d4801?detail=1) | P1 | 任務詳細頁工時改用單卡摘要與分頁明細API | TT-237、TT-215、TT-223 |
| [TT-245](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/a5debbe4-d40e-4d3c-89d9-1539c09d8c61?detail=1) | P2 | 現有通知預覽改為Project分頁與單筆按需讀取 | TT-237、TT-219 |
| [TT-246](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/ee1e7b51-8914-492d-8e0a-0aa129047c55?detail=1) | P2 | 其餘領域寫入改用專用Request與DTO並同步前端契約 | TT-178、TT-237 |

卡片完整包含目前程式證據、前後端範圍、驗收與邊界，見[建卡清單](planning/refactor-gaps-2026-11-12.json)。關聯卡表示協作／施工順序，不新增前置任務阻擋功能。沒有把跨主題Epic整包收進看板。

## 沿用而不重建

| 原卡 | 本輪補強 |
| --- | --- |
| TT-180 | 端到端驗收包含App殼／ProjectHome／側欄能力與數量來源：使用auth/current與Project分頁／屬性，不再要求全部issues／members。建立舊getWorkspace／getTodos呼叫端（含tools的import／verify、tests與文件）移轉／退役矩陣，最後以Network證明一般頁面不走整包入口。新專用看板／選項／管理頁卡負責各頁實作，本卡負責導覽與移除全量預設，不必等所有效能卡才交付一條完整流程。 |
| TT-178 | 本卡包含Issue／Time寫入的專用Request／typed DTO與前端表單／api adapter同步；維持revision／requestId及重放前授權。非Issue／Time領域另由新卡承接，DI只處理依賴，不以DI完成代替契約遷移。 |
| TT-179 | 依實際路由拆出頁面資料生命週期與editor／overlay owner，不只是把整個App搬進composable；統一loading／empty／error／404／撤權狀態。頁面摘要與編輯完整資料狀態分開，不為接新API裝Router／Pinia或全庫TS。與TT-180資料導覽、TT-149草稿安全分工。 |
| TT-214 | v1後端已有SQL分頁，但frontend/src/api/index.js與App尚未接線。本卡必須完成前端filter／search／sort／scope／page的伺服器參數、total／lastPage、過期回應及空頁處理；不能再用當頁摘要切片冒充整個Project。Network不得有getWorkspace/getTodos；只完成後端不能結案。 |
| TT-215 | v1詳細目前只有摘要＋description，缺前端所需關聯／子項按需讀取；IssuePanel仍存取comments.length／history並依全量issues找關聯。須補單卡關聯／子項摘要分頁與tab活動載入；深連結先hydrate完整可編輯資料與最新revision，不以空陣列默認值覆蓋既有關聯／留言。單卡工時另由新工時卡處理；所有摘要／詳細契約與真實隔離瀏覽器同步驗收。 |
| TT-219 | 補明確scope失效／合併矩陣：Project／Board／Issue／Todo／工時／ACL mutation與事件，各自只刷新目前頁需要的資料。看板與設定等由本輪新專用卡提供查詢，本卡接刷新路由，跨Project／Workspace與權限變更時清資料及取消舊請求。 |
| TT-226 | API新舊遷移必須驗證實際frontend／tools使用端：允許endpoint清單、初始與背景request／rows／bytes門檻、未開tab不查活動、500卡／100成員／長期間成長，連遠端服務的SQL RTT分段和正式拓樸另作可重跑量測；本輪新卡不因建卡或DTO新增就完成。保留實際data-access-review-2026-11-12.md的2.3～2.6秒單次觀察，不能當p95。 |
| TT-168 | 本卡仍是未來外部受限API／metadata能力。當前內部頁面分責查詢與新選項／看板／ACL卡共用Application Service與授權，不在本輪加PAT或另造第二套業務；內部前端接線須先完成，不能讓它被外部認證待辦阻塞。 |

其他既有TT-146～149（輪詢／版本／草稿）、TT-182／223（分析／月曆）、TT-181／183（DOM／bundle）、TT-221／224／225／227（定向驗證／Session鎖／重複查詢／receipt）、TT-151／184／163（runtime／容量／長期觀測）繼續沿用，沒有再各建同義卡。已完成TT-220／222／228／229保留closed；例如TT-229完成的是看板收錄寫入，不能誤當看板列表讀取已完成。

## 施工順序與不重複範圍

1. 前端query客戶端與TT-180資料殼協調先行；看板列表摘要與目前看板卡片接線優先，搭配既有TT-214／215列表／詳細，先交付一條真正不讀全Workspace的使用流程。
2. 選項、Workspace／Project管理頁、狀態使用摘要、單卡工時及通知preview按頁面接線，修掉移除全量資料後的隱性依賴；與局部刷新／輪詢生命週期整合。
3. DI先做全庫依賴／Context／Factory設計，再改核心鏈與其他入口；PHPDoc可按明確責任分階段整理，最後全庫整合驗收。不要把不同scope協作者註冊singleton，也不把new單純搬家。
4. TT-178承接Issue／Time寫入；新其餘領域DTO卡承接Project／Board／Todo／Workspace等，不複製兩套Service。內部查詢可供TT-168未來外部API重用，但本輪不做PAT／MCP。
5. 前端／tools（含import／verify）／tests／文件移轉後，退役過量入口；TT-226以真實Network、讀寫rows／bytes與背景請求驗收，不能只數SQL或要求新回應等於舊全集。

## 證據與限制

- 已比對backend/routes/web.php、frontend/src/api/index.js、App.vue、BoardList／ProjectBoard、IssuePanel／Detail／Relations、WorkspaceSettings／ProjectMembers／ProjectSettings、TimeEntryDrawer與NotificationPreview，以及ProjectMembers／ProjectService／WorkspaceQuery／WorkspaceService／ApiExecution等入口。
- [DI／PHPDoc規格](backend-di-phpdoc-refactor-plan.md)、[資料存取複審](data-access-review-2026-11-12.md)、[既有效能審查](performance-review-2026-11-11.md)、[v1契約](query-api-v1.md)與真實分頁卡片摘要／相關單卡描述對照；不是只查標題，也沒有讀整個Workspace來查重。
- 本輪沒有新負載／瀏覽器／遠端SQL分段量測，卡片中的P1／P2是工程優先建議，不是新增量測或聲稱已發生資安事件。前端完整接線與效能P1仍未完成。
- 僅以正常授權應用API建卡／補描述，保留原描述沿革、原卡狀態與指派；不以初始manifest蓋過人工進度。新卡加入對應主題看板，未變更Owner資格、未對業務庫seed／RefreshDatabase或清Redis。

## 讀回驗證

原230張，完成後246張；新增15張工作卡及1張審查交付卡。15張工作卡的ID／open／指派以分頁API讀回確認；8張原卡補描述時狀態與指派保留。

審查交付卡：TT-231。本文件與JSON只記本輪快照，不可重跑覆蓋未來人工進度。

看板收錄另外以一次限定本輪16個Issue ID與兩個目標Board ID的唯讀SQL核對，16／16吻合；沒有為核對看板而載入完整Workspace，也沒有以直接SQL建卡／改權限。沒有執行瀏覽器畫面驗收，不能據此聲稱UI／效能已改善。
