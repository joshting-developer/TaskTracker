# TaskTracker

專案管理平台 Side Project, 參考 YouTrack 的任務操作方式, 以清單與看板追蹤進度

第一版為本機單人原型：建立專案、任務新增／修改、文字搜尋、狀態與優先級篩選、看板拖曳／選單更新、留言及異動紀錄。資料保存到 SQLite, 重新整理或服務重啟後保留

GAS 是後續方向, 依使用者指示等本機版本確認後再產生 `.gs`, 目前只建立本機版本

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

開啟 [TaskTracker](http://127.0.0.1:5174)。首頁是專案列表, 先建立專案, 再點專案卡片進入任務工作區。也可以點「試試示範專案」, 載入後點進示範專案操作 9 個任務

- 清單點任務名稱可編輯, 建立或修改表單需要按儲存
- 任務清單、看板、進度與「我的任務」「已完成」只顯示目前專案的內容；建立任務的專案固定為目前專案
- 「返回專案列表」可選另一個專案, 重新整理與瀏覽器返回依 hash 路由保留正確專案
- 看板拖曳卡片, 或使用卡片下方狀態選單, 會直接保存新狀態
- 「我的任務」會列出負責人填「我」的任務, 目前未綁定團隊帳號
- 留言獨立送出, 標題、狀態等欄位異動會記錄前後值
- 頁面顯示偏好留在瀏覽器, 任務資料留在 API 的 SQLite
- SQLite 路徑預設 `data/tasktracker.sqlite`, 可用 `DATABASE_PATH` 指定另一個檔案, 不刪除資料庫來停止服務
- API 只監聽 `127.0.0.1:3002`, 不提供公開註冊或多人登入

## 驗證

```sh
npm test
npm run build
```

瀏覽器驗證使用隔離 SQLite 與另一個前端埠 5175, 不修改試用資料：

```sh
npx playwright install chromium
npm run test:e2e
```

可用 `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` 指定已有的 Chrome／Chromium 執行檔。操作測試包含示範資料、搜尋／篩選、拖曳與選單、修改、留言／歷史、重新整理、未儲存保護、專案新增、文字安全顯示與 390px 手機畫面。截圖放在被 Git 忽略的 `artifacts/`

本次測試與限制見 [原型工作紀錄](plans/main/prototype-step.md)

## 文件

| 文件 | 內容 |
| --- | --- |
| [AGENTS.md](AGENTS.md) | 本機開發規範、已確認方向、驗證規則 |
| [競品調研](docs/competitor-survey.md) | YouTrack、OpenProject 為主, Linear、Trello 為輔 |
| [架構提案](docs/architecture.md) | 本機／GAS 長期方向, 提案不代表全部已實作 |
| [調研計畫](plans/main/plan.md) | 初次需求與調研範圍 |
| [原型計畫](plans/main/prototype-plan.md) | 本次功能、使用者補充與驗證方式 |
| [原型工作紀錄](plans/main/prototype-step.md) | 已完成項目與驗證證據 |
