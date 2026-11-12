# TaskTracker 日常使用體驗改善：圖片附件、卡片操作與介面精修

> 日期：2026-11-13（Asia/Taipei）
> 狀態：**待 Agent 讀取、查重、拆卡並實作的產品需求規格**，不是已完成報告。
> 使用者需求：TaskTracker 已用於管理 Multi-Agent 等真實開發任務；建卡無法直接貼上 Bug 截圖、核心操作尚有摩擦，卡片與整體 UI 美感需改善。
> 使用者追加：**正式檔案儲存預定採 Cloudflare R2；R2 連線資訊之後才提供。現在先完成可替換儲存層、資料模型、API、驗證與 UI，用本地私有儲存跑通；不可因缺 R2 金鑰卡住整個附件功能。**
> 本次交付：僅新增本文件並 Git Commit，**不直接建卡、不寫產品程式、不配置或啟用 R2**。

## 0. 目標、優先級與拆卡規則

產品目標是把「發現 Bug → 截圖 → 建立 Issue → 補充描述 → 儲存 → 閱讀／留言追蹤」變成低摩擦的日常工作流；AI Agent 也能閱讀清楚的任務內容。

- **P0：圖片附件基礎**：資料模型／ACL／API／可替換檔案儲存實作／上傳生命週期。
- **P0：建卡貼圖體驗**：Cmd/Ctrl+V、拖曳、選檔、進度／錯誤／重試、圖片顯示／放大，含建立前未儲存草稿。
- **P1：編輯與卡片詳細 UX**：Markdown、圖片、描述、屬性、留言、活動與操作回饋一致。
- **P1：列表／看板視覺精修**：掃讀性、資訊密度、文字、間距、操作狀態、響應式；不做無證據的大型改版。
- **P1：R2 Adapter 與設定接線**：基礎可先實作，**實際連線／切換／部署驗收待使用者提供資訊**。
- **P2：PDF／ZIP／其他類型附件、Agent 公開附件 API、縮圖處理／進階編輯器**：可以先規劃相容性，不視為 MVP 必做。

**後續 Agent 建卡前先查重**：現有 TT 專案 Issue、Epic 和看板可能已存在圖片、附件或編輯器候選；補強已存在卡片，不要重複創建。不要虛構 TT 編號或把需求文件等同已實作。一般產品 UX／附件卡歸「實際功能」，必要獨立的性能與安全技術卡歸相應工程板；AI 整合／API 看板只留真正對外 PAT API 的工作，不因內部增加 HTTP 路由就放進去。

## 1. 已確認現況與舊決策

截至 2026-11-13 前讀取的 GitHub main：

- `frontend/src/components/MarkdownEditor.vue`：共用原生 textarea Markdown 編輯器；有粗體、斜體、清單、勾選清單、連結、程式碼及編輯／預覽，但**沒有圖片貼上、拖曳或上傳入口**。
- `IssuePanel.vue`、`IssueDetail.vue` 使用共用 MarkdownEditor 處理描述／留言；`MarkdownContent.vue` 與 `frontend/src/ui/markdown.js` 處理顯示／清理。
- `docs/markdown-editing.md` 舊決策：外部 Markdown 圖片語法僅顯示安全連結，不載入任意外部圖片，也不支援附件；**本文件授權規劃對「自有且已授權的附件圖片」增加顯示**，不能因此解除外部 URL、HTML、SVG 或 XSS 保護。
- 目前沒有已確認的附件 Model／Migration；實作前再次掃描最新 main／working tree。
- 既有 getWorkspace 已退出一般產品頁、採分責有界 API；新附件讀取不能回到整包 Workspace／Issue 子集合。
- 有 48 個已核准視覺基準與 Skill／視覺驗證流程；變更 UI 必須保留原核准證據，不能直接「更新 baseline 讓它過」。

## 2. P0-A：圖片附件 Domain、SQL 與授權邊界

### 2.1 最小資料模型（名稱由實作者依現有分層決定）

建議新增 `attachments` 資料表，至少：

| 欄位 | 說明 |
| --- | --- |
| `id` | 不易猜測的附件 UUID／公開識別碼 |
| `workspace_id`, `project_id` | 明確租戶／Project 範圍，相關 FK／索引不可缺 |
| `issue_id`（nullable） | 建卡前暫存允許為空；正式綁定只能同 Workspace／Project |
| `uploaded_by` | 上傳者；暫存、綁定、刪除必須重新授權 |
| `storage_disk`, `storage_key` | 私有物件實際位置；不回傳 secret／內部儲存 key 給外部客戶端 |
| `mime_type`, `byte_size`, `original_name`, `checksum` | 驗證／管理／去重或診斷所需 metadata（原檔名不得當儲存路徑） |
| `status`, `expires_at`, `created_at`, `deleted_at` | pending／active／失效清理生命週期；status 定義需明確且可測 |

使用獨立 Image／Attachment DTO／Repository／Service、Laravel Constructor DI、標準繁中 PHPDoc；**MySQL 只保存 metadata，圖片 Binary 不存入資料庫、Issue 的 JSON 欄位或 Markdown Base64**。

最小 ACL：
- 上傳：目前登入且對該 Project 具有建立／編輯 Issue 的有效能力；權限以現有 permission＋scope 為準，不以角色名稱硬判斷。
- 讀取：附件屬 Issue 的可讀 Project 且當次有權讀取該 Issue；pending 預設只有上傳者與允許的管理流程可取，不對其他專案公開。
- 綁定／刪除：驗證目前 Workspace／Project、actor、關聯 Issue、上傳者或適用編輯權；不得拿其他租戶的附件 UUID 綁到自己的 Markdown。
- Session／CSRF／X-Workspace-ID、revision／requestId、撤權與未登入的拒絕語意要保持；公開 PAT API 是否支援附件另立 P2，不得在內部 API 偷增權限。
- 刪除卡片／移除圖片引用後，檔案保留與清除應有單一明確政策；不能只憑 Markdown 被刪就立即刪除所有曾使用的圖片，避免歷史、留言與重播破壞。

### 2.2 檔案驗證與私有內容交付

第一版只接受小型常見截圖格式（建議 PNG、JPEG、WebP；GIF 是否允許動畫先評估資安與成本），由伺服器**驗實際內容型別**，不只相信副檔名或前端 MIME。

- 提議單張上限 10 MiB、每張 Issue 最多 20 張，均為可調建議值，Agent 拆卡前核對預估使用情境與儲存成本；前後端必須一致。
- 不接受 SVG、HTML、可執行檔、任意遠端 URL 匯入或 `data:` Markdown 圖片；HTTP 回應固定安全的 Content-Type、nosniff、適當的 Content-Disposition，不執行使用者檔案。
- 設計 `GET /.../attachments/{id}/content`（實際路由依現有 v1 規則）作**經權限驗證的私有讀取入口**，或由後端授權後發極短效 R2 signed URL；MVP 優先保持同源授權體驗，不以永久 Public Bucket／公開 URL 取巧。
- 不能讓 Markdown renderer 直接請求任意外部 `![alt](https://...)`；只有可信的本系統 attachment reference 能轉成授權圖片顯示。DOMPurify／Marked 原本的 XSS／URL 政策繼續有效。
- 大檔、錯誤檔案、上傳過量、失去授權、已刪圖片、連線中斷都應給出清楚錯誤，不回傳 stack、私有路徑、Storage secret。

## 3. P0-B：先把 API 和儲存介面做好，R2 之後接線

### 3.1 不與雲端綁死的儲存層

優先使用 Laravel `Storage`／Filesystem Disk 的既有抽象，**不要為單一 Storage Adapter 再疊一個空泛的 Repository／Interface**。如果未來需要封裝授權 URL、上傳結果或 checksum，可另設有具體責任的 `AttachmentStorage` interface／adapter，由 Container DI 注入。

最重要的是讓下列用例不需知道具體供應商：

- `store`：寫入私有儲存區，返回內部 object key／metadata；
- `read`／`stream`：僅在 AttachmentService 已授權之後讀取內容；
- `delete`：由安全的清理 Job 操作；
- `temporaryUrl`：若目前 disk 支援才使用，否則走受保護的 Laravel download／stream；
- 測試用 `Storage::fake()` 或對應可替換 Adapter，不連真正 R2。

**建議現階段直接做這些實作**（待 Agent 依卡片實作，而非本文立即修改程式）：

1. 建立 Attachment migration／Eloquent model／DTO／Repository／Service／Request／Controller／Resource／Route。
2. 做 `upload`、`bind to issue`、`list for issue`、`authorized content`、`delete or detach` 的有界 API；命名與具體 Method 以現有路由規範制定 OpenAPI／內部契約。
3. `FILESYSTEM_DISK` 或獨立 `ATTACHMENTS_DISK` 使用 **private local disk** 作為目前環境可驗證預設值；磁碟路徑不經公開 static URL；不要連到使用者真實外部 Bucket。
4. 單元與整合測試全部走 fake storage／隔離 MySQL、真實 Laravel HTTP；驗證 ACL、資料一致性、上傳失敗、孤兒清理、大小與 MIME、撤權後無法取得內容。
5. 預留 R2 S3-compatible disk 設定／配置範例／切換文件，但**缺少使用者提供的雲端設定不能阻塞本地端到端驗收**。

### 3.2 Cloudflare R2：正式環境預定方案

使用者已選定 **Cloudflare R2 作為預定的正式圖片儲存方案**。R2 支援 S3-compatible API；規劃透過 Laravel Filesystem 的 S3 disk／相容 adapter 操作，不另造一套直接呼叫 Cloudflare API 的業務流程。

**待使用者之後提供或在部署環境自行設定**（目前都沒有，不可猜或寫死）：

| 設定項 | 用途 |
| --- | --- |
| R2 Account ID 與 S3 Endpoint | Bucket 所屬帳號／實際 endpoint（依 Cloudflare 文件） |
| Bucket 名稱 | 建立可隔離的私有附件存放區 |
| Access Key ID／Secret Access Key | 最小權限存取憑證，僅存在部署 Secret／被 Git 忽略的本機環境 |
| Region／兼容設定 | 依官方 S3-compatible 要求，通常使用 `auto`，由實際 driver 版本驗證 |
| 傳輸方式／來源網域 | 後端代理、短效簽名 URL 或未來自訂網域；**預設 Bucket 不公開** |
| CORS（如將來直傳） | 只有真正採瀏覽器直傳時再限制 origin、method、header |
| 最大檔案大小、預算、保留期 | 避免意外儲存成本與濫用 |

請預先寫入 `.env.example` 或附加專用部署範例（**只有欄位名與空值／非秘密示例**），例如：
- `ATTACHMENTS_DISK=local_private`（開發）
- `ATTACHMENTS_DISK=r2`（正式啟用後）
- `R2_ACCOUNT_ID`、`R2_BUCKET`、`R2_ACCESS_KEY_ID`、`R2_SECRET_ACCESS_KEY`（空值占位）
- `R2_ENDPOINT`、`R2_REGION=auto`（以實際 Flysystem／S3 設定支援情況核實）

不要在本階段要求使用者提供 Secret；不能因為尚未取得 Bucket 資訊，就留下「等 R2 才能開發上傳」的耦合。需要 Composer S3 adapter 套件時先比對現有 lockfile、確認實際版本與必要依賴，再決定是否安裝。

**啟用 R2 的獨立驗收卡**待資訊到齊後執行：僅用隔離測試 Bucket、最小權限憑證，驗證上傳／授權下載／拒絕跨租戶／刪除／逾時與成本，確認正式部署 Secret／TLS／CORS。**切換 storage disk 不等於既有本地圖片自動遷移**：如果已存在本地附件，需另規劃停寫／逐筆複製／checksum／回退與 metadata 切換；儲存實作要保留每筆 disk 欄位以支援平順遷移。

## 4. P0-C：建卡前貼圖、草稿與 Markdown 呈現

### 4.1 一次完整的使用者旅程

從 Issue 新增面板開始，使用者不必先建立空卡：

1. 開啟新 Issue；在描述框以 Cmd／Ctrl+V 貼上系統截圖，或直接將圖拖到編輯器，也可點選插入圖片選檔。
2. 顯示即時預覽／上傳進度／失敗與重試，成功後在游標處插入**本系統內部附件引用**，不得插入短效 signed URL 或 storage object key。
3. 尚未提交卡片時，圖片先屬於本次操作者、Workspace／Project 的 **pending attachment**，不可被其他 Issue 讀取。
4. 使用者送出建立 Issue 時，由後端驗證目前 actor／project 能力與 pending 附件歸屬，在一致的業務邏輯中將引用綁定到新 Issue。
5. 取消建卡／草稿逾時時，由明確 TTL 清理未綁定附件；不能直接取消就漏掉資料或讓他人搶用。
6. 儲存後詳細頁重新載入，描述中的圖片正常顯示；點擊可放大，能顯示圖片檔名／大小及安全的下載動作。

注意：**Object Storage 不能加入 MySQL Transaction**。必須設計 DB／物件儲存的補償、pending TTL、重試與清理邊界；多次上傳／同 requestId 重送不能無限制複製圖片或建立重複 Issue。

### 4.2 編輯器細節

- 保留 Markdown 原稿而不是直接儲存 HTML／Base64；沿用既有 6000 字描述、2000 字留言的主契約，附件 ID 及圖片容量另計，不能以字數限制造成圖片無法貼。
- 新增共用元件圖片能力時要明確決定適用情境：**Issue 描述優先、Issue 留言可納入第二階段、Todo 速記可以先禁用圖片**，不要讓共用 `MarkdownEditor` 在所有入口同時隱式開啟上傳。
- 已完成圖片需在 description／detail 同一個安全 Markdown resolver 呈現；新增圖片插入位置、刪除引用、草稿取消、修改後預覽都不應破壞原生 Undo、中文輸入法、Ctrl/Cmd+B／I、Tab 導覽。
- 維持版本／revision 衝突處理：背景輪詢不可覆寫包含 pending 圖片的草稿，失敗可保留草稿與已成功上傳的附件引用。
- 手機沒有 Cmd+V 流程時提供可點擊且可達的選檔入口；拖曳不是唯一操作方式。
- 讀圖採 lazy loading、限制內容容器最大尺寸、避免造成整頁橫向溢位或大量並發載入。縮圖生成和影像轉檔先不預設，依真實圖片容量與效能再評估。

### 4.3 必過端到端驗收

- 桌機貼上真實 PNG（clipboard API）、拖曳 JPEG、手機選擇 WebP → 顯示上傳 → 建卡 → 重載 → 縮圖／點擊放大。
- 建卡取消、單張失敗、多張部分成功、逾時、沒有權限、跨 Workspace／Project 嘗試、附件已刪除的情況皆有可理解回饋。
- 相同附件 URL 不可被另一個未授權使用者直接讀取；即使曾有權，撤權後也不得透過長期公開連結繼續讀取。
- 有效歷史內容、既有純 Markdown、惡意 HTML／XSS、外部圖片 URL 的顯示與安全策略不得回歸。
- 上傳與建立 Issue 的網路時序交錯時，不得漏附件、錯綁或重複綁定；檔案若保存成功但建立 Issue 失敗，待清理機制確實執行。
- 使用獨立合成資料與假儲存跑完整流程；**沒有 Cloudflare R2 資訊時也應能通過**。

## 5. P1：建卡、編輯與卡片詳細頁的使用體驗

以原本 Vue／Vuetify 的資訊密集型工作畫面為基礎；先改善高頻率流程，不先引進整套富文字框架。

### 5.1 建立／編輯面板

- 標題、描述與狀態／優先級／負責人等屬性有清楚主次，不讓大量欄位壓過最主要的任務內容。
- Markdown 工具列針對最常用的功能重新安排；圖片入口與進度清楚、圖示／文字不造成工具列在 390px 溢出。
- 明確顯示未儲存／儲存中／成功／失敗；錯誤後保留所有已填欄位與 pending 圖片，不因 API error 清空草稿。
- 重複送出、快速連點、切換 Workspace／Project、離開確認、revision 409 及重新提交必須通過既有測試；新增圖檔不可以繞過這些契約。
- 對容易出錯的欄位使用就近提示與 focus，避免所有錯誤只出現在畫面頂部。

### 5.2 Issue 詳細頁

- 先有清楚的 Issue identifier、title、狀態、描述／圖片；右側 metadata 維持可讀但不喧賓奪主。
- 留言、歷史、關聯、工時採既有按需／分頁 API，不因顯示附件退回整包 Workspace 或整個活動記錄。
- 圖片縮圖可在目前上下文直接觀看與放大；失效附件有明確替代內容，不讓 Markdown 留下一片破圖。
- 儲存、關閉、切換標籤、回上一頁與深連結不要製造意外草稿遺失；保留 ACL、鍵盤與手機可達性。

## 6. P1：設計美感、資訊密度與一致性

**不是重新做品牌識別，也不是照搬 Linear／Jira 的畫面。** 保留現有紫色品牌、資訊架構與使用者已核准的 UI 方向；針對真實高頻頁面做有證據的精修。

| 畫面 | 建議改善方向 | 必須保留 |
| --- | --- | --- |
| 建卡／編輯 | 表單主次、描述編輯空間、工具列排版、圖片預覽、儲存回饋、欄位間距 | 原有資料欄位、ACL、草稿、鍵盤 |
| 單卡詳細頁 | 描述可讀寬度、附件呈現、右側資訊密度、活動分區、主要操作位置 | 完整內容、revision、深連結、手機 |
| 任務列表 | 行高、標籤與狀態語意、長標題換行／截斷、Hover／Focus、空／載入／錯誤 | 現有篩選、排序、SQL 分頁、快速操作 |
| 看板 | 卡片文字層次、狀態辨識、欄位間距、密度選項、操作回饋、手機捲動 | 全量卡數、Epic／泳道、拖曳、鍵盤、分批掛載 |
| 共用編輯器 | 工具列與 icon 系統一致、可點區域、預覽明確、上傳與錯誤狀態 | Markdown 原稿、安全渲染、共用契約 |

設計審查基礎：現有 `docs/UIUX審查報告-2026-11-03.md`、`docs/Unslop審查報告-2026-11-11.md`、已安裝的 UI UX Pro Max／Design Taste／Vue Best Practices／Ponytail 等，以共用 registry 的觸發矩陣為準。Hallmark／Unslop 需要符合其明確適用條件，不能因「想變好看」就未經同意重設所有頁面。

**重點區分**：
- 視覺回歸證明「沒有意外退化」；不等於美感提升或操作效率改善。
- 對有意識的 UI 改版，先提供前後對照與候選截圖，讓使用者審閱後才能核准新的 baseline；不得自動覆蓋原 baseline／放寬比較門檻。
- 至少覆蓋 1440×900、768×1024、390×844，含 loading、empty、error、dialog、圖片上傳中／失敗與權限受限狀態。
- 不引入沒有實際用途的裝飾、過度動畫、巨大空白 Hero 或任意外部字型。

## 7. P2：後續可擴充但不阻塞 MVP

- PDF／ZIP 等一般附件支援：另外定義安全預覽／下載、不在 Markdown 中內嵌執行未知內容。
- Issue 留言直接貼圖及附件、Todo 圖片附件：確認共用附件模型後擴展。
- 正式公開 PAT Attachment API：當 Multi-Agent 要把測試截圖寫入 TaskTracker 時再補；需另定義 read/write scope、OpenAPI、requestId、防濫用、有效權限交集與外部客戶端驗收，不因內部圖片 API 自動開放。
- R2 直傳或 multipart、CDN 受保護的圖片快取、影像縮圖／轉碼：先量真實流量、成本與隱私，未經需要不要擴大複雜度。
- Rich Text Editor（例如其他編輯器）僅屬候選，先保留 Markdown 原文與相容契約。

## 8. 建卡建議：獨立可驗收的工作包

下表是**暫定工作包名稱**，不是已建立的 TT 編號。Agent 應先查重並於 TaskTracker 建卡／補卡，明確記錄依賴及驗收，所有新待辦預設 open／未指派。

| 順序 | 建議工作包 | 優先 | 驗收關鍵 |
| --- | --- | --- | --- |
| 1 | 圖片附件資料模型、ACL、生命週期與私有 Storage 抽象 | P0 | 經授權儲存／讀取／綁定／清理、跨租戶拒絕、隔離 MySQL |
| 2 | 內部附件 API 契約與本機私有儲存實作 | P0 | API 不依賴 R2 憑證、Storage Fake／HTTP 回歸 |
| 3 | MarkdownEditor 建卡貼圖、拖曳、選檔、pending 草稿 | P0 | Cmd/Ctrl+V → 新卡 → 重載；取消／錯誤／重試 |
| 4 | 私有附件 Markdown 顯示、縮圖與放大 | P0 | 授權內容可預覽、撤權後不可讀、XSS 安全 |
| 5 | Issue 建立／編輯操作 UX 精修 | P1 | 流程與視覺對照、草稿／409／鍵盤／手機 |
| 6 | Issue 詳細頁資訊層次與附件整合 | P1 | 描述／附件／留言／metadata、按需查詢 |
| 7 | 列表／看板的密度、狀態與視覺精修 | P1 | 視覺候選、Drag&Drop、分頁、可及性 |
| 8 | **Cloudflare R2 配置接線與隔離 Bucket 驗收** | P1（受資訊阻塞） | 使用者提供資訊後才連線；既有附件遷移另外驗 |
| 9 | 留言圖片、通用附件與公開 PAT 附件 API | P2 | 各功能獨立契約及安全驗收 |

可按共用附件後端能力合併第 1、2 包，但**不應把 UI、美感、R2 供應商憑證、通用附件與公開 Agent API 合成一張無法驗收的大卡**。

## 9. 統一驗收、安全與交付規範

- 開工前讀 root／frontend／backend AGENTS、共用 Skill matrix、docs/project-engineering-bindings.md、docs/markdown-editing.md、docs/visual-regression.md、docs/query-api-v1.md 與現有附件／功能卡。
- 技術：Laravel DI／Constructor Injection、繁體中文 PHPDoc、FormRequest、DTO／Service／Repository 合理分工；最小必要介面，不引入空抽象。
- 資料：MySQL 使用隔離 `tasktracker_test` schema；儲存用 fake／私有測試 disk；**不能對現用 MySQL seed、RefreshDatabase，也不能清空共享 Redis**。
- 安全：Session／CSRF、Workspace／Project scope、變更前後授權、revision／requestId、Storage bucket 私有、Secrets 不入 Git；取消／失敗必能清理 pending 孤兒附件。
- 資料量：新增 Issue／附件 API 分頁／限定欄位、SQL 依 scope 篩選，描述與列表不夾全部圖片 binary／base64；有具體 Network rows／bytes／查詢門檻。
- 體驗：真實隔離 Laravel＋MySQL Playwright 走完整貼上與顯示旅程；不靠 mocked response 自稱整合成功。三尺寸、鍵盤、可及性、草稿與版面回歸。
- 視覺：先產候選，對刻意改版採人工審核；本機 macOS baseline 不等於 GitHub Linux runner 已驗收；不要以更新 baseline 消除實際差異。
- 文件：實作後更新 Markdown 安全策略、附件路由／契約、正式環境 R2 設定步驟、保留／刪除／遷移政策與測試證據。
- 追蹤：每張卡依真實進度更新狀態、留言驗證結果、獨立 Commit；不因只寫文件或建好骨架就標 closed。原專案 Git 規範不自行 push；後續是否 push 由使用者當時指示。

## 10. R2 資訊尚未到齊時的完成定義

**可先完成**：附件 Model／Migration、授權、Service、內部 API、私有 Storage 實作／Fake、前端 Cmd/Ctrl+V／拖曳／選檔、pending 與清理、圖片受權顯示、隔離整合測試與文件。

**必須明確留待日後**：建立／挑選 Cloudflare R2 Bucket、憑證、部署 Secrets、實際 R2 上下載測試、雲端來源／CORS 設定、遷移既有 local 附件、雲端流量與成本驗證。

**產品 MVP 不應因 R2 尚未設定而停工，但也不得把「本地假儲存測試綠燈」描述成 R2 已部署成功。**
