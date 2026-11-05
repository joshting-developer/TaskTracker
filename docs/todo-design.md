# 首頁小待辦設計

建立：2026-11-02／更新：2026-11-05（Asia/Taipei）

## 使用者已確認

- 有些溝通速記很小, 需要追蹤完成, 但不值得開 Project Issue
- 可自己速記, Manager 可交辦；也希望成員彼此及成員交給 Manager／Owner
- 在自己的首頁顯示, 有通知回饋
- 他人交辦 **直接加入對方待辦, 可退回給交辦者**, 不先走接受流程
- 2026-11-05：速記改為一整塊 Markdown，不另填標題及補充說明；卡片描述／留言也共用編輯方式
- 2026-11-05：列首勾選容易被誤認成批次選取，完成操作改放「更多」下方，以文字動作呈現
- 2026-11-05：更多改為浮動選單，不撐高待辦列或頁面；移除個人速記「自己記下的」，保留交辦來源與處理狀態

## 第一版實作選擇

小待辦獨立於 Issue, 不要求先選專案。「我的待辦」列出目前由我處理的項目, 「我交辦的」追蹤我交給別人的項目；已完成／已取消／已轉卡預設收合。寬螢幕與專案入口並列，窄螢幕保留「記小事」直達入口。直接輸入一整塊 Markdown，Enter 換行，按加入待辦／交辦才保存；不要求狀態、優先級或估時。首行自動產生摘要，展開閱讀全文，編輯時保留原稿。原先一行 Enter 新增＋選填說明的方案自2026-11-05被整塊速記取代。

角色只映射 permission：有效 Workspace 成員預設都有 `todo.read`、`todo.writeOwn`、`todo.assign`。成員沒有加入任何 Project 也可使用, 但不因此獲得 Project／Issue 的存取權。交辦選單另由後端提供同空間有效成員的最小目錄（ID、姓名、Email）, 不擴張原 `getWorkspace.members` 或回傳專案資料

待辦僅建立者及目前承辦者可讀, Workspace 管理者沒有讀取他人私人待辦的捷徑。建立者可編輯未完成內容／取消；目前承辦者可完成／重新開啟自己的項目, 未完成且承辦者不是建立者時可退回。退回後承辦者改為原建立者, 狀態仍為未完成, 留存退回事件並通知建立者；不自動再次交辦, 本版也不提供改派或群發

整筆待辦的狀態操作採「更多 → 標記為已完成」／「更多 → 重新開啟」，不使用列首checkbox。已完成項目仍以刪除線及狀態文字辨識；取消／轉卡項目不提供重開。依後端capabilities決定能否操作，處理中停用按鈕，同筆草稿編輯時隱藏更多操作，需先儲存或取消修改。Markdown內的唯讀核取清單維持原本顯示，與整筆待辦完成分開。

資料採 `open`／`done`／`canceled`／`converted`, 固定 ID、revision、requestId。交辦者／承辦者、狀態、版本與有效 Workspace 成員資格於同交易檢查；requestId 重試先重新驗證資格, 回傳只含 ID／版本, 不重播私有文字。取消留存歷史, 不刪除資料。前端輪詢不能替換已開編輯表單的基準版本, 衝突保留草稿並提示重載

## 通知

提供專用站內待辦通知入口／未讀數, 包含收到交辦、內容更新、完成、重新開啟、退回、取消及轉卡；自己的操作不通知自己。通知寫入與待辦更新同交易, 重試不重複發生。讀取時重新檢查目前待辦存取資格, 不保留可繞過資格的內容快照；已讀只可由收件人設定。跨專案頁也可查看待辦通知, 點擊回首頁定位有權查看的待辦

第一版使用目前 30 秒輪詢與手動刷新, 不宣稱 WebSocket 即時推播。站內通知不呼叫 SMTP、不發送真實郵件或瀏覽器推播

## 邊界與後續

- 目前只在選定 Workspace 內互相交辦；帳號的跨 Workspace 個人總覽仍待多空間流程
- 使用者追加確認：提供「轉成正式卡片」, 開啟側邊表單帶入待辦標題／說明, 補上所屬 Project、類型、負責人等欄位。只做待辦→Issue, 不提供 Issue→待辦接口
- 本輪選擇由目前承辦者轉換, 目標須有 issue.write；建立 Issue 與標記待辦 converted 同交易, 重試不重複建卡。待辦留存且可於有 Issue 讀取權時連至正式卡片；原交辦者若沒 Project 權限, 僅看到已轉卡狀態, 不回傳該 Project／Issue 的 ID 或名稱
- 自訂待辦狀態、期限／重複提醒、評論串、群組交辦、私訊及跨空間交辦暫緩
- 不自動解析輸入中的卡號／連結為私人 Issue 預覽；手動填入文字不構成 Project 授權
- 與 2026-10-20 暫緩的「首頁既有卡片篩選小清單」分開：這次是新增輕量待辦資料, 不恢復儲存視圖
- 管理者能看全專案不等於能看全員待辦。此隱私選擇、作者與承辦者操作分工是本輪試作方案, 可依試用調整, 非使用者逐項指定

## API 與保存

Project ACL 先更新再載入個人待辦；即使待辦讀取暫時失敗, 仍先清除已失去資格的專案內容。

資料沿用 Workspace RecordStore namespace, 新增 `todos`／`todoNotifications`／`todoRequests`, 不改寫既有 Project、Issue 或舊郵件預覽。API 由 Laravel session 決定操作者與 Workspace, 不接收前端自稱的作者或角色

| RPC | 主要輸入 | 回傳／條件 |
| --- | --- | --- |
| getTodos | 無 | 僅本人參與的待辦、本人可讀通知、最小交辦成員目錄, 待辦各附 capabilities |
| createTodo | content、assigneeId 預設自己、requestId；相容舊 title／note | id、revision；交給別人另需 todo.assign |
| updateTodo | id、content、expectedRevision、requestId；相容舊 title／note | 原建立者, 僅未完成 |
| completeTodo／reopenTodo | id、expectedRevision、requestId | 目前承辦者, 分別 open→done／done→open |
| returnTodo | id、expectedRevision、requestId | 非原作者的目前承辦者, 僅未完成；改由原作者承辦 |
| cancelTodo | id、expectedRevision、requestId | 原建立者, 僅未完成；留歷史不刪除 |
| convertTodo | id、expectedRevision、issue（正式建卡欄位）、requestId | 目前承辦者＋目標 Project issue.write；回 id／revision, 不快取新卡內容 |
| readTodoNotification | id、requestId | 收件人且仍可讀來源待辦, 僅設定本人 readAt |

轉卡的專案候選、狀態、成員、Epic／父子／關聯與正式建立 Issue 使用相同來源及後端驗證。轉卡失敗回滾 Issue、編號、通知、待辦狀態及去重紀錄；成功後來源 `converted` 不再有完成／重開／再次轉卡操作。原待辦不自動跟隨正式卡片後續狀態, 保留原小事的文字與歷程

2026-11-05新增 `todos.content` TEXT 保存完整原稿，`title` 為180字摘要、`note` 保留舊格式相容。轉卡預設以原稿填入描述；舊待辦不自動改寫，首次以新編輯器保存才合併內容。工具列、長度、安全呈現及升降版保護見 [Markdown編輯](markdown-editing.md)。

## 驗收

1. 未加入 Project 的成員仍可快速自記、修改，從更多標記完成及重開，不改變專案可見性
2. 任意角色可交給同空間成員／Manager／Owner, 接收者立即看到；可退回原作者並產生單一通知
3. 第三人（含 Workspace owner）及外部空間不能讀寫此待辦／通知, 不能偽造作者或承辦者
4. 版本衝突保留輸入, 重試不新增重複待辦／通知；失效成員及返還後舊承辦者的 requestId 不繞過資格
5. 通知未讀／已讀僅影響本人, 導航與手機版可用, 已有 Project ACL／任務流程不退化
6. 轉卡可補正式欄位, 未授權 Project 不可選且 API 拒絕；失敗保留待辦且不留新卡, 重試只產一張卡, 原交辦者不因轉卡看到私有專案資訊

## 本輪驗證結果

下列為2026-11-02初版紀錄；2026-11-05 Markdown 升級的42項後端測試、瀏覽器回歸及本機資料保護驗收見 [Markdown編輯](markdown-editing.md#驗證與進度)。

- 隔離 Laravel／記憶體 SQLite：30 項測試、700 個斷言通過, 含無 Project 使用、跨空間／第三人拒絕、作者與承辦者限制、版本、去重、資格失效、轉卡交易回滾／單向狀態、私有 Project 連結過濾
- 真實隔離 Laravel＋Chromium：完整舊流程與新增待辦流程通過。涵蓋快記／純文字、編輯與背景刷新衝突、勾選／重開、Member→Owner 直接交辦及退回、通知已讀／定位、Project 資格變化、轉卡帶入／補欄位／衝突、草稿離開保護及手機版
- 前端 build、文件相對連結及 Git diff 空白檢查通過；桌面／手機截圖已檢視, 產物不提交
- 本機四 Seed 帳號的待辦 API 可讀, 原 Project 範圍仍為 owner／manager／maintainer→first＋second、developer→first。唯讀檢查前後業務 records 完全一致, 無 schema migration／Seed reset, 本機服務維持正常
- 本輪沒有 SMTP 寄信、瀏覽器推播或 Reverb 業務事件；30 秒輪詢的更新延遲仍存在
