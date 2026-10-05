# 工作紀錄

## 2026-10-06：日常工作流程補充

- [x] 確認前輪三階段 commit 與乾淨工作區, 選定依賴關係及常用視圖
- [x] 任務依賴、Epic／工時上下文, 驗證並提交
- [ ] 常用篩選與個人／團隊視圖, 驗證並提交

依賴階段：`npm test` 13／13、build 與隔離瀏覽器完整流程通過。驗證依賴選擇／反向關係、循環拒絕、完成限制、取消不解鎖、前置重開提醒及清單 Epic／2.5h 工時；原本雙帳號與手機流程保留, 无頁面錯誤。截圖 `artifacts/issue-dependencies.png`, 不提交截圖或試用資料。前置限同專案執行 Issue, 不做跨專案或排程

## 2026-10-06：協作階段

- [x] 確認工作區乾淨、使用者 SaaS 補充, 重新查證官方競品與郵件文件
- [x] 設計 Epic、狀態、工時、帳號／工作空間隔離與 SMTP adapter, 交付協作設計文件
- [x] 資料／API 與安全邊界實作、驗證、提交
- [x] 畫面與雙使用者／手機驗證、更新使用文件、提交

資料階段：新增本機 scrypt 帳號、HttpOnly session、工作空間隔離與七日單次邀請；第一位工作空間承接舊資料, 後續註冊空間為空。Issue 支援 Epic、估時與成員 ID, 專案支援自訂狀態與設定 revision；工時綁 session 身分, 有修正／作廢歷史。指派與留言產生預覽通知, SMTP 僅 verify, 不寄信

`npm test` 9／9 通過, 包含真實 HTTP session、跨工作空間 ID 拒絕、成員越權、重試通知防重複、Epic 完成／重開、狀態刪除保護、工時修正／加總及 SMTP 測試 transport 不寄信。HTTP 測試需要 loopback listener, 以 sandbox escalation 執行。此階段畫面尚未接新登入 API, 下一階段完成

畫面階段：登入／新空間、成員邀請、Epic 卡片與子任務連結、專案狀態設定、工時月曆／明細／成員與 Epic 彙總、通知預覽與 SMTP 設定狀態都已接上。清單與看板只呈現執行 Issue, Epic 獨立顯示。保留開始編輯的 revision, 防止背景更新後舊表單覆蓋同伴；登出也排除尚未完成的舊查詢回應

驗證結果：

- `npm test` 10／10 通過, 新增舊單人資料承接、原始資料保留與邀請／session 到期檢查
- `npm run build` 通過, 只有本機前端產物, repo 沒有 `.gs`
- 隔離瀏覽器流程通過：兩個獨立帳號邀請加入同空間、一般成員狀態唯讀、Epic 關聯／完成限制／重開提醒、自訂欄位看板、個人工時修正與團隊 2.5h 彙總、通知預覽及未設定 SMTP 提示
- 使用同帳號另一分頁的可控 clock, 驗證 30 秒背景刷新後保留本機草稿、舊 revision 寫入遭拒, 重載取得同伴最新內容
- 舊專案首頁／切換／深連結／返回、搜尋、拖曳、留言、未保存提醒、文字安全顯示與 390px 手機流程通過, 無頁面執行錯誤
- 桌面檢視：`artifacts/epic-overview.png`、`time-calendar.png`、`notifications.png`；手機：`mobile-time-calendar.png`, 截圖與隔離資料庫不提交
- 實際 SMTP 未配置／實寄, verify 以注入 transport 測試 TLS、清理及不寄信, 不宣稱真實郵件送達
- 原試用資料庫未被測試寫入；本機前後端仍在 5174／3002 運作, 等使用者自行建立第一位帳號才承接舊資料

已知界線：本機 HTTP／loopback、一帳號一空間、邀請限新帳號、空間內專案／工時共享, Project ACL、正式身份復原、背景信件寄送及 GAS 尚未實作。正式 SaaS 所需設計與取捨都記入 `docs/collaboration-design.md`

本輪提交：`ac609e9` 規劃、`35eb472` 資料／API, 畫面階段另建第三筆 commit；不推送遠端

日期：2026-10-05（Asia/Taipei）

## 2026-10-06：階段提交

- [x] 確認目前尚無 commit, 所有待提交內容為本次專案既有成果
- [x] 提交調研與開發規劃文件
- [x] 驗證並提交本機任務管理原型, 包含專案列表入口
- [x] 寫入每階段 commit 的規範並提交, 核對最終 log 與工作區

提交前 `npm test` 5／5 通過、`npm run build` 通過, 原型瀏覽器驗證沿用專案入口調整時通過的結果。三個階段只提交本機歷史, 不推送遠端, 資料庫、截圖與編譯產物均排除

本文件為調研階段的完成快照, 後續已完成本機原型, 目前狀態見 [原型工作紀錄](prototype-step.md)

## 2026-10-06：專案入口調整

- [x] 檢查現有檔案、導覽方式與未追蹤變更, 記錄使用者需求
- [x] 建立專案列表首頁, 任務工具移入專案上下文
- [x] 更新深連結、返回、建立任務的專案限制
- [x] 驗證瀏覽器流程與編譯, 更新開發文件

驗證結果：

- `npm run build` 通過, 本次只編譯本機前端
- 隔離瀏覽器操作檢查通過：首頁沒有任務清單／看板／任務新增入口, 建立專案後停留列表, 點入才顯示該專案內容
- 驗證兩個專案的任務隔離、固定建立專案欄位、專案搜尋、重新整理、瀏覽器返回、任務深連結與舊連結相容
- 既有搜尋、拖曳、留言／歷史、未儲存提醒與手機流程通過, 無頁面執行錯誤
- 桌面專案列表截圖：`artifacts/desktop-projects.png`；手機專案列表：`artifacts/mobile-projects.png`
- 原試用資料庫未寫入或遷移, API 與資料模型未修改

- [x] 檢查 main 分支與空工作區, 讀取上層 AGENTS.md 及工作區 skill 描述
- [x] 讀取 GuildManager 的 AGENTS.md、Architecture.md、README.md、package.json
- [x] 核對 Vite 單檔設定、GAS 打包工具、RPC adapter、入口、manifest 與 Sheets store
- [x] 建立 TaskTracker AGENTS.md, 分開使用者已確認方向與架構建議
- [x] 建立本次規劃與續接紀錄
- [x] 完成競品調研、架構提案與 README
- [x] 驗證文件連結、來源與工作區差異

## 驗證結果

- 本次共新增 6 份 Markdown 文件, 檢查相對文件連結、結尾換行、空白與 code fence 配對
- 調研使用競品官方文件, GAS 行為使用 Google 官方文件查證, 來源直接附在相關段落
- 架構提案與 GuildManager 的 adapter、打包工具及 Sheets store 對照, 未修改參考專案
- 工作區只包含本次新增文件, 沒有應用程式、憑證或編譯產物

## 已知限制

- GuildManager 包含 Vue／Vuetify、本機 Express＋SQLite、GAS＋Sheets 架構, TaskTracker 尚無應用程式
- 本次只有文件, 沒有執行 build、測試、雲端初始化、上傳或部署
- GuildManager 使用提交日誌與分塊 payload 保存 Sheets 資料；TaskTracker 尚未選定儲存實作
- 未沿用參考專案的自動 commit、帳密、Script ID、試算表 ID 或部署授權

## 下一步

收斂第一版主要操作與登入情境, 再建立本機骨架及隔離的 GAS 讀寫驗證
