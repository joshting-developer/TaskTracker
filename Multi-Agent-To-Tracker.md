# Multi-Agent → TaskTracker：實際使用與外部整合交接

日期：2026-11-11（Asia/Taipei）。來源專案：`/Users/josh/Projects/workspace/SideProjects/Multi-Agent`。

## 使用者已確認的目的

1. 用真正的 Side Project 驗證 TaskTracker 能否管理好專案，不只跑隔離展示資料。
2. 對外 API／Webhook 等整合能力是 TaskTracker 未來競爭力的一部分。
3. 讓 Multi-Agent 已完成與未完成的工作有清楚路線；後續所有工作都需在 Tracker 記錄。

本文件記錄實測事實與建議驗收範圍；具體公開 API 路徑、認證技術與上線時程尚未定案。不在本輪修改 TaskTracker 功能。

## 本次實際建立

- 網站：`http://localhost:5174`。
- 工作區：SideProject；ID `79f7df6c-7828-44be-ab77-670151ec4175`。
- 專案：[Multi-Agent／MA](http://localhost:5174/#/projects/796d764a-e7fb-4eb2-a99d-8b7cc249b98b)；ID `796d764a-e7fb-4eb2-a99d-8b7cc249b98b`。
- 依 Multi-Agent 的 v0.2 規格、CHANGELOG、架構及測試紀錄建立 76 張原始盤點卡：9 Epic、63 Task、4 Bug；29 已完成、3 部分完成的 Epic 在進行中、44 待辦。後續另加工程流程 Epic 及 MA-78／MA-79，因此總數會隨工作增加。
- 狀態：`open` 待辦、`inprogress` 進行中、`review` 待驗收、`blocked` 受阻、`closed` 已完成。
- 看板：里程碑總覽、M2 討論 MVP。Epic 真正關聯 Task／Bug，卡片有優先級、來源與驗收條件，未虛構工時或期限。
- 專用帳號：`multi_agent@example.com`，工作區一般成員，只有 Multi-Agent 專案 Developer；未授予 Owner、Manager 或管理專案成員／狀態的能力。
- [MA-78](http://localhost:5174/#/projects/796d764a-e7fb-4eb2-a99d-8b7cc249b98b/issues/78ac666d-8029-4666-be09-98235412c5c2?detail=1)：本次建置、流程與驗證紀錄。
- [MA-79](http://localhost:5174/#/projects/796d764a-e7fb-4eb2-a99d-8b7cc249b98b/issues/444c5b17-5329-492e-a208-467338f273fe?detail=1)：Multi-Agent 正式 API／Webhook 客戶端整合，等待 TaskTracker 能力，維持受阻。

帳號密碼、Session 與邀請 Token 不放在此文件、卡片或 Git。本機私有 Developer 登入設定留在 Multi-Agent 的 `.local/`，已排除版本控制。

## 已經能做，沒有阻斷本次匯入

先用真實 Chrome 註冊／建立工作區，再透過 TaskTracker 既有應用 API 建立專案、狀態、卡片、看板與成員資格，最後重新讀回核對並開啟實際 UI。

| 已使用能力 | 實際方式 |
| --- | --- |
| 官方應用登入／註冊 | `/api/auth/register`、`login`、workspace invitation |
| 工作區與專案 scope | Session、`X-Workspace-ID`、Project ACL／permission |
| 卡片／狀態／看板 | `/api/rpc/createIssue`、`updateIssue`、`updateStatuses`、`createBoard` |
| 成員 | `inviteMember`、`getProjectMembers`、`setProjectMember` |
| 查詢 | `getWorkspace`，目前回傳使用者有權存取的整包資料 |
| 併發與重送 | mutation 的 `requestId` 及 `expectedRevision` |

原始 76 張卡逐筆讀回，核對標題、描述、類型、狀態、優先級、負責人與 Epic 關聯。這證明現有 RPC 足以處理本次工作，不代表已具備正式外部整合產品。

Multi-Agent 新增暫時的 `scripts/tasktracker.mjs`，使用 Node fetch、登入 Session 與 CSRF；不再要求每次透過瀏覽器或 Vite 的 `/src/api/index.js` 匯入。此工具限定既有 Multi-Agent 專案，更新前讀 revision，執行後登出；仍依賴一般帳號密碼，不是正式 service credential／公開 SDK。

## 觀察到的整合缺口與建議驗收

以下根據本輪實際流程及 `backend/routes/web.php`、`frontend/src/api/index.js`、ProjectAccess、RequestReplay 等程式碼核對；不將「沒有看見」擴大解讀為無法建置。

### TT-EXT-01：正式機器認證與受限整合身分（優先）

目前對外整合需一般帳號登入並維護 Session／CSRF；專用 Developer 可用，但長期依賴密碼並不適合作為完整整合產品。

驗收建議：

- 可由有權的使用者建立、命名、撤銷、輪替及設定期限的整合憑證。
- Scope 明確限定 Workspace／Project 與實際 permission；不能依角色名稱直接放行，也不能跨租戶。
- 明確區分 human／integration actor，卡片歷史可識別是哪個 Agent／整合操作。
- API 只提供已授予能力；一般機器帳號不可自行提權、管理成員或存取其他專案。
- 秘密只在建立時顯示，不進 bundle、回應日誌或一般資料匯出；沿用既有撤權與停用規則。

不在此指定必須使用 Sanctum、OAuth 或某個 Token 套件；由 TaskTracker 設計技術方案。

### TT-EXT-02：穩定、版本化的公開 API 契約（優先）

現有 `/api/rpc/*` 為 SPA 操作契約，沒有獨立的公開版本／OpenAPI 交付。外部工具不應依賴 Vite source module、畫面 DOM 或全量 Workspace 回應。

最小需要：

- 查詢目前帳號／有效 workspace／project／statuses／issue types／members 的受權範圍。
- Issue 列表、單卡查詢、新增、更新、留言及 Epic／父子／關聯操作。
- 依專案、類型、狀態、優先級、負責人與 `updatedSince` 篩選，cursor 分頁與穩定排序。
- 公開錯誤碼、版本策略、rate limit／Retry-After，必要時可提供 OpenAPI 與最小 SDK 範例。
- 更新沿用 revision 檢查；權限錯誤不以重試或 Owner fallback 繞過。

### TT-EXT-03：外部識別鍵、增量同步與批次匯入

本次需在描述放 `追蹤鍵：multi-agent:...` 以避免中途重跑產生重複卡；已有 requestId 很有幫助，但不等於長期的外部資源識別。

驗收建議：

- 提供 project-scoped 的 `externalSource`／`externalId`，有唯一性與查詢／upsert 契約；不要只能解析描述。
- requestId／idempotency 規則文件化：同一輸入可安全重送，不同輸入使用相同 key 明確衝突。
- 批次操作回傳逐項成功／失敗與對應 key；避免回應逾時後無從判定哪些卡已建立。
- 跨卡建立 Epic／children 時可引用外部 key 或使用分階段、可恢復匯入。
- 全量匯出、增量同步、刪除／歸檔事件及 cursor 的語意明確，避免只靠 `getWorkspace` 掃描。

### TT-EXT-04：可靠的對外 Webhook

目前 Reverb 是給看板的私有通知訊號；`docs/board-realtime.md` 已明列無 durable outbox、可能遺失並由輪詢收斂。這不等於第三方可靠 Webhook。

驗收建議：

- 可按 Project 與事件訂閱卡片建立／更新／狀態變更／留言／刪除或歸檔等必要事件。
- 事件有 `eventId`、時間、actor／integration、workspaceId／projectId、resourceId、revision 與來源識別；預設最小 payload，資料另經授權 API 取得。
- 事件與業務寫入可靠一致（如 transactional outbox）；交易回滾不發送幽靈事件，commit 後派送失敗可補送。
- HMAC 簽章、時間戳／重播限制、接收端去重、有限重試、退避、死信／失敗紀錄與手動重送。
- 文件明示至少一次投遞、亂序與 revision 處理；不能承諾不可能的端到端 exactly-once。
- 租戶／專案撤權或整合停用後停止投遞；外部接收 URL 需有防 SSRF 設計，localhost 開發例外獨立配置，不直接開放任意內網目標。

### TT-EXT-05：Agent 工作生命週期與稽核

Multi-Agent 需要在卡片中交代實作、驗證、阻礙及交付；正式整合應能可靠補送，而非因 Agent 中斷遺失進度。

驗收建議：

- 更新／留言可附原始任務、Agent run、來源專案與交付物參照。
- 敏感資料不寫卡片；歷史保留實際 actor／時間／before-after，權限與資料隔離不被整合繞過。
- 可辨識整合來源、忽略自己產生的回音事件，避免 Webhook → Agent → 回寫的無限循環。
- 外部整合失敗／撤權／超時有可查狀態，不假裝已同步；補送維持同一 idempotency 身分。

## 建議推進順序

1. 先完成受限整合憑證＋必要公開查詢／寫入 API，讓 MA-79 能解除阻礙。
2. 再補 externalId、cursor、增量／批次與同步失敗恢復。
3. 將現有看板 Reverb 與正式對外 Webhook 分開設計，再補可靠事件／投遞／重送與稽核。
4. 用 Multi-Agent 這個真實專案驗收日常開工、更新、受阻、完成與重啟補送；未通過的需求回寫 TaskTracker 工項。

本次沒有因需要 API 而直接寫資料庫，也沒有臨時修改 TaskTracker 授權。後續由 TaskTracker 專案把上述內容轉成自己的設計及開發卡片。
