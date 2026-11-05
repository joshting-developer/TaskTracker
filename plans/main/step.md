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
