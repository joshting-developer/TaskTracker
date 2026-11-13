# 看板列表與收錄設計

更新：2026-11-05（Asia/Taipei）

## 使用者確認

提供看板列表, 有固定 Default 看板包含全部範圍內卡片；可以建立自訂看板, 明確選擇 Epic 或一般卡片, 選 Epic 時收錄它底下的卡片

使用者已回覆確認 Default 包含「目前專案的所有卡片」。看板綁定 Project, 不新增跨專案或跨 Workspace 看板；沿用目前專案的狀態欄。本文件其餘操作細節是本輪實作選擇, 非使用者逐項指定

2026-11-05新增：[看板即時同步](board-realtime.md)，使用私有Project頻道提示API重讀，保留收錄、ACL、revision及未儲存表單語意。

## 2026-11-05 卡片狀態操作簡化

使用者要求移除看板卡片底部狀態選單。現在拖曳卡片至其他狀態欄位更新狀態；卡片不重複顯示選單或狀態標籤。只讀者不能拖曳，卡片無障礙名稱包含狀態。手機／鍵盤可由標題開完整頁，使用原有「變更狀態」，編輯抽屜也保留原有欄位；本輪不修改API或版本衝突保護。下方早期保留狀態選單的描述為沿革，由本段更新。

## 收錄語意

- 看板引用既有 Issue, 不複製任務、狀態、留言、估時或工時；同卡在多個看板仍使用同一 ID 與 revision
- 自訂看板保存使用者明確選擇的 Issue ID, 可包含 Epic、Task、Bug。選 Epic 時動態展開目前 epicId 指向它的執行卡, 不保存當下的一次性快照
- 後來掛到已收錄 Epic 的卡片自動出現, 移出後自動離開；另被明確選取的卡片則仍保留。Epic 與單卡重複收錄時只顯示一次
- 一般父 Task／Bug 的子任務各是獨立卡片, 單選一般卡片不隱含加入其他卡片；選 Epic 已涵蓋隸屬其 Epic 的父項與子項。一般關聯連結也不擴張收錄
- 解除收錄只更改看板設定, 不刪卡、不解除卡片本身的 Epic／父子／關聯。Default 是系統入口, 不提供改名、改收錄或刪除
- Epic、Task、Bug均可在看板狀態欄顯示。泳道預設不分組；選依Epic分組時，Epic本身與所屬卡片放同一泳道，Epic本身只出現一次。單選一般卡片只引用其Epic作泳道標題，不額外收錄Epic。收錄與歸類分開，卡片狀態或Epic變更仍影響其他看板；拖曳Epic只更新狀態，不把Epic歸到另一個Epic。
- 搜尋／即時篩選再與看板收錄範圍取交集, 不因清除篩選擴張至未收錄卡片

## 資料與操作契約

自訂看板包含固定 ID、所屬範圍、名稱、明確選取 ID、revision、建立者與時間。Default 可由伺服器產生, 避免新舊專案漏建或重建重複資料。自訂看板提供建立、編輯收錄及移除入口；移除看板保留 Issue

設定變更透過 Laravel API, 交易內檢查有效 permission、目標範圍、ID 與 revision, requestId 防重複。只有前端隱藏選項不構成授權。跨 Workspace／未加入 Project 一律拒絕；可讀 Project 但缺 board.manage 不可管理收錄, 看板不能授予額外卡片讀寫權

這是明確收錄卡片的看板, 不恢復已移除的命名篩選／視圖功能, 也不實作首頁個人小清單。看板獨立網址需保留卡片深連結、重整、返回與未儲存表單保護

## 驗證目標

Default 全部範圍、空看板、Epic 動態收錄與未來新增卡片、單卡重複去重、移出 Epic 的明確收錄例外、一般子任務不隱含加入、取消收錄不刪卡、跨空間拒絕、版本衝突保留草稿、防重複提交、桌面／手機及既有拖曳操作


API：getWorkspace.boards 包含每個可讀 Project 的虛擬 Default（id=default、revision=0、isDefault=true）與未封存自訂看板。createBoard／updateBoard 使用 name、projectId、selectedIssueIds；更新及 archiveBoard 帶 id、expectedRevision、requestId。明確收錄上限 1000 項, Epic 動態子卡不計入上限。不需要新 migration, 延用 Workspace namespace 下 boards payload。Default 不寫入資料庫

2026-10-30 已加入 Project ACL：board.manage 由專案負責人／管理者及 Workspace Any permission 提供, 開發者只有 issue.write, 讀者只能看。卡片拖曳／欄內建立依 issue.write, 自訂看板內新建另需 board.manage。後端依 permission＋scope 判斷, 不以角色名、createdBy 或收錄 ID 放行；見 [ACL 契約](project-acl.md)

自訂看板內新增 Issue 可帶 boardId：同交易檢查看板、額外 board.manage 權限, 若已有 Epic 覆蓋則不展開快照, 否則明確收錄新卡並增加看板 revision。失敗會連同卡片寫入回滾, 重試沿用 createIssue 的 requestId。不修改任務歸屬以迎合看板


## 介面與路由

專案側欄「任務看板」開啟 `#/projects/<projectId>/boards` 列表, Default 固定第一張, 其餘自訂看板顯示名稱、明確選取數及目前卡片數（含Epic）。`/board` 保留為 Default 舊深連結, `/board/<boardId>` 是自訂看板；後面可接 `/issues/<issueId>`, 開啟／關閉詳細頁或重整不遺失看板位置。看板被其他人移除時回該專案列表並提示, 不靜默改看其他專案

「新增看板／編輯收錄」搜尋目前專案的卡片並多選, 顯示 Epic 動態收錄說明及目前卡片數（含Epic）。保存衝突保留草稿, 背景刷新不換基準 revision, 關閉重開才使用新版。清除收錄可留下空看板, 不刪除任何卡片。手機表單及列表換行, 卡片仍在看板局部橫向捲動

每個人／專案／自訂看板各有本機泳道、密度及收合偏好；Default 延用先前的瀏覽器偏好。自訂看板只顯示已收錄 Epic 或收錄卡片所屬 Epic 的泳道；原始卡片關聯資訊仍可開啟。跨泳道拖曳沿用變更原卡 Epic 的既有規則, 移出收錄範圍會離開該看板

## 2026-11-05 閱讀與編輯修正

- 先前看板有固定高度的內層垂直捲動，外頁同時可捲動，外頁到底不等於看板到底。改為頁面統一上下捲動，看板只保留左右捲動，最下方卡片及新增入口不被截斷。不改資料或後端權限。
- 無有效偏好時預設不分組；沿用`tasktracker-board:<userId>:<projectId>[:<boardId>]` localStorage，保存分組、密度、收合。不存在／無效JSON／storage拒絕時安全回預設；不保存內容或授權。已有有效偏好維持上次選擇。
- 精簡只顯示識別碼、類型、標題、負責人及狀態；詳細另顯示優先級、兩行描述、期限／留言與Epic／工時／父子／關聯。Epic工時與估時共用既有`epicSummary`加總所屬卡片，不顯示自身0工時造成誤解。
- 看板移除「快速編輯」文字：雙擊卡片開抽屜，F2或鉛筆圖示亦可編輯，手機可點圖示。單擊標題仍讀完整頁；滑鼠標題點擊等待400ms辨識雙擊，雙擊取消待開閱讀頁，鍵盤及觸控單擊不延遲。狀態選單與關聯按鈕不因雙擊變成編輯入口。只讀者沒有編輯圖示／拖曳資格。列表明示快速編輯維持。
- 看板搜尋及類型／Epic／狀態等篩選會涵蓋已收錄Epic；任務列表仍只列Task／Bug。欄位計數與看板預覽包含Epic，與實際顯示對齊。

驗證：完整browser-check、U01–U08 UIUX及F01–F04可用性回歸通過；build通過。在`frontend/`執行`E2E_API_URL=http://127.0.0.1:13024 npm run test:board`使用真實隔離API，`E2E_PREVIEW=1`可驗編譯版本；18張執行卡與2張Epic、1440／768／390px、實際滾輪／底卡／密度／偏好／storage失效、Epic篩選與加總、雙擊／鍵盤／手機、真實拖曳與自訂看板去重。腳本占用5175，與其他瀏覽器驗證分開執行。產物在忽略的`frontend/artifacts/board-ux-*`，不提交；不使用業務資料測試。

本輪只修改前端／文件，無migration、無Seed reset；本機來源掛載即時套用。長看板原本1440px內層高度700px／內容5293px，改後內層高度等於內容4539px（含Epic及調整後卡片高度），垂直捲動交給頁面；三尺寸底卡完整可見、無整頁橫向溢位。雙skill採既有app-screen方向及單元件人工檢視，未宣稱上游inspector全部規則通過或真人可用性研究。日誌`/tmp/tasktracker-board-ux-*.log`，不提交截圖／量測產物。

## 2026-11-13 看板列表已完成數與編輯收錄入口（TT-248）

- 每張看板卡顯示「N 張卡片」「已完成 M」與一條細進度軌（M／N，軌道只作視覺輔助、`aria-hidden`；數字以`aria-describedby`接到開啟按鈕）。已完成只計狀態type＝closed，不看顯示名稱；Default與自訂看板一致。
- 後端`/boards`摘要與單看板`show`多回`closedCount`，與`issueCount`同一收錄／Epic動態展開／去重條件；列表仍是單一SQL的關聯子查詢（perPage上限100），Default／單看板多一個COUNT，不讀卡片內容。
- 「編輯收錄」由底線文字連結改為卡片底部的次要外框按鈕＋鉛筆圖示，桌機36px、觸控／窄螢幕44px；無`board.manage`與Default仍不顯示。無障礙名稱「編輯收錄 {看板名}」包含可見文字。
- 驗證：`E2E_API_URL=http://127.0.0.1:<隔離埠> node tools/board-list-summary-check.js`（frontend；`E2E_BEFORE=1`拍修改前）驗計數、Epic去重、closed type、權限、鍵盤焦點、三尺寸無橫向溢出與不讀整包Workspace。截圖在忽略的`frontend/artifacts/tt248/`。
- 看板列表頁不在48張核准視覺情境內；既有48張compare通過未變。若要把此頁納入視覺基準，需另行人工核准新情境，不自行洗baseline。

## 封存與還原（TT-283, 2026-11-13）

- 原本的「移除看板」其實是封存（`archived_at`）, 卡片、留言、工時和收錄設定都留著, 只是之前沒地方看也沒辦法還原。這次介面文案改成「封存看板」, 並補上：
  - 看板列表有「使用中／已封存」切換；已封存依封存時間新到舊, 顯示封存日期
  - 已封存看板可以點進去唯讀查看：有提示列, 不能新增卡片、不能拖曳、沒有編輯收錄和封存按鈕；卡片本身沒被封存, 照樣可以在任務列表編輯
  - 有 `board.manage` 的人可以從看板頁或清單還原（`restoreBoard`, 帶expectedRevision和requestId, 重送回原結果）, 還原後收錄設定原封不動
  - Default 本來就不能封存, 也不能還原；封存中的看板不能更新或再次封存（回404「看板已封存, 請先還原」）
- 封存期間Epic新卡要不要動態加入：決定**不凍結**, 查看封存看板時照目前的收錄規則即時算出卡片。理由是卡片本來就沒被封存, 即時算出來的結果跟還原後看到的一致；凍結需要另外存一份快照和同步規則, 成本比收益大。之後如果真的需要「封存當下的樣子」再另外設計
- 驗證：BoardArchiveTest（SQLite與隔離MySQL）、tools/board-archive-check.js（封存文案、已封存清單、唯讀查看、兩個入口還原、三尺寸）
