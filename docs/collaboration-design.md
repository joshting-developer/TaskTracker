# Epic、工作流程與多人協作設計

2026-10-20 最新類型決策：目前固定 Epic／Task／Bug, 舊 Feature 轉 Task；自訂類型只記未來方向。下方早期 Feature 敘述屬沿革, 現行以[產品討論紀錄](產品討論紀錄.md) D-022 為準

2026-10-20 更新：使用者已確認子任務與雙向關聯, 前置依賴取消。Task／Bug／Feature 可有一層子任務, 各自狀態／工時、繼承父項 Epic；一般父項不自動加上子項工時, Epic 仍按實際 Issue 各計一次。下方早期「子任務」多指 Epic 的執行項目, 新的一般父子關係及升級契約見[日常流程](workflow-design.md)

2026-10-19 最新實作：原始碼已拆分為 `frontend/`（Vue＋Vuetify）與 `backend/`（Laravel JSON API）, 不用 Inertia, Node API 由 PHP 取代。Compose 提供 API／前端／Queue／Reverb／Redis, 詳見[根 README](../README.md)及[產品討論紀錄](產品討論紀錄.md)。下方 Node／GAS 舊提案及當時功能界線保留作歷史參考, 不覆蓋最新選型；Project ACL 與真實寄信仍待後續完成

調研日期：2026-10-06, 產品定位依使用者補充採 SaaS 服務, 不限定 Google 帳號或網域

## 本次方向

2026-10-19 使用者確認主要流程為：工作空間 → 建立／選擇專案 → 在專案內開 Issue。專案裡有任務看板、Epic、工時與設定, 不要求使用甘特圖。成員邀請可按協作需要處理, 不要求先邀請才建立專案；帳號與個人／企業使用方案持續整理於 [產品討論紀錄](產品討論紀錄.md)

這份文件區分競品事實、建議規則與本機試作範圍。正式登入、帳單、部署規模仍待確認；本機確認前不產生 `.gs`

## 競品調研

| 主題 | 競品事實 | TaskTracker 建議 |
| --- | --- | --- |
| Epic 與 Issue | YouTrack 把 Epic、Story、Task 當不同 Issue 類型, 以父子連結形成層級, 可分管理與開發看板 | 第一版採 Epic → Task／Bug／Feature 兩層, 不加入 Story 強迫所有團隊走 Scrum |
| 自訂狀態 | YouTrack 可按專案管理欄位選項, State 值有 Resolved 標記 | 狀態保存固定 ID、名稱、顏色、順序與語意分類, 看板與完成分析共用資料 |
| 狀態流程 | OpenProject 依專案角色與工作項目類型限制可用狀態轉換 | 先支援自訂欄位與分類；之後加入角色×類型×來源／目標狀態矩陣 |
| 工時加總 | YouTrack 可同步父項目的預估與實際工時, 手動覆寫父項目估時會影響同步 | Epic 不直接填工時或估時, 顯示子項目加總, 避免重複計算 |
| 工時畫面 | OpenProject 有個人工時 Calendar／List, 日／週／月導覽、日期總時數與新增／編輯 | 先做月曆與明細, 每日顯示工時, 可切自己／成員／全團隊 |
| 工時報表 | YouTrack 提供 Timesheet, 以日期與使用者整理議題工時 | 成員、任務、Epic、日期分析, 同時顯示預估與實際, 不拿時數直接推定績效 |

來源：[YouTrack Epic 階層](https://www.jetbrains.com/help/youtrack/cloud/manage-epics-on-agile-board.html)、[專案自訂欄位與 Resolved](https://www.jetbrains.com/help/youtrack/cloud/manage-custom-fields-per-project.html)、[父子工時同步](https://www.jetbrains.com/help/youtrack/cloud/time-tracking-on-agile-board.html)、[報表](https://www.jetbrains.com/help/youtrack/cloud/youtrack-reports.html)、[OpenProject 工作流程](https://www.openproject.org/docs/system-admin-guide/manage-work-packages/work-package-types/workflows/)、[個人工時月曆](https://www.openproject.org/docs/user-guide/time-and-costs/my-time-tracking/)

上表為功能流程參考, 不代表所有版本／方案完全相同, 也不照搬競品的整套模型

## SaaS 與團隊邊界

- Account 是登入身分；Workspace 是資料與成員的租戶邊界；Project 屬於一個 Workspace
- 使用 email＋密碼試作本機登入, 正式可接 OIDC／其他供應商, 不以 Google 群組判斷會員
- 2026-10-19 已確認 Workspace 固定角色為 owner／manager／user：owner 是建立空間者, owner／manager 可操作該空間所有專案；user 必須被加入 Project 並授權, 才能查看或操作。第一版不提供空間內可見途徑, 取代先前 Admin／Member 與空間內可見／私人兩模式提案
- 邀請須有權限已確認；哪些角色可邀請、可授予哪些角色等細項待定。現有原型仍以 admin／member 運作、空間內專案與工時共享, 新角色與 Project ACL 尚未實作, 既有資料遷移需明定
- 已採納授權方向：角色映射 permission ID, 業務操作經集中 authorize 檢查資源 Workspace／Project 與操作條件。owner／manager 的全專案操作須展開為明確的範圍權限, 不限於讀取, 也不使用角色名稱全域放行。專案角色、資源作者例外與完整操作矩陣待細定, 詳見 [兩層權限紀錄](產品討論紀錄.md#workspace-與專案的兩層權限)
- 角色表描述預設權限組合, 可見性與操作一律由有效權限及範圍決定。registry 只把角色指派解析成權限, policy／API／畫面不得另以 owner、manager、PM、開發者、讀者等角色名放行；同權限、同 scope、同條件的授權結果必須一致
- 正式 SaaS 須支援一個 Account 加入多個 Workspace、切換空間、成員停用／撤銷、最後一位管理員保護與帳號復原
- 每次 API 操作由 session 推導 workspaceId／userId, 不接受前端傳入角色或租戶 ID 作授權
- 查詢、異動、關聯、工時、通知、重試紀錄都隔離 Workspace, 知道另一團隊的 ID 也不能讀寫
- session 使用 HttpOnly、SameSite cookie, 正式 HTTPS 才啟用 Secure；密碼以 scrypt＋隨機 salt 保存, 不進入前端或 Git
- 本機只監聽 loopback, 不視為可上線的 SaaS。正式需補驗證 email、限流持久化、密碼重設、session 管理、稽核、備份復原與監控

邀請試作：管理員填 email → 產生限時、單次邀請連結 → 被邀請者以該 email 註冊 → 加入同一工作空間。先複製連結試用, 不假裝已寄出邀請信

舊單人資料只歸入第一個建立的工作空間, 保留原始資料；之後新註冊的工作空間從空資料開始。舊自由文字負責人保留顯示, 「我」轉為第一位使用者 ID

## Epic 與 Task／Bug 的聯動

同一張 Issue 表保存 type, 另以 epicId 建立關係。Epic 是目標容器, 其他 Issue 是執行工作；不要求每個 Issue 都屬於 Epic

| 操作 | 規則 |
| --- | --- |
| 新增或改掛 Epic | 父項目必須是同專案 Epic, 一個子項目只能有一個父 Epic |
| Epic 嵌套 | 第一版禁止 Epic 掛 Epic, 天然避免循環；任意 related／blocks 另用關聯模型, 不混入父子欄位 |
| 子項目完成 | 重算 Epic 摘要, 不擅自改父項目狀態；由負責人確認驗收 |
| Epic 完成 | 若有未結案子項目則拒絕, 全部完成／取消後允許人工完成；沒有子項目也可人工完成 |
| 已完成 Epic 子項目重開／新增 | 保留父狀態與異動紀錄, 在 Epic 概覽顯示「需重新確認」, 避免靜默級聯修改 |
| 已有子項目的 Epic 改類型 | 拒絕, 先解除／移轉子項目；已有工時的普通 Issue 也不能直接變 Epic |
| 父項目刪除 | 先不提供刪除；正式採封存或顯式解除連結, 工時與歷史不得跟著消失 |
| Task ↔ Bug | 可變更類型, 保留 ID、Epic、留言與工時 |

完成率 = 完成子項目 ÷（全部子項目 − 取消子項目）。取消另外顯示, 不當完成。沒有有效子項目顯示「尚無有效工作」, 不呈現誤導的 100%

預估與實際工時只加總 leaf Issue 一次。父項目完成與子項目完成率是兩個指標, 卡片需同時顯示。Bug 可以放在交付功能的 Epic 中, 重開後會反映在完成率與驗收提醒

## 自訂狀態

每專案獨立設定, 新專案有待規劃、待處理、進行中、已完成。可加入「待測試」「等待客戶」「取消」, 每個狀態映射：

| 分類 | 意義 | 分析處理 |
| --- | --- | --- |
| backlog | 尚未排入執行 | 未完成 |
| active | 待處理／處理中／待測試等 | 未完成 |
| done | 驗收完成 | 完成 |
| canceled | 不再執行 | 排除完成率分母, 保留工時 |

- 狀態 ID 固定, 改名不改 Issue 欄位；顯示名稱不作邏輯判斷
- 狀態排序就是看板欄順序, 任務表單、篩選、統計同步取得
- 使用中的狀態不能刪除, 先將 Issue 移到替代狀態；至少保留一個未結案及一個完成狀態
- 設定有 revision, 並行修改回報衝突, 不覆蓋另一位管理員的變更
- 變更分類會重算完成統計, 並留設定異動紀錄；UI 說明這會改變報表語意
- 第一版成員可在任意既有狀態間轉換, Epic 完成仍受子項目規則限制。工作流程矩陣、必填欄位及自動化另做, 不把自訂欄位誤稱完整 Workflow Engine

## 工時與行事曆

TimeEntry 獨立於 Issue, 保存 issueId、userId、workDate、minutes、note、revision 與 createdAt／updatedAt。工作日是 Asia/Taipei 的日曆日期, 不是伺服器 UTC 日期；minutes 使用整數, 避免浮點累加誤差

- 使用者填自己的工作, 不因是負責人就限制其他協作者登記；不能冒用別人的 userId
- 以小時輸入, 最小 1 分鐘, 每筆最多 24 小時, 同一人同一日合計不能超過 24 小時；允許分次紀錄且不自動視為重複
- 不允許未來日期工時；目前試作每筆有說明, 可修改或作廢自己的紀錄, 作廢留稽核而不實體刪除
- Epic 不接受直接工時。開發、會議、測試都記到具體 Issue；行政／請假／帳單成本另規劃
- 月曆日格顯示日總工時與明細, 切換月份／今天／成員；點日期開右側填報抽屜並帶入日期, 平常月曆滿寬。修改本人紀錄開同一抽屜, 保存成功關閉, 未儲存變更在關閉／離開前保護；手機抽屜滿寬。下方顯示成員與 Epic 彙總, 可點任務回到內容
- 日曆顯示填報日期與時數, 第一版不宣稱是有起迄時間的排程日曆。之後可選填 start／end 與 timer, 再處理重疊與跨日拆分
- 報表分母／日期範圍明確, 沒有紀錄顯示 0, 不推定請假、缺勤或工作效率
- 月曆與成員報表受 Workspace 隔離, 正式的團隊工時閱覽權限、週填報送審／鎖帳、CSV 匯出另設計

## 通知與 SMTP

先做 Inbox／Outbox 預覽：指派異動、留言、Epic 交付待確認、邀請等事件產生獨立通知。事務內同時保存業務資料與事件, 寄信失敗不能撤銷任務修改

| 項目 | 設計 |
| --- | --- |
| 寄送時機 | 指派給他人／留言給負責人, 排除自己；到期摘要需背景排程, 不靠打開網頁觸發 |
| SMTP 設定 | host、port、secure、user、password、from 放伺服器環境／secret store, 前端只看到供應方式與設定狀態 |
| 連線檢查 | 管理員可 verify DNS／TCP／TLS／登入, 不寄信；檢查成功不等於 from 或收件人會被接受 |
| 寄送可靠性 | eventId＋recipientId＋channel 唯一, worker 領取租約, 有限次數退避重試, 失敗原因與人工重試, 避免每次 refresh 寄信 |
| 交付語意 | SMTP 接受不等於送達, 正式支援 bounce／suppression／退訂與偏好, 不承諾 exactly once |
| 預設模式 | preview, 保存收件人與信件內容但不外送；本次不要求 SMTP 憑證, 不寄真實郵件 |

[Nodemailer SMTP 文件](https://nodemailer.com/smtp) 說明 465 的直接 TLS、587 的 STARTTLS 及 verify；正式 TLS 憑證必須驗證, 不提供關閉驗證的捷徑

GAS 的 [MailApp](https://developers.google.com/apps-script/reference/mail/mail-app) 是 Google 郵件服務, 不提供任意 SMTP 主機設定；[UrlFetchApp](https://developers.google.com/apps-script/reference/url-fetch/url-fetch-app) 是 HTTP／HTTPS 請求。據此架構建議：一般 Google 寄信可用 MailApp adapter, 任意 SMTP 則由獨立後端／HTTP relay 處理, 不把 Node 的 SMTP transport 塞進 `.gs`

Google 的[配額文件](https://developers.google.com/apps-script/guides/services/quotas) 列出執行時間、每日寄信與並行限制, 配額可能變動。因此 SaaS 方向需重新評估 GAS 是小團隊部署選項, 還是主要後端；這是架構推論, 不在本輪擅自放棄原先 GAS 方向

## 資料與 API 契約

| 實體 | 關鍵欄位／約束 |
| --- | --- |
| Account | id、email 唯一、name、passwordHash；不回傳 hash |
| Workspace／Member | workspaceId、userId、role；session 綁定成員資格 |
| Invite | tokenHash、workspaceId、email、expiresAt、consumedAt；一次性 |
| Project | id、code、statuses、revision、settingsHistory；代碼只在同一 Workspace 唯一 |
| Issue | projectId、type、epicId、assignee（成員 ID）、estimateMinutes、state ID、revision |
| TimeEntry | issueId、userId、workDate、minutes、note、revision、voidedAt；不得跨 Workspace 關聯 |
| Notification | event／issue、recipientId、email、subject、body、status=preview；實際 worker 另做 |
| Request | userId＋requestId、signature、result, 重試不得跨使用者重播 |

本機延續 SQLite transaction, Workspace namespace 由伺服器生成, 共用 domain module 不依賴 Node／Google。正式 SaaS 建議改關聯式資料庫與獨立 worker, 以 tenantId 約束與索引落實隔離, 保留 API adapter 邊界

## 分階段試作與驗證

1. 調研／規劃 commit：本文件、開發規範與計畫, 明確 SaaS 補充與 GAS 暫停產檔
2. 資料／API commit：本機帳號、隔離工作空間、邀請、Epic 規則、自訂狀態、個人工時與通知預覽, 驗證隔離、越權、版本衝突、防重複與加總
3. 可視化試用 commit：登入／邀請入口、Epic 進度卡、自訂狀態設定、工時月曆與團隊分析、通知預覽與 SMTP 檢查, 驗證桌面／手機和雙使用者操作

驗收例：建立「會員系統」Epic → 加登入 Task 與登入失敗 Bug → 加「待測試」欄位 → 同伴加入並填工時 → 月曆按成員查看 → 完成子項目後驗收 Epic → 重開 Bug, Epic 顯示重新確認提醒 → 查看指派／留言通知, 不外送

下一輪才處理：正式身份與部署、專案 ACL、任意關聯／依賴、工作流程矩陣、工時審核／timer、背景 SMTP 寄送／摘要、付費與資料匯入。這些不應阻止本輪先確認主要畫面與規則

## 本機試作的實際界線

本輪先支援一個帳號的一個工作空間, 邀請限新帳號；多空間切換／既有帳號接受邀請尚未實作。所有成員共享空間內專案與團隊工時, 管理員看所有通知預覽, 成員只看自己的通知

HTTP API 仍只監聽 loopback, cookie 未啟用 Secure 因為本機是 HTTP。SMTP adapter 有 verify, 沒有寄送入口或 worker；指派與留言通知是實際保存的預覽資料, 邀請連結顯示給管理員, 不假裝已寄出。全空間資料每 30 秒重新載入, 目前沒有伺服器分頁／即時推播, 正式 SaaS 須另處理查詢、索引與容量量測
