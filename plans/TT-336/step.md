# TT-336 step

開工：2026-11-14 10:11（Claude）, 預估240分鐘

- [x] 開Claude隔離環境5198／13198（自建映像tasktracker-claude-test-backend, Compose掛工作區後端, 含Codex TT-339未提交改動）；本機Playwright 1.63缺chromium 1243, 已補下載到使用者快取
- [x] 重跑12支：workspace-switch、markdown在本環境直接通過；uiux停在SMTP 12px歸TT-334不改

## 逐支原因與修正（都是檢查跟目前產品對不上, 沒改產品, 沒刪斷言）

- browser-check：import已刪掉的checkTodoWorkflow；TT-331後小待辦流程由notes-page／markdown／todo-collaboration負責, 拿掉舊呼叫。reader工時斷言依TT-317改成看得到工時分頁但不能填報。第831行「還有未結案子任務」逾時一次, 單獨重跑整支通過, 先記偶發
- issue-time：TT-317 Josh確認issue.read可讀卡片所有人工時, reader改驗可讀、無填報／修改鈕、logTime 403
- account-settings：共用fixture直接打API驗證後重新整理, 網址留在#/invite, 又跳出已接受的邀請對話框擋住按鈕；產品AuthGate驗證後會回#/, fixture照做
- api-budget：詳細頁多讀一次attachments/limits（TT-293留言圖片鈕需要）, 只加允許端點, 詳細頁仍4請求4308B在5／10000內；報表TT-277拆成摘要＋兩個選項GET, 允許清單換新網址, 請求上限2→3, bytes 1209仍在3000內（要Josh確認）
- lookup-options：建立看板回應只給摘要, 改讀看板詳情比對selectedIssueIds
- refresh-scope：報表與紙條改用新GET網址, 包含「不該讀到」的負向檢查, 舊名稱會讓負向檢查永遠通過
- guest-pages：開發模式沒設VITE_SITE_ORIGIN時用目前測試埠；E2E_PREVIEW不補
- issue-form：紙條轉卡改走#/notes→更多→抽屜→轉為卡片
- issue-form、issue-inline「已加入描述」逾時：TT-293把共用編輯器完成提示改成「已加入內容」, 不是產品退化, 改等新文字, 後面附件引用斷言保留

## 環境受阻
- 新增fixtures/isolated-api.js共用隔離後端清單（13029／13049／13089／13198／13199）；board-performance、comment-attachments、developer-docs、general-attachments、issue-activity-browser、personal-tokens改用；developer-docs埠預設跟E2E_FRONTEND_PORT
- project-acl-query、workspace-settings-query：驗證信箱在Node端用相對網址fetch, 搬進頁面；順便修限流時return不在if裡導致不會重試
- comment-attachments、general-attachments：前端埠寫死5291（舊Codex perf）, 改讀E2E_FRONTEND_PORT
- personal-tokens：寫死OpenAPI 11個路徑, 改成跟backend/resources/openapi.json路徑清單完全一致
- email-verification（TT-338新增）：寫死13199和Codex的Compose／/tmp覆寫檔, 改用共用清單, 依後端埠對應自己的Compose專案直接docker exec, PHP端testing＋隔離MySQL＋tasktracker_test保護不變
- workspace-switch：TT-339後舊分頁打開建立表單就讀承辦人選項, 在這裡先收到409並同步到新工作區, 不會送createTodo；改成兩種路徑都要WORKSPACE_CHANGED, 舊分頁不能建立成功, 原本A工作區沒被寫入和舊內容消失的檢查保留。試過凍結計時器會讓表單本身不能用, 已放棄

## 驗收環境
- 第一次完整回歸途中Codex開始TT-340（未提交, 新增mail_deliveries.invitation_id）, 隔離後端掛工作區程式碼, 跑到他做一半的版本出現500；這次結果作廢
- 改用 `git archive HEAD` 建tasktracker-claude-test-backend, 用暫存區Compose複本拿掉工作區掛載, 後端只跑HEAD；前端檢查仍用工作區（含Codex TT-340未提交的frontend/src/api/index.js）

## 最終驗證（後端HEAD映像, Claude 5198／13198）
- 冒煙 `npm run test:smoke` 通過；test:routes／test:api／test:composables結束碼0；build通過
- 完整瀏覽器回歸66支：58支通過
  - 55支直接通過
  - account-settings、browser-check、workspace-settings-query在工作區失敗, 原因是Codex TT-340未提交的WorkspaceSettings.vue／WorkspaceInvitationManager.vue呼叫HEAD後端還沒有的端點；改用HEAD前端複本＋本卡檢查重跑3支都通過
- uiux：SMTP狀態文字12px, 歸TT-334, 不放寬13px
- 7支需要特殊環境, 沒有硬跑：inline-mail（inline寄信模式）、issue-pagination（codex-perf容器＋/tmp Compose覆寫）、production-smoke（隔離正式runtime的PROD_BASE_URL）、r2-attachment（R2／MinIO）、realtime（即時服務13024）、registration-policy（開放與邀請限定兩套API）、time-analysis-seed（seed密碼）
- 偶發紀錄：browser-check第831行一次、api-budget量測一次（推測是上一支剛結束5198還沒放開）, 重跑都通過
