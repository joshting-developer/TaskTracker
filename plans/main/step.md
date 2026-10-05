# 工作紀錄

## 2026-10-06：協作階段

- [x] 確認工作區乾淨、使用者 SaaS 補充, 重新查證官方競品與郵件文件
- [x] 設計 Epic、狀態、工時、帳號／工作空間隔離與 SMTP adapter, 交付協作設計文件
- [x] 資料／API 與安全邊界實作、驗證、提交
- [ ] 畫面與雙使用者／手機驗證、更新使用文件、提交

資料階段：新增本機 scrypt 帳號、HttpOnly session、工作空間隔離與七日單次邀請；第一位工作空間承接舊資料, 後续註冊空間為空。Issue 支援 Epic、估時與成員 ID, 專案支援自訂狀態与設定 revision；工時綁 session 身分, 有修正／作廢歷史。指派與留言產生預覽通知, SMTP 僅 verify, 不寄信

`npm test` 9／9 通過, 包含真實 HTTP session、跨工作空間 ID 拒絕、成員越權、重試通知防重複、Epic 完成／重開、狀態刪除保護、工時修正／加總及 SMTP 測試 transport 不寄信。HTTP 測試需要 loopback listener, 以 sandbox escalation 執行。此階段畫面尚未接新登入 API, 下一階段完成

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
