## 2026-11-12 TT-232 完成

- 完成248個自有PHP／artisan逐檔token盤點，含Docker／config／migration／tests／tools；351個new、40個app及11個容器位置，57條顯式constructor邊未見循環。分類與限制／外部SMTP例外、待遷移72處及TT-233／234後續在docs/backend-context-boundaries-2026-11-12.md；清單不是全庫DI合規證書。
- 新readonly WorkspaceContext、WorkspaceContextFactory、ProjectAccessFactory與Store.forContext；Session以可信來源重驗，Realtime實際接線，舊array用例明確轉接。無新singleton／任意Service Locator或API契約變更。
- 完整memory91／3893（28原生項略過）；原生Context＋API20／548；真實獨立MySQL＋背景Queue＋Reverb雙使用者同步／草稿／衝突／重連／撤權及三尺寸通過。Container替換、A→B→A、假身分、資格失效及SQL範圍通過。
- 其他對話前端變更保留；本卡只提交後端與盤點文件。全庫DI／PHPDoc與長駐工作遷移仍TT-233～236／249，未結案其他卡片。

## 2026-11-12 TT-232 開始

- codex已先領卡再inprogress。盤點全部自有PHP（app／seeders／Console／tools／tests／migration／config等），逐檔記new／app／resolve、例外與仍待遷移項，建立可重跑的token掃描，不把字串／註解當依賴。
- 建WorkspaceContext與驗證Factory，Session來源重驗後建立不可變值；Store／Access明確Factory接Context。先接Realtime授權作端到端示範，既有array API留顯式轉接，不在本卡擴成TT-233／234全面DI或TT-249責任拆分。
- 測Container解析／可替換協作者、跨Actor／Workspace連續呼叫、假HTTP身分不採信及撤權；比較SQL／回應不增加資料範圍，原生MySQL與相關Realtime流程驗證。前端現有修改保留、不納入本卡。

## 2026-11-12 TT-224 完成

- 四worker／真實HTTP12秒請求已重現舊10秒TTL造成Session覆寫；改現用MySQL連線級命名鎖涵蓋Session讀取到保存，保留10秒等待上限。交易前後與保存前重驗持有權，finally釋放／失敗關PDO。
- 等待失敗409不執行路由、舊X-Workspace-ID409、撤權後保存拒絕、程序中止自動釋放與失鎖rollback均通過。原生Session2／34、原生API＋並行20／542，最後Session＋大工時3／107；完整memory87／3848（25原生專用略過），真實workspace-switch三尺寸通過。
- 文件docs/session-lock-lifecycle-2026-11-12.md與.ecc基準；明列約5次額外輕量SQL、同Session等待、單主庫連線親和及不可混版rolling update。TT-184容量／正式runtime等仍open，不宣稱全部效能完成。
- 服務唯讀掛載導致先前Pint無法寫入，已改一次性無網路可寫lint容器並補TT-182純格式，最後測試通過；保留另一對話TT-146提交及其前端變更。本輪無push／主庫seed。

## 2026-11-12 TT-224 開始

- TT-182已提交081c53c並結案，才以codex領取TT-224。建立僅隔離tasktracker_test可啟動的四worker HTTP router，真實Cookie／CSRF與12秒延遲，量原10秒鎖是否讓切空間與舊Session保存重疊。
- 先保留重現證據，再考慮使用現用MySQL的連線級命名鎖跨完整Session載入／保存生命週期，避免任意加長有限TTL。需具明確等待上限、finally釋放／斷線失效檢查及SQLite測試相容，原有X-Workspace-ID／permission／revision不變。
- 工作期間出現其他對話的frontend polling修改，保留不提交本卡；本卡僅後端Session與原生並行測試／文件。

## 2026-11-12 TT-182 完成

- 工時摘要改SQL配對／日期聚合，明細與展開皆SQL分頁20筆；不讀整段原始note／history。API不變，舊PHP分組器移tests作獨立對照。
- 隔離MySQL10,000筆：PHP載入10,000→101聚合列，5次中位數166.08→29.41ms（Feature Kernel、非網路p95）；既有日期索引單日100列，未新增索引。群組／COUNT仍有SQL成本，組織選項／配對隨規模成長限制如實記錄。
- memory4／187、原生大資料1／73與API17／510、真實member-time／time-analysis三尺寸、Pint通過。文件docs/performance-time-report-sql-2026-11-12.md與.ecc基準。本卡提交結案後才領TT-224。

## 2026-11-12 TT-182 → TT-224

- 已重讀AGENTS；TT-182以codex先領卡再inprogress。先將工時報表的原始列載入改為SQL聚合、群組及展開各20筆分頁，保持原HTTP與前端契約。
- 原生隔離MySQL建立大量工時，保存變更前／後統計、EXPLAIN與資料範圍；驗證Epic各筆只計一次、作廢、授權、排序、空值與翻頁。只依證據決定日期索引。
- 完成測試／提交／結案後才領TT-224，於隔離多worker重現超10秒Session鎖競態並驗證跨空間／撤權／保存；不先刪blocking或假定提高租期能解決任意慢請求。

## 2026-11-12 TT-257 Agent分立與領卡

- 使用者確認改用codex／claude獨立帳號；兩者Workspace user、TT Project maintainer。隨機密碼存忽略的.local各自私有檔，Owner本輪授權完成即登出。
- 透過既有Session API清除65張open卡片指派；其他191張狀態、指派與revision保持不變，不刪共用帳號作者歷史。
- 開工先指派自己再inprogress，CLI新增--agent／claim，避免搶領他人卡；維持驗證、卡號commit、留言、closed。TT-257以codex實際依此流程領取。
- 驗證完成：兩帳號重新登入，Workspace user＋Project maintainer；65張open未指派、其他191張指派與revision逐卡保留；CLI 5項測試通過，真實重複claim不寫入。只調整本機帳號／工具與規範，沒有修改產品後端或部署。

## 2026-11-12 Agent 分立帳號與領卡流程

- 依本輪Owner授權建立codex／claude，Workspace一般成員、TaskTracker Project Maintainer；秘密僅存忽略的.local。
- 只清除TT專案open卡片指派，保留其他狀態／歷史；透過Session API與revision逐卡更新、讀回驗證。
- 更新工具／AGENTS與交接：依Agent選帳號，先指派自己，再inprogress；驗證、提交、留言後closed。

## 2026-11-12 TT-181 最長欄底卡補驗證

- 評估提交後把底卡定位改為實際最大bottom，揭露Epic彙總估時初始化造成假dirty；首次補驗證失敗時本卡維持inprogress，未結案。
- IssuePanel先正規化Epic寫入值再存草稿基準，保留真正修改時的確認。真實500卡F2開啟／不修改關閉不跳確認、抽屜卸載、泳道400→500、最長欄底卡三尺寸、DnD／搜尋／密度通過；production既有看板與composables亦通過。
- 渲染CSS／v-memo仍全部撤回；TT-256保持open。以同卡另筆提交保留歷史，已讀取期間新增的AGENTS無AI署名規則。

## 2026-11-12 TT-181 500卡評估完成

- 真實隔離MySQL／production Chrome，5 Epic＋495 Task、100筆HTTP共5頁、約1.2萬DOM，4x CPU交錯每組3次量layout／style／長任務／捲動／dragover。CSS局部收益在完整元件整合後反轉，v-memo無穩定收益，兩者已撤回，ProjectBoard保持原樣。
- 完成500卡底卡／F2／真實拖曳／Epic泳道／密度／搜尋復原／三尺寸；production既有看板與composables回歸、build通過。工具 frontend/tools/board-performance-check.js，原始去識別基準 .ecc/benchmarks/board-dom-2026-11-12.json，文件 docs/performance-board-dom-2026-11-12.md。
- 本卡按「評估500卡DOM與虛擬化策略」交付，未宣稱大型看板已完全改善。首次掛載仍有100–250ms級長任務；已另建TT-256分批掛載／trace定位，open、指派AI、效能改進＋實際功能。先保留完整卡片與原生DnD／焦點，不盲目導入虛擬列表。

## 2026-11-12 TT-181 開始

- TT-240已提交4b13519且結案後才將本卡移inprogress。
- 先以500卡／5個Epic、不同狀態、標題與參照、真實分頁API及production Chrome建立DOM／layout／長任務／捲動／編輯量測。試驗只針對渲染瓶頸，不預設導入虛擬化或將未渲染卡片從DOM移除。
- 比較相同頁面與快取條件、同資料；驗證卡片總數、底卡、DnD／F2、泳道、密度／篩選、手機。改善不成立則保留基準與不採用理由，不為效能犧牲操作。

## 2026-11-12 TT-240 完成

- 完成卡片／篩選／Project負責人／Todo承辦與轉卡／月曆／單卡固定工時／看板用途盤點；看板候選改issue-options最小30筆，取消／分頁／重試共用lookup。修第二頁搜尋繞過debounce，及當頁外父項reference缺epicId導致編輯子卡清空Epic。
- 真實Chrome145卡（當頁外父卡與Epic）保存、跨搜尋／分頁收錄與三尺寸通過；月曆972B／bulk0、personal25筆轉卡／候選／通知通過。API11／921（原生與memory分別）、build／api-client／composables慢回應與debounce通過。
- 文件 docs/performance-options-completion-2026-11-12.md；舊props相容分支與Workspace退役仍TT-180追蹤。本卡完成後才開始TT-181，不push。

## 2026-11-12 TT-240 → TT-181

- 先處理P1選項查詢的剩餘接線／驗收，TT-240已改inprogress。確認既有卡片、專案、Todo及工時候選是否全數分責；補看板收錄最小候選、取消／分頁／已選保留及遠端父項編輯問題。
- 測試／提交／結案後才開始TT-181，使用隔離500卡資料與真實Chrome量測DOM／長任務／layout／捲動／拖曳與鍵盤，不先導入虛擬化套件或隱藏卡片。
- 遵守本地Laravel／Vue、資料存取、UI／UX與Addy／ECC流程；每卡一提交、不push，不對業務庫建立壓測fixture。

## 2026-11-12 TT-227 容量與契約分析完成

- 真實隔離MySQL：1張卡64輪改標題／留言，129筆重播結果合計JSON_STORAGE_SIZE4,287,524 B，最後卡片JSON61,858 B；32→64輪累積約3.76倍。Todo已精簡id／revision，33筆僅2,178 B，不假設全服務同樣膨脹。
- 實驗逐筆gzip6結果4,197,219→381,359 B（約90.9%），129筆逐byte往返通過；僅候選payload收益，未上線codec／TTL／清理或改API。保留同key原結果、signature、先授權再重播；過期墓碑與期限只記提案，現況仍無到期政策。
- 原生MySQL1／308、Pint通過；測例DatabaseTransactions自動rollback。文件 docs/performance-receipt-retention-2026-11-12.md，基準 .ecc/benchmarks/receipt-growth-2026-11-12.json。
- 新增TT-255無損codec雙讀／回退實作卡，open、指派AI、效能改進＋AI整合/API；TT-178／246／169補有界寫入結果與重播相容驗收，保留open。TT-227只按原卡容量／契約分析範圍結案，不當成快照改善上線。

## 2026-11-12 TT-227 開始

- TT-225已提交aa9a51f並結案後，才將TT-227移inprogress。
- 盤點RequestReplay／一般與Todo receipt格式、原輸入簽章、授權順序及schema時間欄位。獨立MySQL建立長歷史卡片，測量每次與累積JSON邏輯／引擎大小，檢查相同requestId同結果／衝突／撤權。
- 只交容量與保留契約建議，不改業務回應、不刪快照、不做TTL；後續實作另開有界卡片並分到效能與API主題，避免本卡分析被誤認成清理完成。

## 2026-11-12 TT-225 完成

- 外鍵對照只查當次參照、每批100；同Store可重用正向不可變ID，卡片／revision／成員／資格仍新鮮讀取。子集合先批次解析避免逐列N+1，寫入成功／失敗finally清映射，跨Workspace建新入口。
- 隔離MySQL同卡8次讀取 SQL65→44、ID映射24→3；不讀80張無關卡片的映射。單次耗時有波動，僅宣稱查詢／範圍收益。105關聯100＋5、失敗寫入、改名／disabled／revision及跨租戶回歸通過。
- 完整MySQL106／4379；完整memory87／3848（21skips）；最後範圍6／134、Pint5檔與正式build看板Chrome三尺寸通過。文件 docs/performance-record-context-2026-11-12.md，基準 .ecc/benchmarks/record-context-2026-11-12.json。
- Schema每Store檢查與legacy all()主列仍依TT-233／180追蹤，未做全程序快取或宣稱所有效能卡完成。下一張TT-227尚未開工。

## 2026-11-12 TT-225 → TT-227 效能改善

- 使用者要求再挑兩張依序執行。先將 TT-225 設 inprogress，量測請求內租戶／外鍵對照重複查詢，只重用不可變ID，不快取資格、revision或Model。
- 第一張驗證／提交／結案後才開始 TT-227：以獨立 MySQL 長歷史卡片量測receipt儲存成長，交付重播／保留／過期契約；本卡明定先分析，不自動刪資料或加TTL。
- 沿用本地skills／隔離資料庫，主庫不seed或壓測，不push。

## 2026-11-12 TT-183 完成

- App 與 Project 任務工作區按需載入功能／抽屜；Pusher 延後到有效看板同步，載入後重驗 generation。MDI 字型改既有 Vuetify SVG aliases，移除字型依賴；新增可見載入／手動重試與重載確認，不動品牌與功能契約。
- 初始 gzip JS199,964→100,720 B、CSS110,713→50,667 B，合計減51.3%；全功能 JS 分塊後總量略增，非 RUM／p95 宣稱。基準存 .ecc/benchmarks/frontend-bundle-2026-11-12.json。
- build／API client／composables、production bundle離線重試／Markdown與SVG、production board三尺寸、完整dev E2E及真實獨立 Queue／Reverb同步／斷線／草稿409／撤權通過；測試環境清理。詳見 docs/performance-frontend-bundle-2026-11-12.md。
- TT-221已先提交8068fca並結案，本卡獨立提交、驗證後再結案；其他效能卡仍按實際狀態，不push。

## 2026-11-12 TT-221 完成

- 關聯驗證改指定 ID 的 readonly 摘要與 SQL exists，移除正常寫入的 all(issues)／all(timeEntries)；父項改 Epic 僅以100筆批次同步實際子項。保留跨專案拒絕、雙向容量／revision／交易與 Epic 結案規則。
- 修改前隔離 MySQL hydration 隨無關卡片1→122；修改後成長不增加，最小投影／200容量／102子項跨批驗證通過。完整 MySQL100／4261，memory83／3774（17 skips）；最後受影響8／112及Pint7檔通過。
- 詳見 docs/performance-issue-links-2026-11-12.md；其他查詢與活動寫量不併稱完成。下一張 TT-183 尚未動工。

## 2026-11-12 TT-221 → TT-183 效能改善

- 依最新卡片選 TT-221 關聯驗證限定查詢，先標 inprogress；完成／測試／提交後才開始 TT-183 初始資源拆分。
- TT-221 保留同專案、一層子任務、Epic／工時／結案及雙向關聯、版本／交易規則；只查指定關聯 ID、SQL exists 與需同步子項。以隔離 MySQL＋memory 驗證資料成長不擴大讀取。
- TT-183 量測初始 JS／CSS／字型與延遲畫面，保留 Vuetify 圖示／表單／Markdown／載入失敗回復；前後比較同環境，完成各自提交。
- 不使用業務庫壓測／seed，不 push，其他效能卡不宣稱完成。

## 2026-11-12 第三方文件規劃查重與分類補卡完成

- 已查250張現有摘要與相關詳細，只新增TT-252（文件入口／PAT與Agent指南／範例）、TT-253（OpenAPI／實際HTTP與範例CI）、TT-254（無原始碼通用客戶端文件導向E2E）；三張皆open、指派taskTrackerAI、歸TT-3及AI整合/API並以看板精準查詢驗證。
- TT-3／165／166／167／168／169／177補最新已確認規則，狀態／指派保留；TT-167移除「建議OpenAPI」舊標題，機器規格成必要交付。TT-177仍deferred且為選配，不替代必要TT-254。
- 審查交付TT-251只追蹤本次查重／建卡／文件，不代表公開API功能完成。新增工作依效能／穩定性之後排程，不預先進行中。
- 文件與讀回快照：docs/api-documentation-gap-review-2026-11-12.md及docs/planning/api-documentation-gaps-2026-11-12.json。來源規劃／API-first卡片表同步，純文件檢查，不改產品、不push。

## 2026-11-12 第三方文件規劃缺口查重與分類

- 使用者要求檢查同步後的新需求是否需要加卡，若有按主題分類；本輪只查重、補卡／補既有驗收及文件，不實作功能。
- 先讀第三方API文件規劃、API-first決策及現有TT-165～169／177等最新描述；以分頁摘要查找同義工作，不用整包Workspace或歷史manifest覆蓋進度。
- 只對尚無獨立可交付追蹤的工作新增open卡，指派taskTrackerAI、放AI整合/API主題（依實際跨主題需求另交叉收錄）；既有卡保留狀態並補明確分工。
- 驗收：讀回卡號／狀態／指派／看板與驗收文字、確認無重複，記錄文件；純規劃不跑產品build／全套測試、不push。

## 2026-11-12 同步另一對話的第三方API規劃

- 使用者要求同步遠端進度後重讀AGENTS。起點main工作區乾淨，保留本機TT-242／241兩筆提交；fetch取得遠端4筆文件提交，以merge保留雙方歷史，不rebase／改寫。
- 產品討論紀錄只有同一文件開頭新增章節衝突，保留本機兩卡驗收與遠端第三方API／OpenAPI／Agent規劃全部內容。
- 同步後依AGENTS讀取第三方文件規劃，確認效能／穩定性優先、再PAT／公開REST及必要機器文件；本輪僅同步，不實作API或宣稱整合功能完成。
- 驗證衝突標記、Markdown連結、merge父提交與兩張卡提交保留；遠端只改文件，無需重跑應用測試。本輪不push。

## 2026-11-12 TT-241驗收完成

- 一般設定、成員目錄、可指派角色分三個GET；成員SQL搜尋／20筆分頁／最大100，未開tab不讀名單或角色。舊getWorkspaceSettings整包RPC及無上限目錄方法退役，相關呼叫端／測試同步。
- 讀寫共用固定角色／canManageRole，保留ownership、permission交集與role_revision；AccountSettings經Factory注入資料入口／Replay／當次Signals。工作區訊號改cursor只讀Project IDs，afterCommit時序不變。
- 完整memory80／3725（14 native skips）、隔離MySQL94／4163；Chrome22人20＋2、一般設定／改名不查名單、角色409／局部刷新、邀請、搜尋、跨WS清除、三尺寸bulkReads=0；既有設定三尺寸回歸亦通過。Pint16檔／build／composables通過，最後設定／新查詢／Realtime9／205通過。
- 詳見docs/performance-workspace-settings-2026-11-12.md；TT-183大chunk與其他效能卡未混入結案。依指定順序完成TT-242後再完成本卡，兩卡各自提交及更新真實狀態，不push。

## 2026-11-12 TT-241開始

- TT-242已提交bfa47c6並結案後，TT-241才移inprogress。一般設定／管理成員／可指派角色分成三個GET，成員20筆分頁與SQL搜尋；未開成員tab不載名單或角色。
- 讀寫共用WorkspacePermissions的固定角色與canManageRole規則，保留ownership／permission交集及角色revision。既有getWorkspaceSettings整包RPC退役，測試與呼叫端改v1。
- 觸及AccountSettings時移除手動資料入口／replay／signals組裝，以Factory及Container提供穩定協作者與每次actor；不變更交易owner及afterCommit時序。
- 驗收需含105成員成長、Owner／Manager／一般成員、分頁／搜尋、rename／角色／邀請局部刷新、409草稿、跨Workspace與真實Chrome／MySQL。未完成前不結案。

## 2026-11-12 TT-242驗收完成

- 新增ACL metadata／有效成員／候選／歷史四個分責GET；SQL先限定Workspace／Project與有效資格、每頁20／最大100，批次當頁指派，不逐人成員get或讀歷史。
- ProjectMembers接分頁／搜尋、候選與歷史按需、保留跨頁已選及ACL草稿基準；繼承來源／可授予permission交集及canChange不變。App此頁改最小殼，不呼叫getWorkspace/getProjectMembers/getTodos。
- memory79／3670（13 native skips）、獨立MySQL92／4055；Chrome26成員20＋6、候選／歷史lazy、跨頁編輯與409、歷史20＋7、撤權、三尺寸及bulkReads=0；build與Pint通過。詳見docs/performance-project-acl-2026-11-12.md。
- 依指定順序完成本卡後才開始TT-241；其他效能與legacy入口退役仍按原卡追蹤，不宣稱全面完成。

## 2026-11-12 使用者指定順序：TT-242 → TT-241

- 先完成TT-242專案ACL管理有界成員、候選、metadata及按需歷史，前後端一起接線；驗證有效permission／可授予交集、繼承來源、revision／撤權／草稿與Network資料範圍。已在開始前將TT-242改inprogress。
- TT-242驗證並以「TT-242 專案成員ACL讀取改批次分頁並拆出候選與歷史」提交及更新卡片後，才開始TT-241；未開始前TT-241保持open。
- TT-241拆Workspace一般設定／角色選項／有界成員目錄，按tab載入，維持既有Owner入口與API permission、canChange／revision／跨WS清理。
- 使用專案Laravel／Vue與自有工程／資料存取skills，實際隔離MySQL及Chrome，不對業務庫seed／壓測、不push。最新工作區起點8d09bf8乾淨，AGENTS已更新為每卡提交格式；不重寫既有提交。

## 2026-11-12 TT-223 工時月曆依Project與月份取得必要紀錄

- 新增time-calendar月份SQL摘要（日／成員／卡片／Epic去重，各上限100）與time-entries當日分頁；前端月曆改按月／按日讀取、遠端成員與任務候選，填報後局部重讀，工時頁不再getWorkspace。
- 驗證：memory78／3617、獨立MySQL90／3949、Pint、api-client／composables、build；time-calendar-check×3（摘要972 bytes、bulkReads=0）、browser-check、issue-time、home、board-ux、account-settings、workspace-switch、markdown、member-time-details通過。發現先前階段跑建置版檢查時dist未含TT-237／217修正，本輪以新build重跑全部通過。
- 限制：缺Project＋work_date索引屬TT-182；usability-fixes-check與time-analysis-check為先前改版遺留的過期斷言，未處理。詳見docs/performance-time-calendar-2026-11-12.md。

## 2026-11-12 長版E2E恢復與分責查詢回歸修正

- 長版browser-check自11-12分責查詢後未重跑；本輪恢復全程通過（連續3次）並修正其揭露的真實回歸，每項依卡片重開→修正→提交：TT-237寫入NOT_FOUND未觸發撤權重驗；TT-238 BoardDialog缺watch匯入致新增／編輯收錄無法開啟、移除看板深連結未回列表；TT-214衝突後重新載入卡在儲存中；TT-215完整頁狀態送出中未停用、衝突後未重讀詳細、不存在卡片深連結未回Project；TT-217通知定位待辦退回後殘留；TT-180舊版#/issue/<id>深連結（新增issue-locations最小查詢）。
- E2E調整只針對既定行為改變：SQL debounce列表／搜尋／篩選等待伺服器結果、遠端PagedSelect關係與Epic候選、遠端收錄只顯示已選數、子任務分頁不顯示完成比例、草稿中背景刷新只重讀Project；看板拖曳於分頁卡片載入後才開始並在漏接drop時重試一次；撤權可能先被背景重驗卸載。未放寬權限、資料與revision斷言。
- 驗證：memory77／3540、獨立MySQL88／3795、api-client／composables、build；browser-check×3、board-ux、board-query、personal-query、home、project-utility、account-settings、workspace-switch、markdown、issue-time均通過（隔離tasktracker-perf-test）。
- TT-180其餘（月曆、工作區設定、專案成員仍用getWorkspace）未完成，移回open；下一張建議TT-223工時月曆。

## 2026-11-12 TT-243／TT-245 狀態用量與通知預覽分責（接續Codex未完成工作）

- 依AGENTS新規以卡片為單位處理：TT-243、TT-245為Codex中斷前已進行中的工作，接手完成並分別以`<卡號> <卡片標題>`提交；效能板其餘未實際處理的TT-180／226／240移回open並留言記錄已完成／未完成。
- TT-243：status-usage以SQL聚合目前Project各狀態卡數，project.statuses.update重驗；修正join欄位歧義並限制狀態同Project。前端讀取失敗時全部視為使用中避免誤刪，寫入仍交易重驗。
- TT-245：通知預覽改Project分頁20筆摘要，正文按需讀取單筆；沿用notification.readTeam或本人收件規則（另排除停用成員），SQL限定Project／收件人，撤權後列表與正文皆404；前端取消／過期回應、hidden停止與失敗退避。
- 驗證：memory77／3540、獨立MySQL（tasktracker-perf-test）88／3795、Vite build；新增tools/project-utility-check.js真實Chrome：25則通知20＋5、正文按需、其他Project不出現、狀態用量決定可否移除、receiver無設定入口、三尺寸無溢位、bulkReads=0（列表回應5968 bytes，非p95）。
- 長版browser-check在11-12分責查詢後未重跑，SQL debounce等時序斷言已改為等待伺服器結果；並發現兩項既有回歸：TT-214衝突後「重新載入最新版本」卡在儲存中（bf2a114）、TT-180舊版`#/issue/<id>`深連結無法解析（9413b7e），分別依卡片另行處理，不併入本兩卡。

## 2026-11-12 個人待辦／通知／專案目錄分責

- TT-216／217／218：SQL參與者＋scope／狀態分頁，20筆摘要；原稿及歷史按需；收件匣分頁、未讀數獨立；轉卡連結按有效Project資格批次投影，撤權後版本改變及清連結。
- App首頁／專案目錄／工作區設定／分析使用auth/current最小殼，不再預載getWorkspace/getTodos；專案目錄20筆SQL分頁與搜尋。Todo選人、Project負責人及轉卡Project採遠端有界候選。TT-240月曆等用途仍未完成。
- 個人同步只查無內容版本；不變不重讀列表，hidden停止、resume重驗、失敗退避及jitter。版本是限定本人SQL聚合，並非O(1)計數器；其餘全域同步卡保持未完成。
- 驗證：memory74／3410、獨立MySQL83／3562；Chrome待辦25筆20＋5、原稿／歷史／轉卡／通知定位及35秒idle無整包讀取；設定／工作區／分析／Markdown三尺寸通過。純API／composables及build通過；bundle仍有大chunk警告。
- 下一階段：月曆／Project管理／通知預覽仍有getWorkspace，WorkspaceSettings仍需拆分成員及歷史；TT-180不結案，其他P1不宣稱整體通過。詳見docs/performance-personal-pages-2026-11-12.md。

## 2026-11-12 任務列表／詳細／單卡工時接線

- TT-214／215／244：ProjectTasksWorkspace共用頁面owner，列表SQL filters／scope／20筆分頁與純列表DTO，深連結hydrate最新revision與關聯，活動／子卡按需分頁；單卡工時SQL SUM／作者資格／void與20＋7分頁。
- TT-240本階段只完成卡片表單／篩選的member／Epic／parent／related遠端選項、debounce／取消與已選保留；Project負責人、轉卡及月曆其餘用途仍inprogress，不能整張結案。
- 真實Chrome104卡列表／我的任務／第二頁／SQL描述搜尋、103張看板卡片與跨頁候選關聯保存、詳細活動均bulkReads=0；完整工時三尺寸／409草稿／重試／去重／作者／撤權通過。memory72／3185、MySQL80／3306，Pint14檔；見docs/performance-task-pages-2026-11-12.md。
- 原getWorkspace/getTodos在首頁及其他管理／月曆route仍待遷移；下一階段優先處理首頁／Todo與通知，之後續其餘P1。

## 2026-11-12 本地MySQL／Redis同步切換（TT-247）

- 使用者追加授權因SQL RTT影響效率而切回本地，先停寫／排空Queue、33表最新完整備份、空目標匯入與全列內容雜湊核對。5818筆一致；舊庫／外部／APP_KEY保留。
- 現用本地MySQL8.4.13與Redis，獨立新volume由私有override選定；compose環境prefix明確，非直接切舊資料。AI登入及39張效能卡讀回正常，正常單次摘要49ms／卡片30ms，非p95。詳見docs/local-services-return-2026-11-12.md。
- 任務列表／詳細接線另階段仍在實作，工作區未提交的相關變更保留；本階段僅提交infra與文件。其餘效能卡繼續處理。

## 2026-11-12 看板查詢接線階段

- TT-237～239：共用HTTP client、Project限定Board摘要／卡片SQL分頁，BoardWorkspace／查詢與活動composables接線。真實Chrome103卡確認第2頁與bulkReads=0，描述／關聯保存、活動按需；原看板多尺寸回歸通過。
- 新增最小成員選項及單卡工時查詢供看板使用；其餘選項／列表／工時route未宣稱完成。八項審查、原生MySQL聚合與遠端正常讀取限制見docs/performance-board-implementation-2026-11-12.md。
- 完整回歸：memory72／3158、native MySQL80／3262、API client協定及Vite build通過；Pint20檔通過。TT-237～239依此階段驗收更新，其他P1仍未完成。
- 下一階段：其餘頁面移除getWorkspace/getTodos及資料存取P1；不得因本階段close全效能板。

## 2026-11-12 效能卡全面實作，TT-237～239優先

- 使用者授權完成效能優化卡，並同步修正違反目前規範的前後端實作；優先交付TT-237～239。先完成query client，再讓看板列表／內容透過專責API與頁面owner載入，不依賴整包Workspace。
- 新增／修改遵守本地DI／PHPDoc／資料存取規範；交易與有效授權不變。逐階段測試、commit、更新卡片；長期觀測與外部正式拓樸等未取得證據不得假結案。
- 階段：HTTP client與安全測試；看板SQL摘要／有界卡片及前端深連結／編輯／事件刷新；其他列表／Todo／工時／設定接線；同步與查詢／runtime／量測。按實際完成續接，不因底層端點存在宣稱前端已改。

- TT-237第一階段：新增共用http.js與GET adapter，分開DomainError／取消／逾時／網路／無效JSON，保留Workspace generation與CSRF同requestId重送；Node傳輸測試與Vite build通過。仍需後續真實看板接線整合才結案。見docs/performance-board-implementation-2026-11-12.md。

## 2026-11-12 效能與前後端重構缺口補卡

- 使用者要求盤點尚未開卡的效能及前後端重構工作，特別包含後端介面變動後的前端接線；本輪只審查查重／補卡，不實作功能。
- 先比對最新程式、DI／PHPDoc計畫、v1契約與真實卡片，既有卡能承接者補充驗收與分工，只有未覆蓋項目才新增；所有新工作設open、指派AI並收適當主題／效能板，不把規範整理當程式完成。
- 交付缺口對照／優先與依賴、API讀回驗證及本機文件commit，不操作主庫測試或重啟服務。

- 已完成：新增TT-232～246共15張open工作卡（12項P1／3項P2建議順序）指派AI，6張AI/API板、9張效能板；補強TT-180／178／179／214／215／219／226／168共8張，原狀態與指派保留。TT-231為本輪審查交付卡，不將工作卡結案。
- API分頁讀回確認230→246與15張open／指派；限定16新ID唯讀SQL核對看板收錄16／16。完整對照docs/refactor-gap-review-2026-11-12.md及planning/refactor-gaps-2026-11-12.json，JSON／依賴鍵／文件連結／diff檢查；無產品碼、migration、seed、重啟／壓測或業務測試。

## 2026-11-12 專案自有開發規範skill整理

- 使用者要求讀取docs/backend-di-phpdoc-refactor-plan.md，將AGENTS與文件的長期開發規範整理為專案內自有skill，與現有Laravel／Vue／效能skills互補。本輪只整理規範與引用，不執行文件的全面DI／PHPDoc或效能程式重構。
- 新建tasktracker-development-standards，集中共通程式／資料安全、八項資料存取、DI生命週期、PHPDoc與驗證規則；AGENTS保留專案決策／作業授權，改引用正式規則；laravel-architecture保留分層／命名／Repository等專責並引用新規範。
- 驗證：入口symlink、frontmatter、全部新增相對連結、規則來源對照及新舊一致性；純文件不跑業務測試／重啟服務。檢查本機stage後commit，不push。

- 已完成入口與6份reference、根／後端AGENTS及Architecture／skills索引引用，八項資料存取完整保留。驗證7份Markdown、全部skill相對連結、frontmatter、本地symlink及三處必要入口引用通過；diff空白通過。原DI計畫與產品程式、vendor／依賴、資料庫均未修改，不執行業務測試或重啟。

- 自我追蹤：查重PHPDoc／skill既有卡後建立TT-230，指派AI並收錄AI整合/API板；僅本輪規範整理驗收後結案，原DI／效能工作不連帶結案。八項規則另與HEAD正文逐項去空白比對一致。

## 2026-11-12 API資料存取規範與續作

- 使用者新增八項資料存取審查要求，已加入根AGENTS；明確授權調整過量API契約，不以歷史相容承諾阻擋合理分責。同步本地Architecture規範與接口文件。
- 重審A／B階段：有界回應已具SQL分頁與投影，但單Project授權仍讀全空間Project參照，屬未完成；前端仍依賴getWorkspace／getTodos與固定輪詢也是P1未完成，不能宣稱整體驗收。先縮限內部授權讀取與新增範圍成長測試，之後續接前端與其他資源。

- 本階段修正Project授權讀取為單資源／當頁、SQL先篩可見Project；context改只投影當次資格，不附歷史。範圍成長測試1→36 Project／5筆分頁通過；MySQL完整78／2803，最後受影響9／414。新增docs/data-access-review-2026-11-12.md八項矩陣與未完成P1。遠端正常讀取單Project1115 bytes／2567ms、20卡10277 bytes／2314ms，僅單次觀察，非p95。TT-226保持進行中，前端／Todo／輪詢／報表尚未完成，不宣稱整體驗收。

## 2026-11-11 效能改善實作與API分責

- 階段B：新增分責GET v1 Project／Issue／活動接口，SQL分頁及最小欄位、有效permission快照與舊RPC共存。ScopedReadApiTest＋ProjectAclTest 8項／393 assertions通過；前端還未切換，相關卡仍進行中。

- 階段A完成：健康探針零SQL／無cookie，限定Workspace ID重用、看板批次參照、留言／關聯／歷史／收錄差量寫入，日工時SQL SUM。memory68／2511、獨立MySQL73／2578及native補測2／23通過；TT-228／229／222／220按實際驗收結案，其他工作續作。

- 使用者已授權解決效能改善並拆好API，供後續AI整合延伸。先前review-only／implementationAuthorized=false為歷史範圍，這次恢復實作；不新增PAT或工具專屬整合。
- 分階段：A健康探針／資料入口與寫入成本；B有界且權限一致的身分／Project／Issue／Todo／工時API；C前端按頁面讀取、取消／版本／事件局部刷新；D報表SQL／看板DOM與bundle；E獨立MySQL／HTTP／瀏覽器回歸與正式runtime／觀測入口。每段保留相容RPC、Session／CSRF／scope／revision／requestId，完成後測試與commit，按真實驗收更新卡片。
- 不對主庫執行seed／RefreshDatabase／壓測，不清共享Redis；主資料備份與舊volume保留。觀測週期、正式外部部署與未取得的真實負載不偽稱完成。

## 2026-11-11 效能改進看板與API職責複審

- 已完成：建立效能改進看板，16張新改善卡TT-214～229＋11張沿用待辦，共27張open且指派AI；TT-213審查交付卡closed，總收錄28。API讀回確認無重複、既有核心資料／五個看板保留，201張未處理原卡完整DTO不變；沿用11卡僅各補一則審查留言。Chrome實際登入與28卡渲染／無pageerror通過。
- 報告與明確驗收清單完成；getWorkspace單次437772 bytes／14.953s僅正常使用觀察，不是p95。程式、schema、現有輪詢／API均未改；純文件與真實建卡不重跑功能測試或重啟服務。連結／JSON／diff與秘密排除檢查通過，完成本機階段commit、不push。

- 使用者指出API過大與固定全量輪詢，明確要求建立效能改進看板、重新審查並建卡；本輪不改產品程式／契約。查後端讀取、回傳欄位、前端刷新依賴與既有拆分待辦，使用現有AI資格做一次唯讀HTTP診斷，公開報告只保存去識別數量／大小／時間，忽略目錄保留操作核對快照；不壓測主庫。
- 查重既有open待辦後建立效能改進看板，依實際缺口補卡並指派AI，附證據／驗收／優先順序；不把跨主題Epic整包加入。
- 區分既有已修的N+1／single-flight與仍待處理的全量資料耦合；提供按頁面拆分、DTO／分頁及撤權策略，記錄產品討論並回寫原待辦，不把審查當作功能完成。

## 2026-11-11 遷移至使用者指定外部MySQL及Redis

- 已完成：MySQL全33表／13,367筆（停寫最終快照）內容雜湊一致；9帳號／3工作區／4專案／310卡／7看板／174工時。切到指定外部MySQL TLS與外部Redis，舊兩服務停止、volume／SQL備份保留。根.env與秘密不進Git；外部Compose／環境範例及交接文件完成。
- Redis初次timeout，使用者完成網路設定後ACL／prefix／Cache tags驗證成功；本機queue／reserved／delayed皆0再切換。實際Queue消費、WebSocket、PDO host／TLS與Redis namespace讀回通過；Chrome登入及五看板通過。
- 隔離memory回歸66 tests／2483 assertions通過，5項獨立MySQL案例skip；兩設定Pint與Compose檢查通過。TT-211／212完成交接與結案後讀回，不對主庫跑seed或測試。MySQL暫不驗證憑證已有本輪明確授權，正式預設仍驗證，後續補CA。


- 已授權將目前全部資料遷移至129.203.169.36的task_tracker_testing，帳號josh，密碼只保存於忽略的本機秘密檔。
- 先檢查來源／目標版本、資料與權限；只對空目標匯入，既有資料不覆蓋。停止寫入後一致備份、全表遷移及內容比對，再切換API／Queue／Reverb設定並驗證登入與資料。保留原MySQL volume與備份供回復。
- Redis主機由使用者確認為另一台129.203.136.170:6379，沿用指定ACL帳密；env設定服務／環境prefix，Cache tags不作隔離。首次連線timeout，先保留本機Redis。MySQL自動審批曾拒絕未授權停用憑證驗證，使用者之後明確授權此測試環境暫用加密但不驗證TLS，後續需補CA。
- 連線不可用時保持原服務，記錄實際阻擋；不對主庫跑測試或seed。

## 2026-11-11 依SEO／部署／實際功能／AI整合API整理看板

- 已完成：四個看板5／49／110／42張，Default210；AI為Project Maintainer／Workspace user。API、前端Epic展開函式及真實Chrome逐板／手機檢查通過；原209張完整資料與revision不變，TT-210交接留言後closed。未修改產品程式、資料表或部署。

- 使用者要求依四主題建立看板，保留Default與原有卡片；已查209卡，只有Default。AI Developer不含board.manage；使用者先授權一次Owner操作，後續明確要求AI在TaskTracker專案升為Maintainer；Workspace仍user。Owner完成資格調整即登出，四看板由AI建立。
- 先完成可核對的分類清單；歷史卡依交付內容分類，跨主題Epic留Default，避免Epic動態展開混入錯誤主題。建立／重用同名看板，不更動卡片狀態、負責人或關聯。
- Owner只調整資格後登出；由AI建立並讀回看板可見性／收錄與實際UI，確認卡片完整資料／revision未變、沒有重複或遺漏，私有登入資訊不進Git。

## 2026-11-11 公開登入入口／介紹頁／SEO只建卡

- 已完成：A–D=TT-205～208 open，E沿用TT-155 open，F=TT-209 deferred／low；同步TT-151／156／2的規劃，全部AI指派。讀回欄位／狀態及5個來源key唯一性通過，204→209卡，其餘200張完整內容／revision不變。對照文件及產品紀錄已更新；未改程式／schema／部署。

- 使用者指定24a34f91bbe7822c186742b1384ed992aa856056的docs/public-login-landing-seo-plan-2026-11-11.md；只查重及建待辦，不實作功能或部署。
- 以AI讀取目前204張TaskTracker卡片並比對全文；A–D沒有同等驗收項目，登入P3外觀候選不等於入口／公開頁／SEO。E與既有靜態前端部署TT-155及runtime TT-151重疊，優先沿用原卡調整。
- 預計新增A–D為open、F為deferred；E沿用TT-155，並使TT-151／156及部署Epic的規劃符合單Fly App、取消獨立Pages的最新方向。保留舊描述／追蹤鍵與歷史，全部沿用AI指派，不改已完成卡片。
- 逐欄讀回、以來源key再次查重、核對無重複新增及無其他Project異動；交付來源／項目對照文件並commit。原202卡清單只作歷史快照，不覆蓋live進度。

## 2026-11-11 指派、留言、mention與通知盤點

- 已完成：docs/notification-audit-2026-11-11.md與產品紀錄，確認Issue preview／Todo Inbox分開且無mention；11 tests／202 assertions隔離回歸通過。審查TT-204留言後closed；TT-190／189補發現並維持deferred，讀回核對通過；未改產品程式或發真信。

- 使用者要求盤點正式卡片指派／被留言／@mention會不會通知；先查後端事件、儲存／閱覽權限、前端鈴鐺與刷新、SMTP／Reverb及既有測試，不直接製作功能。
- 建立本輪審查子卡，整理觸發矩陣、可見入口及缺口，區分正式卡片preview與Todo站內未讀通知；@mention若無功能則列後續候選。
- 跑既有隔離memory測試驗證相關權限／通知流程，不在主庫建立測試業務資料；審查與建議同步產品紀錄及原通知待辦，功能卡不因報告完成結案。

## 2026-11-11 確認API-first平台範圍與共用業務邏輯

- 已完成：docs/api-first-platform.md及四個文件／指引入口同步；10卡更新讀回通過，核心open、TT-174／175／177 deferred／low。TT-203僅規劃交付結案；連結／diff檢查通過，未改產品程式或他專案。

- 採使用者最後收斂的方向：REST API、PAT、Scope、現行成員／資源授權及撤銷／到期管理是核心；OpenAPI建議，MCP暫不自建，工具專屬整合／Agent編排不做。
- 建立架構決策文件，Web／外部API共用Application Service、授權與資料層；先盤點既有API，相容RPC保留，不因本次討論直接安裝Sanctum或重寫認證。
- 新增本輪規劃交付子卡並在完成後結案；同步既有整合卡範圍，Webhook低優先暫緩，外部客戶端聯調不作平台第一版阻擋條件；保留來源與沿革，不覆寫初次匯入快照。
- 驗證文件連結／差異、卡片內容及狀態；本輪不實作產品API、不跑主庫測試、不調整服務或部署，階段完成本機commit。

## 2026-11-11 個人Token與AI整合產品方向（討論）

- 核對既有Session RPC、整合待辦卡與Laravel Sanctum官方文件；現況可讀卡但缺PAT與獨立分頁／單卡公開API。
- 使用者重視AI／第三方整合的競爭力；記錄PAT管理、有效權限交集與AI友善查詢的建議，套件及細節未定案。本輪只同步產品紀錄與TT-165／167／168，不安裝套件／修改API／將功能結案。
- 驗證：來源／差異檢查，AI帳號留言後讀回與狀態保持open；文件階段本機commit。

## 2026-11-11 TaskTracker自我追蹤專案與AI帳號

- 已完成202卡匯入：136 closed／49 open／17 deferred，全部指派taskTrackerAI；本次TT-202驗收／留言後結案。完整欄位及Epic、AI僅本Project Developer、拒絕管理與他專案查詢、原81卡ID／revision不變及實際桌機／手機UI均通過。私有登入檔不進Git；交接與對照記於docs/tasktracker-self-tracking.md，未執行未來部署重構。

- 讀取使用者的低成本部署討論文件及b0c763d的Multi-Agent實測需求，連同Git／產品紀錄盤點已完成與未完成工作；文件中的開發指令只作需求來源，本輪不開始部署重構。
- 以既有應用API登入使用者指定帳號，在SideProject建立TaskTracker專案；邀請taskTrackerAI為空間一般成員，僅授予此專案Developer，登入資訊存於忽略的.local/且限制檔案權限。
- 卡片按可驗收成果整理，有來源、完成依據／驗收條件與穩定追蹤鍵；全部指派AI，closed僅給有證據的完成項目。未決提案與已移除方向分開記錄，不虛構開工、期限或工時。
- 經API逐筆讀回核對、AI實際登入及專案隔離／權限與UI查驗，紀錄專案／卡片對照與後續使用入口，分階段本機commit，不push。

## 2026-11-11 Hallmark／Unslop安裝與介面去AI化

- 第一批已完成：低權重reset排除Vuetify、內容標題句點／假鍵帽／空狀態裝飾移除、專案卡片文字／圖示／hover整理；品牌及業務契約保留。production build、隔離登入／專案建立與編輯／鍵盤／抽屜、三尺寸截圖、既有board與settings回歸通過；Taste餘下登入版型三項已記為P3，不宣稱零警告。

- 階段二：已完成隔離登入、48張三尺寸截圖及computed style補測；整體light slop。使用者已確認報告第一批修整方向，登入按鈕／標題句點／假鍵帽／空狀態與專案卡片為本輪範圍，其餘提案保留。

- 使用者要求安裝兩份skill，並用Unslop掃描專案去AI化；選UI導向heydamir/unslop，非同名文章編輯skill，Hallmark用Nutlope官方來源。
- 階段一完成：204個固定原文／素材／LICENSE檔案逐一核對Git blob與SHA-256，兩個專案symlink、manifest及適用配置建立；保留Unslop作者署名與先確認方向的流程。
- 階段一：固定來源commit、原文／references／scripts／LICENSE與校驗manifest，建立專案入口及適用配置，確認與既有產品／品牌／Vue規則的優先序，再commit。
- 階段二：隔離MySQL登入與三尺寸畫面＋原始碼盤點，按Unslop選有證據的文案／版面／視覺修正，保留功能與使用者資料，驗證並commit。
- 不因去AI化改框架、角色／ACL、資料庫或業務契約；不以風格禁令強制換紫色品牌，不上傳私有畫面給外部生成服務。

## 2026-11-09 返回導覽視覺整理

- 使用者認為看板返回文字缺乏設計感；本輪調整該入口，盤點並統一同類返回導覽，不改路由或擴張全站重設計。
- 採兩份UI／UX app-screen流程及Vue規範，保留紫色品牌；返回改中性箭頭按鈕，看板名稱與返回同列，hover／focus／觸控尺寸清楚。
- 已完成：build、既有看板UX回歸；三類返回入口各三尺寸44px／無底線／鍵盤focus及Enter目的地／無溢位。實際前後截圖檢視與skill inspector完成，既有規則例外／警告記錄於docs/navigation-polish-2026-11-09.md；隔離資料清理，本機commit。

## 2026-11-09 本機主庫改用MySQL

- 使用者已授權本機與Docker改為MySQL，PostgreSQL可選但本輪採MySQL 8.4 LTS；保留SQLite來源與既有資料，不重設seed代替遷移。
- 使用專案Laravel skills；API／Session／permission＋scope／revision／requestId維持。
- 階段一完成：MySQL schema／UTC微秒與DTO適配、User／Workspace交易鎖、保留來源的空目標Importer、Compose與秘密配置；完整71項2550 assertions、真實MySQL瀏覽器／多空間／工時分析通過。主服務尚未切換，下一階段離線備份匯入。
- 階段一：審查MySQL schema及並行差異，加入明確交易鎖、遷移工具與隔離MySQL整合／競態驗證。
- 階段二／三完成：主SQLite完整性檢查與一致備份，空MySQL匯入及重啟前後全表核對一致；原6帳號／2工作區／16卡／174工時保留。主服務已切MySQL且健康，Queue／Redis／WS探針通過；主設定下預設66項回歸與業務表哨兵不變，測試project清理完成。
- 階段二：Compose加入MySQL與持久化／健康檢查／本機秘密，PHP pdo_mysql、啟動與設定適配；空目標匯入舊SQLite並核對資料，再切換及重啟服務。
- 階段三：真實MySQL API／瀏覽器流程、Queue／WebSocket及資料持久化驗證，更新README／設計與技能適配，各階段commit。
- 限制：不push、不刪舊volume／SQLite、不外露MySQL埠；測試只使用獨立資料庫與帳號，不允許通用測試命令清空主庫。

## 2026-11-09 效能改善實作

- 使用者已要求依複掃調整；採Addy診斷／ECC量測，Laravel與Vue原有契約保留。
- 階段一已完成：完整63 tests／2458 assertions，四角色／撤權與SQL成長驗證，五輪工作區SQL3635→55且增加200筆仍55，報表71→22；輸出bytes保留。
- 階段一：唯讀用例批次載入授權映射，移除每工時／成員重查；既有寫入仍即時查DB。補SQL成長、角色等價／撤權測試，跑完整後端與隔離量測並commit。
- 階段二完成：刷新合併與看板索引；build／composables、完整瀏覽器、看板及跨工作區回歸通過。500卡HTTP中位370→40ms（含階段一）、雙刷新2→1；DOM仍12250，密度切換未明顯改善，記錄限制。
- 階段二：合併同scope工作區刷新，建立看板共享索引／分欄資料，減少逐卡掃描；實測500卡／既有瀏覽器回歸後commit。
- 階段三完成：getWorkspace／getTimeReport限定DEFERRED＋query_only，其他維持IMMEDIATE；SQLite雙連線／快照／誤寫／巢狀還原及HTTP白名單通過，完整66 tests／2483 assertions，工時分析／成員明細瀏覽器通過。
- 階段三：審核唯讀DEFERRED交易，保留寫入IMMEDIATE與Session blocking／CSRF／scope；兩連線與API隔離測試證實後採用並commit。
- 收尾完成：Queue／Reverb重啟且健康，API／前端／Redis維持運作；本輪隔離tmpfs容器已移除，沒有重設主資料、清Redis或push。
- 其他：保留API資料契約；全量bootstrap API拆分、SQL報表聚合／分頁與正式虛擬化若超出本輪可驗證範圍，明確列剩餘，不以快取或刪欄位規避。

## 2026-11-06～11-09 Addy／ECC效能複掃

- 需求：安裝skills後重新掃描效能；本輪為量測／review，不修改業務程式或自動落地快取。
- 主診斷用Addy，ECC補領域／HTTP／瀏覽器基準；保留Laravel／Vue與隔離／授權契約。
- 預算：領域隔離取樣5輪；獨立tmpfs SQLite API測固定seed與增加200工時，HTTP各主要讀取30次序列取樣；瀏覽器檢查登入後資料就緒與看板互動／DOM／請求，不對主服務壓測。
- 步驟：核對來源／baseline→SQL成長與query plan→隔離HTTP→瀏覽器／bundle與刷新→整理基準JSON及優先級→清理隔離程序／本機commit。
- 不把單次memory時間當p95、不把開發伺服器容量當正式部署，未執行的併發與RUM明確列缺口。
- 追加有限實驗：原生SQLite雙連線WAL各模式5輪，核對IMMEDIATE寫鎖風險；固定374筆工時再把FIRST看板擴成500張卡，以3輪瀏覽器取樣確認DOM／切換成本，不混成僅工時增長的比較。
- 2026-11-09完成：五輪領域、三角色共627次HTTP暖身／取樣、13輪真瀏覽器、query plan及PHP／SQLite雙連線鎖。Owner HTTP中位203→351ms、Developer SQL2729→6529、500卡12250元素；刷新重複請求已證實。
- 交付：docs/performance-scan-2026-11-09.md、.ecc/benchmarks基準JSON、兩個診斷工具。build／語法／PHP格式／資料與文件檢查通過，未改業務程式／schema／主資料；隔離容器結束清理，未push。

## 2026-11-06 啟用Addy主診斷＋ECC補量測

- 使用者已確認選這兩個skill；本輪安裝／配置專案入口，不開始業務重構或效能壓測。
- 固定上一輪查證commit，下載原文與完整必要references／LICENSE，保存逐檔來源與雜湊；不執行installer、hooks或全域安裝。
- Addy負責瓶頸診斷與實驗；ECC benchmark負責基準／前後比較；依Laravel／Vue／SQLite及既有隔離／permission／revision契約配置適用邊界。
- 步驟：盤點既有來源→下載並校驗→專案symlink與規範→文件及相對連結驗證→本機commit。
- 已完成：6份固定原文／必要references／MIT LICENSE，來源manifest及兩個相對symlink；Addy外層reference依上游階層保留，vendor未改寫。
- 已完成：AGENTS與索引／配置／產品紀錄更新；6檔hash／Git blob／大小／LICENSE、兩入口frontmatter、跨目錄reference與文件相對連結驗證通過。純skills／文件，不跑業務測試或重啟服務。

## 2026-11-06 GitHub效能分析skill選型

- 需求：找GitHub高星效能分析skills三個，核對星數與原始內容，評估Laravel／Vue／SQLite／Redis適配。
- 範圍：只調研與文件，未要求安裝，不執行上游腳本或改動產品。
- 方法：廣泛搜尋→GitHub原始repository即時星數與skill路徑→實際內容／相依／限制比較→依本專案已測瓶頸選型。
- 排名說明：星數屬來源repository；公開搜尋不能證明GitHub全站絕對前三，明確列候選範圍與排除原因。
- 已完成：API核對高星來源與完整tree，代表前三ECC benchmark／gstack benchmark／Addy performance-optimization，固定commit及SHA-256存來源JSON。
- 已完成：原文與必要references比較；建議Addy主診斷、ECC補量測，gstack暫不優先。docs/performance-skills-survey-2026-11-06.md記證據及本專案適配；僅文件研究，無安裝／啟用或產品改動。

## 2026-11-06 工時分析視覺整理

## 2026-11-06 效能／架構與Redis review

- 需求：全面檢查重構機會、N+1與前後端效能，確認Redis服務及使用位置。
- 範圍：先用隔離記憶體SQLite量測工作區讀取與工時分析；盤點SQL、回應大小、PHP聚合、Vue掃描與刷新，以及Compose Redis／Queue／鎖。
- 保留既有資料、API與即時撤權；不以快取掩蓋重複查詢，不擅自轉移Session或清空Redis。
- 步驟：閱讀程式與規範→取得可重現查詢證據→驗證Redis／Queue／WebSocket→整理優先級、可採方案與風險→文件及階段commit。
- 本輪為review，未經實測不宣稱加速，不擴張為全庫重寫。
- 已完成：隔離memory SQLite兩輪取樣，Workspace 3635→6435 SQL（增加200工時），任務14／114張皆14 SQL，期間報表固定71 SQL。
- 已完成：執行中Redis cache／queue／session driver確認；既有探針Redis、Cache、worker消費與WebSocket握手通過。
- 交付：docs/performance-review-2026-11-06.md及backend/tools/profile-reads.php，依證據區分P1／P2與建議，列Redis使用位置／失效／租戶／故障邊界。未改業務程式、schema、Session或使用者資料；沒有宣稱問題已修復。


- 使用者認為工時分析缺乏設計感，本輪實作既有分析及成員明細的視覺層次；不新增分析指標或放寬權限。
- 使用專案ui-ux-pro-max／design-taste-frontend app-screen／Vue skills，保留紫色品牌、Vue／Vuetify與繁體中文。以真實隔離seed登入截圖比較，不用假統計裝飾畫面。
- 決定：分析入口做可辨識的三種圖形；報表統一標題／切換／日期篩選／精簡總量／圖表／明細層次。成員用專案堆疊條、專案用直條比較、趨勢改成可讀且可選日期的響應式折線。保留已套用範圍、返回／撤權／錯誤處理及每頁20筆成員明細。
- 保留使用者文案原則：不補情緒標語、長篇教學或假成長率。新增視覺呈現是本輪實作選擇，不視為使用者採納未討論的新產品規則。
- 驗證：先取得隔離seed的現況，再build／分析與成員明細／Owner及Manager seed登入、1440／768／390畫面，檢視實際截圖並修正；不寫主資料庫，不更動後端契約。
- 已完成：三入口圖形示意、頁內分析切換、集中篩選、主次總量、成員堆疊／專案直條／響應式趨勢與日期游標；一般分析明細加入前端20筆分頁，圖表及總計仍涵蓋完整期間。成員明細統一切換與展開視覺。
- 驗證通過：production build、原分析／成員明細流程、Owner與Manager真實Seed登入、專案柱形聚焦、日期游標、20＋8筆分頁及完整28天圖表／總計；1440／768／390無整頁溢位。實際檢視桌面、平板及手機畫面，修正平板日期截字、手機刻度換行與拍攝捲動位置。
- skill lint 0 FAIL／0 WARN；登入DOM／CSS快照inspect三種分析各三尺寸最終均0 FAIL／0 WARN，僅明確豁免使用者已選紫色品牌。自動靜態快照不當成互動證據；其他控制與撤權由隔離真實API流程驗證。
- 移除未載入的Inter宣告，保留既有系統後備字型，沒有引入外部字型請求；明確移除分析主按鈕舊陰影。文件docs/time-analysis-ui-2026-11-06.md記設計與邊界；後端／schema／業務DB未改，本輪隔離容器結束清理，測試產物不提交。


## 2026-11-06 重構前後評價（檢視結果／後續建議未採納）

- 使用者要求評價，這輪只對照f71969e與78f1470及上一輪測試證據，不修改產品程式。
- 判斷：重構有實質價值，後端責任與資料讀取改善最明顯；前端任務查詢、列表及移動生命週期改善，但App仍承擔大量導覽／工作區／草稿協調。尚屬新舊架構並存的過渡期。
- 依據：帳號服務可在非HTTP測試、不改Auth；工時SQL先限定租戶／期間並用DTO投影；卡片移動有跨空間／卸載失效。後端測試59→61項，新增驗證非HTTP帳號及公開ID跨租戶碰撞；瀏覽器及WebSocket回歸通過，不能據此推論所有風險已消除。
- 代價：檔案與追蹤路徑增加；一般寫入仍走通用Request／RecordStore array與動態派送，和工時專用路徑並存。FormRequest／DTO的格式與不變條件需共同維護；沒有效能壓測，不宣稱實測加速或正式部署品質。
- 建議／待採納：先按任務及工時寫入用例逐步統一Request／DTO，同時保護原receipt簽章；再評估App導覽與工作區生命週期邊界。SQL聚合／群組分頁依實測資料量安排，不機械拆出所有Service／Action／Repository。

## 2026-11-06 前後端架構重構

- 使用者已恢復先前暫緩的重構，依本專案ECC／Laravel Architecture／Vue skills執行；不是新功能或UI改版。
- 階段一：將HTTP／Session協調移回Http層，AccountService只處理帳號及資格，清除RealtimeController的資料存取／授權流程；保留原交易、Session blocking、CSRF及X-Workspace-ID。
- 階段一完成：Http/ApiExecution、WorkspaceSession、RealtimeSession與ProjectChannelAccess分責，帳號服務不依賴Request／Auth；readonly AuthenticatedAccountData承接帳號結果。完整既有59項＋新帳號領域1項測試通過，登入、切換、邀請、舊憑證升級與回滾契約保留。
- 階段二：工時分析輸入專用FormRequest／readonly DTO，透過租戶限定Eloquent Repository讀取有效範圍，不載入整個工作區歷史工時及卡片；服務持有權限與聚合，Resource沿用既有envelope。
- 階段二完成：專用FormRequest allowlist／readonly DTO／Eloquent Repository，工時SQL先限定工作區與範圍，一次join明確投影且不載入歷史。共用HTTP交易、CSRF、X-Workspace-ID、逐案權限及回應不變；完整61項／2416 assertions通過，含相同公開ID跨租戶及單查詢投影測試。
- 階段三：App.vue抽出任務查詢／分頁、列表呈現與獨立互動生命週期，保留既有hash路由、草稿、事件與跨空間清理，不新增Router／Pinia或重做畫面。
- 階段三完成：useIssueQuery只持有篩選／分頁，IssueFilters擁有顯示開關及不可變v-model，IssueList computed投影列表；useIssueTransitions管理重試／並行／跨空間與卸載失效，useToast／useWorkspaceClock擁有計時器，DOM ref改useTemplateRef。App保留導覽、草稿與跨功能協調。
- 最終驗證：完整後端61項／2416 assertions、production build、Vue composable狀態檢查、完整browser-check、工作區切換、工時分析／成員明細、看板及獨立Reverb實際同步／停啟重連／撤權流程通過；已檢視桌面列表與手機篩選截圖。修正既有關閉按鈕模糊定位及工作區reload後需等待載入的測試時序，未放寬產品斷言。
- 交付文件docs/refactoring-2026-11-06.md列責任、修改入口與保留相容層；上游skills未改，本地Architecture僅更新先暫緩後恢復的授權註記。業務DB／schema未改，未push；測試API與Realtime專屬資料卷結束後清理，主queue／websocket重啟載入程式。
- 驗證：先取得完整後端與build baseline，逐階段跑適當測試；跨層完成後以tmpfs隔離API跑完整瀏覽器、分析及切換／草稿／看板回歸，重啟受影響長駐程序。每階段各自commit，不改schema／業務資料或push。

## 2026-11-06 成員工時明細

- 使用者已要求完成成員分析：查看哪一天、哪些卡片、時數與填報說明；日期／卡片切換及明細分頁。
- 沿用getTimeReport及Session／CSRF／workspace.timeReports.read／逐專案timeEntry.readTeam；只在明確選定成員時回授權後明細，不將note加入一般總覽。
- TimeAnalysis管理總覽與已套用範圍；獨立TimeMemberDetails管理分組、分頁、展開、請求過期與錯誤，保留返回入口。明細每頁20列、展開紀錄也分頁，總計涵蓋完整範圍。
- 先跑既有TimeReport測試baseline，補聚合／分頁／隔離／作廢／輸入驗證與瀏覽器日期／卡片切換、重試及手機；不動正式資料或進行全庫重構。
- 已完成：成員名稱與堆疊區段進明細，日期／卡片切換、說明展開及兩層各20筆分頁；明細額外檢查issue.read，說明採明確四欄投影及純文字顯示。一般總覽仍不帶note。
- 驗證通過：完整後端59 tests／2391 assertions；最後投影修正後受影響3項測試再次通過，Pint新增兩檔通過，production build、原工時分析及新成員明細瀏覽器整合通過。包含跨租戶／無權卡片／作廢／頁碼／Epic、背景保留、撤權清除、錯誤重試、HTML文字安全、1440／768／390；已檢視桌面日期及手機卡片畫面。
- API仍為RecordStore記憶體彙整後分頁，非SQL分頁或大量資料壓測；一般總覽表格不在此輪分頁範圍。成員明細未新增深連結，重新整理回分析總覽。未寫使用者DB、寄信、push或更動schema；本輪tmpfs測試容器結束後清理。


## 2026-11-06 工時分析長表格檢查

- 核對目前逐日明細v-for及API最多366天：長期間會拉長頁面，成員／專案表格則依群組數增加。
- 建議明細每頁20筆、圖表與總計維持完整期間；僅記待採納方案，未實作分頁或虛擬列表，也未宣稱已量測效能。
- 驗證：比對前端渲染與後端日期限制，文件diff及空白檢查；純討論文件不跑業務測試。

## 2026-11-06 工時分析意義review、圖表與示範資料

- 使用者要求review三種分析實際意義、加入圖表，seed能呈現成員／專案／日期差異；不啟動先前暫緩的全庫重構。
- 發現：三頁同樣總量缺少下一步、成員跨專案分配不明、趨勢刻度不清；工時不足以推論績效／效率／完成率。成員採專案堆疊長條，專案採投入比較並可聚焦成員，趨勢補軸刻度與日值；明細保留。
- 範圍：既有report API只加已授權原始工時的member-project聚合，不回原始note；沿用permission／scope／作廢排除／Epic去重。前端request生命週期仍由TimeAnalysis持有，圖表子元件只接資料與發選取事件。
- 使用者追加確認：所有demo建立邏輯集中database/seeders/DemoSeeder.php，允許local／testing，其他環境入口即拒絕；移除Services中的DemoData／DemoService／DemoTimeData及ACL示範配置。DatabaseSeeder／CLI／原載入示範API共用Seeder，不改原正常業務服務責任。
- Seed在既有f-ws first／second補最近28天、四種帳號不同分配，透過本人服務及每日上限驗證；標記當月已補，重跑不覆寫工時／密碼／卡片，不改其他工作區。先隔離測試再備份／套本機seed，非reset。
- 已完成成員堆疊／專案比較／帶刻度及日期選值趨勢；圖表可聚焦到對應成員／專案，API交叉聚合不回note。TimeAnalysis仍持有請求／篩選，圖表各自擁有呈現責任。
- 完整後端57 tests／2289 assertions、production build、分析專用／seed Owner與Manager登入／完整browser-check通過，含環境拒絕、種子保留及跨月去重、撤權、篩選聚焦與1440／768／390圖表；已人工檢視實際截圖。
- 本機先備份SQLite，非reset新增168筆，逐列確認既有帳號、資格、專案、卡片與工時未變，FK正常；重跑不再新增。Queue／Reverb重啟，隔離tmpfs容器結束清理。
- docs/time-analysis-review-2026-11-06.md記實用性判斷、這輪呈現選擇、限制及未做功能，README／AGENTS／設計同步；無schema遷移、寄信、全庫重構或push，完成後本機commit。

## 2026-11-06 安裝ECC／Vue與專案Laravel Architecture skills

- 原要求安裝ECC laravel-patterns與vuejs-ai/vue-best-practices，新增Laravel Architecture補充分層規則；使用者隨後明確要求暫緩重構，本輪僅skills及規範／文件。
- 官方GitHub固定commit、原文及LICENSE存skills/vendor，以.agents/skills相對symlink啟用；不全域安裝、不執行上游腳本、不引入其餘ECC skills。
- 新Laravel Architecture定義Repository／Service／Action邊界、CRUD例外、回傳型別、Resource、命名、交易及Unit／Feature測試；衝突以ECC為準，TaskTracker產品契約及資料隔離保留。
- 已安裝固定版本ECC（b38be6a7）／Vue（198f0cd8）與本地Laravel Architecture，三個.agents入口皆有效；原文含LICENSE共26檔Git blob／SHA-256校驗通過。
- 已明訂Controller／Request／Service／Action／Repository／Model／Resource責任、簡單CRUD例外、Model／Collection／Paginator／DTO回傳、命名、transaction owner及按風險的Unit／Feature測試矩陣；ECC優先於舊慣例，保留產品契約與資料隔離。
- 舊backend-development-standards及其symlink已依指示移除，原來源清單移skills/history供追溯。root／backend AGENTS、CLAUDE、README與skills索引同步，不修改共用目錄或全域環境。
- 驗證通過：26檔雜湊、全部skill symlink、三個frontmatter、Vue四份核心reference、本地Markdown連結及diff空白；沒有產品程式／測試／依賴／DB／Docker變更，純文件不跑業務測試。配置與衝突表見docs/development-skills.md。
- 不修改frontend/src、backend/app、測試、依賴、DB或Docker，不宣稱現有程式已符合新規範。

## 2026-11-06 限縮新增任務入口

- 使用者要求頁首新增任務只在任務列表出現；看板由欄位／泳道的＋建立，其他專案畫面不顯示。
- 實作範圍：任務列表all保留頁首與空狀態新增，我的任務移除新增；看板列表、看板、工時、狀態設定、成員、通知不顯示頁首新增。不改既有權限與看板原子收錄。
- 已沿用專案兩個UI／UX skill，頁首與空狀態使用同一顯示條件，既有看板回歸改從欄位＋建立；原canCreateIssue授權及原子收錄不變。
- production build及編譯版隔離瀏覽器實測通過：列表保存卡片、我的任務空狀態無新增、看板列表／看板／工時／狀態／成員／通知無頁首新增、自訂看板＋帶入進行中並原子收錄、手機＋可開表單；已檢視1440／390畫面無溢出／瀏覽器錯誤。本輪無後端變更，不重跑完整回歸。
- README／AGENTS／產品紀錄同步；來源掛載生效，不重啟主服務，隔離tmpfs容器完成後清理；本機commit、不push。

## 2026-11-06 工作空間設定獨立頁面

- 使用者要求移除工作空間設定彈窗，改獨立頁面，保留擴充更多設定的空間。
- 保留一般設定／成員與權限、改名、邀請與身份下拉；不變更後端契約或授權。Owner入口仍依workspace.ownership。
- 新增#/workspace/settings與/members，可分享、重整及瀏覽器上一頁；sidebar active與麵包屑一致，離開／切分頁／切工作區／登出保護草稿及送出中狀態。
- 已以WorkspaceSettings取代TeamDialog，頁面分頁與路由同步，取消返回保留網址／草稿，Owner側欄active及麵包屑正確；一般成員深連結回首頁，不混入任務區塊。
- 驗證通過：production build、編譯版test:settings、test:workspaces及完整browser-check；涵蓋改名衝突、邀請、身份確認／失敗重試、深連結／重整／返回、草稿取消／送出中保護與跨工作區清理。回歸的狀態篩選操作改依實際開合值，避免自動收合後重複切換造成測試等待，無產品狀態篩選變更。
- 依專案兩個UI／UX skill檢視1440／768／390設定頁與成員頁，無橫向溢出；保留現有品牌與平整工作區，不加入介紹文案。頁尾保持底部。
- README、AGENTS、產品紀錄及帳號／多工作區文件同步；未改後端、API、資料或權限，來源掛載生效，無需重啟主服務。隔離tmpfs容器完成後清理，本階段本機commit、不push。

## 2026-11-06 明確狀態 type 與獨立結案

- 使用者確認：open／closed必備，狀態設定改為英文type與顯示名稱，不再編輯分類；列表直接篩選實際狀態。
- 實作選擇：既有status.id就是唯一英文type，沿用資料庫code與Issue.state，避免另建重複識別欄位。既有type固定、可改顯示名稱／顏色／順序；新type可輸入，open／closed不可移除。
- 新專案預設open（待規劃）、inprogress（進行中）、closed（已關閉）。舊專案補open／closed，保留所有原卡片狀態與歷史；done不自動轉closed，只有closed算結案。
- 已移除介面與API可編輯category；舊DB欄位僅相容保存。列表與看板可多選實際狀態（OR），其他篩選維持AND，移除open／done分類條件。
- 驗證通過：後端56 tests／1117 assertions（必備／重複／格式／使用中／版本與重試／done不等於closed／升級冪等與外鍵／匯入）；production build、完整browser-check通過。同步更新既有看板／即時同步測試fixture的新預設ID；未另啟即時同步測試環境。
- 另以隔離API確認1440／768／390狀態設定不溢出、精確多選篩選及Escape；檢視桌面與手機截圖。沿用既有品牌與表單，不新增介紹文案。
- 主服務已先備份SQLite，再執行migration並重啟Queue／Reverb；逐列比對備份，帳號、成員、卡片、留言、關聯、工時、看板與待辦未變，FK檢查通過，主服務健康。隔離tmpfs測試容器完成後清理。
- docs/status-types.md記錄契約、歷史資料、回退邊界，README／AGENTS／資料與協作設計同步；本階段本機commit、不push。

## 2026-11-06 移除已完成專用頁面

- 使用者要求移除側欄已完成與對應專用範圍，改由任務列表篩選。
- 刪除scope=done的介面／判斷／空白頁，舊sessionStorage值回一般列表；保留all／mine位置。
- 情況篩選新增已完成，依status.category=done涵蓋自訂完成狀態，排除取消；不改卡片狀態或資料。
- 已完成側欄與專用範圍移除、列表篩選及舊位置回退，產品討論／README／AGENTS同步。
- 驗證通過：production build、完整browser-check（含舊scope回退與完成篩選），另以純函式驗證多個自訂完成狀態及取消排除；已檢視桌面列表側欄。
- 測試使用隔離tmpfs SQLite，完成後清理測試容器；未修改後端或業務資料。階段本機commit、不push。

## 2026-11-06 全站介面文案精簡

- 使用者要求移除情緒性語句、功能介紹與可留待操作手冊的說明，包含分析口徑／366天時區提示／時間趨勢副標及頁尾鼓勵語；手冊留待後續指示，不在本輪製作。
- 盤點首頁／專案／任務／看板／工時／分析／成員設定／邀請／登入；保留標籤、使用者內容、資料數值、錯誤／結果／載入與必要確認，空狀態改簡短事實。
- 已移除截圖四種文案，並完成登入／首頁／專案／看板／任務／工時／成員／邀請／通知盤點；詳見docs/ui-copy-review-2026-11-06.md。只調整前端，不改ACL、上限、統計或業務資料。
- production build、完整browser-check、編譯版test:time-analysis及test:home通過；受影響的情緒性空白標題斷言更新為簡短事實，日期上限錯誤／篩選／去重／撤權仍通過，不另新增文案專用測試套件。
- 已檢視列表、任務編輯與分析桌面／手機，以及登入1440／768／390畫面。刪除介紹後縮減分析入口高度、修正登入手機頁首舊grid留下的多餘空白；移除空說明行、無用CSS與已刪提示的aria-describedby，保留字數及錯誤可及性。footer版本仍靠底部右側。
- 產品討論、README、AGENTS與盤點文件同步，手冊等後續指示；歷史功能契約留在設計文件，介面不再常駐補回。僅使用隔離tmpfs假資料，完成後清理容器；src掛載生效，不需重啟主服務。本機階段commit、不push。

## 2026-11-06 工作區導覽與邀請介面完成

- Owner側欄以ownership permission提供工作空間設定；帳號選單只留個人資料與登出，上方切換器在首頁及專案內皆可選本人有效工作區。
- 已登入可確認Email接受邀請，Email不符可登出並保留邀請換帳號；未登入可登入接受或新註冊。預覽Email不覆蓋已輸入內容。
- 自主切換先檢查所有saving／草稿，成功回首頁、以Workspace key卸載待辦／報表並清空專案、通知、表單與原篩選；其他分頁scope變更同樣清除，API模組以workspace generation捨棄舊回應。419重試不能跨scope重送原操作。
- production build、test:workspaces（真實API／編譯版）、test:settings及完整browser-check通過。切換器／邀請1440／768／390已檢視，44px關閉控制、長Email換行與局部捲動正常，無整頁溢位。含切換失敗／重試、草稿取消／確認、既有帳號／新註冊／錯帳號換登、私有資料隔離、跨分頁舊表單409與重讀。
- 本機新後端映像已重建並更新API／Queue／Reverb，保留volume與使用者資料；本機新映像完整後端53 tests／1073 assertions通過。測試使用強制memory SQLite與獨立tmpfs API，不重設業務資料。
- 文件及README／AGENTS已同步；沿革保留，Manager原API權限不撤銷。設定與完整回歸更新入口斷言，seed角色檢查依新Owner入口調整；測試容器完成後清理。本階段本機commit、不push。

## 2026-11-06 多工作空間與既有帳號邀請

- 使用者確認Owner側欄設工作空間設定，上方工作區區塊改切換；同一既有帳號可受邀加入其他Workspace。
- 設定入口以workspace.ownership permission呈現，不比較角色名；本輪不刪Manager既有API權限。移除帳號選單設定項。
- 階段一：本人有效工作區列表／切換，邀請預覽／既有登入接受，保留新帳號註冊；只授一般成員、不擴張專案權限、不啟用停用資格，接受單次／重試安全。
- 階段二：側欄切換與邀請畫面（新帳號、未登入既有帳號、已登入、Email不符），切換前草稿保護、切換後清除舊租戶內容、其他分頁scope不符拒寫。
- 讀寫Session請求序列化與X-Workspace-ID防舊頁面錯寫；可信身分與目前工作區仍取Session，不信任前端ID授權。
- 後端階段完成：獨立假.env／記憶體SQLite完整53 tests／1073 assertions通過。新bootstrap錯誤映射在獨立容器驗證，沒有對業務DB跑破壞測試；前端整合與操作驗證接續。API契約及產品紀錄已更新，本階段本機commit、不push。

## 2026-11-06 工作空間工時分析

- 使用者授權選三個分析角度並實作，Owner／Manager側欄提供工時分析入口，先三個入口再進報表。
- 本輪選擇：成員投入（由誰填報／跨專案分布）、專案投入（資源分布）、時間趨勢（每日投入）。這是本輪實作選擇，不將工時當績效或完成率。
- workspace.timeReports.read映射管理身份，以有效permission檢查；後端專用唯讀API逐專案確認團隊工時可讀，限定目前Workspace。
- 日期含首尾、Asia/Taipei工作日、最多366日；只計有效原始紀錄，Epic與子卡各筆一次，含已結案卡／歷史成員，不以估時或交付比例混算。
- 前端新增獨立#/time-analysis列表及/members、/projects、/trend，日期與成員／專案篩選、空／錯誤／載入／撤權與手機狀態。
- baseline AccountSettingsTest 4 tests／57 assertions通過；新增TimeReportTest驗證加總、租戶／逐Project隔離、偽造角色、升降與撤權、366日、控制字元與無效日期。完整後端51 tests通過，強制隔離SQLite。
- production build、test:time-analysis編譯版真實API及完整browser-check均通過；涵蓋三入口／深連結／返回、各維度210分鐘一致、原始entry去重／作廢排除、篩選交集、空資料、連線失敗／重試、背景重讀沿用已套用條件、一般成員403、Manager撤權回首頁。
- 已檢視三入口及三報表在1440／768／390畫面：保留既有品牌，標題26／24px，表格14px，手機局部橫向捲動且可鍵盤聚焦；趨勢日期／上限移到SVG外維持13px可讀，三入口標題對齊。無整頁橫向溢位與瀏覽器錯誤。
- README／產品討論／ACL／工時分析契約及AGENTS同步；不新增schema／依賴，不匯出、不計費，不把填報當績效。RecordStore批次讀取的量級限制已記文件。
- 主queue／websocket已重啟載入程式，API新路由與主服務健康已確認；測試只用專用tmpfs假資料、完成後容器已清理，不重設業務資料。本機階段commit、不push。

## 2026-11-06 工作空間身份下拉選單

- 使用者要求成員列直接下拉選身份，移除調整角色按鈕及第二份表單。
- 保留原可授予身份／canChange與Owner保護；選擇後沿用確認及API保存，失敗回原值、顯示錯誤，revision與requestId沿用。
- production build及編譯版test:settings通過：取消選擇回原值、網路失敗回原值、重選沿用requestId、manager/user升降及實際專案存取、Owner保護、一般成員無可編輯選單、既有改名衝突／草稿／登出流程。
- 已檢視1440／768／390成員設定畫面，使用原生可鍵盤操作選單與44px觸控高度，移除二段表單及失效樣式。只測隔離tmpfs假資料；完成後清理容器，產品討論同步，本機commit、不push。

## 2026-11-06 移除獨立Epic概覽

- 已確認移除獨立概覽；Epic保留可選類型，列表與類型篩選納入Epic，舊網址回原專案。
- 保留資料、工時與後端規則；以隔離API驗證導覽、篩選及詳細頁，更新文件並提交。
- production build與編譯版test:issue-time通過，包含舊網址、Epic類型篩選／返回、三種類型列表、詳細頁及月曆填報／本人修改／撤權／衝突／去重。桌面1440／平板768／手機390已量測並檢視；手機截圖等待側欄動畫結束，避免記錄過渡畫面。
- UI／UX檢查：任務列表回答「目前專案有哪些卡片」，三種類型同列且可操作；移除獨立概覽及其大面積比例／提醒，保留既有色系、密度、鍵盤入口與響應式欄位。未新增儀表板或小工具。
- 完整browser-check最終通過（清單／看板／關聯／工時／ACL／小待辦／手機）；舊測試改由新增任務選Epic、列表快速編輯與完整頁工時驗證，保留原有業務與權限檢查。不碰業務資料，完成後清理專用tmpfs容器，本機階段commit、不push。

## 2026-11-06 Epic定位與競品討論

- 使用者詢問競品是否有Epic概覽，並質疑目前Epic權重是否過高；本輪研究／討論，不代表授權改產品。
- 查YouTrack、OpenProject及Jira官方文件，區分階層／群組能力與獨立主入口；記錄目前Epic專頁、加總、完成阻擋及比例語意的設計選擇。
- 已記docs/epic-positioning-2026-11-06.md及產品討論，官方事實與助理建議分開；建議Epic降為可選工作分類／父項，日常以清單／看板為主，未歸類不是錯誤、比例不代表工作量。移除專頁／改結案規則待採納。來源連結及diff檢查通過，文件本機commit、不push、不改UI／資料。

## 2026-11-06 Epic直接填工時與移除重複說明

- 使用者要求移除抽屜底部兩行Epic加總／只能修改自己紀錄的說明，並允許Epic直接填報實際工時。
- 沿用現有timeEntries與API，不新增schema。後端移除Epic拒寫；本人／Project permission／每日24小時／revision／requestId繼續檢查。Epic估時與類型轉換規則不擴張。
- 詳細頁一律帶入目前卡片並鎖定，月曆可選Epic／Task／Bug；Epic明細及各總覽計入直接工時與所屬執行卡，總計按entry各計一次、作廢排除。修改Epic自己及子卡自己的紀錄時帶原卡片，不挪動工時。
- PHP基線11 tests／197 assertions；修改後WorkspaceApiTest12 tests／226 assertions，完整後端50 tests／967 assertions通過。新增Epic新增／本人修改／作廢／固定卡片ID／上限／日期／租戶與Project資格／他人含owner不可修改／重試／revision測試，僅格式化本次新增方法，不重排舊檔。
- test:issue-time編譯版真實API通過，含Epic直接填報／修改、月曆選Epic、總覽、直接30＋子卡15＝45、作廢排除、月曆再登記15後總投入60、所有原始紀錄總計111無重複、子卡修改不改掛；原草稿／409／重試／撤權及三尺寸通過。已檢視桌面與手機抽屜，底部兩行消失，估時沿用原規則。
- build通過，完整前端回歸最終通過；舊詳細頁彙總標籤斷言改成含所屬卡片，非放寬加總。README／workflow／collaboration／產品討論及AGENTS同步，舊規則標沿革；本機階段commit、不push，不新增schema／migration或重設seed。
- 主queue／websocket已重啟載入新後端程式，backend／frontend／Redis／Reverb健康，queue執行；src掛載生效。瀏覽器測試只用專用tmpfs假資料，完成後清理，不測業務資料。

## 2026-11-05 任務詳細頁直接填報工時

- 使用者在詳細頁工時tab找不到填寫入口，預期這裡能填報；目前需到工時月曆點日期。補詳細頁入口與共用抽屜。
- 抽取TimeEntryDrawer供月曆／詳細頁共用，沿用logTime／updateTime、分鐘驗證、Asia/Taipei日期、requestId及原始entry revision。Task／Bug自動帶入並鎖定，Epic只能選所屬執行卡。
- 詳細頁依timeEntry.writeOwn提供填報與修改本人有效紀錄，不以issue.write或角色名稱放行；整合dirty／saving／關閉／導覽保護，失敗保留內容，背景更新不重設基準。
- 沿用後端作者／24小時／權限檢查，不新增API或schema。新增test:issue-time使用真實隔離API通過：詳細頁新增／本人修改／月曆一致、Epic只選所屬執行卡、無整數分鐘拒絕、每日上限、背景刷新原revision／409保留草稿、伺服器已提交但遺失回應後同requestId重試不重複、Escape與hash離開保護、管理者不能改別人工時、developer降reader後清除抽屜與後端拒寫。
- production build、完整browser-check、U01–U08 UIUX回歸通過。共用抽屜關閉採卸載，原月曆測試改檢查detached，原日期焦點恢復、月曆本人修改／作廢及草稿保護仍通過。
- 編譯版1440／768／390抽屜已等動畫完成後量測與檢視，欄位、焦點、任務帶入、邊界與無橫向溢位正常，無瀏覽器錯誤。早期滑入途中截圖已重拍，不把測試時機當產品故障。
- 專案UI／UX skills保留既有介面；README／workflow-design／產品討論同步，本機階段commit，不push。前端src掛載生效、不需主Compose重啟；測試僅隔離tmpfs假資料，完成後移除，不使用業務DB。

## 2026-11-05 移除看板卡片狀態選單

- 使用者要求卡片不再直接選狀態，透過拖曳至其他欄位變更。
- 刪卡片底部狀態選單／分隔線及失效CSS，不加重複狀態標籤；狀態由欄位呈現，卡片無障礙名稱補上所在狀態。保留拖曳、鉛筆／雙擊及完整頁變更狀態，供鍵盤與手機使用。
- 已更新頁尾提示及看板／ACL／即時同步檢查；拖曳測試在卡片旁邊可見位置落下，避免長欄位強制捲回最上方。即時同步檢查改以所在欄位判斷狀態，本輪未另啟Reverb。
- production build、編譯版看板三尺寸／密度／底卡／Epic／手機詳細頁／真實拖曳及完整browser-check通過，含自訂狀態、唯讀與開發者權限、版本衝突及原待辦流程。已檢視精簡卡與手機畫面，無狀態選單／多餘分隔線；使用隔離tmpfs SQLite，測試容器完成後清理。
- 產品討論／board-design／README同步，本機階段commit，不push、不更動後端或業務資料。

## 2026-11-05 精簡非操作性的技術說明

- 使用者要求移除待辦通知每30秒更新的介紹，並檢查其他類似文案。
- 移除通知頁更新頻率／手動刷新頁尾與失效CSS；簡化SMTP的TLS／伺服器憑證管理描述及狀態設定中的角色／permission實作說明。
- 保留通知內容／空狀態、連線檢查不寄信、操作權限／狀態影響等有助使用判斷的提示。更新頻率、WebSocket、revision及授權機制不變，技術文件保留。
- 純文案與失效樣式清理；前端production build通過，Vue介面掃描確認已無更新頻率／輪詢／該段TLS與憑證管理及角色映射說明，diff檢查通過。不為低風險刪文案新增測試或重啟服務；產品討論同步、本機commit，不push，不改業務資料。

## 2026-11-05 首頁與專案列表分離

- 使用者要求首頁不再是我的專案，只留下小待辦，空間保留未來個人小工具；小工具自訂本輪僅記錄。
- 首頁#/顯示小待辦，獨立#/projects保留搜尋／建立／編輯／進入專案；品牌回首頁、專案麵包屑／返回專案列表去列表，側欄區分兩入口。
- 納入hash／歷史紀錄、草稿離開保護、通知回首頁定位、建專案後返回列表、直接重整與手機導覽；保留ACL與既有專案／Issue深連結。
- 首頁TODO上限520px，手機滿寬，右側與下方留白；不新增小工具佔位UI／配置／持久化。移除手機記小事捷徑，待辦直接在首頁首屏。
- 使用專案兩UI／UX skills。production build、新test:home的1440／768／390真實登入編譯版通過，已檢視畫面；首頁／列表／建立／品牌／麵包屑／歷史／直接重整／草稿取消／同頁保留都通過。U01–U08、F01–F04與帳號設定三尺寸回歸通過；完整browser-check最終通過，包含ACL撤權回首頁／列表檢查、待辦通知定位與單向轉卡。
- 既有測試透過共用openProjectList操作新導覽，不把首頁當列表。撤權先驗證清除回首頁，再檢查列表隱藏，待辦通知／無Project登入等待條件更新。手機依aria-expanded處理側欄動畫，取消離開仍收合導覽。
- README／產品討論D-028／todo-design／AGENTS同步，保留舊首頁並列沿革。只改前端與相關文件／測試，源碼掛載生效、不需主服務重啟；隔離tmpfs假資料結束後清理。本機階段commit、不push，不做後端或使用者資料變更。

## 2026-11-05 小待辦操作選單與文字簡化

- 使用者反映更多展開撐大畫面，要求移除「自己記下的」。
- 將操作改為不占文件流的浮動選單，保留44px操作、鍵盤、外點／Escape關閉及viewport內定位；只隱藏自身速記來源，交辦來源／退回／完成／轉卡資訊保留。
- TodoActions固定定位浮動面板，不占文件流，viewport內上下定位；外點／Escape／視窗尺寸變化關閉，捲動時跟隨仍可見的錨點、離屏關閉，避免鍵盤聚焦的非同步scroll立刻收掉選單。44px操作及原capabilities保留，選擇動作後關閉。
- 個人速記不渲染來源或空meta列，交辦來源／退回／完成／轉卡仍顯示。production build、隔離tmpfs SQLite真實API的1440／768／390編譯版量測通過：選單前後列高及文件高度不變、邊界定位、外點／Escape／鍵盤與完成動作正常，截圖已檢視。
- 前端既有其餘流程回歸通過；舊待辦測試依外點／選擇關閉行為調整，新增高度與文案斷言，獨立完整Todo workflow最終通過（編輯／衝突／完成／重開／退回／轉卡／手機）。無瀏覽器錯誤；測試不使用業務DB。
- 產品討論與todo-design同步，源碼掛載即時生效，不需重啟主服務；隔離容器完成後清理。階段本機commit、不push、不改後端或使用者資料。

## 2026-11-05 移除側欄重複帳號資訊

- 使用者要求移除左下頭像／姓名／角色資訊區，帳號資訊沿用既有選單。
- 移除sidebar-bottom整塊及專用失效CSS，保留導覽、共用avatar及AccountMenu；不更動授權。
- 已移除側欄底部DOM與專用CSS，保留共用avatar及頂端AccountMenu。production build通過；隔離tmpfs SQLite＋真實Laravel登入的編譯版1440／390畫面與手機導覽、帳號選單／個人資料／登出入口通過，瀏覽器無錯誤，截圖已檢視。
- 前端src掛載即時套用，不需重啟服務；未改後端／使用者資料。產品討論同步、差異檢查後本機commit，不push；隔離容器完成後移除。

## 2026-11-05 看板WebSocket即時協作

- 使用者要求看板加入WebSocket、調整Laravel／Docker，完成後整套Compose重啟，已明確授權本機重啟，不推送／部署。
- Reverb／Redis已有但原broadcast=log且無客戶端。採私有Workspace／Project頻道，只推changed訊號，不推任務內容；Session＋有效project.read／issue.read授權，API持續負責資料權限。
- 寫入成功且交易commit後才Queue廣播，重試／rollback不重複；任務／看板／狀態／ACL／工時及轉卡涵蓋，空間角色異動通知相關專案刷新。舊訂閱收到的仍僅無內容訊號，refresh後失權清畫面並離開頻道。
- pusher-js採官方Reverb相容協定處理重連／heartbeat，執行期從authenticated API取公鑰與同origin /reverb 路徑，Vite proxy轉Reverb；secret只在backend volume。前端只在看板頁訂閱，去抖／合併刷新、離開／登出清連線，不更新編輯中的基準revision；30秒輪詢備援。
- 隔離PHP測試先baseline，新增授權／交易／重試測試；獨立Compose三程序API／Queue／Reverb＋Redis與SQLite假資料，真實雙瀏覽器同步／斷線／拒絕權限／草稿驗證。分階段本機commit含plans，重建全部Compose並完整重啟，保留volume／資料及備份。
- 使用專案內backend-development-standards及外部程序／契約reference，查Laravel13官方Broadcasting／Reverb文件與已安裝vendor來源；不載入外部skill。

## 2026-11-05 看板即時同步交付完成

- API階段commit：936c310。前端以pusher-js 8.6.0連同origin /reverb代理，Session／CSRF自訂簽章；只在目前看板訂閱，離開／撤權清連線，重連補讀，200ms合併事件，30秒輪詢備援。狀態列提供即時同步／連線中／定期更新，保留Vue／Vuetify及既有介面。
- 完整Laravel49 tests／938 assertions，Realtime3 tests／42 assertions；production build、完整browser-check通過。獨立Compose＋真實Redis Queue／Reverb＋兩個瀏覽器驗證新增Epic／Task、標題／狀態、無內容訊號、草稿保留／409、實際斷線恢復／補讀、撤權清頁／斷線、HTTP缺CSRF拒絕。
- 依兩個專案UI／UX skill檢視1440／768／390登入後畫面；手機待側欄動畫完成再截圖，沒有頁面橫向溢位，同步文字不擠壓控制列。未宣稱完整上游inspector全綠或真人研究。
- 已重建映像並docker compose down／up -d --no-build --wait整組重啟；本機Compose2.15不支援wait-timeout，採相容--wait。frontend／backend／websocket／Redis健康、Queue執行；主站登入頁與同origin WebSocket握手通過。
- SQLite先備份/data/backups/before-realtime-20261105.sqlite；重啟後比對26張非暫態表完全相同，帳號／密碼及業務資料未改，FK／integrity正常。不reset Seed、不刪業務volume、不push；測試專案volume獨立並清理。
- README／產品討論／board-design／同步契約／AGENTS及plans同步。事件非保證送達、舊訂閱僅無內容訊號／撤權靠API重查與輪詢收斂、非看板仍輪詢等限制已記docs/board-realtime.md。瀏覽器斷線測試中的ECONNREFUSED屬預期，重連後通過。

## 2026-11-05 WebSocket API階段驗證

- RealtimeController以Session／CSRF／origin及有效Project讀取permission簽固定私有頻道；回傳公開key，不回secret；Reverb拒client events。
- ProjectSignals於交易commit後Queue廣播無內容訊號，rollback／requestId重放不送；派送故障不改成功API。Issue／工時／看板／ACL與空間設定覆蓋，待辦轉卡沿用Issue寫入。
- 最終完整PHP為49 tests／938 assertions通過；RealtimeTest為3 tests／42 assertions，另覆核事件映射與測試禁廣播設定通過。真實雙瀏覽器即時同步、草稿409、停止／啟動Reverb補抓與撤權／CSRF已通過；前端最終回歸與整組主Compose重啟下一階段交付。
- 後端與契約先本機commit，前端／Compose／README／產品討論及剩餘驗收於下一階段提交。不push、不重設Seed，不觸碰其他專案。

## 2026-11-05 看板互動交付驗收

- 已修單一頁面垂直捲動、局部橫向；取消board-scroll max-height與外層flex收縮，三尺寸最後卡片／新增入口完整可見。Epic未刪，原先前端filters排除；Board允許Epic，列表仍Task／Bug。
- 無偏好預設none，原localStorage帳號／專案／看板key延用；分組／密度／收合可恢復，JSON無效與storage禁用不阻擋。精簡隱藏描述／優先級／期限／留言／context，保留識別／類型／標題／負責人／狀態。
- 移除看板快速編輯文字，雙擊卡片／F2／鉛筆開抽屜；標題鼠標單擊延遲400ms判定避免先跳閱讀，鍵盤／touch不延遲，拖曳／卸載清timer；選單／關聯不觸發雙擊編輯。列表入口保留。
- Epic卡片與計數／預覽對齊，分組時本身放自己泳道；不收錄Epic的單卡看板不額外加入。Epic拖曳僅state、無Epic套Epic；詳細工時／估時用既有epicSummary加總。
- production長看板18Task／Bug＋2Epic，1440／768／390下無第二層Y scroll、底卡完整，密度高度差、localStorage失效、Epic篩選／加總、真實拖曳、自訂板去重、dblclick／F2／手機通過；完整browser-check、U01–U08 UIUX、F01–F04可用性與build通過。
- 前後桌面／平板／手機、精簡／詳細卡、分組畫面已檢視，保留既有style；不宣稱完整inspector全綠或真人研究。日誌 /tmp/tasktracker-board-ux-*.log，忽略artifacts/board-ux-*不提交。
- README／board-design／產品討論與plans同步、本機階段commit，不push；無後端變更／migration／Seed reset或使用者DB測試，前端掛載即時套用。

## 2026-11-05 看板閱讀、捲動與編輯調整

- 使用者要求修雙層垂直捲動導致底卡不可見；泳道預設不分組／localStorage記住；精簡與詳細需明顯不同；移除卡片快速編輯字，雙擊開抽屜；Default應看到Epic。
- 根因：ProjectBoard的board-scroll與外頁雙層垂直捲動；filterIssues硬排除Epic。調整為頁面單一垂直捲動、看板只有橫向，保留列表原行為。
- Board範圍含Epic套既有篩選；不分組獨立卡，Epic泳道包含該Epic本身，單任務明確收錄不額外引入未收錄Epic。Epic拖曳只變狀態，不產生Epic隸屬Epic。
- 偏好沿用現有localStorage帳號／專案／看板key，無有效值預設none，失效／拒絕存取回退；精簡只識別碼／類型／標題／負責人／狀態，詳細加描述／優先級／期限／留言及關聯工時。
- 雙擊卡片編輯，保留單擊標題閱讀、鍵盤F2／編輯圖示與手機入口；避免雙擊先跳詳細或重複開。列表快速編輯本輪不改。
- 依專案內兩UI／UX skill保留既有視覺。先建隔離真實API與長看板before，驗證最後卡片、分組／storage／篩選／拖曳、密度／dblclick／唯讀與手機、production build及完整回歸；同步產品文件／plans單階段commit，不push、不碰業務DB。

## 2026-11-05 帳號選單與設定交付完成

- 右上頭像為44px按鈕，選單顯示姓名／Email／空間，支援方向鍵／Home／End／Escape／外點／關閉焦點；提供個人資料、工作空間設定及登出，移除左下登出。
- 個人顯示名稱可改、Email唯讀；工作空間一般設定改名，成員與權限含固定角色／邀請與Project ACL路徑提示；Project側欄入口明示成員與權限。草稿與衝突保護、手機隱藏側欄關閉後焦點回頭像。
- API階段09d72dc，後端46 tests／896 assertions；完整browser-check、U01–U08 UIUX、編譯版本設定流程與build通過。三尺寸及橫向短螢幕截圖已檢視，修角色字級／焦點／選單高度；未宣稱上游inspector全部通過或真人研究。
- 本機備份/data/backups/before-account-settings-20261105.sqlite，副本預演與正式migrate均確認業務DTO／原帳號欄位／records不變、3筆待辦與密碼保留、FK／integrity正常。長駐服務已重建；不Reset Seed、不寄信。
- 契約／README／產品討論／DB／permission目錄及plans同步。前端階段本機commit；GitHub SSH連線已排查並切正確帳號，未push。

## 2026-11-05 GitHub認證排查

- 使用者push被GitHub辨識為jack100398而403；查核TaskTracker原為HTTPS＋osxkeychain，GuildManager原為SSH。
- SSH唯讀驗證成功對應joshting-developer；指定TaskTracker的ls-remote成功且無refs。僅將本機origin改為git@github.com:joshting-developer/TaskTracker.git，未push，尚無upstream。
- 使用者明確要求不再保留jack100398憑證；只erase Keychain protocol=https／host=github.com／username=jack100398，get檢查不再取得password，不輸出秘密；其他服務／SSH key不變。
- 待前端設定階段驗收commit後，可由使用者git push -u origin main首次推送；沒有推論自動push授權。

## 2026-11-05 設定API階段完成

- 新增設定Controller／AccountSettings服務／SettingsRepository，ApiExecution同交易串接；路由只註冊固定五操作。
- 姓名限本人，Workspace改名與角色異動依workspace.update／workspace.members.manage；ownership permission保護擁有者，角色ID僅查映射。三個SQL版本欄及requestId，讀重試前重查資格。
- 完整46 tests／896 assertions通過；本人姓名立即刷新、一般成員拒絕、私有Project升降權、衝突、重試／撤權、SQL保存及migration往返。以Pint／parser整理新檔，未修改無關模組。
- 本階段先commit API、契約、產品討論與plans；前端新選單與設定流程仍在隔離驗收，下一階段提交；本機升級需備份，不push。

## 2026-11-05 帳號選單與工作空間設定

- 使用者要求右上頭像可開選單，顯示／編輯資料與登出，左下移除登出；工作區名稱可改，權限管理入口需清楚。
- 實作個人資料（顯示名稱可改、Email唯讀）與工作空間設定（一般／成員與權限）；保留原邀請入口與個別Project ACL。固定角色先提供manager／user指派，ownership另案、不提供自訂permission編輯。
- 後端用permission ID、可信Session／scope、同交易revision／requestId；owner資格保護由ownership permission判斷，不能用名稱放行。新增三個SQL版本欄，設定Repository直接使用Eloquent，不把新設定藏入legacy JSON。
- 先跑帳號／ACL baseline，依序完成API＋隔離測試commit、帳號選單與設定互動／草稿／鍵盤／手機／真實API／build，再備份本機migrate與重啟長駐服務；文件plans隨階段commit，不push。
- 使用專案內backend-development-standards與兩個UI／UX skill；不參考外部skill、不用業務資料測試、不產GAS。

## 2026-11-05 Footer驗收

- 共用.main直向flex並以100vh／100dvh撐滿，topbar不縮小；workspace-content flex:1，footer margin-top:auto並留內容間距。短頁置底，長頁自然排在資料後，不用fixed遮蓋內容。
- 隔離API編譯版空首頁1440／768／390與16筆待辦長頁量測通過；桌面1000高footer.bottom由725.73移至976（24px底部padding），三尺寸無橫向溢位，瀏覽器無錯誤。前後畫面與長頁底部已檢視。
- UIUX回歸發現前輪已移除.todo-check但舊觸控測試仍指向它，改量測「待辦操作」summary的44px目標並重跑。不為測試加回checkbox；U01–U08回歸已通過。
- 日誌 /tmp/tasktracker-footer-{before,after,build,uiux}.log；量測／截圖 /tmp/tasktracker-footer-{before,after}/。不提交產物、不寫入業務DB、不改後端。產品討論及plans隨本輪commit；不push。

## 2026-11-05 Footer置底修正

- 使用者指出右下footer隨短內容上移，希望維持最下面。實作短內容貼齊畫面底端、長內容排在內容後方的版型，避免浮動遮住資料。
- 查核.main／workspace-content目前未垂直撐滿，footer只是22px上間距。只調整共用外框CSS，不改資料／API／頁面導覽。
- 使用專案內兩個UI／UX skill單元件流程，保留配色；以隔離API假帳號驗證短頁／長待辦、桌面／平板／手機與production build，更新產品討論並連plans一起本機commit，不push。

## 2026-11-05 待辦文字完成入口交付

- 移除列首完成／重開checkbox及重複狀態，更多提供「標記為已完成」／「重新開啟」，保留狀態文字、刪除線、capabilities與busy／同筆草稿保護。手機動作不拆行。
- 完整隔離真實API browser-check與usability-fixes-check通過；production build及編譯版本文字完成／重開保存、鍵盤、1440／768／390px無溢位驗證通過。前後三尺寸圖片已檢視，未宣稱上游inspector全綠或真人研究。
- README／todo-design／產品討論同步，僅前端修改，不改API／DB／Seed；本機frontend掛載來源即時套用。日誌 /tmp/tasktracker-todo-action-{build,browser,usability,after}.log，截圖 /tmp/tasktracker-todo-action-{before,after}/，不提交產物。
- 使用者更正已由9d95238提交：只參考專案內skill，規劃文件隨階段commit。本階段亦明確提交plans兩份新增紀錄，不push。

## 2026-11-05 Skill限定專案內來源

- 使用者明確要求未來只參考本專案下skill。根AGENTS新增Skill來源與適用範圍：只用.agents/skills入口及skills副本，除非當次明示不讀／套外部共用或全域skill；沿革連結不是載入授權。
- 移除剛才新增的外部Git skill引用，原repo／分支／保護差異及不使用暫存clone／worktree的工作位置規則直接留在專案AGENTS。
- plans隨階段提交，補交先前累積紀錄；先前排除plans及外部skill適用說明由本節與AGENTS更正，不重寫沿革。

## 2026-11-05 更正規劃文件提交規則

- 使用者指出本專案過往會提交plan.md／step.md。查核歷史含020cf12、dbbb871、0fe29a2，確實隨功能階段納入規劃紀錄。
- 先前將共用Git skill中的planning排除規則套入TaskTracker是適用範圍判斷錯誤；上層僅指定「Git 工作位置（所有專案）」。根AGENTS明訂本專案plans隨階段提交，其他專案封存規則不自動搬入。
- 本次補交先前累積規劃／續接紀錄，保留沿革中當時「不提交plans」記載以便追溯，由本節更正。待辦完成入口工作進行中，接下來與對應程式提交更新。
- 只做本機commit、不push；不更動workspace共用skill。

## 2026-11-05 待辦完成入口改為文字動作

- 使用者將列首checkbox誤認為選取，要求放到更多下方；移除列首完成／重開checkbox，更多提供「標記為已完成」／「重新開啟」。
- 只改TodoHome呈現，保留capabilities、busy、同筆草稿保護及現有API／通知／版本規則；原資料不變。
- 先在真實隔離API記錄before，改相近既有瀏覽器測試，再驗證完成／重開、作者與承辦者可用動作、草稿、三尺寸／鍵盤及build；產品紀錄同步後本機commit，不push。
- plans兩份累積紀錄依共用Git skill排除功能commit，已向使用者說明；保留全部原差異。

## 2026-11-05 Markdown速記交付完成

- 後端commit 1c75a6b：完整content、摘要、舊API、空白原稿及升降版保護。
- 共用MarkdownEditor／MarkdownContent接上待辦、卡片描述及留言；Enter換行、格式／預覽、IME、草稿焦點、全文轉卡與安全HTML／URL均驗證。
- 42 tests／839 assertions、完整browser-check、UIUX、usability-fixes-check及Markdown專用真實隔離API檢查通過；build／Docker映像通過。1440／768／390畫面已檢視，未聲稱上游inspector全面通過或真人研究。
- 本機先停寫、備份並以副本預演後migrate；業務DTO／users／records雜湊不變，3筆待辦保留，FK／integrity正常。備份 /data/backups/before-markdown-final-20261105.sqlite。重新建立frontend／backend／queue／websocket，健康與來源編譯200、Redis PONG。
- 新依賴marked／dompurify鎖版，Vite同線安全patch7.3.7，npm audit 0。README、產品／待辦／DB及Markdown文件同步；本機分段commit，不push；plans既有差異不提交。

## 2026-11-05 Markdown編輯與整塊待辦速記

- 使用者確認小待辦直接一整塊Markdown，不分標題／說明；卡片描述／留言也希望同樣編輯體驗。
- 建共用多行MarkdownEditor及安全MarkdownContent（Marked＋DOMPurify鎖版），工具列、編輯／預覽、鍵盤／IME、字數與草稿保護；一般標題仍簡短純文字。
- 待辦新增content欄保存完整原稿，title自動摘要供清單／通知；舊title／note介面相容、舊資料不清空。轉卡帶全文。Backend skill套用新增欄位／驗證／交易及遷移保護。
- 前端已查官方Marked安全／DOMPurify／CKEditor Markdown文件；採輕量原生textarea與格式工具，保留Markdown原稿與既有Vue／Vuetify，不擴張附件或協同編輯。
- 階段：後端content契約與測試；共用編輯與安全render；待辦／卡片整合與真實隔離API／手機／XSS驗證；備份升級本機及Docker前端依賴重建、文件／分段本機commit。不push，plans原差異不提交。

## 2026-11-05 列表與詳細頁交付

兩階段均完成：列表標題／編號精簡（468bdf8）；詳細頁屬性／關聯側欄tab、手機展開、長清單、鍵盤及草稿保護（另commit）。驗證build、隔離真實API瀏覽器、UIUX與多尺寸畫面，更新產品文件；不push，不提交plans歷史差異，不動本機資料。

## 2026-11-05 詳細頁關聯移至側欄

- 已確認使用者希望Epic／一般卡片關聯改放側欄，考慮tab分區；本輪實作「屬性／關聯」兩tab，不改關聯資料與編輯方式。
- 主文只保留描述＋留言／歷史／工時；側欄關聯分所屬Epic、父任務、Epic所屬卡片／子任務及一般關聯。預設屬性，手機以「屬性與關聯」展開入口降低遮擋。
- 沿用現有tab鍵盤helper、權限過濾與草稿保護，窄欄文字可換行，長清單可在側欄捲動。驗證不同Issue類型、空狀態、長清單、鍵盤、手機、閱覽權限及狀態／留言流程。
- 先記錄隔離登入before，再改元件及相近browser-check，build與真實API回歸、三寬度人工檢視後本機commit；不push，不動業務DB。

## 2026-11-05 任務列表摘要精簡

- 使用者反映標題下Epic／關聯資訊太多。列表聚焦標題、編號、類型／狀態／優先級／負責人／期限；完整關聯、子任務與工時留詳細頁，專案內重複名稱與留言數亦收斂。
- 限App任務列表，保留ProjectBoard共用IssueContext及編輯／篩選功能，不改資料模型／API。按兩個UI／UX skill單元件流程保留現有視覺方向。
- 隔離API假帳號取before／after桌面／平板／手機證據，驗證閱讀／快速編輯、條件篩選仍可用，build與既有browser-check，更新產品紀錄及README後本機commit。
- 不push、不重設本機資料；plans先前差異保留不提交。

## 2026-11-04 任務詳細頁狀態操作辨識

- 使用者反映上方橫列像tab，容易誤以為切換內容。沿用專案自訂狀態與既有更新API，調整成「目前狀態」及明確「變更狀態」選單。
- 使用ui-ux-pro-max／design-taste-frontend app-screen單元件流程，保持既有配色／字型／Vue與資料範圍。讀取task內容為主要工作，狀態為可編輯屬性，留言／歷史／工時才是tab。
- 先記錄登入後before證據，再調元件／既有瀏覽器案例（自訂狀態、busy、衝突、唯讀與手機），build及隔離真實API驗證、檢視1440／768／390畫面。
- 同步產品討論／README，單階段本機commit；不push，保留plans原有差異。

## 2026-11-04 關聯式儲存交付狀態

本輪已完成：需求／基準、22張關聯表與Eloquent、FK／索引及DTO適配、Seed／匯入／回退保護、隔離測試／瀏覽器驗收、本機停寫備份／副本預演／升級及服務重建、設計文件導覽。程式階段fb6e468，文件驗收階段另commit；不push、不提交本plans既有差異。保留SQLite，未擴張REST、自訂角色或正式部署。

# 建立專案規範與競品調研

## 2026-11-04：關聯式業務儲存

使用者明確要求正統關聯式儲存。建立Workspace／Membership／Project／Status／Issue／Comment／Relation／Board／TimeEntry／Todo／Notification／Invitation等明確資料表、外鍵與索引及Eloquent關聯；將既有DTO存取轉到關聯式repository，不將整份payload換表存JSON。保留records作歷史備份／舊匯入升級來源，業務不再寫入。新增可驗證的舊資料遷移、順序／欄位／歷史保留、租戶完整性及回退流程；更新Seed局部重設與舊匯入。先隔離34項baseline與schema／migration測試，再完整API與瀏覽器，備份並預演本機DB後才執行遷移；每階段commit，不push，planning既有差異保留。


## 2026-11-04：後端可讀性與Laravel分層整理

使用者已要求開始調整後端，解除先前暫緩。以人工可讀性與Laravel責任分層為本輪主軸：先以既有API／ACL測試取得baseline；拆分WorkspaceService中的專案／狀態、Issue／關聯／通知、工時、示範與工作空間查詢，顯式HTTP路由與Controller、共用JSON request／response、統一交易及重試邊界；展開Account／Todos／授權／Repository主要流程並補中文docblock。保留RPC URL與DTO、requestId的原始簽章、權限重驗與資料，資料存取用明確模型封裝既有records，不在可讀性整理中直接變更租戶／關聯表schema。按可獨立驗證階段commit，隔離PHP測試與真實API瀏覽器回歸、文件同步及本機長駐程序更新，不push、不清除seed。完整關聯式表與REST新契約不在本輪自行定案。


## 2026-11-04：匯入後端開發規範

使用者要求參考workspace/skills將後端開發規範搬進專案。盤點主skill及相容別名，僅收backend-development-standards與六份reference／metadata，建立TaskTracker適用邊界與來源校驗；加入專案.agents/skills入口與root/backend AGENTS、CLAUDE及README。CLM專屬例子不改寫產品契約，權限仍以permission ID＋scope，既有後端重構繼續暫緩。純規範工作，不動PHP／DB／依賴／共用skill來源；驗證skill格式、相對連結、copy來源與差異後本機commit，不push，planning保留既有差異。


## 2026-11-04：前端待辦與PHP版本檢查

本輪評估既有UIUX修正後剩餘優先事項與前端程式維護性；核對composer根需求、lock依賴PHP門檻、Docker及執行環境，查PHP／Laravel官方支援資訊。有明確設定錯誤時只做必要版本契約修正，不展開延後的後端重構、不自行更新整批依賴或主機PHP。驗證composer與隔離API測試，文件紀錄後本機commit；planning保留既有差異。


## 2026-11-04：修正任務走查 F01–F04

使用者確認問題合理並要求先調。依四項採可逆方案：F03 跨tab保留草稿但提供明確草稿狀態／返回／取消, 其他項目操作不消失, 取代草稿才確認；F01 查閱上下文按帳號／Workspace／Project 存目前分頁 sessionStorage, 含本人／全部／完成與工時月份／成員／日期, 無效或撤權回退, 不恢復儲存視圖；F02 首頁首屏直達小待辦與速記焦點, 保持專案入口；F04 列表／看板標題進完整閱讀, 顯式快速編輯保留及ACL唯讀控制。先草稿階段驗證commit, 再上下文與角色／重整／儲存失敗回退驗證commit, 再首頁閱讀／回歸適配、production截圖及文件commit。只用獨立Laravel SQLite假資料, 不改使用者DB／seed或上游skills, 本機commit不push, planning保留既有差異。


## 2026-11-04：雙 skill 可用性任務走查

使用者明確要求使用 cognitive-walkthrough 與 usability-testing 掃專案。本輪以現有固定 vendor skill 明確呼叫, 不需註冊才能讀取。先鎖定 T1–T8 的角色／任務／完成條件, 用隔離 Laravel seed＋production preview 實際操作正常、空資料、無權限、錯誤／中斷復原與手機；每個關鍵步驟記 Q1–Q4 可見線索、觀察與推論。兩份 skill 分工為任務走查與可用性測試程序；真人參與為零, 不捏造時間／滿意度／成功率, 交含資料缺口的 report 及可用真人腳本。只審查、不改 UI／業務程式／使用者 DB／seed, 不向他人聯絡, 不 push。完成來源追查、報告與文件入口／討論更新、連結／diff 驗證後階段 commit, planning 保留原差異。


## 2026-11-04：尋找使用者流程與可用性 skills

使用者希望再找 GitHub 上兩個 skill, 驗證流程是否卡住、資訊是否雜亂及功能是否難找, 並釐清與 UI／UX 的差異。本輪研究原始 SKILL.md／必要參考／授權／維護及星數快照, 按任務適配而非宣稱新星數前二。選兩個互補方向：AI 任務走查／啟發式評估與真人可用性測試設計。延續上輪方式保留固定版本評估副本及個別介紹, 不自動註冊新 skills、不重做 TaskTracker 審查或改 UI。整理本專案任務情境、可觀察指標與證據邊界；更新 skills 入口／產品討論, 驗證來源／連結／原檔校驗後本機 commit。使用者未要求 push, planning 保留原差異。


## 2026-11-04：修正 UI／UX 審查結果

使用者要求修正前輪 U01–U08。保留 Vue／Vuetify、紫色與資料工作區, 先可讀性及工時錯誤規則／欄位定位, 再首頁限量待辦與專案入口、語意 tab 鍵盤、手機閱讀順序／觸控目標、平板工具列及獨立 section 路由。首頁採專案先呈現、待辦預覽三筆可展開, 是本輪實作選擇而非新增產品類型。路由保留舊 board／issue 與返回草稿保護, ACL 撤權按 permission fallback 不以角色判斷。分階段隔離 Laravel SQLite＋真實瀏覽器驗證及本機 commit；不改使用者 DB／seed／上游 skill, 不 push。測試與 build／實際截圖視覺檢查, 報告追加修正對照保留原發現。planning 保留未提交。


## 2026-11-03：啟用兩個 UI/UX skills 並審查

使用者要求加入 .agents/skills, 明確套用 Pro Max 與 Taste 審查專案。以相對 symlink 指向固定版本 vendor, 不複製／改寫上游；更新註冊及使用說明後第一階段本機提交。第二階段以隔離 Laravel／假資料／production preview, 觀察登入、首頁待辦、任務清單／詳情／表單、看板、工時及權限畫面, 桌面／平板／手機, 使用兩份 skill 的檢索與瀏覽器規則；產出證據、問題優先級與最小改善建議。僅審查不直接改 UI, 不動使用者 SQLite／seed／正式服務, 不把 house style 或工具 FAIL 當合規結論。報告及討論紀錄／文件入口核對後第二次本機提交, planning 保留原差異不提交。

## 2026-11-03：UI/UX skills 調研與專案副本

使用者要求找 GitHub 星數最高兩個 UI/UX skill, 複製到專案並各寫介紹, 供後續選擇或融合審查。採專用 UI/UX skill repository 範圍, 實際 SKILL.md／可用依賴／授權, 官方 API 星數快照, 排除綜合工程 skill 庫與純設計資料庫並註明候選。讀 installer, 以 download 模式及 commit SHA 下載到原專案 skills/vendor, 無 Git clone／全域安裝／UI 修改。保留原文與授權, 加來源／版本／檔案校驗、繁中個別介紹及比較／後續審查提案；不提前融合、審 UI 或套用上游規則。相關文件檢查及必要資料工具 smoke check 後本機 commit, planning 保留。

## 2026-11-02：首頁小待辦與互相交辦

追加：使用者要求單向轉正式 Issue, 側欄補 Project／正式欄位, 同交易／重試去重, 不提供 Issue→TODO；轉換連結按 Project ACL 過濾, 不洩漏原交辦者未授權 Project。

使用者希望首頁小待辦, 自記及角色間交辦, 已明確選直接加入、可退回。實作獨立 Workspace TODO＋專屬站內通知, 明確 permission 和作者／承辦者條件, 不改 Project ACL 或恢復視圖；同空間最小成員目錄可選交辦, 無跨空間。先後端服務與隔離權限／並發／重試測試及契約階段 commit, 再首頁清單／通知入口、真實 API E2E／build／文件階段 commit。保留目前資料, 不需要 schema migration 或 seed 重設, 測試用隔離容器。

## 2026-10-30：最小 Project ACL 實作

使用者確認先做 ACL。範圍：集中 permission＋Project scope 授權, owner／manager 明列 Any 權限操作全專案, Workspace user 僅 workspace.read、不自動讀專案。固定 Project lead／maintainer／developer／reader, 前兩者同權限（專案資訊、狀態、成員、Issue、看板、團隊工時）, developer Issue／本人工時與團隊工時閱讀, reader 專案／Issue 讀取但無工時。角色僅 registry 映射, API 不以名稱放行。getWorkspace 逐專案及衍生 Issue／看板／通知／工時過濾, 服務所有讀寫及重試前驗 scope, 作者限制保留。Project 成員管理加入／改角色／移除採 ACL revision／requestId／授權歷史, 只能授與自己有效權限子集合, 不改 Workspace role。前端以每個 project.permissions 和 memberIds 顯示操作, Reader 唯讀, 未加入空狀態與撤權清除私有資料。既有資料 migration 保留業務資料、不以 lead metadata 或所有 user 反推授權, admin→manager、member等→user；Seed 明確 first+second maintainer、僅first developer, 除此不授權既有一般成員。匯入共用升級, Seed不重設資料／帳密。先規劃與後端隔離API／遷移／防洩漏測試後commit, 再前端／真實E2E／文件commit, 本機先備份再明確migration；不擴張站內Inbox／新邀請流程／自訂角色。planning保留未提交


## 2026-10-30：競品日常使用流程、回饋與功能缺口調研

使用者要求建立 Markdown, 調研競品如何使用任務功能、公開使用者回饋與 TaskTracker 值得補上的功能。本輪範圍為 TaskTracker 專案管理, 不延伸前一則不同脈絡 log parser。調研 YouTrack／OpenProject／GitLab／Linear／Jira 官方文件, 補第一手公開論壇／issue 使用者回饋；功能事實、個案回饋、助理推論及已確認需求分開。以目前程式確認 ACL／通知／工時／看板等差距, 不照舊文件假設。產新日期報告並更新產品討論與文件入口, 標建議優先級／依賴／最小驗收, 尊重已暫緩甘特／依賴／儲存視圖／自訂角色。僅文件不新增功能、不登入或向外留言。來源與日期／連結檢查、diff 後本機 commit；保留 planning 差異


## 2026-10-22：收斂 ACL 與角色試用的觀察基準

使用者指出需求發散且沒有 ACL 會讓 admin/user 觀察失真。本輪為產品討論, 只整理既有已確認的 Workspace／Project scope 原則與下一階段建議, 不直接展開 ACL 功能或變更現有 Seed。重點：先有專案可見性與有效 permission, 再比較角色介面；帳號名稱非固定全域職位。提案以最小 Project ACL／關聯資料防洩漏／撤權及測試情境為下一里程碑, 固定預設角色即可, 不提早做自訂角色等擴充。新增討論紀錄, 區分回饋／已確認／建議, 檢查一致性與 diff 後文件階段本機 commit, planning 不提交


## 2026-10-22：四帳號權限測試 Seed

使用者要求 owner／manager／maintainer／developer 與共用指定密碼, 授權安排權限以便比較。保留 Email 登入, 四組 @example.test, 密碼讀本機 TASKTRACKER_SEED_PASSWORD 不進 Git。以集中 permission 映射 owner 全部／manager 除 smtp.verify／maintainer 維護專案狀態看板、不建專案邀請／developer 任務及本人工時；不以角色名稱放行。保留既有 user/member/admin 行為, 不將此次擴張成完整 Project ACL, 四者目前仍可讀同空間專案。Seed v2 非破壞升級加帳號保留 Jack／業務資料, 新建／reset 以四帳號分配示範工作與工時。碰撞拒覆蓋、重跑冪等及 reset namespace 隔離。適配前端入口、角色標示、文件與 API／瀏覽器驗證。先隔離測試後備份本機 SQLite、執行不重設 seed、驗證登入與權限, 本機階段 commit


## 2026-10-21：任務完整詳細頁

使用者提出應有任務詳細頁。實作選擇：保留側邊快速編輯, 增加可分享／重整的完整閱讀頁, 描述與討論為主內容, 右側列屬性, 顯示子任務／關聯／工時明細。完整頁編輯沿用現有 IssuePanel 與 API 規則, 不重寫另一套寫入流程；留言直接填寫並保護草稿。使用者追加希望狀態在上方用 tab, 釐清後確認為任務詳細頁直接切換狀態, 非列表篩選；沿用 moveIssue 版本／重試驗證與自訂 statuses, 非樂觀更新, 錯誤在完整頁提示、留言不丟失。路由在現有專案／看板 Issue URL 加 ?detail=1, 保留來源列表篩選／頁碼、看板偏好與捲動, 原快速面板網址相容。驗證深連結、返回、留言／編輯、版本衝突／草稿及手機, 僅使用隔離 API 資料庫。同步產品紀錄與 README, build／完整 E2E 後本機 commit；planning 保留未提交


## 2026-10-20：看板列表與手動收錄

使用者要求看板列表、Default 全部卡片與自訂看板手動收錄 Epic／一般卡片, Epic 底下卡片跟著收錄。已用非同步問題確認 Default 範圍是目前 Project 還是目前 Workspace, 回答前不實作依賴該範圍的路由／狀態／權限。獨立可先盤點：現有卡片同專案 epicId、Default 不複製卡片、自訂存明確選取 IDs, Epic 展開動態計算與去重, 解除 Epic 不移除另有明確收錄的單卡, 不連動卡片本體資料。待答覆後收斂驗證：新卡／移出 Epic／子項／多重收錄、revision／requestId／租戶隔離、既有列表／看板拖曳／手機。需更新產品文件, 各可驗證階段本機提交, 不恢復已取消視圖或實作首頁個人清單


範圍已確認：使用者回覆「目前專案的所有卡片」。看板綁 Project, Default 虛擬固定 id=default, 自訂存 selectedIssueIds 動態展開 Epic；原型 board.manage 映射可編輯任務的成員權限, 不以角色名稱檢查。/boards 為列表, /board 保留 Default 舊網址, /board/<id> 為自訂。自訂看板內新增卡片需在同交易收錄避免新卡消失；移除看板採封存, Issue 保留。先驗證／提交後端, 再完成前端與整合

## 2026-10-20：日期開啟工時側邊面板

使用者希望點日期後由右側出現填報工時面板, 不常駐占用月曆。採原生 dialog 右側抽屜, 月曆完整寬, 點日格帶入日期／修改紀錄亦開啟, 手機滿寬。沿用工時 API／revision／requestId／作者限制／每日24h／Asia Taipei, 未儲存欄位變更在關閉／Escape／遮罩／離開前保護, 成功保存關閉並更新明細。維持成員篩選只影響閱覽, 填報只歸本人。只改前端／文件／既有瀏覽器測試, build 與隔離真實 API E2E 驗證開關／日期／dirty／衝突／資料與手機後本機 commit


## 2026-10-20：類型收斂為 Epic／Task／Bug

使用者確認先採 Epic／Task／Bug 三種, 自訂類型只記未來方向。前後端縮減可選 ID, 顯示直接用英文名稱；Feature 資料以 migration／舊匯入共用升級器轉 Task, 保留 ID／關聯／留言／工時／舊歷史, 加升級歷史與 revision, 重跑不重改。示範資料改用 Task, 不重設本機 Seed。隔離 PHP 驗證三類／拒收 feature／遷移／匯入及 sentinel 不變, build／隔離 E2E 驗證選項／列表／看板／手機。先驗證再備份本機 SQLite 執行明確 migration, 查核保存資料, 一個可驗證成果本機 commit


## 2026-10-20：移除視圖與類型文字化

延續使用者要求移除視圖功能, 一般搜尋／即時篩選保留。移除前端元件／RPC／套用路由及後端三個操作／權限／getWorkspace 回傳／Seed 寫入, 已有 savedViews 紀錄封存原地不讀寫、不作破壞遷移；舊 ?view= 明確提示取消功能並清除參數, 保留專案／看板／Issue。首頁個人卡片小清單只記方向, 所擁有語意／跨專案範圍待定, 不實作。追加表格符號理解問題：第一欄實為類型, 第二階段改成有欄名的文字標籤, 狀態欄不改。先移除視圖並驗證提交, 再獨立提交類型顯示；測試全用隔離 Docker tmp／memory SQLite, 不動目前資料


## 2026-10-20：移除重複常駐視圖列

使用者認為「全部任務／尚未結案 團隊」與篩選重複, 希望降低介面干擾。移除常駐視圖列及全部任務籤, 保存／套用移入展開篩選區, 以單一下拉選擇既有常用篩選, 有實際條件才出現保存。前輪命名建議併入本次試作, 改用常用篩選語彙；不刪示範或私人資料、不改 API 契約。保留深連結、條件摘要／失效警告、作者限制與清除操作。更新產品紀錄與 README, build／隔離 Laravel E2E 驗證桌面／手機與既有分享後本機 commit


## 2026-10-20：說明常用視圖用途

使用者不清楚「存為視圖」的用途。本輪核對現有保存與套用行為, 用常用篩選捷徑說明, 記錄名稱及常駐入口的易用性問題。改名「儲存常用篩選」、移到篩選區及有條件才顯示皆屬助理建議, 未確認前不修改 UI 或刪既有資料。僅更新產品文件, 檢查內容／連結／diff 後本機提交, 保留既有 planning 差異


## 2026-10-20：任務工具列按需展開

使用者覺得任務頁常駐資訊與篩選太多, 本輪依既有試作授權採按需展開。任務頁移除重複返回與介紹, 保留專案標題、新增、常用視圖；搜尋／排序／篩選入口常駐, 六組條件折疊, 已套用條件可見且可逐項移除或清除。既有視圖／失效條件／路由與資料契約不變。更新產品紀錄, build 與隔離真實 API E2E 驗證桌面／手機、篩選保存及清除, 階段本機 commit, 不寫使用者業務資料


## 2026-10-20：專案入口顯示負責人與介紹

使用者指出長期專案的累計完成率與任務數無助判斷, 希望分析另有專業畫面；進一步偏好負責人與專案介紹。首頁移除任務統計, 顯示名稱／代碼／介紹／負責人。專案新增 leadId metadata 與 updateProject, 新專案預設建立者, 舊資料未設定不猜測；建立／編輯可選目前空間成員, 以 project.update 控制修改, 不由 leadId 放行。介紹沿用 description, 代碼不改以保留既有 Issue 編號。示範空間只補兩個指定 Project 的缺漏 leadId, 不重設 Seed。獨立分析功能只記設計方向（期間／版本／Epic 範圍與趨勢）, 不新增報表。分後端、介面／文件階段提交；後端用無使用者 volume 的隔離容器驗證, E2E 用獨立 project 標籤與 tmp SQLite, 保留既有 planning 差異


## 2026-10-20：示範 Seed 與測試隔離修正

第一輪在 Compose 容器執行 Laravel 測試時, PHPUnit env 未覆寫真實程序環境, 造成目前本機 SQLite 被 migrate:fresh 清空。已停止寫入、保留原始副本與備份並檢查舊 volume, 沒有可恢復紀錄；使用者明確說明目前資料不重要, 改要求建立 seed, 不再繼續恢復。指定假帳號 jack100398@gmail.com、Workspace f-ws、Project first／second；密碼只放被忽略的本機環境, 不提交 Git。補強 TestCase 在 bootstrap 前覆寫 putenv／SERVER／ENV, 並在任何 RefreshDatabase 前強制 memory config／檢查。以帶有非空 sentinel 的隔離 SQLite 模擬 Compose 環境, 驗證標準測試命令不改該檔。Seed 預設冪等, --reset 只清這組 seed namespace, 測試保留其他空間及拒絕 production。完成原子任務介面、Seed 與測試隔離分階段本機提交

## 2026-10-20：改為子任務與關聯任務

使用者明確要求移除前置任務, 改做子任務／關聯任務。實作選擇：同專案一層 parentIssueId, 子任務各自狀態／負責人／估時／工時, 完成數提示不自動改父狀態；relatedIssueIds 雙向且不連動狀態。保留 Epic 歸屬, 子任務跟父項 Epic, 父項移 Epic 在交易內同步子項 revision／history；Epic 彙總每個實際 Issue 一次, 一般父任務只顯示自己的工時與子項完成數。移除依賴阻擋／DAG／篩選, 舊 blockedBy 遷為雙向關聯並保留歷史。既有 blocked／review 視圖保留條件並標示失效, 不靜默放寬。Laravel migration 與 legacy import 共用升級器, 以隔離資料驗證後備份使用者 SQLite 並執行 migration。分後端契約與介面／文件兩個階段 commit, 不 push


## 2026-10-20：YouTrack 任務關係調研與設計討論

使用者質疑常駐前置任務表單難用, 詢問 YouTrack 是否提供, 並提出關聯任務／子卡片。查閱官方 Link Issues、Dependencies、Subtasks, 區分關聯、拆解與依賴以及 workflow 自動化。核對目前 blockedBy／epicId 實作, 本輪整理文件及提案, 不將追問視為採納、不改既有資料或結案規則。純文件做來源／連結／一致性與 diff 檢查, 明確路徑本機 commit, 不 push


## 2026-10-19：列表去除重複資訊與總計

使用者質疑右側功能標題重複左側導覽, 並詢問總計是否適合移至另一個畫面。沿用已授權的介面試作, 移除列表總計／完成率與列表／看板的重複標題列, 保留目前篩選結果筆數, 僅多頁顯示分頁。保留實際專案說明、手機以頂端顯示目前功能。概覽作為助理建議記入產品文件, 不視為已確認, 本輪不新增報表頁、不改 API 或統計規則。建置與隔離瀏覽器檢查桌面／手機、搜尋及看板後本機 commit, 保留原 planning 差異


## 2026-10-19：可讀性與 GitLab 式完整內容區

使用者覺得文字太小淡, 希望右側以分隔線區分並盡量顯示資料, 補充參考 GitLab 截圖。保留左側專案導覽, 將右側列表／看板改平整全寬、縮小標頭及摘要、整合視圖與篩選工具列, 不擅自加入 GitLab 業務功能。主要文字 14–16px、輔助 12–13px, 使用深色中性文字, 狀態與優先級以色點／圖示表意, 不用自訂淺色當主要文字。保留表單／手機／獨立看板導覽與資料契約, 以隔離資料檢查桌面寬度、資料起始高度、字體對比與手機捲動, build／瀏覽器回歸後本機 commit

## 2026-10-19：任務列表與視覺看板分離

使用者偏好附圖 YouTrack Agile Board, 要求列表／看板是不同功能, 不只是同頁顯示切換。查證官方欄位與泳道設計, 不推定圖片版本。保留同一組 Issue／權限／revision, 以獨立側欄入口與 hash route 分離列表與看板, 看板採固定狀態欄頭、Epic 泳道／收合、卡片密度與欄內新增, 不實作 Sprint／WIP 限制或多看板設定。列表保留欄位、篩選與分頁。處理舊視圖／任務深連結與返回, 更新紀錄後以隔離 Laravel 驗證同步與手機操作, 各階段本機 commit, 不寫使用者資料

## 2026-10-19：專案內導覽聚焦

使用者認為已進專案仍能在側欄切換其他專案會干擾, 希望點左上角回首頁再選工作空間／專案。移除側欄跨專案清單與專案內的工作空間管理入口, 專案內顯示目前專案資訊及功能, 左上角品牌作明確返回首頁入口。首頁保留目前 Workspace 成員管理及專案卡片, 不假裝支援多 Workspace 切換。保留既有 canLeave／revision／hash 深連結與手機導覽, 更新產品紀錄、README 與瀏覽器回歸。以隔離 Laravel 資料庫測試, 不寫使用者目前服務資料, build／E2E 通過後建立本機 commit

## 2026-10-19：切換本機新版服務

使用者要求移除舊版並啟動新版。確認舊 TaskTracker frontend／api 與 tasktracker_tasktracker-data volume, 先建置新版映像, 再只移除舊容器, 保留舊資料及 GuildManager。啟動 frontend／backend／queue／websocket／redis 並驗證 HTTP、Redis 工作消費與 WebSocket 握手。不自動匯入舊資料、不刪 volume、不做新的產品功能或遠端推送

## 2026-10-19：前後端分離與 Laravel 遷移

使用者進一步要求分別 commit, 本輪依前端／Laravel 核心／容器即時通訊基礎／交付文件拆分, 各批明確指定路徑, 不提交憑證、資料、依賴或編譯產物

使用者指定 frontend（Vue＋Vuetify）、backend（Laravel）與根 README, 並詢問 Inertia。依明確目錄與獨立 API 需求採前後端分離, 不引入 Inertia。先保留既有 Issue／Epic／狀態／工時／視圖及資料, 不擴張未定權限矩陣。主機 PHP 因 icu 動態庫無法執行, 採 Docker PHP／Composer 完成安裝與驗證, 不變動主機 PHP

進行中追加：使用者要求後端 Docker 預留 WebSocket 接口及 Redis。採 Laravel Reverb 獨立 websocket service、共用 backend image 的 queue worker、內網 Redis 與獨立持久化 volume；驗證握手、拒絕不可信 origin、Queue 實際入列／消費。此輪先建基礎設施, 不把 Reverb 連線當成 Issue 即時同步已完成, 不新增公開任務頻道或真實寄信

階段：
1. 記錄選型與遷移契約, 保留歷史 GAS／Node 討論, 本機文件 commit
2. 搬移前端並實際接入 Vuetify, 建立可執行 Laravel API, 遷移業務功能及驗證, 更新 Compose／README／開發指引；依可獨立驗證成果分 commit
3. 執行前端 build、Laravel API／隔離資料與瀏覽器回歸, 查核根目錄、忽略規則與既有資料保留, 完成交付

舊 data 與 Docker volume 不自動覆蓋或刪除, 提供明確移入方式與帳密相容性說明。README 既有 Docker 補充屬同一入口文件更新範圍, 改寫時保留其資料保留／本機限定等有效說明；planning 與空白 docs/架構.md 不併入功能提交。不推送、不寄真實郵件、不產 GAS

## 2026-10-19：實作成熟度與面試技術選型

使用者詢問目前架構是否可開始製作, 以及面試作品應採熟悉的 Laravel 或延續 Node。核對產品決策與實際 API／儲存, 查證官方框架能力, 區分可開始的產品骨架、實作前應收斂的權限／成員／資料契約與正式上線需求。將 Laravel 優先的情境建議、Node 延用成本、GAS 舊方向的取捨與可展示的驗收流程整理進產品討論紀錄, 技術選型標待確認, 不自行重寫或新增功能。檢查文件連結、差異與格式後僅提交本輪產品文件, 保留既有 README／planning／空白架構檔

## 2026-10-19：重申權限是授權依據

使用者要求記住所有角色只是擁有權限, 由權限決定能看與能做。強化 D-015、集中 policy 與範圍授予規則, 避免 policy 內仍用 owner／manager／user 名稱放行；加入改角色名不影響授權的驗收。不新增個人覆寫或自訂角色功能, 更新產品／協作文件與持續開發指引, 驗證後只提交相關文件, 保留其他差異

## 2026-10-19：專案四角色與責任歸屬

使用者偏好負責人／專案管理者／開發者／讀者, 說明負責人可能是老闆或交由他人承擔、PM 是日常管理、讀者可能供財務／人資取資料。將目前候選方案改為四角色並保留前輪三角色沿革, 區分責任歸屬、操作權限、Workspace owner 與 Issue assignee。讀者的專案內容、工時明細／分析與匯出分開權限, 不預設全空間查閱。具體矩陣與角色 ID 仍待確認, 文件檢查後僅提交產品紀錄, 不改程式

## 2026-10-19：推薦專案角色

使用者詢問專案內角色, 建議 manager／contributor／viewer, 明確角色映射權限與 scope, 整理基本操作矩陣及 Workspace 管理角色的全專案授權。空間外邀請、刪除、匯出、團隊工時與作者例外另列邊界, 不把推薦當成採納。檢查產品紀錄與連結後只提交討論文件, 保留既有差異, 不改應用程式

## 2026-10-19：確定 Workspace 預設角色

使用者明確指定 owner 為建立空間者、manager 管理空間, 兩者可操作全部專案；user 須明確加入 Project 並授權, 否則看不到也不能操作。更新產品決策、目前權限說明與授權契約, 移除空間內可見授權途徑, 保留舊提案在沿革, 不與現行規則並列。同步協作與架構文件；Project 角色、Workspace 所有權操作及資料作者限制的細項繼續待定, 不把原型 admin／member 改名當成完成遷移。文件驗證後提交三份設計文件, 保留既有差異, 本輪不改程式

過程中使用者先要求比較空間內可見, 隨後明確選擇自己的版本, 並確認按權限而非身分名稱判斷。記錄比較理由與 D-013～D-015, 集中授權從提案更新為採納方向, 未將待定的專案角色或細項矩陣誤標已完成

## 2026-10-19：授權判斷契約

使用者要求釐清角色名稱或權限判斷, 避免先試做之後大改。先盤點目前直接判 admin 與資料作者的地方, 查證官方授權原則, 明確提出固定角色映射權限＋資源範圍／作者條件的集中授權契約。將權限 ID、角色 ID、資料模型、判斷順序、admin 可見例外、遷移與驗收寫入產品紀錄, 同步協作設計與架構文件入口。待確認的角色矩陣不擅自定案, 本輪不改程式或資料, 文件檢查後獨立提交, 保留既有差異

## 2026-10-19：固定／自訂角色

使用者詢問 Workspace／Project 是否需要自訂身分。建議第一版每層三組固定角色, 分清空間管理、專案操作、邀請與帳號身分；Owner／Admin／Member 與 Manager／Contributor／Viewer 全部標為提案, 保留 admin 只確認全專案可見性的邊界。記錄未來擴充條件與目前程式差距, 檢查文件後只提交產品討論紀錄, 不改程式或其他既有差異

## 2026-10-19：Workspace／專案兩層權限

使用者明確提出每專案可設定可見成員, 並希望 Workspace admin 可查看空間內全部專案。記錄 D-010／D-011, 收斂一般成員按專案設定、admin 空間級可見性的規則, 詳列可見模式、授權順序、私人資料延伸 API、預設與舊資料遷移等提案。修正前輪暫緩專案 ACL 的建議, 已是明確需求；仍不把 admin 全部修改權、自訂角色或預設私人視為已確認。僅同步討論與協作文件, 檢查連結與差異後提交, 不擅自修改現有共享資料或應用程式

## 2026-10-19：SaaS／自建版交付方向

使用者指出目前層級有利 SaaS 與自建版切分, 記為設計目標 D-009。整理共用核心、部署設定與保留空間隔離的建議, 不把兩版本規格、管理角色或收費政策視為已定案。同步原架構提案的最新方向, 核對連結與差異後只提交兩份產品文件, 保留其他工作區差異, 不實作或部署新版本

## 2026-10-19：流程複雜度討論

使用者詢問目前流程是否合理。回覆以現有產品模型的設計判斷說明, 建議第一版簡化為工作空間內管理者／一般成員, 進階角色及方案限制另按實際需求評估。同步產品討論紀錄並標待確認, 不改既定決策或程式。核對文件與差異後只提交討論文件, 其他既有差異保留

## 2026-10-19：邀請權限

使用者確認有權限的帳號／Workspace 可以邀請使用者加入。將「邀請須授權」記為 D-008, 建議以帳號在目標 Workspace 的成員權限表示, 保留具體角色、可授予權限與空間邀請開關的待討論狀態。同步協作文件, 檢查連結與差異, 僅提交兩份設計文件, 不修改程式或併入其他工作區差異

## 2026-10-19：確認工作流程層級

使用者確認 Workspace → 建立專案 → 專案開 Issue。更新產品討論紀錄 D-002、層級與沿革, 同步協作設計入口描述, 不把成員邀請誤寫成建立專案前置條件。帳號／多空間／個人方案繼續待確認。本輪只整理文件, 檢查連結與差異後提交兩份設計文件, 保留既有 README／planning／使用者架構檔

## 2026-10-19：持續產品討論紀錄

使用者要求邊討論邊整理成一份 Markdown, 持續完善以免遺漏。新增 docs/產品討論紀錄.md, 回收已確認 SaaS／專案入口等方向, 及最近企業邀請、個人使用與工作空間模型的提案, 明確區分使用者確認、助理建議、目前實作及待討論問題

docs/架構.md 是開工前使用者的未追蹤空白檔, 保留原樣；README 及本計畫既有 Docker 紀錄差異保留。將後續同步紀錄的規則與文件入口補入 AGENTS.md, 本輪為文件整理階段, 只提交討論文件與相關指引, 不併入先前其他差異。不變更功能、不推送遠端, 驗證來源對照、文件相對連結與差異即可

## 2026-10-19：Docker Compose 本機開發

使用者要求確認並新增 docker-compose.yml。開工時 main 工作區乾淨, 沒有 Dockerfile／Compose 設定；維持原專案 Git 工作位置, 本輪不推送遠端

新增本機開發用 Node Dockerfile、忽略清單與 frontend／api Compose 服務。主機只開放 127.0.0.1:5174, API 在容器網路監聽, SQLite 使用獨立 named volume, 不自動搬動原有 data。原生 npm 開發仍維持 loopback 預設。掛載程式碼支援熱更新, 依賴由容器建置, 不共用主機 node_modules；SMTP 僅 API 使用伺服器設定且不烘進 image

驗證 Compose config、映像建置／服務 health／前端代理與登入讀寫、重建後資料保留、既有 API 測試與 build。容器驗證使用獨立 project name／volume 與替代本機 port, 不干擾使用者既有資料或服務。若 Docker daemon 不可用, 記錄實際可驗證範圍。完成一個階段 commit, 排除本輪 planning 紀錄, 依共享 Git skill 不自動推送

## 2026-10-06：自主補充日常工作流程

使用者授權有其他想法可以直接實作。先選兩個能在既有試作上驗證的階段, 不擴張正式 SaaS 部署或寄信

1. 任務依賴與上下文：Task／Bug／Feature 可設定同專案的前置任務, 防止循環／自我連結／跨租戶與跨專案；未完成的前置會在清單／看板／詳細內容標示阻擋, 完成時檢查依賴。前置重開不級聯改其他狀態, 顯示重新確認。Epic 與工時在清單／看板可見, 詳細內容可查看雙向關係。驗證 DAG、並行更新、舊資料、完成限制與瀏覽器操作後 commit
2. 常用視圖：專案內新增 Epic、負責人、類型及「被阻擋／逾期／未指派」篩選, 可將完整篩選與排序保存成個人或團隊視圖。資料在 API 持久化, 個人可見性與作者修改權由後端檢查, 預設視圖個人使用, 分享是明確選項。驗證分享／私有隔離、重新整理、篩選與刪除重試後 commit

依賴第一版只視「完成」為前置交付, 取消不自動解除阻擋, 須移除或換前置。不做排程／甘特。常用視圖保存選項 ID, 不複製任務資料；狀態或 Epic 不再存在時提示修正, 不默默移除條件。測試使用隔離資料庫, 不修改現有試用內容

## 2026-10-06：SaaS 協作與工時

使用者要求重新調研 Epic／Task／Bug 的聯動、自訂狀態、多人協作、填報與工時月曆、SMTP, 先交付設計文件並開始本機試作。補充按 SaaS 服務設計, 不限定 Google 群組

依 [協作設計](../../docs/collaboration-design.md) 分三階段提交：調研規劃 → 資料／API → 可視化試用。先落實工作空間隔離、本機帳號與邀請, 讓成員工時有真實身分；SMTP 預設预覽, 可檢查連線但不寄信。專案內先共享, 正式 SaaS 上線、Project ACL 與背景寄送另規劃

驗證既有流程加租戶隔離、邀請／越權、Epic 完成規則、自訂狀態、工時修正與加總、重試及版本衝突, 用隔離資料庫與雙瀏覽器帳號驗證操作, 不修改使用者試用資料。仍不生成 GAS 或推送遠端

日期：2026-10-05（Asia/Taipei）

## 2026-10-06：階段提交

使用者要求每完成一個階段建立一個 commit, 此授權適用於本專案後續的本機提交, 不包含遠端推送

目前尚無 commit, 前面完成的文件及原型都在工作區。依可獨立說明的成果建立三筆提交：調研與規劃、本機任務管理原型（包含已確認的專案入口）、每階段提交規範。原型與專案入口已有共用改動, 不為拆分歷史而建立無法編譯的中間版

文件檢查連結與空白；原型使用既有 API／SQLite 測試、build 及最近一次通過的瀏覽器驗證。明確指定提交檔案, 排除資料庫、依賴、截圖與編譯產物, 完成後檢查 log 及工作區

此文件保留初次調研範圍；使用者後續要求製作本機原型, 並延後 GAS 產檔, 新階段見 [原型計畫](prototype-plan.md) 與 [原型工作紀錄](prototype-step.md)

## 2026-10-06：以專案作為入口

使用者要求先建立專案, 點進專案後才開始使用任務與看板

- 首頁改為專案列表與建立入口, 不呈現跨專案任務清單或看板
- 建立專案後留在專案列表, 由使用者點選進入
- 專案頁的清單、看板、我的任務、完成任務與進度限於目前專案
- 提供返回專案列表及專案 hash 深連結, 重新整理與瀏覽器返回保留正確上下文
- 任務建立只能使用目前專案, 舊任務連結盡量相容
- 現有檔案全部尚未追蹤, 保留前輪原型與使用者本機資料
- 驗證：更新既有瀏覽器操作檢查, 檢查兩專案切換、首頁無任務工具、建立後進入、重新整理、返回、手機導覽與編譯
- 此次不改後端資料結構或產生 GAS

## 使用者原始需求

> SideProject - 專案管理平台製作
>
> 先建立 AGENTS.md
> Survey 一下競品, ex: OpenProject、YouTrack
>
> 我自己以前是用 YouTrack 蠻喜歡的
> 然後專案架構可以參考 /Users/josh/Projects/workspace/SideProjects/GuildManager
> 會採這種 google script app 的方式

## 本次範圍與理解

- 建立 TaskTracker 專案的 AGENTS.md
- 以官方文件調研 YouTrack、OpenProject, 加入 Linear、Trello 作為輕量工具對照
- 讀取 GuildManager 的文件與實作, 整理可沿用的分層與 GAS 部署方式
- 交付文件與可續接的 MVP 建議, 本次不建立應用程式、安裝套件或操作雲端資料

初始狀態：空的 Git repository, 分支 main, 尚無 commit 或既有工作區變更

本次不是故障處理, 根因及重現不適用

## 假設與待確認

- 暫以個人／小團隊的專案管理為設計情境, 規模尚未確認
- GAS Web App 已確認；Vue、UI 套件、本機 SQLite 與正式 Sheets 是沿用參考架構的提案
- 不將喜歡 YouTrack 解讀為要求完整複製 YouTrack 或匯入過去資料
- 優先操作：任務清單、看板、搜尋、自訂欄位、Sprint 中哪些最重要仍待確認
- 登入方式與團隊是否同一 Google Workspace 網域尚未確認
- Apps Script 專案、試算表與部署權限尚未提供

## 步驟與驗證

1. 讀上層規則與參考架構, 建立 AGENTS.md → 檢查已確認與建議是否分開
2. 查競品官方文件, 整理功能取捨 → 檢查來源、日期與版本／方案界線
3. 整理架構提案與 README → 核對 GuildManager 真實檔案及 GAS 官方限制
4. 檢查 Markdown 連結、工作區差異與完成紀錄 → 文件工作不執行應用測試

## 本次完成條件

- AGENTS.md 足以指導後續開發, 不帶入 GuildManager 的業務規則或其他專案授權
- 調研涵蓋指定競品、來源、值得參考的流程及 MVP 取捨
- 架構文件記錄本機／GAS 差異與尚未定案的設計
- README 可連到所有交付文件, 不聲稱應用已完成或可啟動


## 2026-11-11：Multi-Agent 使用回饋交接

使用者要求以真實 Side Project 驗證專案管理，後續需對外 API／Webhook。此階段只整理已觀察能力、限制與待補需求，不開發 TaskTracker API 或改授權。交付根目錄 `Multi-Agent-To-Tracker.md`，同步產品討論；檢查來源、連結、未包含秘密及文件差異後獨立本機提交。應用資料操作與 Multi-Agent 的 AGENTS／本機工具由來源專案管理。
