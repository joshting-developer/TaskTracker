# TT-340 進度

- 已完整讀原紙條與交接, 確認需求為邀請Email及待接受清單；TT-339轉待驗收後才領取
- 已盤點現有AccountService邀請只存hash並回連結, MailDelivery只允許已註冊收件者；會重用加密outbox而非另起寄信系統
- 未接受者列在設定頁待接受區, 不偽造已加入資格或查全平台帳號姓名；同Email、requestId重試與寄前資格一起處理

- 初輪舊會員／寄信／啟用45項通過；新邀請測試SQLite 5項過, MySQL因持久測試schema尚未套新migration而失敗, 不是已完成雙引擎驗收。主庫已套用這支增量（259.67ms）, 無seed／資料修改；已補套Codex隔離schema後重測

- 新邀請雙引擎10項／135斷言通過, 包含新地址寄信、加密receipt／outbox、同key重播與異payload409、撤權先於重播、寄前撤權／接受取消、分頁範圍及既有帳號接受
- 發現MySQL同秒時間精度與舊RecordStore ISO契約差異, Repository改用既有DatabaseTimes轉換並讓舊連結明確到期；不改全域時間規範
- 真瀏覽器邀請即列待接受、接受後移到成員、普通成員403、回應遺失同requestId重試、草稿保護與三尺寸通過。Session OpenAPI 1.3.0官方3.1離線Schema通過
- 邀請UI獨立WorkspaceInvitationManager, 設定頁只協調分區與離頁保護；審美lint 0 FAIL／0 WARN
- 最新後端完整428項／18127斷言, 1項既有skip, 其餘通過；正在跑61支完整前端檢查, 不與同DB後端測試並行
- 第一輪視覺流程在新增邀請清單前仍有未清空的回覆草稿, 離頁保護擋下測試導向；已修fixture先清空並關抽屜, 不略過產品草稿保護
- 檢視新增成員清單畫面發現fixture的隨機Email及實際日期會讓後續比較不穩定, 已在既有嚴格隔離CLI新增該人造工作區限定的正規化, 只在這個新畫面前使用, 不以遮罩或放寬門檻忽略產品資料
- 修正手動重新載入確認捨棄邀請草稿後也要清空新子元件狀態, 保留原設定頁語意
- 正式比較第一次87張中的desktop紙條抽屜背景排序不同, 已檢視diff確認為相同fixture時間＋隨機公開ID造成的順序差異；隔離CLI改用該工作區至多100筆內部建立順序配不同秒數, 不改產品排序或比較容忍
- 第二輪審閱補上設定頁儲存角色時邀請表單應同樣禁用, 避免拆元件後多寫入同時送出；最後畫面與專項會重驗這個狀態
- 全部61支瀏覽器已執行：第一輪49通過；本卡按鈕改名影響account-settings已同步選取器, 最後單獨重跑通過。累計50通過（含重跑）、7環境限制、4既有失敗／時序不穩定, 不宣稱全套通過
- 其餘4項：browser-check的已儲存status匹配2個元素、notes-page在完成寫入收尾前Escape造成抽屜未關、personal-query未等歷史請求即斷言、uiux的既有12px門檻；將回填TT-336／TT-334, 不因這張卡改非邀請流程
- 最後冒煙與設定頁、邀請專項均通過, 邀請專項包含角色寫入時禁用表單；WorkspaceSettings手動載入確認捨棄後正確清空子元件草稿
- 最終87張正式視覺比較通過, approved／environmentMatches均true, 已保存舊基準及中間候選, 比較器／容忍設定不變
- 本人正式投入35分鐘已填, 不計中斷、無人等待或其他Agent投入；卡片待提交後轉review, 請Josh確認實際邀請流程

- 依Josh退回續做：邀請連結直接驗證新帳號的指定信箱, 不寄第二封啟用信；前端鎖信箱及雙密碼。沿用90分鐘估時, 本階段另外填工時
- 定向44項505斷言通過；完整430項18155斷言, 1項既有skip其餘通過。新帳號驗證時間透過既有Repository保存, 不加入User可由輸入填寫的欄位
- 視覺新增邀請註冊畫面第一次因同一login路徑只換hash沒有重新建立AuthGate而失敗, 已改測試導覽成新的document, 不放寬斷言。失敗候選不核准, 正在重拍
- 完整前端61支初輪51過, 7支環境前置不足, browser-check編輯器關閉攔截／workspace-switch舊邀請fixture／uiux 12px失敗；沒有宣稱全套通過
- workspace-switch除了雙密碼需同步, 還有使用過邀請的既有成員登入重試, 已保留後端冪等路徑；該檔使用build預覽, 需先重新build再重跑, 不能拿舊dist判斷現碼
- Python全域沒有jsonschema, 已找到專案私有openapi-validation環境完成Session OpenAPI1.4.0離線官方Schema驗證；沒有另裝套件

- 最終workspace-switch、邀請新帳號／既有帳號／Email唯讀／密碼不符不送出／三尺寸Chrome及冒煙通過；rawRegistration定向44項505斷言通過。90張正式視覺比較通過, 門檻不變
- 全批61支連同workspace-switch重跑後52過、7環境受阻、browser-check編輯器關閉與TT-334字級2項未解, 未宣稱全庫通過。日誌artifacts/tt340/followup-browser-suite；不新增SMTP診斷信或修改現存帳號
