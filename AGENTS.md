# TaskTracker 開發指引

## 專案定位與目前階段

TaskTracker 是專案管理平台 Side Project, 目前是本機協作試作, 提供工作空間／帳號、專案入口、Epic、任務與自訂看板、工時月曆及通知預覽

已確認方向：

- 使用者過去使用過 YouTrack 且喜歡, 產品設計優先研究其任務管理與操作方式
- 競品調研至少包含 YouTrack、OpenProject, 以官方文件為主要來源
- 初期參考 `../GuildManager` 的 GAS 架構；2026-10-19 已改選 Vue＋Vuetify／Laravel 前後端分離, 舊方向留沿革
- 介面、文件與操作訊息預設使用繁體中文, 顯示時區採 `Asia/Taipei`

使用者已要求先做本機第一版, 以任務清單與看板試用；甘特圖暫不製作。GAS `.gs` 產檔、雲端儲存與部署等本機版本確認後再處理

使用者已確認以專案作為入口：首頁先建立／選擇專案, 點入專案後才呈現任務清單、看板與進度。任務操作、我的任務與完成任務都限於目前專案, 不自動回到跨專案任務首頁

尚未確認：正式功能範圍、使用人數、團隊登入方式、正式試算表與 Apps Script 專案

2026-10-06 補充：產品按 SaaS 服務規劃, 不限定 Google 群組／帳號；本輪實作 Epic 與子項目、自訂狀態、團隊工時月曆及通知預覽。登入、工作空間與租戶隔離優先, 本機 prototype 不視為正式 SaaS 部署。詳見 `docs/collaboration-design.md`, 任意 SMTP 需獨立後端／HTTP relay, 不假定 MailApp 是 SMTP 客戶端

文件中的 MVP、角色、欄位及目錄設計都是建議, 未經使用者採納前不可視為已確認需求

## 開始工作

使用者已要求邊討論邊更新 `docs/產品討論紀錄.md`。後續產品討論先讀該文件, 每輪將新增需求、選項、決策理由與待討論問題同步整理, 更新日期。明確區分使用者已確認、助理建議與目前實作；不把追問、沉默或已試作當成採納。變更決策時保留沿革, 確認後同步受影響的設計文件

已確認的目標授權原則：角色只是權限組合, 由有效 permission ID、Workspace／Project scope 及資源條件決定能看與能做。新授權設計的 policy／API／畫面不得以角色顯示名稱或角色 ID 作放行捷徑, roleId 只用於查角色的權限映射。Laravel 已建立集中權限入口，Project ACL與固定矩陣已落地；自訂角色／權限編輯及正式部署另行設計

目前本機實作（2026-10-19 已改為前後端分離）：

- `frontend/`：Vue 3＋Vuetify 4＋Vite, 登入／專案表單已使用 Vuetify, 既有清單／看板保留專用元件與 CSS
- `backend/`：Laravel 13 JSON API, PHP 8.5 Docker, SQLite 與 Laravel session／CSRF；不使用 Inertia, Node 僅用前端工具鏈
- 根目錄 Compose 提供 frontend（5174）、backend（3002）、queue、websocket／Reverb（8080）與 Redis。主機接口只監聽 loopback, Redis 只在容器網路
- `cd frontend && npm run dev`／`npm run build`；`docker compose exec backend php artisan test --do-not-cache-result` 驗證 Laravel API／隔離 SQLite
- `frontend/tools/browser-check.js` 以 `E2E_API_URL` 接隔離 Laravel 服務, 使用 5175 埠, 不使用業務 mock 或使用者資料庫
- 預設空資料, 可手動載入示範專案或 `tasktracker:seed-demo`；指定 Seed 建 Jack／f-ws／first／second, 密碼只讀本機 TASKTRACKER_SEED_PASSWORD, --reset 只重設此 seed namespace；首頁先建／選 Project, 點入才有任務功能, 所有操作保留目前專案上下文
- `backend/app/Repositories/RecordStore.php` 由伺服器選定Workspace，經RelationalRecords／Children寫入明確業務表；AccountService從Laravel session推導操作者與空間，不信任前端身分。2026-11-04新增22張關聯表、Eloquent模型及FK／索引，舊records只供歷史遷移／回退／匯入；見docs/database-design.md
- 租戶間隔離，ProjectAccess逐案檢查有效permission／scope及作者條件；Project ACL／固定角色矩陣已落地。新關聯表以複合外鍵再限制同Workspace／Project，不以FK取代權限檢查
- 舊 Node API／測試由 PHP 取代, 原 SQLite 與舊 Docker volume 保留；匯入指令只接受空目標、不覆蓋來源, 保留作者歷史, 舊帳號登入後升級雜湊
- 2026-10-20 類型固定 Epic／Task／Bug, feature 透過 migration／匯入共用升級器轉 task 並保留歷史與 revision 保護；自訂類型僅記構想, 不提前實作
- 狀態用固定 ID＋category 判斷完成, Epic 估時／工時只加總子項目。2026-10-20 改 parentIssueId 一層子任務與 relatedIssueIds 雙向關聯, 移除前置／DAG／結案阻擋；子項跟父項 Epic, 狀態獨立, 舊 blockedBy 透過 migration 轉關聯並保留歷史
- 工時只能修改自己有效紀錄, 作者限制不因管理者而略過；每人每日最多 24 小時, 工作日採 Asia/Taipei
- 2026-10-20 使用者要求移除視圖／常用篩選保存：介面與三個 RPC 停用, getWorkspace 不回傳, 舊紀錄原地保留不使用；即時搜尋／篩選保留。首頁個人卡片篩選小清單只記構想, 未要求實作前不製作
- 2026-10-20 看板以目前 Project 為範圍：/boards 列表、/board Default、/board/<id> 自訂；selectedIssueIds 明確收錄, Epic 動態展開／去重, 不複製卡片。board.manage 經集中權限檢查, 原型映射任務編輯成員；新增卡片可帶 boardId 同交易收錄, 移除看板不刪 Issue
- 看板以Reverb私有Project頻道／Redis Queue在commit後通知API重讀，HTTP訂閱保留Session／CSRF／permission／scope授權，事件只含無內容訊號；重連補抓及30秒輪詢備援，未保存表單不得偷換基準revision。非看板仍輪詢，細節與限制見docs/board-realtime.md
- SMTP 只 verify, 通知 preview, 不在業務 API 同步寄信；秘密由環境或忽略的持久化 volume 提供, 不進前端或 image
- 本機確認前不產 `.gs`／GAS manifest／雲端 build 或部署指令

1. 讀本文件、`README.md` 及當次工作相關文件, 檢查 `git status` 與既有差異
2. 多步驟工作先更新 `plans/<目前分支>/plan.md` 與 `step.md`, 記錄需求、假設、步驟、驗證與待確認事項
3. 依當次指示完成工作, 可以自主處理可逆的文件與本機修改, 不把調研擴張成未要求的功能實作
4. 將使用者確認、競品事實、架構推論與提案分開記錄, 查證可變動資訊並附來源及調研日期
5. 只修改任務相關內容, 保留使用者既有變更, 不修改參考專案
6. 完成後回報成果、驗證方式與實際限制, 不把本機或 mock 驗證說成正式部署成功

## 架構方向

使用者已選 `frontend` Vue＋Vuetify、`backend` Laravel 的前後端分離架構, 取代原 Node／GAS 雙後端主方向。根 README 為目前啟動入口, 歷史 GuildManager 參考保留在 `docs/architecture.md`, GAS 不同步開發

- 前端只透過 `frontend/src/api/` 呼叫 JSON API, 不直接操作資料庫或匯入 PHP 業務服務
- Laravel controller 負責 HTTP 邊界, service 負責規則, repository 負責隔離存取；權限與業務寫入在同一交易驗證
- 同 origin 反向代理不影響程式與部署分離；如跨 origin 部署須明確設計 Cookie／CSRF／可信來源
- Reverb、Queue 使用同一 backend image, 各自獨立服務；Redis 用 Queue／Cache, 業務資料以 SQLite 為準
- 保留 API DTO、revision、requestId、日期與狀態語意, 不用更換框架作為清空資料或放寬權限的理由
- 不修改或複製 GuildManager 的資料、帳號、憑證與部署設定

## 開發規則

- JavaScript 預設 2 格縮排、單引號、分號, 優先使用 `const`
- 函式與變數使用 `camelCase`, 常數使用 `UPPER_SNAKE_CASE`, 資料欄位命名由契約文件統一
- 元件負責顯示與操作狀態, 後端負責權限、驗證與資料存取；元件不直接操作 Sheets 或資料庫
- GAS `.gs` 共用全域環境, 避免名稱衝突與載入時執行資料寫入
- `google.script.run` 是非同步 RPC, 必須處理成功、失敗、重複提交與過期回應
- 介面只傳可序列化資料, 時刻使用 ISO 8601 字串, 日曆日期使用 `YYYY-MM-DD`, 不傳 `Date`、`Range` 或 `Sheet`
- 正式寫入使用固定 ID、版本檢查及重試識別；跨工作表寫入須有明確的部分失敗處理
- 權限由後端檢查, 不以隱藏按鈕、前端傳入的角色或網址參數判定權限
- 不假設 GAS 以部署者執行時可以取得訪客 email, 登入方案需獨立設計與部署驗證
- 使用者文字預設以文字呈現, Markdown／HTML 顯示須處理不可信內容
- 憑證、session、私有 Script Properties、資料庫與備份不得寫入前端 bundle 或 Git
- 維護 lockfile, 實作時再查證套件版本與執行環境, 不照抄參考專案的版本

## Git 與階段提交

- 使用者已要求每完成一個階段就建立一個本機 commit, 此授權持續適用於本專案, 不需每次重新詢問
- 階段以可獨立說明與驗證的成果為單位, 例如完成規劃、建立原型或調整一個使用流程, 不以每次存檔作為階段
- 開始前檢查分支、工作區與既有差異, 保留使用者的改動
- 完成階段後先執行適合該變更的驗證, 明確指定該階段的檔案, 檢查 staged diff 與空白後立即提交
- `plans/<分支>/plan.md`、`step.md` 隨相關階段提交，保留規劃與續接歷史，不預設排除；不套用其他專案的 planning 排除／封存流程（2026-11-05 使用者更正）。
- Git 操作直接在本專案原有 repository 進行，操作前確認根目錄、分支與既有差異；不在 `/private`、`/tmp` 或其他暫存目錄另建 clone／worktree 規避限制。
- commit 訊息採 Conventional Commits, 用繁體中文說明成果, 例如 `feat: 調整專案入口與任務導覽`
- 不提交憑證、資料庫、依賴、截圖、暫存檔或編譯產物
- 保留每階段的歷史, 不自行 amend、squash 或重寫已提交內容
- 使用者授權的是本機 commit, 遠端 push 依另行指示執行
- 完成回報包含 commit 短雜湊、成果與驗證結果；未能提交時說明原因

## 驗證與交付

- 純文件工作檢查內容一致性、來源、相對連結與差異, 不額外建立測試套件
- 功能實作驗證實際使用流程, 依風險補權限、版本衝突、防重複提交與資料保存測試
- 前後端必須符合相同 API 契約, PHP／SQLite 的 API 測試與瀏覽器整合互補, mock 不取代真實整合驗證
- 資料遷移以隔離 SQLite 驗證, 不用使用者現有資料做會破壞性的測試；TestCase 在 bootstrap 前強制 putenv／SERVER／ENV 及 memory config, 不只依賴 phpunit.xml。隔離 Docker helper 必須覆寫 com.docker.compose.project／service 標籤, 避免繼承映像的正式服務標籤
- 編譯、上傳、初始化資料與更新 Web App 部署是不同動作, 各自記錄驗證結果
- 本專案依上述規範每階段建立 commit, 不沿用其他專案的推送／部署授權
- 不刪除 `AGENTS.md`、skill、規劃文件或使用者資料來清理工作區

## 文件位置

- `docs/產品討論紀錄.md`：持續更新的產品討論、決策、個人／企業使用模型與待討論清單
- `README.md`：專案入口與目前狀態
- `docs/competitor-survey.md`：競品比較與功能取捨
- `docs/architecture.md`：GuildManager 參考結果與本專案架構提案
- `docs/database-design.md`：現行關聯式資料表、Eloquent關聯、外鍵／索引與遷移／回退保護
- `plans/<分支>/plan.md`、`step.md`：當次工作範圍與續接紀錄

## Skill 來源與適用範圍

2026-11-05 使用者確認：後續工作只參考本專案底下的 skill，以 `.agents/skills/` 的入口及 `skills/` 內的專案副本為準；兩者指向同一份內容時不重複套用。

- 除非使用者在當次任務明確指定，不讀取或套用專案外的 skill，包括 `/Users/josh/Projects/workspace/skills`、使用者家目錄及全域安裝的 skill。
- skill 內的來源／沿革連結不代表授權載入外部規範；所需規則應由本專案的 skill／references 明確提供。缺少必要內容時先說明，不自行回到共用目錄補規則。
- 本專案 `AGENTS.md`、相關子目錄指引與使用者指示優先；不因專案外 skill 的提交、封存、權限確認或工作流程規則改變本專案做法。

## UI／UX skills

使用者已指定 UI／UX 工作使用 `.agents/skills/ui-ux-pro-max/SKILL.md` 及 `.agents/skills/design-taste-frontend/SKILL.md`。後者對 TaskTracker 採 app-screen 流程；保留使用者產品決策與 Vue／Vuetify, 不因 house style 自動換色。審查要有實際登入畫面／操作證據, 區分可用性問題與審美偏好；只要求審查時先交報告, 不直接修改產品 UI。來源與使用邊界見 `.agents/skills/README.md`。


## 後端開發 skill

後端Laravel／PHP開發、review與測試設計使用 [backend-development-standards](skills/backend-development-standards/SKILL.md)，由`.agents/skills/backend-development-standards`提供專案入口。這是2026-11-04由workspace共用skills搬入並適配的副本，來源與差異見 [校驗清單](skills/backend-standards-sources.json)；使用細節及現行授權狀態以 [backend/AGENTS.md](backend/AGENTS.md) 為準。

上方2026-10-19的原型狀態保留沿革；2026-10-30已完成Project ACL及固定矩陣，不應再當成空間內共享。引入規範不代表現有程式已全面合規，使用者於2026-11-04後續已要求開始調整，核心流程已分層，現況見docs/backend-development.md。
