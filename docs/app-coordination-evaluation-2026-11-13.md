# App導覽、工作區與草稿協調責任評估

TT-179／2026-11-13。範圍：frontend/src/App.vue（587行）與ProjectTasksWorkspace的分工。依共用vue-best-practices的責任邊界檢查，不以行數為拆分理由，不引入Router／Pinia／全面TypeScript。

## 現況責任地圖

| 生命週期 | 目前位置 | 負責內容 | 評估 |
| --- | --- | --- | --- |
| 身分與工作區Session | App.vue：authenticated、switchingWorkspace、changeWorkspace、logout、resetWorkspaceUI、邀請流程、refreshGeneration／refreshScope | 登入／切換／登出時清除舊scope、重設UI與hash | 行為正確但與畫面狀態交織；可抽成單一composable，對外只給`changeWorkspace／logout／reset`與狀態 |
| Hash導覽 | App.vue：readHash、projectHash、navigate、goLanding、openIssue、closeIssue、issueReturn | 解析路由、離開保護、回到來源頁 | `view`只會是list／board／boards；邏輯集中但與資料載入混在同檔，可抽`useHashNavigation`並以callback接離開保護 |
| 草稿／離開保護 | App.vue `canLeave()`逐一查10個工具ref的`saving／dirty` | 未儲存草稿確認、儲存中禁止離開 | 新增任何可編輯畫面都要改App；應改為各owner登記自己的guard（provide／inject的leave registry），App只問registry |
| 專案任務頁（列表／看板／詳細／編輯） | ProjectTasksWorkspace＋useProjectTaskQueries | 有界查詢、看板、詳細頁、編輯抽屜、撤權與404 | 已是正確owner（TT-180／239等交付） |
| **舊整包任務路徑** | App.vue `<template v-else-if="selectedProject">`內的BoardList、任務區（IssueFilters／IssueList／ProjectBoard）、IssueDetail、IssuePanel、BoardDialog、舊看板realtime、useIssueQuery、useIssueTransitions | 早期以getWorkspace整包資料渲染任務 | **不可達**：有選Project且section為tasks時`isTaskRoute`恆真而改走ProjectTasksWorkspace；`showIssuePage`、IssuePanel、realtimeState都要求`!isTaskRoute`，而開卡／詳細頁一律把section設為tasks。屬死碼，仍讓App相依整包issues／timeEntries |
| 撤權／404 | 各頁`@access`→refresh；ProjectTasksWorkspace handleFailure清草稿並回上一層 | 失效即停止顯示與寫入 | 符合規範；拆分時保留 |
| loading／empty／error | App多處error-box＋各頁自己的狀態 | — | 刪除舊任務區後App只剩首頁／專案列表／非任務分頁的錯誤框，重複自然消失 |

## 決策（採Claude建議）

1. **先刪除舊整包任務路徑**（最大收益）：純刪除不可達UI與其computed／composable，App不再為任務頁持有整包issues。刪除後再評估getWorkspace能否不回issues／timeEntries（屬資料存取，需實際Network證據）。
2. **以leave registry取代canLeave逐一查ref**：各草稿owner（PersonalWorkspace、ProjectTasksWorkspace、設定／個人資料／Token／轉換對話框、TimeCalendar等）在掛載時登記`{dirty, saving}`並於卸載移除；App只呼叫registry。行為不變，回歸含切換工作區、撤權與瀏覽器返回。
3. **抽出`useHashNavigation`與`useWorkspaceSession`**：只在1、2完成後做，避免把死碼一起搬進composable；不改URL格式與既有深連結。
4. 不做：Router／Pinia／全面TS、為行數拆元件、改變既有hash或API契約。

## 後續卡片

依共用分類規則（驗收目的為前端結構與協調，非產品新功能或效能量測）收「結構優化」板，open未指派：

- TT-284 移除App.vue不可達的舊整包任務工作區（決策1）。
- TT-285 以草稿leave registry取代App逐一查詢工具ref，並抽出導覽／工作區Session composable（決策2、3）。

本卡為評估交付：只新增本文件與卡片，未改產品程式；驗收以靜態路由分析（readHash只產生list／board／boards）與模板條件交叉核對為依據，實際刪除時需跑完整browser-check與核准視覺比較。

## 實作結果（2026-11-13, Claude）

- TT-284：舊整包任務區已從App.vue刪掉。實際比評估時更確定是死碼：App的 `workspace.issues` 一律是空陣列, 舊詳細頁和編輯面板根本不可能出現。側欄在非任務頁顯示的任務數也是讀這份空資料, 一直是0, 一起拿掉
- TT-285：
  - 離開保護改成 `src/ui/leaveGuard.js`：各草稿owner在setup裡 `useLeaveGuard({ dirty, saving, unload })` 自己登記, 卸載自動移除；App的 `canLeave()` 只剩「有人儲存中就不離開, 有未儲存就先確認」。`unload: true` 的owner才會在關閉分頁時提醒, 範圍跟原本的beforeunload一樣
  - hash網址的解析和組成抽成純函式 `src/ui/hashRoutes.js`（`parseHash`／`buildHash`／`settingsHash`）, 有 `npm run test:routes` 驗17種既有網址組回去一模一樣；App的readHash只負責把結果套到畫面狀態
  - `useWorkspaceSession` 這次不抽：TT-284刪完後登入／切換／登出只剩約30行, 而且跟重設畫面狀態綁很緊, 抽出去只是把十幾個ref傳來傳去, 不符合「不為行數拆」的原則。之後如果Session流程再長大再拆
