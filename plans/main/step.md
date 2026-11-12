## 2026-11-13 TT-271最終收尾

- 初審及獨立Codex複核發現的P1／P2已修正：harness含實際helpers、完整sourceRoots集合、舊PASS失效／source與approval綁定、平台分檔／CI範圍、atomic lock。四種harness變更及新增來源實測拒絕，還原gate PASS；最後校準與正式compare重跑通過。
- 使用者核准48圖及AA設定保持fcf9ee11原digest，沒有洗baseline或改UI業務。共用規範owner已依使用者要求集中、頻率沿階段驗證配置：每階段驗影響流程，共用變更擴相關頁／三尺寸，大階段完整回歸；frontend/AGENTS只7行入口。共用repo最後1406d35／be12b56已提交。
- cd05f47已交付270並回填closed；本階段提交271後回填closed／讀回，再claim並closed Epic266，結構優化原6卡全部按證據收尾。TT-273遠端Linux啟用歸部署、TT-274時間戳排序歸實際功能，均open未指派且未擴張實作。
- 純交接／入口／核對清單檢查來源hash／引用／語法／diff，不重跑已通過的相同完整測試。只有本機提交，不push；原始trace／圖片及帳密不進Git，本次測試資源清理。

## 2026-11-13 TT-270三尺寸正式驗收完成

- 16×3=48合成候選經使用者明確核准，manifest fcf9ee11不變；正式比較3／3通過。校準原樣0／spacing、overflow、hide-action各1＋真實diff／還原0；調高容忍度及刪shot亦被harness lock拒絕，還原通過。
- 獨立初審P1／P2已補完整harness綁定、sourceFiles／approval過期檢查、舊PASS失效、平台分檔、CI觸發及atomic lock。參數單一policy，版本來自實際安裝；核准圖／參數未改。
- 完整既有browser-check、build／composables／API、既有memory155／7855、新guard3／6、Python4及共用2＋4＋3、Pint／語法通過。產品frontend/src及backend/app／routes無變更，所有專屬服務／volumes／lock清理。
- 前端持續驗證與頻率移共用Skill；本地入口不重複規則。遠端Linux首次基準／Required Check因無push授權另建部署TT-273 open未指派，不冒充遠端CI成功。TT-270提交後closed；TT-271最終獨立複核中。

## 2026-11-13 TT-269驗收完成

- 唯一registry selectionRules＋實際consumer framework偵測；TaskTracker僅runpy adapter，間接UI／資料流tags只增補。check_evidence拒缺失／失敗／過期來源／矩陣縮減／未核准visual；4選用＋2證據＋原2工具測試通過。
- Codex／Claude各四個全新唯讀session，對最終registry cefbe2c6選用與實際tool-read 8／8通過，Ponytail lite及分層／PHPDoc／安全保留。Claude已由使用者換帳號恢復，不再受先前額度阻擋。
- 共用庫與產品repo分別提交後回填269 closed；270已因前段額度等待例外領取，現在繼續真實隔離MySQL／Laravel三尺寸候選／比較，未核准前不標visual PASS。

## 2026-11-13 TT-268可讀性收尾

- Codex新session兩次命令exit0，Claude擴充套件native binary新session四次Read成功，實際讀AGENTS／baseline／registry／Ponytail，無Memory／私有設定／業務操作。來源、14入口、379hash、358vendor及2項負向工具測試重驗通過。
- Claude最終報告受5-hour quota限制（訊息01:10台北恢復），不宣稱四案例選用已驗；268只驗明確引用可讀，已具tool_result證據可結案。提交後closed再claim269；另繼續可獨立完成的矩陣／視覺工具，不以額度缺口假PASS。

## 2026-11-13 結構優化看板收尾計畫

- 使用共用工程／驗證、Ponytail lite及task-tracker-usage；實際路徑由.agents連結解析至Side Project共用庫。先補TT-268兩個全新host唯讀session；Claude使用現有VSCode native binary，不安裝全域工具／hook。
- 再按卡片claim269，建立唯一機器矩陣／diff＋tag門檻，四類任務各用Codex與Claude新session驗選擇／實際讀取；缺host證據不能假PASS。
- TT-270沿既有Playwright與隔離Laravel建三尺寸候選與比較／退化注入，準備具體review bundle，核准baseline前不洗成PASS。TT-271第二輪驗收後才關Epic；有實質進度持續回填，逐卡提交。
- 不改產品UI／業務、不碰主庫、不自動push；共用與產品repo分別提交，保持AGENTS規範／Memory記憶分工。

## 2026-11-12 TT-268共用搬移／Memory階段

- 全12份原Skill搬共用分類庫，泛化工程／Laravel；加既有TaskTracker使用及固定Ponytail共14份，原路徑改symlink。358原vendor hash一致，379完整公開檔案hash／引用／12啟用入口通過，2項隔離工具負向測試通過。
- 使用者追加AGENTS專注Skill／規範；根收斂47行、backend12行，沿革移Memory.md有現行索引／過期提示。共用baseline與工程Skill同步規則，產品綁定留consumer。
- 兩repo分別本機提交；不改全域plugin／hook、業務程式／主庫或其他consumer。不push。TT-268新Codex／Claude session證據仍缺，保持進行中；後續269觸發矩陣、270核准視覺baseline、271獨立驗收另交付，不假完成。

## 2026-11-12 TT-267盤點完成

- 12正文／10啟用入口／2手動研究，358manifest檔案hash通過、0失效link；兩組相同檔均授權而非重複Skill正文，完整保留。
- 機器inventory與報告已交付，工具只讀、不碰私有資料；Codex存在／Claude PATH缺失，尚未新session驗證。視覺僅有截圖，無approved golden comparator。
- 提交／回填後領268，按使用者更新遷到分類共用庫並泛化自有規範；保留所有產品安全及上游授權。

## 2026-11-12 結構優化TT-267～271／共用Skill庫

- 使用者要求所有side projects以/Users/josh/workspace/side-project/skills為canonical，TaskTracker現有skills全部搬移／分類；只有經證明專用者才留專案。新需求優先於舊project-only與本地存放位置，六張卡已追加範圍。
- 先TT-267只讀盤點／機器inventory與視覺缺口，驗證提交後領268搬移泛化／Ponytail，再269觸發矩陣／載入，270視覺門檻，271第二輪驗收。專用產品資料留AGENTS／docs；私有憑證不移入Git、不動全域host設定。
- 兩個repo保護現有差異、獨立本機commit不push；固定vendor／LICENSE／references／metadata，alias不複製正文。宿主新session或人工核准baseline缺證據時明列未完成，不假結案。

## 2026-11-12 TT-261完成

- 補齊33檔116項用途／param／return，reference100檔241方法及required258檔846方法均0；擴大Unit門檻並排除生成cache。
- 註解階段33檔執行token相同，Pint後解析import與trait順序的AST同起始版本；無斷言／migration邏輯變更。memory155／7855assertions、原生MySQL65／2128assertions及最終PHPDoc門檻2項通過。
- 詳見docs/backend-phpdoc-reference-2026-11-12.md；提交後回填closed／讀回，清理codex獨立測試Compose，未push且主庫不seed／reset。

## 2026-11-12 TT-261開始

- 使用者指定補齊參考範圍PHPDoc，已claim codex／inprogress；開始工作區乾淨。以689369e已通過的memory／MySQL作既有baseline，不改斷言或migration邏輯。
- 新盤點reference100檔／241方法，33檔116項；逐項補實際用途與簽章，Feature／Unit及migration提升為必須檢查，routes／config／bootstrap也檢視。
- 比較PHP去註解／空白的執行token證明行為未改（檢查門檻本身除外），限定Pint、audit --all、memory完整suite與codex獨立MySQL後提交／回填。

## 2026-11-12 TT-246完成

- 7領域專用Request／readonly Data及28路由矩陣；原始簽章與舊receipt原結果保留，Project／Board新結果只回定位／revision。前端不把部分結果當read model，表單與測試fixture依需要GET。
- memory155／7855assertions通過、66skip；原生MySQL65項已驗（64初跑＋報表fixture修正後重測1）；完整瀏覽器及build設定／工作區切換通過，api／composables／PHPDoc258檔846方法／Pint／diff檢查通過。詳見docs/other-write-contracts-2026-11-12.md。
- 舊領域array相容入口仍明列，不宣稱全庫DTO化或所有效能待辦完成；沒有主庫seed／reset、未push，獨立測試Compose用畢清理。完成提交後回填卡片closed並讀回。

## 2026-11-12 TT-246開始

- TT-262完成bf17af3／closed後claim246，codex承辦inprogress。
- 非Issue／Time領域按端點建立專用Request／typed Data，有界結果及前端按需讀取；保留原始receipt簽章／舊結果與重播前授權，更新契約矩陣。
- 驗證傳輸型別／未知欄位／缺漏語意、撤權、revision／requestId、舊receipt／資料量、真MySQL及真Laravel瀏覽器；只用codex獨立13069隔離DB。

## 2026-11-12 TT-262完成，準備領取TT-246

- 21Controller／84action建構子注入，公開Project／Issue／Changes拆分；帳號／PAT／稽核／訊號資料經Repository，歷史升級移Database。AST／Container無SQL及領域隔離／替換依賴門檻與ReceiptCodec設定生命週期補齊。
- memory152通過／63skip／7718assertions；原生MySQL62／2012通過（大型既有報表需512MB）；真Laravel完整瀏覽器流程通過；PHPDoc241檔811方法零required缺漏。見docs/strict-layering-delivery-2026-11-12.md。
- TT-262提交／回填closed後才claim246；未改主庫資料、未推遠端、未擴張公開API。

## 2026-11-12 TT-262開始，之後TT-246

- 使用者授權先262嚴格分層／建構子DI，完成測試／提交／回填才領246其餘寫入Request／DTO與前端契約。
- 工作區開始乾淨，29個Controller中21個84個action待遷移；新external／audit／HTTP資格邊界一併盤點，保留交易／鎖／租戶／revision／receipt。
- 隔離baseline、一般Controller constructor與公開領域拆分、Service資料入口Repository化、架構防退化／容器範圍／PHPDoc、memory／MySQL／HTTP與瀏覽器驗證；有進度寫卡。只操作獨立測試DB，不動主庫seed／reset。

## 2026-11-12 TT-265 公開API實際操作確認

- 使用者要求確認Claude交付AI/API能力能否用於操作；讀即時看板與部署OpenAPI0.9.0／19operations，核心closed，TT-3仍open及Webhook／選配聯調deferred。
- Codex臨時PAT由公開API建TT-265、掛TT-3，驗查詢／指派／狀態／留言／兩種重播／409／read403／無Cookie／增量，臨時PAT全部撤銷並讀回401。只改本卡，無primary seed／壓測。
- 專用MySQL13059／tasktracker_test補驗外部識別、批次、同步及回音等23項808assertions通過，環境已清理。現有CLI仍Session，未自行改成PAT或存長期Token；報告docs/public-api-operational-readiness-2026-11-12.md。
- 本輪只實際驗證與交付文件，不恢復後續產品開發；報告提交／卡片回填後closed。

## 2026-11-12 TT-262 建卡／盤點與規範收斂

- 使用者要求查Controller方法注入，討論後確認嚴格Controller → Service → Repository，包含簡單CRUD；穩定服務／Repository皆建構子注入。
- 盤點28個Controller／21個候選／84個action，34個Service有5個直接資料存取候選；另區分Artisan handle與RequestReplay scope參數。
- 本輪只開TT-262、補既有Architecture／Development Standards skills與原DI計畫，不改產品程式，保留他人未提交的Controller／DTO／Service／測試變更。TT-262維持open未指派；AI/API暫停不變。
- 已驗證文件／skill相對引用、兩入口symlink與frontmatter、規則一致性、卡片分類／open未指派及diff；只提交本輪文件與skills。後續程式遷移須正式領卡及隔離測試。

## 2026-11-12 使用者要求完成TT-170後暫停

- TT-170已完成並提交337a898，驗證及主服務載入完成，卡片已回填並closed。
- 停止後續看板開發，不領取或開始TT-171；其餘待辦狀態保留。使用者恢復後再讀AGENTS與即時看板接續。

## 2026-11-12 TT-170 驗證完成

- 新增Project issue-changes、OpenAPI0.5.0、公開cursor指南／Python迴圈；既有頁碼及前端不改。共用SQL篩選、認證加密cursor、固定syncUntil、包含邊界及唯一鍵，不宣稱跨交易無損changefeed。
- 完整隔離CI memory138通過／55略過7187assertions、MySQL18通過654assertions；1000筆無關資料、同秒多頁、索引EXPLAIN無filesort、SQL界限／撤權及公開客戶端／Chrome／限流通過。PHPDoc219檔705方法零缺漏。
- 6c15508已連帶收初版部分檔案，保留歷史，本卡提交其餘契約及驗收。載入主服務只新增索引，不seed／reset／刪volume。回填後接TT-171。

## 2026-11-12 TT-170 開始

- 新增公開issue-changes摘要，沿既有IssueReadRepository共用SQL篩選／IssueListResource及Project permission，不改前端與舊頁碼契約。keyset(updated_at,id)、perPage+1最多101、無COUNT／OFFSET，新增Project複合索引。
- 認證加密cursor綁本人／Workspace／Project／所有filters及perPage，續頁重驗資格。固定syncUntil與inclusive updatedSince，下一輪依id/revision去重；非多HTTP snapshot或刪除feed。
- 新增隔離memory／MySQL同秒多頁、null歷史、邊界、投影／SQL、非法cursor與撤權驗證，正式OpenAPI0.5.0、指南及外部HTTP範例同步；不提交其他人的PHPDoc變更。

## 2026-11-12 TT-254 驗收完成

- 獨立空目錄／python -I／公開OpenAPI operationId及Markdown，建卡／重播／409草稿／Scope／隱藏及跨Workspace／Reader撤權receipt／移除／停用／到期／撤銷等10階段通過；作者實際Token帳號，無Session。
- 本地完整CI memory22通過／13原生類略過2286assertions、MySQL14通過508assertions、py／sh／Chrome／限流及官方負向皆通過。記錄docs/public-api-independent-client-acceptance.md，非其他真人／模型外部聯調。
- 提交本卡明確檔案，保留同工作區其他PHPDoc變更；回填結案後續接TT-170穩定cursor及增量查詢。

## 2026-11-12 TT-254 開始

- 空目錄單一通用HTTP客戶端／python -I，只接公開文件與OpenAPI、PAT與不透明fixture輸入，不讀原始碼／DB／Cookie／內部helper；依operationId找公開路徑。
- fixture獨立在13049／tasktracker_test建人造帳號，僅測試環境CLI限制schema／帳號及人造email，控制資格／到期；正向＋重播／409／Scope／跨Workspace／移除／停用／到期／撤銷。
- 同步CI runner，發現contract.md規格版號過時，改以唯一OpenAPI的info.version為準。驗完回填／提交才結案。

## 2026-11-12 TT-253 驗證完成

- 全新獨立CI／13049通過：memory22／13原生類略過2285assertions；MySQL14通過508assertions；官方Schema、3種結構／7種契約負向、下載py／sh、Chrome15頁尺寸及鍵盤、真HTTP限流通過，結束自動清理獨立環境。
- 修正Docker context漏resources，公開文件及規格不靠人工copy。GitHub Actions已配置但未push／未觸發遠端job；非TT-254獨立拒絕流程或p95證據。
- 完成提交／回填TT-253後領TT-254；進度已留卡，不碰現用主庫。

## 2026-11-12 TT-253 契約CI開始

- 雙向路由／Scope／認證／FormRequest必填與正式Schema範例驗證，七種負向漂移必須失敗。
- 獨立tasktracker-contract-ci／13049／tasktracker_test，不使用現用主庫或Agent憑證；公開下載範例、真HTTP、文件瀏覽器共用可重跑入口。
- GitHub Actions加入相同門檻；本輪只驗本地等價流程，不推送或聲稱遠端job已執行。

## 2026-11-12 TT-252 驗證完成

- 五頁匿名文件、Markdown原文、同源py／sh範例下載與唯一OpenAPI0.4.1發現連結完成。安全渲染固定slug、CSP無腳本／表單／Session，查業務DB為0。
- 完整memory131通過／51原生類略過5806assertions；文件與契約8通過617assertions。真實Chrome五頁×三尺寸／鍵盤skip／無水平頁面溢出／Cookie皆通過，已看桌面及手機截圖，程式碼14px。
- 兩種範例從公開URL下載後執行，各自完成身分／Project／狀態→建卡一次→卡號→最新詳細→PATCH closed→留言一次及重播，最終revision3。測完撤銷PAT並登出。
- 本機載入同部署文件，無migration／seed／reset主庫，不推送；提交／回填後續接TT-253契約CI。各階段在TT-252已留言，無需重新猜測進度。

## 2026-11-12 TT-252 開始

- 接續3ff79e6已領文件入口；匿名/api/public/docs及authentication／examples／agents／contract，.md原文與可下載py／sh共用內容來源。Laravel同部署、無Session／PAT表單、無新常駐服務。
- 沿品牌做文件閱讀版型：TaskTracker識別、5項導覽、單欄內容、程式碼／表格內捲動、手機折行、鍵盤skip與focus。UIUX本地搜尋確認keyboard／nav規則；非行銷hero，不造KPI／裝飾。
- 安全Markdown轉HTML、固定slug allowlist、CSP無腳本、無Cookie／業務DB查詢；加入匿名頁面及瀏覽器三尺寸證據。完整curl／Python範例以隔離HTTP實跑，再提交／回填進度。

## 2026-11-12 TT-169 驗證完成

- 公開POST Issue／PATCH／POST留言沿既有IssueService，writeOnce先資格與路徑資源重驗再讀RequestReplay；首次成功業務／來源／receipt同交易，重播無重複通知或audit。
- 有界回應四欄位，舊Session／receipt不改，公開operation namespace獨立。PATCH省略保留，expectedRevision嚴格整數與requestId UUID必填；Epic／父子／關聯沿同一規則。
- memory完整130通過／51原生類略過5735assertions；MySQL寫入／撤銷競態8通過285assertions，受影響契約17通過976assertions。獨立HTTP寫入PASS（建卡一次、closed、留言一次、最終revision3），官方OpenAPI與Pint通過。
- 新版本機資源核對，無migration／seed／reset主庫，不推送；完成提交與卡片回填後接TT-252。各階段已留TT-169進度三則，避免只在對話保存。

## 2026-11-12 持續完成API看板／TT-169開始

- 使用者授權一路依序處理AI整合/API看板，每張有進度即留言，驗證／commit／結案後領下一張，不僅在對話交代。
- TT-169已領：共用既有IssueService及通知／關聯規則，Bearer冪等寫入先重驗目標後才讀receipt，限定結果避免全歷史；PATCH只改明確欄位、expectedRevision與requestId必填。
- 後續TT-252／253／254，再同步／Webhook卡；deferred與外部依賴逐卡核對，不偽造已驗證狀態。

## 2026-11-12 TT-168 驗證完成

- 已交付7個公開Bearer只讀端點，既有身分加總8個公開GET；正式規格11paths／12operations，版0.3.0。共用既有Service／Resource，不複製授權與SQL；狀態新增最小有界資料投影。
- memory完整124通過／45原生類略過5463assertions；MySQL相關10通過337assertions。最終受影響跨引擎＋規格19通過950assertions，涵蓋狀態超限409、SQL列表不讀描述／活動／工時、狀態不讀Project描述、無Session與資格撤銷。
- 只用標準HTTP與私有環境PAT的Python範例通過：Project列表／詳細、成員／狀態、卡號精準定位、最新revision2、詳細與留言。未匯入App原始碼，測後撤銷Token。既有Chrome PAT流程／三尺寸、Pint與官方OpenAPI結構通過。
- 本機載入新版公開JSON／Markdown，無migration／seed／reset主庫；公開寫入仍TT-169，CI／完整文件站／全流程驗收仍TT-252～254，不推送遠端。

## 2026-11-12 TT-168 開始

- Codex領取公開只讀API，沿BearerTokenSession.read與現有ProjectReadService／IssueReadService及Resource，不複製授權或資料存取。
- 公開Project列表／詳細、成員選項、狀態、Issue列表／詳細／留言；SQL分頁20預設100上限、identifier精準定位、單卡最新revision、活動分開讀取。不增加寫入或前端輪詢。
- 同卡更新OpenAPI／匿名Markdown／範例，隔離memory＋MySQL及獨立HTTP驗證不同Project／Workspace／撤權、無Cookie與讀取範圍，保留TT-169寫入及TT-254最終建卡驗收。

## 2026-11-12 TT-167 驗證完成

- 正式OpenAPI3.1規格版0.2.0與匿名Markdown契約已交付，僅列實作5操作；另交接TT-168／169的REST目標，不提前放入正式spec。
- 修正具名限流桶、429 Retry-After／rate headers、405 Allow與公開非預期500；未知版本404，來源拒絕保留no-store／nosniff。未更動業務SQL／資料／輪詢。
- memory完整119通過／40原生類略過5169assertions；MySQL相關10通過262assertions；契約7通過418assertions。官方結構／負向規格、實際HTTP持續限流及Chrome流程通過，Pint6檔通過。
- 構造驗證採固定官方Schema＋專案Python venv，實際回應採Opis標準資料Schema；不自製validator、不改官方dynamicRef。工具鎖版及授權一併保存，CI留TT-253。
- 本機新版公開資源與來源核對，不seed／reset主庫，不推送遠端。

## 2026-11-12 TT-167 開始

- 領取公開REST契約與必要OpenAPI規格；保持正式spec只列已實作5個操作，不越卡實作TT-168／169。
- 核對每操作status／security／parameters／schema及範例、URL v1與規格版號、限流與Retry-After、防重送／revision及棄用規則。新增匿名Markdown契約入口，完整文件站／CI仍TT-252／253。
- 修正限流回應Header丟失與不同操作共用計數的問題，補真實HTTP與標準JSON Schema驗證，不自製schema validator、不測主庫。

## 2026-11-12 TT-166 完成驗證

- Bearer read/write邊界、TokenContext、成功來源表與本人有界查詢已實作；PAT管理已接Session來源，公開卡片操作留TT-168／169。讀取不追加稽核且只查一次Token；寫入保留鎖前hint／鎖後驗證。
- memory完整113通過／40原生類略過；MySQL相關10通過；最終跨引擎／規格14通過353 assertions。含真實獨立PDO撤銷競態、Project降權／撤權、同人跨Workspace、來源分頁與audit失敗原子rollback。Chrome及Pint通過。
- 新integration_audits migration batch3、本機新版服務及OpenAPI一致性已確認，無seed／reset主庫。只提交本卡相關檔案，不推送遠端。

## 2026-11-12 TT-166 開始

- 領取使用者／Token來源稽核與即時撤權；以可信TokenContext及Bearer交易邊界共用WorkspaceContextFactory／ProjectAccess，不依賴瀏覽器Session。
- 寫入採User→Workspace鎖後重查Token、Scope及資格；成功寫入同交易追加來源metadata，失敗rollback。只記user／token ID與名稱、scope及受信任操作識別，不保存秘密或payload；讀取不寫稽核。
- PAT建立／撤銷亦記Session來源；提供本人目前工作區的有界稽核清單。測試有效權限交集、撤銷／到期／停用、跨租戶、原子rollback與MySQL並行撤銷。公開業務REST仍留TT-168／169。

## 2026-11-12 TT-165 開始

## 2026-11-12 TT-165 驗證與交付

- 完成個人Token管理、純Bearer身分與機器規格；公開專案／任務／留言仍未提供。
- 隔離memory回歸108通過／32略過（原生MySQL類）；原生MySQL相關23通過。PAT包含雜湊、期限、撤銷、本人／工作區隔離、Scope、重送及有界分頁；實際瀏覽器驗一次性原文、重新開啟、撤銷401、無storage秘密與三尺寸。
- 本機只載入新映像與新增PAT表，不seed／reset主庫；獨立測試庫與主庫分離。最終commit與TT-165結案於驗證完成後。


- codex負責AI整合/API，先領TT-165再inprogress。採Sanctum4.4.0與既有Laravel13，保留Session／CSRF；不改Claude進行中的ApiExecution／WorkspaceServices依賴重構。
- 個人Token管理頁：工作區綁定、read/write（write含read）、預設一個月／最長兩年、建立原文一次、雜湊保存／撤銷／到期顯示。新增獨立管理服務、路由檔及受限Bearer身分驗證入口，不自動開內部RPC或越卡實作所有業務REST。
- 同卡同步OpenAPI／文件／隔離SQLite及原生MySQL／真實瀏覽器。效能／安全：本人SQL分頁、穩定排序、重新驗工作區資格、不可管理別人Token；秘密不進Git／log／localStorage。

## 2026-11-12 TT-259 API看板分類整理

- 使用者確認AI整合/API聚焦個人Token與外部直接HTTP操作；內部DI／PHPDoc／Request DTO／效能歸效能改進，UI／一般功能與沿革歸實際功能。
- 實際看板55→19張，36張移出（效能改進25、實際功能11），目的板原收錄保留。API只收專屬TT-3，動態展開18張；首批PAT／REST／文件與後續同步／Webhook分清，不修改卡片優先與狀態。
- 既有258張摘要與revision逐欄未變；看板分頁API讀回有效集合、目的板保留與移入逐項核對。新TT-259由codex先領再inprogress，驗證與提交後結案。不實作API產品、不重跑seed／build；保留frontend/tools/load-baseline.js既有未追蹤檔。
- 文件docs/api-board-classification-2026-11-12.md、JSON快照、AGENTS及主題看板規則同步，未改Claude已完成的卡片進度。

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

- 已讀兩份指定資料、README／產品沿革、API及Git歷史；指定commit為Multi-Agent交接文件。主服務健康，操作只走既有應用API，不直寫主庫／不seed。
- 階段一已提交來源副本、202卡初始清單與應用API工具；階段二完成真實帳號／專案／卡片建立、狀態核對與交接。

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

## 2026-11-05 本輪交付完成

- 列表精簡commit 468bdf8，詳細頁關聯側欄另階段提交；預設屬性／關聯tab，手機可展開，主文描述及活動、側欄關聯，各類資料及API／ACL不改。
- production build、完整真實隔離API browser-check、UIUX回歸皆通過；Task／Epic三寬度、21卡長清單、空狀態／父項導覽／鍵盤／留言草稿驗證通過。
- 已檢視桌面／平板／手機展開及收合截圖，修長清單撐開標題間距；Taste沿用既有風格啟發式界線，未宣稱完整inspector全綠。
- 本輪隔離API容器移除，業務volume未動；README／workflow及產品討論同步。保留plans原有差異不提交，未push。

## 2026-11-05 關聯側欄驗證進度

- 詳細頁屬性／關聯tab已實作；主文移除原清單，側欄分Epic／父項／子項／一般關聯、空狀態、獨立捲動；手機收合入口。
- build、完整真實隔離API browser-check通過；聚焦測試驗證Task與21張卡片Epic在1440／768／390px、鍵盤tab切換／草稿保留、父項導覽／空狀態。已看截圖並修長側欄造成主文標題空白（grid rows auto 1fr）。
- UIUX回歸發現先前狀態列改版遺留舊唯讀文案「目前狀態：」selector，更新為角色group＋實際標籤後重跑。產品未為測試改文案。
- 日誌 /tmp/tasktracker-sidebar-{before,after,build,browser,uiux,lint}.log；畫面與Taste模組量測 /tmp/tasktracker-sidebar-ui/。不聲稱上游inspector全面通過。

## 2026-11-05 詳細頁關聯移至側欄

- 已確認使用者希望Epic／一般卡片關聯改放側欄，考慮tab分區；本輪實作「屬性／關聯」兩tab，不改關聯資料與編輯方式。
- 主文只保留描述＋留言／歷史／工時；側欄關聯分所屬Epic、父任務、Epic所屬卡片／子任務及一般關聯。預設屬性，手機以「屬性與關聯」展開入口降低遮擋。
- 沿用現有tab鍵盤helper、權限過濾與草稿保護，窄欄文字可換行，長清單可在側欄捲動。驗證不同Issue類型、空狀態、長清單、鍵盤、手機、閱覽權限及狀態／留言流程。
- 先記錄隔離登入before，再改元件及相近browser-check，build與真實API回歸、三寬度人工檢視後本機commit；不push，不動業務DB。

## 2026-11-05 列表精簡驗收完成／詳細頁關聯側欄續作

- 列表只留標題＋編號及主要欄位；同樣本1440／768／390px列高96／148／120降72／72／70，無橫向溢位；完整隔離API browser-check與build通過。
- 新驗證首次使用錯誤fixture名稱付款API，改為實際TEST-5付款畫面後通過；未放寬產品檢查。
- 下一階段：使用者要求詳細頁Epic與一般關聯集中右側，以屬性／關聯tab分區；主文只留描述／留言活動。保留原關聯／資料／草稿／權限，需驗證Epic／父子／一般關聯導覽、鍵盤、手機及多張卡片。

## 2026-11-05 任務列表摘要精簡

- 使用者反映標題下Epic／關聯資訊太多。列表聚焦標題、編號、類型／狀態／優先級／負責人／期限；完整關聯、子任務與工時留詳細頁，專案內重複名稱與留言數亦收斂。
- 限App任務列表，保留ProjectBoard共用IssueContext及編輯／篩選功能，不改資料模型／API。按兩個UI／UX skill單元件流程保留現有視覺方向。
- 隔離API假帳號取before／after桌面／平板／手機證據，驗證閱讀／快速編輯、條件篩選仍可用，build與既有browser-check，更新產品紀錄及README後本機commit。
- 不push、不重設本機資料；plans先前差異保留不提交。

## 2026-11-04 詳細頁狀態操作驗收

- 移除橫排狀態按鈕，改為目前狀態badge＋明確變更狀態選單；專案選項、狀態API、revision／requestId與草稿保護保留。
- production build與完整真實隔離API browser-check通過；新增保存中暫停回應確認鎖定、目前badge不提前改、恢復後自訂狀態更新及衝突／唯讀斷言。
- 已登入1440／768／390 before／after截圖及Taste模組量測，人工檢視after各尺寸；無整頁overflow，新select44px、可鍵盤聚焦／選取，自訂狀態成功且保留留言。
- 既有編輯按鈕shadow FAIL、品牌代碼／字型fallback等Taste啟發式提醒仍保留，不宣稱upstream完整inspector全綠。單元件lint0FAIL、1個box-shadow:none誤報WARN。
- 文件同步README、workflow-design及產品討論（保留先前tab試作沿革）。證據 /tmp/tasktracker-state-*；隔離測試帳號不寫業務DB，未改後端。
- 單階段本機commit；不push，不提交plans原有差異與產物。

## 2026-11-04 任務詳細頁狀態操作辨識

- 使用者反映上方橫列像tab，容易誤以為切換內容。沿用專案自訂狀態與既有更新API，調整成「目前狀態」及明確「變更狀態」選單。
- 使用ui-ux-pro-max／design-taste-frontend app-screen單元件流程，保持既有配色／字型／Vue與資料範圍。讀取task內容為主要工作，狀態為可編輯屬性，留言／歷史／工時才是tab。
- 先記錄登入後before證據，再調元件／既有瀏覽器案例（自訂狀態、busy、衝突、唯讀與手機），build及隔離真實API驗證、檢視1440／768／390畫面。
- 同步產品討論／README，單階段本機commit；不push，保留plans原有差異。

## 2026-11-04 關聯儲存已完成

- 階段1 commit fb6e468：22張關聯表、模型／FK／索引、DTO映射與舊資料往返遷移；38項／796斷言及真實隔離API瀏覽器全流程通過。
- 本機停寫最終備份 /data/backups/before-relational-final-20261104.sqlite；最新副本預演與原庫升級皆逐欄比對69筆舊紀錄一致，records／帳號hash未改，FK／integrity通過；無resetseed。
- backend／queue／websocket已重建；前端、API、Reverb、Redis healthy、Queue running。Redis／Queue工作及WebSocket握手驗證通過；PHP語法／Composer平台檢查通過。
- docs/database-design.md提供ER、22張表、主鍵／scope／快照邊界、升級回退及驗收，root／backend README／AGENTS、架構／協作文件及產品紀錄同步。
- 原始plans差異保留且不提交；本輪隔離API容器已移除，未動GuildManager／其他容器，未push。

## 2026-11-04 關聯儲存驗證進度

- 新增22張關聯表、Eloquent關聯、複合租戶／專案FK、保留API的mapper、遷移／回退及Seed／匯入適配。
- 完整測試38項／796斷言，真實隔離API／Chrome全流程通過；日誌 /tmp/tasktracker-relational-{tests,browser}.log。
- 本機SQLite online backup：/data/backups/before-relational-20261104-135902.sqlite；唯讀掛載備份到獨立helper，副本升級逐欄比對69筆來源（含31重試與3舊視圖），records／帳號hash未改、FK與integrity通過。
- 尚待本機停寫、再次備份＋預演後套用migration、重建長駐服務與文件收尾。保留既有plans差異，不提交planning。

# 工作紀錄

## 2026-11-04：關聯式業務儲存

- [ ] baseline與既有資料種類／遷移契約盤點
- [ ] 關聯schema／模型／repository與backfill、Seed及import相容
- [ ] 隔離遷移／約束／API及瀏覽器驗證、階段提交
- [ ] 本機DB備份／副本預演／升級、文件與交付


## 2026-11-04：後端可讀性與Laravel分層整理

- [x] baseline、契約與分層盤點
- [x] 拆分業務服務／授權／重試，展開流程與中文docblock，測試並提交
- [x] 明確HTTP入口、JSON Request／Response、資料模型入口，契約回歸並提交
- [x] 隔離瀏覽器／完整API回歸，文件及本機程序同步，交付提交


最終提交：f3cde29（條件收尾）、419cfc9（導覽／驗收／規範現況）。隔離測試容器已移除，本機服務保持運作。

階段：705d2b5（服務／授權／重試＋展開與docblock）、9f51ba7（明確HTTP／FormRequest／Resource／StoredRecord＋契約測試）。既有30／700基準及階段回歸通過，新增4項相容契約測試後34／743通過，完整真實API瀏覽器回歸通過。最後工時及待辦長條件收尾後34／743再次通過。獨立API容器tasktracker-backend-refactor-api無業務volume，PHP8.5.12，臨時SQLite；映像重建成功，三服務已重建並通過前端代理health／Redis Queue／WebSocket握手；保留before-architecture-refactor映像回退標籤。文件docs/backend-development.md；logs /tmp/tasktracker-backend-refactor-*.log，影像frontend/artifacts（ignored）。未變更schema／RPC／前端／lockfile，planning排除，不push。

## 2026-11-04：匯入後端開發規範

- [x] 盤點主skill／相容別名、讀規範與references，確認main既有差異
- [x] 複製並適配TaskTracker，建立來源清單及專案入口
- [x] 更新開發指引與討論紀錄、驗證連結／skill／差異並提交


交付：主skill八檔適用版＋來源／本機SHA-256；六份references齊全，已合併別名不重複搬入。新增.agents相對symlink，接root/backend AGENTS、backend CLAUDE／README及skills說明。調整permission／scope、DomainError、未授權存在性、PHPUnit metadata與壞相對連結；共用來源及既有UIUX原檔均未修改。quick_validate及agents metadata通過（validator缺PyYAML，在一次性venv補依賴，驗證後已刪除），相對連結／來源hash／diff通過；純文件不跑業務測試，無程式／DB／runtime變更，planning不stage。

## 2026-11-04：前端待辦與PHP版本檢查

- [x] 現況／官方版本需求／前端剩餘問題盤點
- [x] 必要版本契約修正、lock一致及隔離驗證
- [x] 文件整理、差異與連結檢查、本機提交


結果：PHP8.5.11→8.5.12，Dockerfile明確pin；Composer根需求與lock不變且檢查通過，新映像隔離測試30／700通過，三個服務重建並確認8.5.12，Queue／WebSocket基礎設施通過，前端build通過。首輪隔離容器缺APP_KEY，補fake test key及tmp env後通過，主機PHP8.2.4 ICU錯誤未修改。前端只評估建議，未改UI。報告docs/前端後續改善與PHP版本檢查-2026-11-04.md；log /tmp/tasktracker-php8512-*.log；舊映像before-php8512保留可回退，未push，planning排除。

## 2026-11-04：修正走查 F01–F04

- [x] 核對規範／main／既有差異、前輪報告與兩份 UIUX 指引
- [x] 跨 tab 草稿可見、操作與恢復, 真實 API 驗證及階段提交
- [x] 分頁查閱上下文按 actor／workspace／project 保存, 邊界回退與階段提交
- [x] 首屏待辦入口、標題閱讀／快速編輯, 適配既有回歸
- [x] build／全套與針對性瀏覽器／production視覺驗證, 文件與交付提交


階段：f0d2755（F03）、380e122（F01）；34e49ba（F02／F04與驗收文件）已提交。隔離API容器tasktracker-flow-fixes，13024；完整E2E與U01–U08通過，最終針對性回歸及production證據在frontend/artifacts/usability-fixes-2026-11-04；logs /tmp/tasktracker-*-flow.log。所有建置／完整E2E／針對性／U01–U08／production檢視通過，122個上游檔案hash不變，文件連結與diff檢查通過。專用隔離容器已移除，主服務／業務資料未更動。保留原planning差異，不stage。

## 2026-11-04：雙 skill 任務走查

- [x] 明確讀取兩份 skill 與任務方案, 核對 main／既有差異
- [x] 定義 T1–T8 角色、情境、起點／終點與觀察表
- [x] 隔離 production 真實 API 操作、45份畫面／狀態, 三個context與權限階段；四項發現有 source 追查
- [x] cognitive 每步 Q1–Q4 報告、四項可用性 findings／真人0人的資料缺口及試用腳本
- [x] 文件入口／產品紀錄同步, 連結與122個原 skill hash、45份截圖／狀態核對；preview／瀏覽器／隔離 API 及暫存腳本已清理, 已提交 527fbf8

交付：527fbf8, 8任務／45份畫面狀態／三個context, 發現 F03跨tab草稿導致操作消失、F01查閱範圍重整重設（Major）；F02手機速記入口在首屏外、F04閱讀先進編輯（Minor, 理解風險待真人）。核心轉卡／網路失敗重試／看板／工時／通知／邀請授權皆有操作證據。真人0, 不給成功率或滿意度。build通過, source只讀追查, 未跑整套回歸／未改UI／未動業務資料, original skill122檔hash不變；連結／staged diff驗證完成。證據留 frontend/artifacts/usability-audit-2026-11-04（ignored）, planning保留未提交。


## 2026-11-04：使用者流程與可用性 skills 調研

- [x] 核對 Git 規範、main、既有 planning 差異與產品討論
- [x] 查五個 GitHub 原始 skill／授權與適配, 選 cognitive-walkthrough＋usability-testing
- [x] 固定來源評估副本（7 檔／24,531 bytes）、各別介紹及 TaskTracker 可用性驗證提案；不註冊、不執行新審查
- [x] 文件／來源校驗、產品紀錄與入口同步, 本機 commit 完成（見本輪最新 log）

交付：兩個評估副本＋個別介紹＋可用性驗證方案；五候選原始 SKILL／GitHub API、MIT 與固定 commit 核對；新增 7 檔原始 blob SHA-1／SHA-256 驗證, 原有 115 檔 SHA-256 不變；Markdown 本機連結與 staged diff 空白檢查通過。純文件／來源收錄, 無需產品測試；未啟用／未跑新 audit 或真人研究／未修改 UI 或資料, planning 保留未提交。


## 2026-11-04：修正 U01–U08

- [x] 讀前輪報告、兩份 skills、目前程式與既有差異
- [x] 可讀性與工時欄位錯誤修正, 隔離驗證及階段提交 8b09bed
- [x] 首頁／tabs／手機與平板布局修正, 隔離驗證及階段提交 7e204b8
- [x] 獨立功能路由與草稿／權限／返回保護, 完整既有及針對性回歸通過；production 16 組無 overflow／文字對比警告, 已提交 a160cb4
- [x] 審查對照／產品紀錄／README 更新、文件連結／115 上游 hash／diff 驗證；隔離 API、preview 與臨時腳本已清理

交付：8b09bed 可讀性與工時錯誤；7e204b8 首頁／tab／跨尺寸；a160cb4 功能路由、草稿／標籤邊界及修正對照。完整既有 E2E 通過, 最後的預覽排序草稿修正另外通過 uiux-check.js；最終 CSS production 16 組量測無整頁 overflow／contrast flag, Epic 手機小 metadata 比例提示保留為啟發式限制。build／來源語法／文件與差異驗證通過, 未變更業務 DB／seed。log /tmp/tasktracker-uiux-{fixes,regression,production}.log；ignored artifacts/uiux-fixes 保存本機取證。planning 保留原有差異未提交。


## 2026-11-03：啟用與 UI/UX 審查

- [x] 讀 Git 規範、兩份 skill、專案規範與既有差異
- [x] 相對 symlink 註冊、說明與上游 hash 驗證, 階段提交 be1fda7
- [x] 隔離 production preview 實際登入、44 組跨尺寸／角色, skill 檢索／source lint／登入頁上游量測模組；實際轉卡／30 分鐘工時／看板選單保存通過
- [x] 審查報告、文件同步與差異驗證, 階段提交 1b9f6a2；測試 API／preview 與臨時腳本已清理, 本機證據留 ignored artifacts

交付：be1fda7 啟用兩個相對 skill 入口, 115 原始 hash 不變；1b9f6a2 審查報告 8 項、44 組畫面量測, 無整頁 overflow／已捕捉 JS error, 轉卡／30 分鐘工時／看板 select 真實保存通過。build、文件連結、diff 空白驗證完成。僅報告未改 UI, 未執行全套回歸或宣稱完整 WCAG／ACL 安全驗收。只用隔離 SQLite 與假帳號, 無使用者資料異動；planning 保留原差異不提交。

## 2026-11-03：UI/UX skills 調研與專案副本

- [x] 讀規範、現有文件與 installer, main 僅既有 planning 差異
- [x] 查證 GitHub 主來源／星數／候選與版本, 確定專用 skill repo 口徑
- [x] 複製兩個固定 SHA skill／依賴／授權, 115 個上游檔案與 GitHub blob 完整一致
- [x] 各寫繁中介紹、比較／融合建議, 同步產品紀錄與入口
- [x] 文件／工具最小驗證與 staged diff：115 上游檔案 blob／SHA-256 一致, UX／Vue 查詢3／1結果, Taste app library 列舉與 inspector 語法檢查, 文件連結與表格通過
- [x] 本機 commit：完成, 詳最新 git log；既有 planning 差異保留


交付紀錄：2026-11-03 GitHub 官方 API snapshot：nextlevelbuilder/ui-ux-pro-max-skill 144294★／SHA713eef51b04efc98d6fa565323b495f7c9c89fd4, Leonxlnx/taste-skill 108756★／SHA12af471f627f866411b4bd785b19646d3672784c。明確專用 UI/UX skill repo 範圍, Anthropic整庫186781★、Matt工程庫310257★排除綜合集合星數混算, awesome-design-md125276★查完整tree無SKILL.md, impeccable100509★及emil53045★較低。不是全站普查保證。以 skill-installer download-only／固定SHA複製到skills/vendor, 沒有gitclone或全域註冊；保留完整子樹及根MITlicense。ProMax75檔3601559bytes、Taste40檔2425234bytes, 共115檔原始內容與GitHubblob一致, sources.json記來源與SHA256。原gitignore vendor/data誤忽略來源, 只加精確skills例外, backend/vendor與使用者data仍忽略

新增skills兩份繁中介紹＋比較索引, 區分事實／TaskTracker適用性推論／未採納融合建議。Taste目前v2有app-screen專章, 不照舊認為只有landing；CDP啟動新profile沒有Laravel登入介面, 明示未來要接隔離測試登入, 不把登入頁當產品審查。ProMax不自動套用插件路徑, 文件提供本機Python明確路徑, 不新增dependencies。smoke只查公共範例domain/stack及Taste列舉kinds, 沒有跑瀏覽器或資料寫入, PYTHONDONTWRITEBYTECODE避開副本cache；查驗log在/tmp/tasktracker-uiux-*.json、/tmp/tasktracker-taste-kinds-smoke.txt。staged diff顯示46處空白提示均為原樣vendor, 保持hash而不改上游；自寫檔空白檢查另跑。無產品源碼／DB／Seed改動, 不推送。planning保留未提交

## 2026-11-02：首頁小待辦與互相交辦

追加：使用者要求單向轉正式 Issue, 側欄補 Project／正式欄位, 同交易／重試去重, 不提供 Issue→TODO；轉換連結按 Project ACL 過濾, 不洩漏原交辦者未授權 Project。

- [x] 讀既有產品紀錄／ACL／首頁／通知與 Git 規範, 檢查 main 僅既有 planning 差異
- [x] 釐清直接加入可退回, 記錄需求與第一版範圍
- [x] 後端 TODO／站內通知／原子單向轉卡與隔離測試：30 項700斷言通過, 第一階段 commit 83d2cf3
- [x] 首頁快記／雙向交辦／通知／單向轉卡介面, 真實瀏覽器驗證與 build
- [x] 文件、資料保留／本機四帳號 API 及五服務確認
- [x] 第二階段 commit：6359899, 保留既有 planning 差異


驗證紀錄：後端 83d2cf3, 30 項700斷言通過, /tmp/tasktracker-todo-php.log；測試最初隔離容器缺 /app/.env 產生 warning, 補空檔後乾淨通過, 沒有使用使用者環境檔。隔離 tasktracker-todo-check 使用 /tmp/todos.sqlite、獨立 Compose labels, 無使用者 volume。完整真實 API＋Chromium 新舊回歸 /tmp/tasktracker-todo-e2e.log 通過, 聚焦 /tmp/tasktracker-todo-focus.log；第一輪測試用 title 選行在編輯後失去匹配, 改固定 todo ID。勾選失敗回復修正為完成請求後處理。聚焦測試發現重載轉卡資料時可提早輸入而被稍後回應覆蓋, 已在共用 IssuePanel 重載期間鎖定表單, callback settled 後解除；完整回歸含舊編輯衝突流程通過。build /tmp/tasktracker-todo-build.log, 桌面／手機截圖確認, 文件相對連結／diff 檢查通過。暫存聚焦 runner 及截圖不提交。補強刷新順序：先套用 Workspace／Project ACL, 再讀個人待辦, 避免待辦 API 暫時失敗阻止失權專案清除；瀏覽器撤權案例另注入一次 getTodos 斷線, 最終全回歸通過。最終補文件腳本誤在 frontend cwd 用根相對路徑而失敗, 改由根目錄執行後完成, 沒有產生錯位檔案

本機只讀四 Seed 帳號登入與 getTodos／getWorkspace, 待辦 API 正常, 目錄5人, 各帳號目前待辦0筆, Project ACL 範圍不變。檢查前後原業務 records SHA-256 一致, 沒有操作真實待辦或通知、沒有 migration/reset、沒有寄信／外部部署。Queue／Websocket 已重啟且五服務正常。新增轉卡只允許當前承辦者、同交易建立 Issue+converted marker, 原交辦者沒 Project 權限不回連結/ID。隔離容器 tasktracker-todo-check 及暫存 runner 已移除, planning 保持未提交

## 2026-10-30：最小 Project ACL 實作

- [x] 核對既有規則、權限／產品契約與目前程式、main planning差異
- [x] 後端集中 scope／Project 成員管理與過濾, 版本重試及遷移／Seed
- [x] 隔離 PHP 驗證 ACL 矩陣／撤權／ID 與重試洩漏／遷移保留, 24 項576斷言；後端階段 commit b157fa7
- [x] 前端 Project 權限／成員介面／Reader 與空狀態, 隔離真實 E2E 及 build
- [x] 同步文件, 備份本機 SQLite 並升級 Seed ACL／四帳號真實 API 驗證
- [x] 前端階段 commit：1dc7510（planning 保留未提交）


交付驗證：後端 b157fa7, 隔離 PHP 24 項576斷言, /tmp/tasktracker-acl-php.log。前端完整真實 API＋Chromium 回歸通過, /tmp/tasktracker-acl-e2e.log；聚焦 ACL /tmp/tasktracker-acl-focus.log。涵蓋邀請後無 Project 空狀態、同帳號多 Project 不同角色／可見性、Reader 完整頁／無工時／停用狀態、Default 與自訂看板能力、Project 成員 UI／ACL revision 衝突保留草稿／移除歷史、撤權時儲存 dirty IssuePanel 即時回首頁清除內容、手機版、四 Seed 首頁／操作權限／我的任務及全部既有流程。初輪舊測試仍預期 Workspace Maintainer／Developer 角色標籤, 改依帳號 Email 與 Workspace=user 驗證；新 fixture 超過代碼長度先修正, 聚焦通過後全回歸通過。自訂看板內新增要求 board.manage, 開發者只移動既有卡片；新增 UI 已與伺服器一致。建置、相對文件連結及 diff 檢查通過, 截圖忽略不提交

本機 VACUUM INTO 備份：/data/backups/before-project-acl-20261030-47c2ac.sqlite, 私有 manifest 同目錄。明確 migration 及非 reset Seed 後逐筆保護 records、users 比對不變：2 Project／14 Issue／6 工時, 新增3 Project grant／2 ACL revision。四帳號透過真實 HTTP／CSRF／session 登入：owner／manager 讀 FIRST+SECOND；maintainer Workspace=user 讀 FIRST+SECOND；developer Workspace=user 只讀 FIRST。密碼讀容器忽略環境, 未輸出或提交。queue／websocket 重啟且 Compose 五服務健康。測試沒有寫入使用者業務資料、沒有 reset、本輪沒有推播／寄信或外部部署。隔離容器 tasktracker-acl-check 已移除, 最終工作區僅保留既有 planning 差異。先前本機 PHP 檢查工具使用已棄用的 http_response_header 變數顯示一次 deprecation, 產品程式不使用此變數, 檢查結果成功


## 2026-10-30：競品日常使用流程、回饋與功能缺口調研

- [x] 核對原專案 main／既有差異、產品紀錄與舊調研
- [x] 盤點目前程式功能與缺口, 查證五家官方流程
- [x] 搜尋並核對第一手公開使用者經驗, 明確標樣本限制（6 個主題＋1 工程 issue）
- [x] 建立調研報告／排序與驗收建議, 同步討論與入口
- [x] 文件與來源檢查（35 個外部來源、相對檔案連結及表格欄數）, 本機文件階段 commit：9c57715


交付內容：docs/競品使用流程與功能缺口研究-2026-10-30.md, 五家競品官方工作路徑／模型差異、6個第一手討論、1個清楚區分的 GitLab 工程提案、現況盤點與 A1→A2→B1 建議順序。公開回饋只為目的性個案, 未宣稱現版故障或滿意度。查驗來源時無法取得或 404 的推測網址未列引用, Linear 工時未核對則明示未確認而不宣稱缺少。README／舊調研加入口, 產品紀錄同步且新建議不視為採納。只改文件、不執行本機業務測試／資料寫入, 無 log parser 變更, 不產截圖或外部留言。相對檔案連結、表格欄位及 diff 檢查通過, 不建立測試套件；planning 保留未提交

## 2026-10-22：收斂 ACL 與角色試用的觀察基準

- [x] 讀取產品紀錄、README、既有權限提案與 Git 規範
- [x] 記錄使用者疑慮、既有已確認原則與下一階段最小 ACL 建議
- [x] 檢查文件一致性與差異, 僅文件本機 commit；無程式／資料改動, 不新增測試


## 2026-10-22：四帳號權限測試 Seed

- [x] 盤點 Seed／權限／登入與 UI, 確認 main 只有既有 planning 差異
- [x] 更新權限映射／Seed 非破壞升級與介面入口
- [x] 補 Seed 與真實權限 API 測試, 隔離驗證與前端 build／E2E（18 項／371 斷言, 全流程與四角色畫面通過）
- [x] 更新產品文件, 本機備份並套用 Seed, 四帳號本機真實 API 登入通過
- [x] 階段本機 commit：完成（詳最新 git log, planning 保留未提交）


交付驗證：舊 v1 非破壞升級, 已建立 owner／manager／maintainer／developer@example.test, 本機環境密碼核對一致且四帳號可登入, f-ws 仍 2 專案／14 Issue。/data/backups/before-four-accounts-20261022-0dfcbd.sqlite 已備份；排除新四個會員及 seed marker 後原 records 與 users SHA-256 逐一核對不變, Jack 及其指派／歷史／工時保留, 沒有執行 reset。完整 API 權限／偽造名稱／本人工時／seed 重跑碰撞與舊資料保存通過 18 項371斷言, /tmp/tasktracker-seed-php.log；E2E 含所有既有流程及四角色隔離瀏覽器登入／首頁建專案／編輯／邀請／狀態／看板／SMTP入口／我的任務, /tmp/tasktracker-seed-e2e.log。第一輪回歸因舊測試仍預期無權限成員可進入唯讀設定頁而失敗, 更新為已隱藏入口後全通過。隔離 seed 預設 production 被拒後明確以 local 執行, 未放寬環境限制；隔離測試容器已移除。build 與 diff 檢查通過, 密碼不進 Git, planning 留未提交

## 2026-10-21：任務完整詳細頁

- [x] 核對既有側邊編輯、深連結、產品文件與 Git 規則
- [x] 新增完整閱讀頁、入口與返回上下文, 沿用側邊編輯
- [x] 留言與導覽草稿保護、深連結與手機驗證；追加上方自訂狀態 tab 及版本衝突保護
- [x] 更新文件、build／隔離完整 E2E
- [x] 本機 commit：9ba0f6e（planning 保留未提交）


交付驗證：完整隔離真實 Laravel API E2E 通過, 涵蓋完整頁／快編／分享重整／原列表篩選及頁碼／看板捲動、子任務／關聯、Epic 不重複工時、留言保存及衝突草稿、瀏覽器返回與手機。使用者追加上方狀態 tab, 釐清為單卡狀態修改後實作, 測試自訂狀態與狀態版本衝突保留留言。測試抓出同專案不存在 Issue 要等背景刷新, 已改即時讀取驗證；看板捲動斷言改為比對點卡片後的實際位置, 避免 Playwright 自動捲到元素造成錯誤基準。最終截圖檢查完成, 未寫入使用者業務 DB 或重設 seed；測試 tmp SQLite, Compose labels 與正式服務隔離。完整 E2E log: /tmp/tasktracker-detail-e2e.log；產物 frontend/artifacts 忽略不提交

## 2026-10-20：看板列表與手動收錄

- [x] 核對 Git／開發規則、main 與 planning 差異, 讀目前看板／API／產品文件
- [x] 確認 Default 範圍為目前專案, 記錄收錄／動態展開規則
- [x] 獨立先完成收錄語意與看板設計文件, 明確標註範圍待確認；文件檢查後本機提交
- [x] 後端看板契約／權限／版本與隔離驗證, 階段提交（16 項／272 斷言, sentinel 不變）
- [x] 看板列表／自訂收錄介面／路由, 隔離 E2E 與手機驗證
- [x] 同步文件並完成前端階段本機 commit


交付驗證：後端 ef01b37, 16 項／272 斷言通過, 外部 sentinel SQLite SHA-256 4125cfcbd3d44da4eb6f16b63b7c74b3d13810794849ef687df68491902aa033 不變。前端 build 與乾淨隔離完整 E2E 通過；涵蓋 Default／列表、Epic 後增卡動態加入／去重、移 Epic 的明確收錄例外、一般父項不含子項、共享狀態、看板內新卡原子收錄、篩選交集、重整／Issue 深連結、未保存與版本衝突草稿、封存不刪卡、手機列表／表單與橫向捲動。已檢視三張桌面／手機截圖。首次主測試 fixture 透過 API 新增專案後未刷新前端快取, 補 reload；聚焦測試發現已封存看板 hash 導覽只等背景更新才驗證, 修正路由即時 refresh／回列表後聚焦與完整驗證皆通過。曾在 frontend cwd 用根相對路徑執行編輯腳本而失敗, 改根目錄重跑後成功；未產生錯位檔案。文件連結／diff 檢查通過。本機服務唯讀確認既有兩個專案各有 Default, 不寫使用者業務資料、不重設 Seed；隔離 API 使用 tmp SQLite 與獨立 labels, 完成後停止移除。planning 留未提交

## 2026-10-20：日期開啟工時側邊面板

- [x] 核對 main、既有 planning 差異與 TimeCalendar／測試／產品紀錄
- [x] 日期開啟右側抽屜、全寬月曆、未儲存保護及編輯流程
- [x] 更新文件, build／隔離 E2E 與手機檢視
- [x] 本機 commit, 保留其他差異


驗證：build／完整隔離 Laravel E2E／文件連結／diff 通過。新增月曆完整寬度、點前日帶入日期、僅選任務也 dirty、關閉／Escape／遮罩取消捨棄保留內容、確定關閉回日期焦點；新增／修改成功關閉並更新日格／明細及團隊加總。另一分頁修改工時後舊 revision 衝突保留草稿, 關閉重開載新版後可保存。手機抽屜 390px 滿寬／內部不溢出, Escape 保留草稿；初次截圖取在滑入動畫中途, 追加等待動畫完成的邊界斷言並用獨立隔離 smoke 確認實際完整顯示。截圖已檢視。只改前端, 無需重跑未變後端測試；helper 使用 tmp SQLite／獨立 Compose labels, 未寫使用者業務資料, 完成後停止移除。planning 留未提交

## 2026-10-20：類型收斂為 Epic／Task／Bug

- [x] 核對規範、main 與 planning 差異, 盤點類型契約及遷移／匯入
- [x] 前後端三類、Feature 遷移／Seed／文件與未來自訂方向
- [x] 隔離 PHP／build／瀏覽器驗證, 備份後更新本機資料
- [x] 明確範圍本機 commit, 保留 planning


驗證：build、完整隔離 E2E、PHP 13 項／223 斷言通過；外部 sentinel SHA-256 4125cfcbd3d44da4eb6f16b63b7c74b3d13810794849ef687df68491902aa033 未變。新增三類合法值／拒收 feature、跨 Workspace 升級、保留全部原欄位／歷史／留言／時間、舊 revision 衝突、冪等及舊匯入來源 hash 不變。表單選項 Epic／Task／Bug, 執行篩選 Task／Bug, 桌面手機截圖已檢視。文件連結及 diff 通過。現有本機 SQLite 先 VACUUM INTO /data/backups/before-three-types-20261020-105859.sqlite, 再只執行單一新 migration；逐筆比對 2 張 feature→task, 其他 57 records payload 完全不變、總數相同, 未重設 Seed。隔離 helper 完成後停止移除, planning 不提交

## 2026-10-20：移除視圖與類型文字化

- [x] 核對共享 Git／AGENTS／後端規則及 main 既有差異
- [x] 移除視圖前後端與 Seed, 保留舊紀錄並說明舊網址行為, 記錄首頁構想
- [x] build／隔離 PHP 與瀏覽器回歸, 完成第一階段 commit
- [x] 表格類型改文字、桌面手機驗證與第二階段 commit


第一階段驗證：build、完整 E2E、隔離 PHP 12 項／183 斷言皆通過；隔離容器標準 php artisan test 前後 sentinel SHA-256 4125cfcbd3d44da4eb6f16b63b7c74b3d13810794849ef687df68491902aa033 不變。移除 API 在 request 去重前拒絕, 不回傳或改舊 savedViews, Seed 不建立。列表／看板／Issue 舊參數提示與保留路徑、AND 篩選／清除、手機皆已驗證。首頁小清單只記構想, 尚未實作；舊資料與目前業務 SQLite 未操作

第二階段：類型欄改文字與可見欄名, build 及隔離 API 瀏覽器驗證通過。1440px／390px 類型欄寬 82／64px, 類型文字無截斷、頁面無溢出；手機問題類型篩選及移除條件通過。已檢視兩尺寸截圖, 狀態仍獨立。初次 build 誤在根目錄執行找不到 package.json, 改在 frontend 成功。對可逆顯示調整用聚焦驗證, 未再次跑無關後端測試；第一階段完整回歸已通過, 不使用業務資料庫

## 2026-10-20：移除重複常駐視圖列

- [x] 確認 main 與既有 planning 差異, 盤點視圖元件／深連結及測試
- [x] 移除常駐籤列, 整合按需使用的常用篩選選單與保存
- [x] 同步文件, build／隔離瀏覽器驗證及本機 commit


驗證：build、完整隔離 Laravel E2E、文件相對連結／diff／Node 語法通過。1440px 表格 top 183px（前版 239.5px）, 字體及對比維持；常用篩選保存／下拉套用／更新／作者移除、私人／團隊、閱覽者 mine、舊分享網址、失效狀態及重設保留資料皆通過。另以隔離真實 API 驗證手機選單、警告與重設, 元素邊界皆在 390px 內；補等待側欄動畫後截圖, 避免換視窗尺寸中途取圖。現有五個服務維持運作；Compose ps 自訂 format 不支援, 改用標準輸出確認。無後端變更、未操作使用者資料庫；隔離容器用獨立 labels 與 tmp SQLite, 完成後停止移除, planning 不提交

## 2026-10-20：說明常用視圖用途

- [x] 確認原 repository／main 與既有 planning 差異, 核對 SavedViewsBar／SavedViewDialog
- [x] 記錄用途、命名與入口提案, 未把詢問當成刪除或改版指示
- [x] 文件連結及 diff 檢查, 僅提交產品紀錄；無程式變更, 不跑應用測試


## 2026-10-20：任務工具列按需展開

- [x] 讀取 Git 工作位置與既有差異、目前篩選／視圖與產品文件
- [x] 精簡頁首、按需展開篩選與可移除條件摘要
- [x] build／隔離瀏覽器驗證, 更新文件並提交


驗證：build、完整隔離 Laravel E2E、文件連結與 diff check 通過。1440px 表格 top 239.5px（前版約 328px）, 字體與對比維持；新增預設收合、條件交集、收合後逐項移除／清除、重整視圖仍顯示摘要、手機展開不溢出驗證。首次測試用桌面品牌連結操作手機隱藏側欄而逾時, 改用一直可見的麵包屑後完整通過, 非產品故障。檢視桌面與手機截圖確認排版, 隔離容器使用 tmp SQLite 與獨立 Compose labels, 未寫使用者資料。初次 build 在根目錄找不到 package.json, 改在 frontend 執行後成功。planning 保留未提交

## 2026-10-20：專案入口顯示負責人與介紹

- [x] 讀取規範、產品紀錄與既有 main 差異, 確認目前首頁仍以 Issue 計算累計完成率
- [x] 加入專案負責人與 metadata 更新契約／權限／revision 驗證, 隔離測試後提交（eb9d561, 11 項／177 斷言, sentinel 不變）
- [x] 首頁移除統計、加入負責人／介紹及編輯表單, 同步文件
- [x] build／隔離瀏覽器驗證桌面手機、搜尋、編輯保存／衝突, 補示範 leadId 後完成提交


驗證：後端 11 項／177 斷言通過, 外部 sentinel SQLite hash 不變；build、完整既有 E2E 及新增首頁 metadata／保存／版本衝突保留草稿／手機／負責人無額外權限案例通過。首次回歸因游標正好停在任務 hover, 發現原 hover 紫色對比不足, 改用 accent 後約 5.77；新測試曾透過 API 建專案後未刷新 UI 快取而逾時, 修正測試重載後獨立及完整驗證通過。本機僅補指定 first／second 的缺漏 leadId 為 Jack, 比對 Issue／工時雜湊不變, 登入唯讀 smoke／桌面手機截圖已檢視；未重設 Seed。文件連結及 diff 檢查通過, planning 保留未提交

## 2026-10-20：Seed 與測試隔離修正（使用者新增要求）

- [x] 告知測試隔離失效、保留本機 SQLite／舊 volume 副本並停止寫入；目前資料為空, 無可恢復業務紀錄。使用者說明假資料不重要, 指定改 seed, 不繼續恢復
- [x] TestCase 在 bootstrap 前強制程序環境及 memory config, 獨立容器模擬 Compose 執行 10 項／152 斷言, sentinel SQLite 雜湊完全不變
- [x] 建立指定 Jack／f-ws／first／second seed, 預設冪等、只重設 seed namespace, 密碼放本機忽略設定
- [x] 載入本機並驗證指定帳號、兩個專案與卡片／工時, 更新指令及相關文件, 分階段提交


實際驗證：新版完整 E2E（含設定既有父項／重整／解除）通過；舊 blocked 視圖保留限制、明確修正後保存與子任務草稿離開保護皆用隔離 SQLite 驗證通過。Seed 服務測試與 production 拒絕共 10 項／152 斷言；Compose-like 容器中執行標準測試後外部 sentinel SQLite hash 完全不變。本機 seed 重跑及 db:seed 保持資料, 指定帳密登入與 first／second、14 Issue／4 子任務／6 工時、列表／看板唯讀 smoke 通過。首次 Compose up 被測試容器繼承的映像標籤干擾, 已移除 helper 並使用獨立 project／service labels, 現服務已恢復。資料庫副本留於忽略的 data/recovery-20261020, 不能當成原資料已恢復的證據

## 2026-10-20：改為子任務與關聯任務

- [x] 讀取 Git／後端規則、目前契約與既有差異, 確認移除前置的明確要求
- [x] 後端父子／雙向關聯、revision／history 與舊資料／視圖升級, 隔離 PHP 測試後提交（bf3459d, 8 項／134 斷言）
- [x] 按需新增子任務／關聯介面、列表／看板脈絡與舊視圖提示, 更新實作文件（145e173）
- [x] build／完整隔離 E2E／手機與資料保存驗證, 完成第二階段 commit；本機原資料已清空, 依使用者新增要求改由 Seed 載入, 不宣稱恢復原資料


## 2026-10-20：YouTrack 任務關係調研與設計討論

- [x] 核對原專案 main、既有 planning 差異與目前依賴表單
- [x] 查證 YouTrack 三類 link、建立關聯／子任務入口及可選依賴結案 workflow
- [x] 更新產品討論、競品調研與流程文件, 檢查後建立文件 commit


驗證：官方 Link Issues／Dependencies／Subtasks 文件核對, 本機實作 epicId／blockedBy 與候選表單確認；文件相對連結、錨點、code fence 及 diff check 通過。只有文件變更, 不跑應用測試, 不修改前置規則／資料；planning 留未提交

## 2026-10-19：列表去除重複資訊與總計

- [x] 讀取 Git 工作位置規則、目前 UI／產品文件及 main 既有 planning 差異
- [x] 移除重複標題與總計列, 保留結果筆數與手機定位
- [x] 記錄概覽提案與計算範圍, build／隔離瀏覽器驗證, 明確檔案提交


驗證：build、完整既有 E2E、文件連結與 diff check 通過。1440px 下資料表 top 由約 415px 降至 328px, 字體／對比維持。檢視桌面／手機截圖, 另外以暫存腳本驗證 21 筆翻頁、篩選回第一頁、單筆／零筆結果與手機目前功能。暫存腳本首次未重載登入狀態而逾時, 修正後通過, 非產品問題。隔離 tasktracker-summary-check 容器已停止／自動移除, 不寫使用者資料；planning 留未提交

## 2026-10-19：可讀性與 GitLab 式完整內容區

- [x] 核對 main 與既有差異, 盤點小字、淺色與巢狀卡片來源, 納入 GitLab 參考
- [x] 平整右側資料工作區、壓縮摘要／工具列並提高字體與文字對比
- [x] 核對桌面／手機截圖及資料空間, build／既有 E2E 通過後同步文件並提交

實測：1440px 視窗下 main／workspace／table 皆寬 1196px, 資料表 top 約 415px；標題 16px／對比約 14.53, 輔助字 12px／對比約 5.78, 狀態字對比約 10.03。完整既有 E2E 與新增瀏覽器排版／對比驗證全部通過, build／文件連結／diff 檢查通過。檢視列表、泳道與手機截圖確認右側扁平滿寬、文字加深, 只有工作資料卡仍保留邊界。隔離 tasktracker-readability-check 使用 /tmp SQLite, 未寫使用者資料。驗證不宣稱完整無障礙稽核, planning 保留未提交

## 2026-10-19：任務列表與視覺看板分離

- [x] 核對目前分支與差異, 查證 YouTrack 官方 Agile Board／Swimlane 說明
- [x] 分離入口、路由與元件, 保留共用 Issue 與版本／保存語意
- [x] 看板加入 Epic 泳道／收合／卡片密度與欄內新增, 同步 D-018／來源／README／流程文件
- [x] 隔離 E2E 驗證雙入口、深連結、狀態同步、未儲存保護及既有流程, build／文件檢查通過後提交

本輪新增 ProjectBoard 元件, list／board route 各自保留 Issue 詳細頁及 filters.view 分享語意, 舊視圖網址會導向正確功能。泳道／密度／收合依使用者與專案存在瀏覽器, 跨泳道與跨欄以同筆 revision 原子更新；欄內新增預填 state／epicId

驗證：build 通過, 全部原有 E2E 與新增 Epic 泳道新增／跨欄跨泳道移動／revision／密度與收合保存／舊 board 視圖／詳細頁重整關閉／手機橫向捲動全部通過。先前測試發現手機 sticky 欄頭溢出, 已在看板容器建立局部定位及裁切邊界, 實測頁面寬 390、看板內容寬 1000, 不裁切整個頁面。隔離 tasktracker-board-check 使用 /tmp SQLite, 不寫使用者服務資料；新元件已透過 frontend source mount 載入。文件連結、code fence、決策 ID 與 diff 檢查通過

## 2026-10-19：專案內導覽聚焦

- [x] 確認 main／既有 planning 差異, 讀取導覽、路由保護與既有回歸
- [x] 側欄只顯示目前專案功能, 左上角品牌回首頁, 跨專案操作集中首頁
- [x] 同步產品決策與文件, build／完整 E2E 通過, 包含桌面／手機／品牌離開確認／首頁換專案與深連結

本輪只改導覽與紀錄, 不修改 API 或使用者資料。獨立 tasktracker-navigation-check 容器使用 /tmp SQLite, E2E_API_URL=13006, 完整原有測試與新增品牌導覽驗收通過。檢視桌面截圖確認只顯示目前專案與功能, 畫面已透過原服務 source mount 熱更新。規劃差異保留未提交, 本輪只提交 App／CSS／既有瀏覽器檢查／README／產品紀錄

## 2026-10-19：切換本機新版服務

- [x] 確認舊容器名稱與獨立資料 volume
- [x] 建置新版、移除舊容器並啟動五服務, 保留 tasktracker_tasktracker-data 舊 volume
- [x] 前端／API／CSRF HTTP 200, Redis 連線、Queue 入列消費、Reverb WebSocket 握手通過；接口為 5174／3002／8080

## 2026-10-19：前後端分離與 Laravel 遷移

- [x] 核對原 repository／main 與使用者既有差異, 盤點 API／資料／前端與 Docker
- [x] 查閱 Laravel／Sanctum／Vuetify／Inertia 官方文件, 選擇獨立 SPA＋Laravel API
- [x] 更新選型紀錄並提交文件階段（73d2606）
- [x] 搬移 frontend, 整合 Vuetify, 建立 backend Laravel 與容器執行環境
- [x] 遷移業務規則及隔離 API, Laravel 7 測試／94 斷言通過, 包含舊 SQLite 匯入與 Node 密碼升級
- [x] 更新 README／Compose／指引, 驗證 build、後端與瀏覽器流程後分階段提交

實際驗證：原 Node 基準 16 tests 全過, Laravel 最終 7 tests／94 assertions 全過, frontend build 全過；完整瀏覽器回歸改接隔離 Laravel, 雙帳號／Epic／狀態／工時／通知預覽／依賴／視圖／版本衝突／手機版全部通過。新隔離 Compose tasktracker-commit-check 的 frontend／backend／Reverb／Redis 均 healthy, queue 運作；infrastructure 指令確認 Redis 連線、工作入列與消費、Reverb 101 升級與 connection_established。映像檢查不包含 .env／資料庫／Git

早期 frontend 單檔 bind mount 在 Docker Desktop 出錯, 移除 index／Vite config 單檔掛載並在 README 記錄這兩類變更需重建, 保留 src 熱更新。自動審核拒絕整個 server／tests 目錄刪除, 後續核對無修改／無未追蹤檔, 改用明確 9 個受追蹤檔案 git rm, 已安全完成。主機 PHP 仍未變動。原始 data、舊 Docker volume 與使用者空白 docs/架構.md 保留

提交：84fe1da（前端）、adff643（Laravel）、7 項後端測試與完整 E2E 完成後提交 Docker／文件。規劃文件沿共享 Git skill 留工作樹, 未推送遠端

## 2026-10-19：實作成熟度與面試技術選型

- [x] 核對 main／既有差異, 閱讀最新產品文件與原生 HTTP／SQLite 儲存實作
- [x] 查閱 Laravel 授權／Sanctum／Queue／Migration、Node 與 NestJS、GAS 配額官方文件
- [x] 整理可開始範圍、待定契約與面試選型理由, 檢查文件後提交, 不改應用程式

產品紀錄補上三份最小契約、分階段實作與完整展示流程, 說明目前 records JSON 儲存及共享專案尚未滿足目標。Laravel 建議依既有經驗與近期完成作品的目標, 未視為使用者已選定；保留 Node 原有規則可延用、Laravel 需重寫 JS domain 及 GAS 主方向仍待確認的成本。相對連結、code fence、19 個問題 ID 與 git diff --check 通過, 純文件工作不執行應用測試

## 2026-10-19：重申權限是授權依據

- [x] 核對原 repository／main／既有差異與最新產品決策
- [x] 明確角色→權限→資源授權關係, 修正角色名稱捷徑的歧義
- [x] 加入持續指引及驗收條件, 檢查文件後提交, 不改程式

強化 D-015, policy 依授予來源與 scope 判斷, 不在內部再使用 role === user／manager 捷徑。補上相同有效權限／scope／資源條件應有相同結果的驗收, 將持續原則寫入 AGENTS.md。文件相對連結、code fence 與 diff 檢查通過, 僅文件提交, 未修改或宣稱原型已符合新政策

## 2026-10-19：專案四角色與責任歸屬

- [x] 核對 main／既有差異, 讀取前輪角色提案
- [x] 記錄使用者偏好的四角色、負責人交接、PM 與讀者的用途及邊界
- [x] 更新提案參照與待討論問題, 檢查文件後提交, 保留既有差異

產品紀錄以負責人／PM／開發者／讀者作最新候選, 舊三角色留沿革, 同步更新契約示例參照。區分 Project 責任與 Workspace owner／Issue assignee, 讀者的進度／工時彙總／逐筆明細／匯出分開權限提案；四角色完整矩陣、交接與角色 ID 不標已確認。文件連結、code fence 與 diff 檢查通過, 僅提交討論文件, 未改應用或執行功能測試

## 2026-10-19：推薦專案角色

- [x] 核對原 repository／main 與既有差異, 讀取最新角色決策
- [x] 整理專案三組固定角色、基本操作矩陣與作用範圍, 明確標待確認
- [x] 更新 Q-005 與沿革, 檢查文件後只提交產品紀錄, 不修改其他使用者差異或程式

## 2026-10-19：確定 Workspace 預設角色

- [x] 確認原 repository／main／既有差異, 讀取產品紀錄與授權提案
- [x] 更新 owner／manager／user 與全專案操作／指定成員門檻, 清理衝突提案
- [x] 同步相關設計文件並驗證後提交, 不修改現有資料與應用程式

使用者比較空間內可見後明確選擇指定 Project 成員＋授權, 並採納按權限而非角色名稱判斷的方向。產品紀錄整理為單一最新規則, 舊可見性／角色提案改列沿革；集中授權示例移除 visibility 讀取授予, owner／manager 擴成全專案操作, 不誤留僅讀取。相對連結、文件格式與 diff 檢查通過, 本輪只提交三份設計文件, 不跑應用測試或修改共享資料

## 2026-10-19：授權判斷契約

- [x] 核對 main／既有差異, 盤點 auth.invite、SMTP、checkAdmin、通知、視圖作者與工時作者的授權
- [x] 明確整理集中授權 API、固定角色與權限映射、資源範圍及作者條件, 標清提案與現況
- [x] 記錄遷移與驗收、同步文件並檢查後提交, 不改原型行為

產品紀錄新增 D-012 與集中授權契約：roleId／label／permissionId 分離、範圍內權限映射、可信 context／resource、基礎可見性與寫入區分、作者條件、交易與重試結果也重新授權。列出目前角色判斷位置、SMTP 部署級範圍、遷移順序與驗收情境。核對 NIST RBAC 與 OWASP 授權指引, 明確區分來源原則及本專案提案, 相對連結與 diff 檢查通過。純文件工作未執行應用測試, 只提交三份設計文件, 不改使用者既有差異

## 2026-10-19：固定／自訂角色

- [x] 核對原專案 main 與既有差異, 讀取產品紀錄
- [x] 整理兩層固定角色的第一版建議、用途、授權邊界與擴充條件, 保留待確認
- [x] 更新 Q-005／沿革, 檢查連結與 diff, 本輪僅文件提交, 沒有應用變更

## 2026-10-19：Workspace／專案兩層權限

- [x] 核對 main 與保留差異, 讀取產品紀錄與協作設計
- [x] 記錄個別專案可見性與 admin 查看全專案的方向, 舊建議保留沿革並更新適用範圍
- [x] 補上角色／可見性區分、空間邊界、授權順序、相關資料與 API、預設／遷移待確認, 更新現況差距
- [x] 檢查設計文件連結與 diff, 只提交兩份文件, 不執行應用測試或修改共享資料

## 2026-10-19：SaaS／自建版交付方向

- [x] 核對 main 與既有差異, 讀取產品紀錄及架構提案
- [x] 加入 D-009、兩種部署比較提案與 Q-012, 分清目標與未定規格
- [x] 同步架構文件, 檢查連結／diff 後只提交產品文件, 未改程式、GAS 或 Docker 設定

## 2026-10-19：流程複雜度討論

- [x] 核對原專案 main／既有差異與產品紀錄
- [x] 加入模型合理性與第一版簡化建議, 保留待確認標記, 不新增產品決策
- [x] 檢查文件差異與連結, 本輪僅提交產品紀錄, 不修改應用程式

## 2026-10-19：邀請權限

- [x] 確認原專案 main 與既有差異, 讀取產品紀錄及協作設計
- [x] 新增 D-008、邀請權限說明與沿革, Q-005／Q-011 保留角色與空間政策待確認
- [x] 同步協作文件、檢查相對連結與 diff, 僅文件提交, 不改原型行為

## 2026-10-19：工作流程層級

- [x] 在原專案核對 main／既有差異, 讀取產品討論紀錄
- [x] 將 Workspace → Project → Issue 寫為已確認, 保留註冊／邀請及個人方案的待確認狀態
- [x] 同步協作設計入口、檢查文件連結與 diff, 只提交兩份設計文件, 無程式變更

## 2026-10-19：持續產品討論紀錄

- [x] 讀取 Git 工作位置與專案規則, 確認 main 與先前保留差異, 不修改使用者的 docs/架構.md
- [x] 建立單一產品討論紀錄, 分清已確認、提案、目前實作與待討論
- [x] 補上後續同步規則, 驗證文件並建立本機文件 commit

新增 docs/產品討論紀錄.md, 回收帳號／企業邀請／個人工作空間的兩輪討論, 建立確認方向、建議流程、現況差距、待討論與日期沿革。依 server/auth.js 核對仍只支援新帳號受邀與單空間登入；個人功能方案、多空間切換、owner 角色等保留為建議, 沒有冒充使用者已採納或功能已完成

AGENTS.md 加入持續同步規則與入口。相對連結、換行與 git diff --check 通過, docs/架構.md 保持原空白內容；純文件變更不執行應用測試。本次只提交產品討論紀錄與必要開發指引, 原 README／planning 差異及使用者架構檔保留, 不推送遠端

## 2026-10-19：Docker Compose

- [x] 讀共享 Git 工作位置規則, 確認原 repository／main／乾淨工作區, 盤點 npm 啟動與 API Host／Origin 邊界
- [x] 建立 Compose、開發映像、容器網路設定與 README
- [x] 驗證 config／映像／持久化及既有 API, 記錄限制並提交

新增 Dockerfile（Node 24 Debian slim、非 root node 使用者）、白名單 .dockerignore、frontend／api Compose。API 由 API_HOST 選擇容器監聽, 預設原生環境仍為 loopback；Vite 的 API_PROXY_TARGET 保留瀏覽器 Host, 沒有放寬 Host 檢查。TASKTRACKER_PORT 同步對外埠與 API Origin allowlist, named volume 與主機原 data 分開

驗證：

- Docker Compose v2.15.1 config --quiet 通過, 兩個 image 真實建置完成
- 主機 Node 測試 16／16 與 build 通過；Node 24 容器內測試 16／16 與 build 亦通過
- 隔離 project `tasktracker-compose-check`、15174 埠啟動, 兩服務 healthy, 只對主機 127.0.0.1 暴露前端埠
- 真實 HTTP 經 Vite → API 驗證健康、登入 cookie、專案新增、任務新增／修改與 Origin 拒絕
- force-recreate 重建兩容器後, 登入同一帳號可讀到原專案、已完成任務與 revision 2, named volume 持久化通過
- 容器 image 不含 .env.local／.git／AGENTS.md, 主機 node_modules 未掛載
- 測試 project 與 volume 驗證後移除, 未啟動使用者正式 Compose project 或寫入原資料庫
- 未實測正式郵件或 GAS, Compose 是本機開發配置。README 補上啟停、重建、改埠、SMTP 與資料保存說明

本輪採共享 Git skill 的提交範圍：功能檔案建立一個本機 commit；README 與 plan／step 的文件修改留在工作區, 不納入功能 commit, 不推送遠端

## 2026-10-06：日常工作流程補充

- [x] 確認前輪三階段 commit 與乾淨工作區, 選定依賴關係及常用視圖
- [x] 任務依賴、Epic／工時上下文, 驗證並提交
- [x] 常用篩選與個人／團隊視圖, 驗證並提交

依賴階段：`npm test` 13／13、build 與隔離瀏覽器完整流程通過。驗證依賴選擇／反向關係、循環拒絕、完成限制、取消不解鎖、前置重開提醒及清單 Epic／2.5h 工時；原本雙帳號與手機流程保留, 無頁面錯誤。截圖 `artifacts/issue-dependencies.png`, 不提交截圖或試用資料。前置限同專案執行 Issue, 不做跨專案或排程。提交 `dbbb871`

常用視圖階段：新增結構化 AND 篩選、成員／Epic／類型／工作情況, 可存個人或團隊視圖。API 依作者檢查私有可見性與修改權, 保存版本、重試與封存；深連結帶 view ID, 關閉任務保留視圖。已移除狀態保留原條件與提示, 「我的任務」依閱覽者, 導覽切換清除進階篩選

`npm test` 16／16 通過, 含私有／團隊可見性、跨工作空間 ID、作者權限、視圖版本／移除重試、過期選項及篩選語意；build 與隔離雙帳號瀏覽器通過：建立／更新／移除、私有連結不洩漏、團隊 mine 依人計算、重新整理／任務上下文、失效狀態提示、手機無溢出及前輪所有流程。截圖 `artifacts/saved-views.png`、`team-view.png`、`mobile-views.png`, 不提交截圖或測試資料。只做本機 commit, 不推送、產 GAS 或寄真實郵件

## 2026-10-06：協作階段

- [x] 確認工作區乾淨、使用者 SaaS 補充, 重新查證官方競品與郵件文件
- [x] 設計 Epic、狀態、工時、帳號／工作空間隔離與 SMTP adapter, 交付協作設計文件
- [x] 資料／API 與安全邊界實作、驗證、提交
- [x] 畫面與雙使用者／手機驗證、更新使用文件、提交

資料階段：新增本機 scrypt 帳號、HttpOnly session、工作空間隔離與七日單次邀請；第一位工作空間承接舊資料, 後續註冊空間為空。Issue 支援 Epic、估時與成員 ID, 專案支援自訂狀態與設定 revision；工時綁 session 身分, 有修正／作廢歷史。指派與留言產生預覽通知, SMTP 僅 verify, 不寄信

`npm test` 9／9 通過, 包含真實 HTTP session、跨工作空間 ID 拒絕、成員越權、重試通知防重複、Epic 完成／重開、狀態刪除保護、工時修正／加總及 SMTP 測試 transport 不寄信。HTTP 測試需要 loopback listener, 以 sandbox escalation 執行。此階段畫面尚未接新登入 API, 下一階段完成

畫面階段：登入／新空間、成員邀請、Epic 卡片與子任務連結、專案狀態設定、工時月曆／明細／成員與 Epic 彙總、通知預覽與 SMTP 設定狀態都已接上。清單與看板只呈現執行 Issue, Epic 獨立顯示。保留開始編輯的 revision, 防止背景更新後舊表單覆蓋同伴；登出也排除尚未完成的舊查詢回應

驗證結果：

- `npm test` 10／10 通過, 新增舊單人資料承接、原始資料保留與邀請／session 到期檢查
- `npm run build` 通過, 只有本機前端產物, repo 沒有 `.gs`
- 隔離瀏覽器流程通過：兩個獨立帳號邀請加入同空間、一般成員狀態唯讀、Epic 關聯／完成限制／重開提醒、自訂欄位看板、個人工時修正與團隊 2.5h 彙總、通知預覽及未設定 SMTP 提示
- 使用同帳號另一分頁的可控 clock, 驗證 30 秒背景刷新後保留本機草稿、舊 revision 寫入遭拒, 重載取得同伴最新內容
- 舊專案首頁／切換／深連結／返回、搜尋、拖曳、留言、未保存提醒、文字安全顯示與 390px 手機流程通過, 無頁面執行錯誤
- 桌面檢視：`artifacts/epic-overview.png`、`time-calendar.png`、`notifications.png`；手機：`mobile-time-calendar.png`, 截圖與隔離資料庫不提交
- 實際 SMTP 未配置／實寄, verify 以注入 transport 測試 TLS、清理及不寄信, 不宣稱真實郵件送達
- 原試用資料庫未被測試寫入；本機前後端仍在 5174／3002 運作, 等使用者自行建立第一位帳號才承接舊資料

已知界線：本機 HTTP／loopback、一帳號一空間、邀請限新帳號、空間內專案／工時共享, Project ACL、正式身份復原、背景信件寄送及 GAS 尚未實作。正式 SaaS 所需設計與取捨都記入 `docs/collaboration-design.md`

本輪提交：`ac609e9` 規劃、`35eb472` 資料／API, 畫面階段另建第三筆 commit；不推送遠端

日期：2026-10-05（Asia/Taipei）

## 2026-10-06：階段提交

- [x] 確認目前尚無 commit, 所有待提交內容為本次專案既有成果
- [x] 提交調研與開發規劃文件
- [x] 驗證並提交本機任務管理原型, 包含專案列表入口
- [x] 寫入每階段 commit 的規範並提交, 核對最終 log 與工作區

提交前 `npm test` 5／5 通過、`npm run build` 通過, 原型瀏覽器驗證沿用專案入口調整時通過的結果。三個階段只提交本機歷史, 不推送遠端, 資料庫、截圖與編譯產物均排除

本文件為調研階段的完成快照, 後續已完成本機原型, 目前狀態見 [原型工作紀錄](prototype-step.md)

## 2026-10-06：專案入口調整

- [x] 檢查現有檔案、導覽方式與未追蹤變更, 記錄使用者需求
- [x] 建立專案列表首頁, 任務工具移入專案上下文
- [x] 更新深連結、返回、建立任務的專案限制
- [x] 驗證瀏覽器流程與編譯, 更新開發文件

驗證結果：

- `npm run build` 通過, 本次只編譯本機前端
- 隔離瀏覽器操作檢查通過：首頁沒有任務清單／看板／任務新增入口, 建立專案後停留列表, 點入才顯示該專案內容
- 驗證兩個專案的任務隔離、固定建立專案欄位、專案搜尋、重新整理、瀏覽器返回、任務深連結與舊連結相容
- 既有搜尋、拖曳、留言／歷史、未儲存提醒與手機流程通過, 無頁面執行錯誤
- 桌面專案列表截圖：`artifacts/desktop-projects.png`；手機專案列表：`artifacts/mobile-projects.png`
- 原試用資料庫未寫入或遷移, API 與資料模型未修改

- [x] 檢查 main 分支與空工作區, 讀取上層 AGENTS.md 及工作區 skill 描述
- [x] 讀取 GuildManager 的 AGENTS.md、Architecture.md、README.md、package.json
- [x] 核對 Vite 單檔設定、GAS 打包工具、RPC adapter、入口、manifest 與 Sheets store
- [x] 建立 TaskTracker AGENTS.md, 分開使用者已確認方向與架構建議
- [x] 建立本次規劃與續接紀錄
- [x] 完成競品調研、架構提案與 README
- [x] 驗證文件連結、來源與工作區差異

## 驗證結果

- 本次共新增 6 份 Markdown 文件, 檢查相對文件連結、結尾換行、空白與 code fence 配對
- 調研使用競品官方文件, GAS 行為使用 Google 官方文件查證, 來源直接附在相關段落
- 架構提案與 GuildManager 的 adapter、打包工具及 Sheets store 對照, 未修改參考專案
- 工作區只包含本次新增文件, 沒有應用程式、憑證或編譯產物

## 已知限制

- GuildManager 包含 Vue／Vuetify、本機 Express＋SQLite、GAS＋Sheets 架構, TaskTracker 尚無應用程式
- 本次只有文件, 沒有執行 build、測試、雲端初始化、上傳或部署
- GuildManager 使用提交日誌與分塊 payload 保存 Sheets 資料；TaskTracker 尚未選定儲存實作
- 未沿用參考專案的自動 commit、帳密、Script ID、試算表 ID 或部署授權

## 下一步

收斂第一版主要操作與登入情境, 再建立本機骨架及隔離的 GAS 讀寫驗證

本輪交付 commits：bf3459d（後端關係與升級）、145e173（介面與 E2E）、8ff6dd2（指定 Seed 與測試隔離）、ada4cdc（產品文件／README／開發規範）。最終只有 planning 差異保留。指定密碼檢查改以 NUL 分隔路徑處理中文檔名後通過, 所有 tracked 檔案均無該明碼。


## 2026-11-11：Multi-Agent 使用回饋交接

- [x] 核對現行 routes、session／CSRF client、RequestReplay、Project ACL 與 Reverb 文件。
- [x] 以使用者授權的真實專案操作驗證現有 RPC，沒有直接寫資料庫。
- [x] 新增 `Multi-Agent-To-Tracker.md`，區分可用能力、正式整合缺口與尚未定案的方案。
- [x] 同步產品討論紀錄；未修改 TaskTracker 功能、依賴或授權。
- [x] 檢查文件及秘密排除；純文件交接不重跑應用 Build／測試。


## 2026-11-12：TT-258 狀態篩選崩潰

- [x] 查重無既有卡，建立TT-258（bug／urgent）與Bug看板，並收錄實際功能看板。
- [x] claude帳號領卡；隔離API（13029）＋真實Chrome重現Target crashed，最小HTML確認為focusout同步關閉details所致。
- [x] 修正IssueFilters.vue關閉邏輯；新增回歸檢查，修正前失敗／修正後headless、headed通過；npm run build通過。
- [ ] 限制：完整browser-check因共用隔離庫已有固定josh@example.test帳號，在註冊步驟即停止，未重置他人使用中的隔離庫；已人工核對其狀態篩選步驟與新關閉行為相容。

## 2026-11-12 TT-171 外部識別與upsert（Claude）

- [x] 使用者改派：TT-262／246交Codex（已釋出），Claude做AI整合/API；建立TT-263給Josh的白話備忘。
- [x] 設計確認：已綁定時upsert必須帶expectedRevision；主庫先備份再migrate。
- [x] 實作issue_external_refs、Repository／Service／Controller／Request，OpenAPI0.6.0與契約／Agent指南。
- [x] 隔離MySQL完整203通過、官方OpenAPI結構檢查通過。
- [x] 契約CI完整通過（Chrome路徑）；主庫備份（私有.local）→migrate只新增空表、35表筆數一致；重建backend／queue／websocket，主站OpenAPI0.6.0。

## 2026-11-12 TT-172 批次匯入（Claude）

- [x] 決策採建議：逐項savepoint、整批receipt、epicRef／parentRef、createOnly、上限50。
- [x] Request／Service／Repository／路由、OpenAPI0.7.0、契約／Agent指南／docs/public-external-batch.md。
- [x] 隔離MySQL完整207通過、OpenAPI結構檢查、Pint。
- [x] 契約CI完整通過；重建backend／queue／websocket，主站OpenAPI0.7.0，主容器memory146通過；無migration。

## 2026-11-12 TT-173 同步語意（Claude）

- [x] 查核：無刪除／封存操作；syncUntil=now有較晚提交遺漏風險。
- [x] syncUntil settle緩衝、issue-changes附externalRefs、契約同步語意章節、OpenAPI0.8.0、docs/public-sync-semantics.md。
- [x] 隔離MySQL完整209通過。
- [x] 契約CI完整通過；重建backend／queue／websocket，主站OpenAPI0.8.0，主容器memory147通過；無migration。

## 2026-11-12 TT-176 來源參照與回音（Claude）

- [x] X-Client-Reference、稽核resource_revision／client_reference、lastWrite回音辨識、批次逐項稽核、OpenAPI0.9.0、文件。
- [x] 隔離MySQL完整211通過、OpenAPI結構檢查、Pint。
- [x] 契約CI完整通過；主庫私有備份→migrate只改migrations筆數；重建後主站OpenAPI0.9.0、主容器memory148通過。
