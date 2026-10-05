# TaskTracker 開發指引

## 專案定位與目前階段

TaskTracker 是專案管理平台 Side Project, 目前是本機協作試作, 提供工作空間／帳號、專案入口、Epic、任務與自訂看板、工時月曆及通知預覽

已確認方向：

- 使用者過去使用過 YouTrack 且喜歡, 產品設計優先研究其任務管理與操作方式
- 競品調研至少包含 YouTrack、OpenProject, 以官方文件為主要來源
- 參考相鄰專案 `../GuildManager` 的 Google Apps Script Web App 架構
- 介面、文件與操作訊息預設使用繁體中文, 顯示時區採 `Asia/Taipei`

使用者已要求先做本機第一版, 以任務清單與看板試用；甘特圖暫不製作。GAS `.gs` 產檔、雲端儲存與部署等本機版本確認後再處理

使用者已確認以專案作為入口：首頁先建立／選擇專案, 點入專案後才呈現任務清單、看板與進度。任務操作、我的任務與完成任務都限於目前專案, 不自動回到跨專案任務首頁

尚未確認：正式功能範圍、使用人數、團隊登入方式、正式試算表與 Apps Script 專案

2026-10-06 補充：產品按 SaaS 服務規劃, 不限定 Google 群組／帳號；本輪實作 Epic 與子項目、自訂狀態、團隊工時月曆及通知預覽。登入、工作空間與租戶隔離優先, 本機 prototype 不視為正式 SaaS 部署。詳見 `docs/collaboration-design.md`, 任意 SMTP 需獨立後端／HTTP relay, 不假定 MailApp 是 SMTP 客戶端

文件中的 MVP、角色、欄位及目錄設計都是建議, 未經使用者採納前不可視為已確認需求

## 開始工作

目前本機實作：

- Vue 3＋Vite, 原生 CSS 與 SVG 圖示, 不使用額外 UI 框架
- Node.js 內建 HTTP API 與 `node:sqlite`, SQLite 保存在忽略的 `data/tasktracker.sqlite`
- 前端 `http://127.0.0.1:5174`, API `http://127.0.0.1:3002`, 只監聽 loopback
- `npm run dev`／`npm run server` 分別啟動前後端, `npm run build` 編譯前端
- `npm test` 驗證 API／SQLite, `npm run test:e2e` 使用隔離資料庫與 5175 埠的瀏覽器驗證
- 預設空資料, 使用者可手動載入獨立示範專案；不自動匯入示範內容
- `#/` 是專案列表, `#/projects/<id>` 是專案工作區, `#/projects/<id>/issues/<id>` 是任務詳細內容；建立專案後先留在列表供使用者選擇
- 本機 scrypt 帳號＋HttpOnly session, Account／Workspace／Member 分離, API 由 session 推導租戶與操作者
- 工作空間內先共享專案與工時, 管理員可邀請新帳號及改狀態, 一般成員只能填寫／修改自己的工時；Project ACL 尚未實作
- `server/auth.js` 的 tenantStore 隔離業務資料與使用者重試紀錄, 所有新操作都必須走這個邊界；舊單人資料歸第一個空間, 原始記錄保留
- 狀態使用專案設定的固定 ID＋category, 不以 `state === 'done'` 或名稱判斷完成；Epic 不重複填估時／工時
- 通知預設 preview, SMTP 只連線 verify, 禁止在 refresh／業務 API 中同步寄信；秘密由 `.env.local`／伺服器環境提供
- 每 30 秒更新資料, 有未保存表單時不得把基準 revision 偷換成最新資料版本
- 本機版本確認前不建立 `.gs`、GAS manifest、GAS build 指令或雲端初始化／部署

1. 讀本文件、`README.md` 及當次工作相關文件, 檢查 `git status` 與既有差異
2. 多步驟工作先更新 `plans/<目前分支>/plan.md` 與 `step.md`, 記錄需求、假設、步驟、驗證與待確認事項
3. 依當次指示完成工作, 可以自主處理可逆的文件與本機修改, 不把調研擴張成未要求的功能實作
4. 將使用者確認、競品事實、架構推論與提案分開記錄, 查證可變動資訊並附來源及調研日期
5. 只修改任務相關內容, 保留使用者既有變更, 不修改參考專案
6. 完成後回報成果、驗證方式與實際限制, 不把本機或 mock 驗證說成正式部署成功

## 架構方向

參考 `../GuildManager/Architecture.md` 與實際原始碼, 詳細提案見 `docs/architecture.md`

- 正式執行環境採 Google Apps Script Web App, 由 `doGet()` 與 HTML Service 提供頁面
- 建議沿用 Vue＋Vite 前端, 本機 Express＋SQLite, 雲端 GAS＋Google 試算表的雙平台開發方式；UI 套件另依後續決定
- 畫面統一透過 `src/api/` 資料介面, 本機 HTTP 與雲端 `google.script.run` adapter 提供相同資料契約
- 將可共用的驗證與運算放在不依賴 Node 或 Google 服務的 domain 模組
- SQLite 與 Sheets 使用各自的 repository, 不將 SQL 交易、外鍵或唯一約束視為 Sheets 原生能力
- GAS 前端編譯為內嵌 JS／CSS 的單一 HTML, 後端來源另外打包；不手改編譯產物
- 多頁面先考慮 hash 路由, 在 GAS iframe 實測深連結、重新整理與返回行為
- 不直接複製 GuildManager 的幫會業務、帳號、角色、資料、憑證或部署設定

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
- commit 訊息採 Conventional Commits, 用繁體中文說明成果, 例如 `feat: 調整專案入口與任務導覽`
- 不提交憑證、資料庫、依賴、截圖、暫存檔或編譯產物
- 保留每階段的歷史, 不自行 amend、squash 或重寫已提交內容
- 使用者授權的是本機 commit, 遠端 push 依另行指示執行
- 完成回報包含 commit 短雜湊、成果與驗證結果；未能提交時說明原因

## 驗證與交付

- 純文件工作檢查內容一致性、來源、相對連結與差異, 不額外建立測試套件
- 功能實作驗證實際使用流程, 依風險補權限、版本衝突、防重複提交與資料保存測試
- 本機與 GAS adapter 必須符合相同契約, Google 服務 mock 不替代真實 GAS 授權及部署驗證
- 及早以隔離試算表驗證 GAS 完整讀寫, 不等全部功能完成後才處理雲端差異
- 編譯、上傳、初始化資料與更新 Web App 部署是不同動作, 各自記錄驗證結果
- 本專案依上述規範每階段建立 commit, 不沿用其他專案的推送／部署授權
- 不刪除 `AGENTS.md`、skill、規劃文件或使用者資料來清理工作區

## 文件位置

- `README.md`：專案入口與目前狀態
- `docs/competitor-survey.md`：競品比較與功能取捨
- `docs/architecture.md`：GuildManager 參考結果與本專案架構提案
- `plans/<分支>/plan.md`、`step.md`：當次工作範圍與續接紀錄
