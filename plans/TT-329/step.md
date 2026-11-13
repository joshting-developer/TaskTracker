# TT-329 步驟

- [x] 11個側邊欄項目改成連結, 一般點擊走原本的切換流程, 修飾鍵／中鍵／右鍵交給瀏覽器
- [x] 「我的任務」加 `?scope=mine`, 新分頁直接是我的任務
- [x] 圖示不縮的CSS補到連結
- [x] 既有瀏覽器檢查改用link找側邊欄（工作空間設定頁的「成員與權限」分頁不動）
- [x] 新增 `frontend/tools/sidebar-links-check.js`, `hash-routes-check.js` 補scope案例

## 驗證結果（自己開的隔離環境 tasktracker-claude-tt329, 13129）

- sidebar-links-check通過: 每個項目的網址、外觀跟原按鈕一樣、一般點擊不整頁重載、Ctrl／Cmd點擊開新分頁且直接是我的任務、有未儲存內容時先確認, 三種寬度無橫向捲動
- test:routes、前端build通過
- 回歸通過: project-utility、time-calendar、member-time-details、time-analysis
- 回歸失敗但跟側邊欄無關: browser-check（側邊欄部分都過, 停在卡片關聯狀態）、issue-time-check（撤權後工時分頁）、account-settings-check（第二個帳號重新整理）；uiux、usability-fixes、home、state-filter-crash、status-notification、workspace-switch停在TT-319驗證碼畫面, 這幾支還沒處理註冊驗證
- 沒跑完整視覺比對: visual-check會重建tasktracker-codex-visual, Codex正在用；改在同頁放原本的button比對尺寸與樣式, 結果一致
