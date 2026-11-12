# Agent／CLI 整合指南

適用任何能發 HTTP 的外部工具，不是某個模型專用 Skill，也不是操作授權。先取得使用者允許的 Workspace／Project／操作範圍與有效 PAT；無 HTTP 能力時應明確說明，不能因讀過文件便假稱已開卡。

## 發現與基本模型

- Base URL 是使用者提供的部署 origin；`GET /api/public/openapi.json` 是唯一正式 operation／Schema 來源，`GET /api/public/docs/agents.md` 是本指南原文。
- Workspace → Project → Issue。PAT 固定一個 Workspace，透過 `GET /api/public/v1/projects` 分頁找可見專案，不猜私有 ID、不從 DOM／Vite 模組取得資格。
- 類型只有 epic／task／bug；Epic 是可選分組，父子一層，雙向關聯，不是前置任務。狀態依 Project 候選，open／closed 必備；不能硬編其他狀態名稱。
- 回應是 ok/data，錯誤是 ok=false／error.code／message。業務 ID 用回傳的公開 ID，勿用資料庫整數或把 identifier 卡號誤當路徑 ID。

## 最短可靠流程

1. `GET /me`，確認 PAT 的 Workspace，不依瀏覽器 Session。
2. `GET /projects` 找專案；必要時翻頁，讀 `/projects/{project}/statuses` 與 `/members` 取得候選。
3. 寫入須有 write 能力且本人有 issue.write。每個不同操作建立一個 UUID requestId，完成前保留 payload 與該 key，勿存 PAT。
4. `POST /projects/{project}/issues` 建卡。成功只回 id／projectId／identifier／revision，**不是最新完整快照**。
5. 用 `GET /projects/{project}/issues?identifier=EX-1` 精準找到 ID，再 GET 單卡詳細取得最新 revision。
6. `PATCH .../{issue}` 只送要改的欄位，加 expectedRevision（JSON 整數）與新 requestId；省略的描述／關聯應保留，不拿列表摘要覆蓋整張卡片。
7. `POST .../{issue}/comments` 用最新 expectedRevision、新 requestId 及 content 留言；作者由 PAT 決定，不自稱其他人。
8. GET 詳細／留言確認實際結果；依授權改 closed／其他狀態。沒有刪除／封存接口，不能透過猜測 DELETE 硬刪。

## 分頁與失敗續接

列表預設20、最多100，data.items/page/perPage/total/lastPage；page 超最後頁回空。請依 lastPage 翻頁，不把第一頁當全部。state 篩選用 `state[]=open&state[]=closed`，identifier 是精準匹配；其他限制依 OpenAPI。

- 409 REVISION_CONFLICT：GET 重讀，和使用者意圖／草稿比較；不要自動強制覆蓋，也不要只換 requestId 不換過期版本。
- 相同 requestId＋相同有效內容重送回**首次**四欄位結果，不重複業務／通知／稽核。結果 revision 可能舊於當前卡片，另 GET 最新。
- 409 REQUEST_CONFLICT：同一 key 已用於不同有效內容／operation；核對紀錄後才決定新操作，避免建兩張。
- 網路 timeout／暫時 5xx：不代表未寫入；先沿相同 key 與 payload 核對，有限退避。429 依 Retry-After，401／403 停止自動重試。
- 404 不洩漏是否存在；檢查指定 Project／Workspace，不能換成 Owner 或掃其他租戶。
- PAT 建立是例外：原文只一次，重送409不重播秘密；不要將卡片 receipt 規則套到 Token 管理。

## 安全與進度

PAT 不印在對話／卡片／Commit／log，不傳給不受信任網址，不把 OpenAPI 的示例 Token 當實際憑證。Token 清單與來源管理是 Session 介面，不屬 Agent PAT 自助增權。

需要長任務接手時，可在已授權卡片留言記「已做／驗證／剩餘／阻礙／下一步」，並如實調整狀態；不要貼秘密或把尚未完成內容標 closed。TaskTracker 提供 API，不決定外部 Agent 的分工／工具／執行授權，也不內建 MCP 或跨模型編排。

## 增量接續

大量或增量讀取用OpenAPI的listPublicIssueChanges（項目附externalRefs，syncUntil留有數秒緩衝、下一輪會重讀，請依id／revision去重；目前沒有刪除事件，鏡像同步請不帶篩選並定期全量對帳）（Project issue-changes），避免深頁碼／COUNT。保留所有filters與perPage，上一批成功保存後送回nextCursor；nextCursor為null才以syncUntil開新輪updatedSince，邊界包含且按id／revision去重。同一秒多張卡片不只用時間戳當cursor；不要把live列表當多請求snapshot或刪除feed。Python下載範例含完整接續迴圈；其他注意事項見contract的增量章節。

## 外部識別對應

從其他系統匯入或同步卡片時，用OpenAPI的upsertPublicIssueByExternalId（`PUT .../issues/external/{source}/{externalId}`）代替「先搜尋描述再決定建立或更新」。第一次送出建立並回created=true；之後要更新同一張卡，先用getPublicIssueByExternalId讀revision，再帶expectedRevision送出，409時重讀後再決定。每次新的變更用新的requestId；逾時才以同requestId與同內容重送核對。外部識別區分大小寫、不能含斜線。

大量匯入改用batchUpsertPublicIssuesByExternalId（`POST .../issues/external-batch`，最多50項）。先匯入Epic，再匯入以epicRef／parentRef引用的子卡。只建立不更新時設`createOnly: true`，失敗項修正後可換新requestId整批重送。逾時則以原requestId與原內容重送取回逐項結果；逐項status=failed不會讓整批失敗，要逐一檢查error。

## 來源參照與回音

寫入時可帶`X-Client-Reference: <你的run或工作ID>`，方便日後從本人來源紀錄追溯是哪次執行寫的；不要放秘密。讀issue-changes時，`lastWrite.sameToken`為true的項目是你自己剛寫回的版本，同步時可以略過，避免來回循環。lastWrite為null代表最新版本來自Web或其他來源，需要處理。
