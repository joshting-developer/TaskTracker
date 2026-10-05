# TaskTracker

專案管理平台 Side Project, 參考 YouTrack 的任務操作, 以 SaaS 工作空間設計帳號與團隊協作

目前是本機協作試作：帳號登入／邀請、工作空間隔離、專案入口、Epic 與 Task／Bug 關聯、自訂狀態看板、個人工時月曆與團隊分析、留言／異動紀錄與通知預覽。資料保存到 SQLite, 重新整理與服務重啟後保留

依使用者指示, 本機版本確認前不產生 `.gs`。正式 SaaS 部署、GAS 適用範圍與真實郵件寄送另評估

## 開始試用

需要 Node.js 22.18 以上的 22.x 或 Node.js 24.12 以上, 本次以 Node.js 22.23.2 驗證；`node:sqlite` 在 Node 22 會顯示 ExperimentalWarning

```sh
npm ci
npm run server
```

另開一個終端：

```sh
npm run dev
```

開啟 [TaskTracker](http://127.0.0.1:5174), 點「第一次使用？建立工作空間」, 填名字、Email、至少 10 字密碼與空間名稱。第一位建立者為管理員, 會承接原本的單人試用資料；後續自行建立的空間從空資料開始, 不會看到其他團隊內容

首頁先建立／選擇專案, 點進卡片才有任務工具。空首頁可以載入 9 個任務的示範專案, 也可以直接建立自己的內容

1. 進專案 → Epic 概覽 → 建立「會員系統上線」Epic
2. 新增 Task／Bug, 在「所屬 Epic」選目標, 填預估小時與負責人
3. 狀態設定 → 新增「待測試」, 分類選「未結案」→ 儲存, 看板會多一欄
4. 點左側工作空間名稱 → 填協作者 Email → 建立邀請連結, 複製到另一個瀏覽器或無痕視窗
5. 受邀者用該 Email 建立新帳號, 加入同一團隊。自行註冊新的工作空間會得到獨立資料
6. 工時月曆 → 選 Task／Bug → 填日期、投入小時（例如 1.5）、說明 → 登記, 可修改或作廢自己的紀錄
7. 工時成員切「全團隊」, 月曆、明細與下方成員／Epic／任務彙總一起更新；Epic 概覽顯示全部工時
8. 指派給同伴或在同伴負責的任務留言, 到通知預覽看收件人與信件內容, 目前沒有寄出

邀請目前限新帳號, 七日內一次使用；一個帳號加入／切換多工作空間另做。API 只監聽 loopback, 若要不同電腦連線需正式部署設計, 不能直接把這版公開到網際網路

## 操作與規則

- 任務工具限目前專案, 首頁仍以專案卡片作入口。任務清單／看板呈現 Task、Bug、Feature；Epic 在獨立概覽管理
- 負責人改為成員選單, 「我的任務」依登入身分, 舊文字負責人保留顯示
- Epic 只能連同專案子項目, 不允許嵌套。尚有未結案子項目時不能驗收完成；驗收後重開子項目會出現重新確認提醒
- Epic 完成率排除取消項目, 工時與估時只加總子任務一次, 不自動改父項目的狀態
- Task／Bug 可選同專案前置任務, 未完成前置會阻擋結案, 取消不自動解鎖。詳細內容有雙向關係, 清單／看板直接顯示 Epic、實際／預估工時與阻擋提醒
- 自訂狀態可新增、改名、分類、改色與排序, 使用中的狀態先移轉任務才能移除。管理員可以改設定, 一般成員只能查看
- 工時綁登入使用者, 不能代填或修改他人；每筆／每日最多 24 小時, 不接受未來日期, 工作日期採 Asia/Taipei
- 月曆顯示填報日期與時數, 目前沒有起迄排程或 timer, 報表不判定出勤與績效
- 工作空間內專案及工時先共享, 私人專案／專案角色 ACL 尚未實作
- 每 30 秒更新協作資料, 也可按右上角重新整理。修改時保存開始編輯的版本, 同伴更新後會提示衝突, 保留表單供確認／重載
- 頁面偏好留在瀏覽器, 資料留在 SQLite；預設 `data/tasktracker.sqlite`, 可用 `DATABASE_PATH` 指定其他檔案
- session 為八小時 HttpOnly cookie, 登出即撤銷。正式 Email 驗證、密碼復原與完整 SaaS 身分管理尚未提供

## SMTP 檢查

預設 preview, 不外送信件。不需設定 SMTP 就能試指派／留言通知, 管理員可看專案的所有預覽, 一般成員只看自己的通知

若要試連線, 複製 `.env.example` 為被 Git 忽略的 `.env.local`, 填伺服器 host／from 等欄位, 重新啟動 `npm run server`。`SMTP_SECURE=true` 通常搭配 465；587 使用 `false` 並強制 STARTTLS。畫面只顯示設定狀態, 不接收或回傳密碼

在「通知預覽」按「檢查 SMTP 連線」只驗證連線、TLS 與認證, 不寄測試信。成功也不代表寄件者／收件人被接受。真實寄送、背景 worker、到期摘要、重試與偏好設定見設計文件, 本輪沒有啟用

## 驗證

```sh
npm test
npm run build
```

瀏覽器使用隔離 SQLite 與前端埠 5175, 不修改試用資料：

```sh
npx playwright install chromium
npm run test:e2e
```

可用 `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` 指定已有 Chrome／Chromium。瀏覽器驗證涵蓋原本專案入口與任務流程, 以及兩帳號邀請、權限、Epic 驗收／重開、自訂看板狀態、工時修正與團隊加總、通知預覽、背景更新時的版本衝突及 390px 手機畫面。截圖在被忽略的 `artifacts/`

## 文件

| 文件 | 內容 |
| --- | --- |
| [協作與工時設計](docs/collaboration-design.md) | 本輪競品調研、SaaS、Epic、自訂狀態、工時月曆與 SMTP 規劃 |
| [日常工作流程](docs/workflow-design.md) | 任務依賴與常用視圖的規則及操作 |
| [AGENTS.md](AGENTS.md) | 開發規範、已確認方向、階段提交與驗證規則 |
| [競品調研](docs/competitor-survey.md) | 初次 YouTrack、OpenProject、Linear、Trello 調研 |
| [架構提案](docs/architecture.md) | GuildManager 參考與 GAS 後續方向 |
| [計畫](plans/main/plan.md)／[工作紀錄](plans/main/step.md) | 各階段成果與驗證證據 |
| [原型計畫](plans/main/prototype-plan.md)／[原型紀錄](plans/main/prototype-step.md) | 初次單人原型的完成快照 |
