# Project ACL 契約

2026-10-30, 本輪沿用使用者已確認的 Workspace owner／manager／user 與「角色提供權限, 依有效 permission＋scope＋資源條件授權」原則。以下是這次選定的最小實作矩陣, 非完整企業權限系統

## 授權範圍

- Workspace owner／manager 經明列的 Any permission 存取同空間所有專案, 不要求逐案加入；owner 另保留目前本機 SMTP verify, manager 無此權限
- Workspace user 只有 `workspace.read`, 加入空間後可登入及看自己的身分, 不自動看任何 Project
- Project grant 只在該專案有效, 也要求仍為有效 Workspace 成員。移出專案、降權或失去空間資格, 下一個 API 及舊 requestId 重試都重新判斷
- `roleId` 只查 registry 的 permission 組合, 所有業務呼叫依 permission 授權。沒有星號、角色排序、帳號名稱放行或個人 deny 覆寫

## 固定 Project 角色

| 操作 | lead 負責人 | maintainer 專案管理者 | developer 開發者 | reader 讀者 |
| --- | --- | --- | --- | --- |
| 讀取 Project、Issue、Epic、看板及留言歷史 | 可 | 可 | 可 | 可 |
| 建立／修改 Issue、狀態切換及留言 | 可 | 可 | 可 | 不可 |
| 填報／修改／作廢本人有效工時 | 可 | 可 | 可 | 不可 |
| 讀取專案團隊工時 | 可 | 可 | 可 | 不可 |
| 修改專案資訊、自訂狀態、看板收錄 | 可 | 可 | 不可 | 不可 |
| 加入、改角色、移除 Project 成員 | 可 | 可 | 不可 | 不可 |
| 讀取專案全部通知 preview | 可 | 可 | 僅本人 | 僅本人 |
| 邀請新 Workspace 成員／建立 Project | 另須 Workspace 權限 | 另須 Workspace 權限 | 不可 | 不可 |
| 修改／作廢他人工時 | 不可 | 不可 | 不可 | 不可 |

負責人與 PM 這輪共用管理權限, 不為名稱不同製造權限高低。Project `leadId` 是責任資訊, 不自動授權；更換責任人須選可存取專案的有效成員。讀者可看到 Issue 內的預估欄位, 不回傳實際工時紀錄；不提供匯出功能。開發者目前可查看團隊工時, 與管理別人的工時分開

## Permission 目錄

Project permissions：`project.read`、`issue.read`、`issue.write`、`project.update`、`project.statuses.update`、`project.members.manage`、`board.manage`、`timeEntry.readOwn`、`timeEntry.readTeam`、`timeEntry.writeOwn`、`notification.readTeam`

對應的 Workspace Any IDs：`project.readAny`、`issue.readAny`、`issue.writeAny`、`project.updateAny`、`project.statuses.updateAny`、`project.members.manageAny`、`board.manageAny`、`timeEntry.readOwnAny`、`timeEntry.readTeamAny`、`timeEntry.writeOwnAny`、`notification.readTeamAny`

Workspace 專屬 IDs：`workspace.read`、`workspace.members.read`、`workspace.invite`、`project.create`、`smtp.verify`。Project 成員管理不授予 Workspace 邀請或升權能力；可授予的角色須是操作者在目標 Project 有效權限的子集合, 調整／撤回既有角色也檢查此條件

## API 與可見資料

- `getWorkspace` 回傳當次可讀 Project, 每個 Project 附 `permissions`、`accessSource`（workspace／project）、`memberIds`（可被指派的有效成員）。不把某個 Project 權限攤平到 `meta.permissions`
- `meta.permissions` 只表示 Workspace 權限。一般成員的 `members` 僅包含自己及可見 Project 的可存取成員, Workspace 成員閱覽權另可讀空間目錄
- Issue、父子／Epic／關聯、Default／自訂看板、工時與通知, 依相同可讀專案及操作權限過濾, 不能用數量、ID 或深連結取得未授權專案資料
- 沒有專案讀取資格的資源讀寫回通用 404；專案可讀但欠缺操作 permission 回 403。未知 ID／跨 Workspace 不回傳資源內容
- 寫入與重新讀取成員資格、授權、revision 及 requestId 在同一交易。先驗 scope／作者條件, 才讀舊重試結果；不以快取重播已失權資料
- 新指派的 Issue assignee 或 Project lead 必須有該 Project 的讀取資格。移除成員不改寫舊指派、留言、工時或歷史；舊指派不構成存取權或通知訂閱

## Project 成員操作

| RPC | 輸入 | 回傳 |
| --- | --- | --- |
| getProjectMembers | projectId | revision、members、可選 Workspace candidates、可授予 roles、history |
| setProjectMember | projectId、userId、roleId、expectedRevision、requestId | projectId、最新 ACL revision |
| removeProjectMember | projectId、userId、expectedRevision、requestId | projectId、最新 ACL revision |

候選僅限有效 Workspace 成員, 這輪不擴張邀請既有帳號或切換 Workspace 的流程。名單的 revision 與 Project 內容版本分開, 避免狀態設定／新增卡片造成成員表單不必要衝突；任意成員修改仍會讓舊成員表單衝突。移除以 tombstone 保存, 歷史保留操作者 ID／名稱、時間、前後角色；Workspace 管理者的繼承權限不因移除 Project grant 消失

## 資料升級及 Seed

Migration `2026_10_30_000000_add_project_acl` 與舊匯入共用 `ProjectAclUpgrade`：

- 不刪除或重新建立 Project、Issue、看板、工時、留言及 requestId 歷史
- 舊 admin 映射 manager；member／暫時的 Workspace maintainer／developer 映射 user；未知角色不產生權限
- 不把所有 Workspace user 自動加入全部 Project, 不根據 `leadId`／assignee／歷史作者推測 Project grant。一般成員升級後可能沒有專案, 需由 Workspace 管理者明確加入；管理者保有原資料存取
- 只有明確的測試 namespace `f-ws` 與 Seed 穩定帳號 ID 作對照：maintainer 加入 FIRST／SECOND 為專案管理者, developer 只加入 FIRST 為開發者。兩者 Workspace role 為 user；owner／manager 不需 Project grant
- 既有 second 卡片若曾指派 developer, 升級不重寫；developer 沒有 second 存取權。新 Seed／reset 的 second 工作與工時改由 maintainer 負責
- 系統升級及 Seed 新 grant 保留記錄；重跑不重新加入已撤回的成員, 不覆蓋既有自訂角色指派或帳密。rollback 不猜測舊共享模型, 回舊程式應還原升級前備份

## 驗收與界線

後端以隔離 SQLite 驗證固定角色、Any／Project 授權、無專案空結果、不可見 ID／衍生資料、撤權重試、降權、作者限制、授權版本衝突、未知角色及跨空間, 並檢查 migration 重跑及業務資料不變

前端已使用每個 Project 的 capabilities；撤權後重新整理／輪詢會移除私有內容及編輯草稿, 不以「保留草稿」繼續展示已無權存取的專案。後端立即於下個請求生效, 前端依刷新或目前 30 秒輪詢反映, 不宣稱已接即時推播

驗證結果：隔離 Laravel 測試 24 項／576 斷言通過；真實 API＋Chromium 完整回歸通過, 包含未加入空狀態、同人多專案不同角色、讀者完整頁／看板、成員衝突草稿、撤權時未儲存任務與手機版。前端 build 通過。本機升級前已用 VACUUM INTO 備份, 升級及非重設 Seed 後逐筆比對原業務 records／users 不變（2 專案、14 Issue、6 筆工時）, 四帳號真實登入範圍符合上表。未驗證正式部署或即時推播

本輪不做 Workspace owner 移轉、manager 任命、每個欄位 ACL、自訂角色編輯器、單筆 Issue 分享、外部 Guest／公開專案、群組、跨專案看板、工時匯出、附件或通知寄送
