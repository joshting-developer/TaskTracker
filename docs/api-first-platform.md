# API-first平台架構與第三方整合邊界

## 2026-11-12 TT-169 公開寫入交付

已提供Issue建立／部分更新／留言，沿現有業務與權限、來源、revision及requestId原結果契約，回應與新receipt固定四欄位。見[寫入契約](public-write-api-v1.md)；TT-252～254文件／CI／獨立全流程驗收仍後續，不因API可呼叫便全部結案。


## 2026-11-12 TT-168 只讀能力

公開Project／成員／狀態／Issue／留言讀取已落地，沿TT-166的Bearer邊界及既有查詢服務，不複製業務邏輯。Token資格交集、分頁及最新revision與範例見[只讀契約](public-query-api-v1.md)。正式OpenAPI0.3.0只列已交付能力；TT-169寫入、TT-252文件站／253 CI／254最終建卡驗收仍待完成。


## 2026-11-12 TT-167 正式契約

已交付唯一OpenAPI、匿名Markdown契約及實際回應Schema／限流測試，見[交付與後續目標矩陣](public-rest-contract-v1.md)。正式機器規格只列已交付操作；TT-168／169仍未實作，完整文件站、CI及獨立建卡驗收仍TT-252～254，不能視為本卡一併完成。


## 2026-11-12 TT-166 實作更新

已建立可信TokenContext及Bearer read/write交易邊界：Scope與當下Workspace／Project有效permission取交集；寫入鎖後重驗並原子保存成功操作來源。PAT建立／撤銷已記Session來源，來源清單只限本人目前工作區；讀取不逐次寫日誌。見[契約與撤權時序](token-authorization-and-audit.md)。TT-168／169必須沿此邊界使用既有Service，不可重複建立授權或業務流程；本輪沒有新增公開卡片端點、全站帳號停用欄位、專屬稽核UI或MCP。


## 2026-11-12 TT-165 實作更新

先前「尚未安裝／全部待辦」為規劃當時狀態。TT-165已安裝Sanctum4.4.0，交付Session管理介面、個人Workspace綁定PAT、read／write、最長兩年、一次揭露、雜湊／撤銷與到期管理；純Bearer身分端點 `/api/public/v1/me` 與正式OpenAPI同步。詳細[實作契約](personal-access-tokens.md)。TT-166來源稽核、TT-167完整REST契約、TT-168／169業務端點、TT-252～254文件站／CI／獨立客戶端驗收仍另行處理，不因此結案。


日期：2026-11-11。狀態：**使用者已確認產品方向，尚未實作PAT或公開REST API**。本文件採用使用者提供GPT討論的最後收斂，取代先前「TaskTracker自行提供MCP與工具整合」的候選順序；保留前段討論作沿革。

## 產品責任

TaskTracker提供完整的任務管理能力與可被整合的標準介面。使用者可以自行用Codex／Claude、VS Code、Discord／Slack機器人、n8n、GitHub Actions或自己的程式呼叫；平台不維護這些工具的專屬邏輯，也不替使用者編排AI工作。

| 項目 | 已確認範圍 |
| --- | --- |
| RESTful API | 必須；可穩定取得和操作有權限的專案／卡片等資源 |
| 每位使用者自行管理PAT | 必須；建立、命名、能力／資源Scope、有效期與撤銷 |
| Project成員與有效permission驗證 | 必須；Web與API共用規則，Token不提權 |
| Token到期／撤銷管理 | 必須；不以無限期且不可撤銷的密碼代替 |
| OpenAPI／Swagger | 公開REST API須附機器可讀OpenAPI契約及開發者／Agent使用文件；Swagger UI是建議呈現介面，不強制額外常駐服務 |
| TaskTracker自建MCP Server | 暫不製作；使用者可依公開API自行建立 |
| Codex／Claude或其他工具專屬整合 | 不在產品範圍；提供工具中立的契約與範例 |
| Agent編排／自動化工作流程 | 不在產品範圍；保留外部操作來源稽核 |
| Webhook | 後續低優先候選，先完成Token與API；不因此宣稱已有可靠投遞 |

先前V3自建MCP不再是本專案既定開發階段。外部Multi-Agent可作後續選配聯調客戶端，但其自身功能、MA-79結案或客戶端adapter不是TaskTracker基礎API的驗收阻擋條件。

## Web與API共用一套應用能力

以下為目標責任流，不代表類別或路由已建立：

```mermaid
flowchart TD
  Web[Vue 網頁] --> Session[Session / Cookie / CSRF 邊界]
  Client[外部工具或自建 MCP] --> Token[PAT / Bearer 認證與 Scope 邊界]
  Session --> HTTP[薄 HTTP Controller / Request / Resource]
  Token --> HTTP
  HTTP --> Service[共用 Application Service / 必要 Action]
  Service --> Rules[共用 permission / 資源條件 / 交易內重驗]
  Rules --> Data[既有 Repository / Model / MySQL]
```

- 網頁與外部API共用Service／Action、集中授權、資料存取、revision／requestId／歷史及交易邊界。不同Controller只適配認證、輸入與輸出，不複製第二套建立／移動／留言流程。
- 認證確定使用者及Token來源；由伺服器建立可信的操作者與Workspace／Project上下文，再驗證範圍。外部請求不能自行指定可放行的角色或跨租戶工作區。
- 外部Token不應依賴人類瀏覽器Session的「目前工作區」。請求的目標資源、Token範圍與使用者有效成員資格必須一致；具體路徑／header形式於契約設計時定案。
- 共用邏輯不等於必須同一條HTTP路由。先盤點現有API，適合的能力直接重用，格式或認證不適合公開的入口才補薄REST adapter；不強迫另開一個後端服務，也不讓所有SPA RPC自動對Token開放。
- 2026-11-12契約修正：Session／CSRF、camelCase／ok-data、錯誤及revision／requestId語意維持；使用者已授權依資料存取審查調整路徑／DTO與前端依賴、退役整包RPC。一般頁面不得預設取整個Workspace，列表SQL篩選／分頁、詳細活動按需；版本與移轉步驟見[分責契約](query-api-v1.md)，不能為相容永久保留過量載入。
- Repo只負責資料存取；既有Service不因外部入口繞過權限、作者限制或MySQL寫入鎖。遵循本專案[Laravel Architecture](../skills/laravel-architecture/SKILL.md)，不機械增加空層。

## Token的授權語意

**有效操作 = 使用者當下有效permission ∩ Token能力 ∩ Token指定的Workspace／Project範圍，並符合資源／作者條件。**

例如使用者只有專案A的讀取權，即使Token宣告寫入能力仍不能寫A，也不能讀B；退出A、被停用或撤權後，舊Token對A也不能繼續使用。角色只是permission映射，不能用角色名稱代替檢查。

PAT管理屬於個人帳號能力；Scope不超出本人可授予能力。第一版可用使用者ID＋Token ID／名稱記錄操作來源，不能因需要稽核就先引入Agent run編排或複雜整合身分平台。Token原文僅建立時顯示、保存雜湊，寫入仍使用既有版本與防重送機制。

Laravel Sanctum是優先評估的既有認證機制；套件版本、Token儲存擴充、與現有HTTP／Session邊界的接法於實作前盤點。本次沒有安裝或產生Token。

### 2026-11-12 使用者確認的PAT第一版規則

| 項目 | 使用者已確認 |
| --- | --- |
| Scope | 第一版只分`read`／`write`，`write`隱含`read`；Scope以清單儲存，細項之後擴充 |
| 期限 | 預設1個月，最長2年；不提供無期限 |
| 過期處理 | 只在Token管理頁顯示已過期，不主動通知；不提供延長，過期後由使用者新建；過期API回401 |
| 綁定範圍 | 綁定整個Workspace，代表該使用者；一人多個Workspace需各自建立Token；實際可見Project仍是本人當下有效Project資格，不因Workspace綁定提權 |
| 刪除 | 公開API第一版不提供刪除，與網頁現況一致只以狀態closed結案；系統與API的刪除／封存見TT-250，後期設計 |

輪替機制、Token數量上限仍待實作設計，不把上游預設直接當產品需求。先前官方查證見[Sanctum文件](https://laravel.com/framework/docs/13.x/sanctum)。

## 漸進式交付

1. **盤點與契約**：對照現有查詢／寫入用例與授權入口，決定資源、版本、錯誤、分頁與認證邊界；列出最小必要調整。
2. **PAT與必要REST能力**：Token管理、Scope、到期／撤銷，身分／權限metadata、專案與狀態清單、單卡／可篩選分頁查詢，以及必要寫入／留言／關聯操作。未授權與跨專案拒絕、撤權、衝突及重送可驗證。
3. **文件與互通驗收**：交付工具中立的HTTP範例、正式OpenAPI機器契約與第三方／Agent使用指南，讓任意受限客戶端可只憑文件完成查詢、建卡、修改、留言與錯誤處理；不依賴特定模型、DOM或Vite模組。詳細交付清單、文件同步規則與E2E完成定義見[第三方 API 文件規劃](third-party-api-documentation-plan.md)。
4. **後續增量能力**：externalId、批次／增量與可靠Webhook按需求逐項排程。Webhook要有獨立可靠投遞設計，不能把Reverb視為替代品。MCP與工具端adapter留給外部。

「完整CRUD」中的刪除已於2026-11-13（TT-250）定義並提供：永久刪除、無封存，需`issue.delete`，有工時或子卡時拒絕，同步端以issue-deletions墓碑移除；語意見[卡片刪除](issue-deletion.md)。

## 卡片與沿革

- TT-165：PAT管理與Scope；TT-166：使用者／Token來源稽核與撤權；TT-167：公開REST契約；TT-168／169：查詢與寫入。功能尚未完成，維持待辦。
- TT-171～173的外部識別／批次／增量仍是後續平台能力，未因本次方向直接實作。
- TT-174／175的Webhook與TT-177外部客戶端聯調暫緩；TT-176保留通用整合來源稽核，排除內建Agent工作流。
- TT-203只追蹤本輪架構決策與卡片同步，規劃交付完成才結案；不以它結案宣稱PAT或REST已完成。
- 初次202卡的[來源清單](planning/tasktracker-backlog.json)及[匯入結果](planning/tasktracker-import-result.json)保留為歷史快照，實際進度以Tracker為準。本輪不修改原始Multi-Agent交接報告或他專案卡片。

來源：[使用者先前產品討論](產品討論紀錄.md)、[Multi-Agent實測交接](../Multi-Agent-To-Tracker.md)與本輪最後確認。部署重構是另一條工作線，本次沒有調整Compose、Reverb、主庫或雲端環境。

## 本輪規劃交付驗證

已完成文件與README／架構／AGENTS入口同步、本地連結及diff檢查；以taskTrackerAI更新並讀回10張既有整合卡的最新範圍、指派與狀態。TT-174／175／177為deferred／low，其餘核心卡維持open；規劃子卡TT-203留言交接後closed。沒有將文件結案誤作API功能完成，初次匯入快照不重寫。未安裝套件、修改產品程式／schema／部署或操作其他專案卡片；純文件不額外跑產品測試。

## 2026-11-12 文件工作拆卡

OpenAPI與第三方文件已確認為整合階段必要交付。TT-167承接正式REST／機器契約，TT-252提供開發者入口／PAT與Agent指南及可執行範例，TT-253驗契約CI，TT-254做只憑公開文件／有效PAT的必要通用客戶端E2E。TT-177仍是選配特定工具聯調，維持deferred；不能因其暫緩而省略TT-254。新工作均open、尚未實作，並收錄AI整合/API；完整[查重與讀回證據](api-documentation-gap-review-2026-11-12.md)。


## 2026-11-12 寫入結果與重播容量分析（TT-227）

既有卡片完整結果的receipt會隨留言／歷史累積膨脹。量測與既有重播不變條件見[容量與保留契約分析](performance-receipt-retention-2026-11-12.md)。TT-178／246／169的專用輸入／輸出與公開寫入契約需驗證有界結果及Web呼叫端相容；TT-255另追蹤無損codec／雙讀回退。這些尚未實作，不改目前同key原結果語意。保留天數、過期錯誤及墓碑仍是提案，沒有使用者採納的TTL或自動刪除政策。

## 小待辦公開API（TT-311, 2026-11-13）

- Josh想用小待辦交代事情給Agent（請Codex做某件事、請Josh複查）, Agent用PAT走公開API, 所以補上小待辦的公開端點：列表（交辦給我／我交辦出去）、單筆、可交辦對象, 以及建立交辦、修改、完成、重開、退回。OpenAPI升到0.11.0
- 讀寫都沿用網頁同一套TodoReadService和Todos用例, 誰能看、誰能改、誰能完成或退回的規則完全一樣, 外層再加Token的read／write和requestId重送
- 轉成任務、取消、通知這次沒開放：轉成任務牽涉專案權限和卡片欄位, 等真的有Agent需要再評估
- Agent的使用規則（接任務前先看小待辦、一個待辦不要太大）放在共用task-tracker-usage Skill（TT-312）

## 我的通知公開API（TT-315, 2026-11-13）

- Josh覺得去「通知」Agent很奇怪, 應該讓Agent自己讀, 所以補上 `GET /api/public/v1/notifications`：只回寄給Token本人的任務通知, 依產生順序遞增, Agent存下 `nextAfter` 下次接著讀。OpenAPI升到0.17.0
- 沿用網頁同一張通知表和專案權限, 每次都重驗本人現在還能不能讀那個專案（issue.read）, 不能讀的跳過, 位置照樣往前
- 這次沒做未讀篩選和標記已讀：網頁的任務通知本來就沒有已讀狀態（任務通知收件匣是TT-190, 暫緩）, 為了API另外加一套已讀會跟網頁不一致。Agent自己存的 `nextAfter` 就等於讀到哪裡
- 小待辦通知沒有另外開放：交辦給自己的事用 `/todos?scope=mine` 就讀得到, 再開一個待辦通知端點意思重複
