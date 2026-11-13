# TT-284 步驟

- [x] 對照模板條件與資料來源, 確認舊任務區、IssueDetail、IssuePanel、BoardDialog、舊看板即時同步都走不到
- [x] 刪掉App.vue裡對應的元件、computed、useIssueQuery／useIssueTransitions／useBoardRealtime的使用, 以及只為它們存在的狀態（creatingIssue、editingDetail、boardEditor、moving等）
- [x] workspace物件拿掉沒人讀的issues、boards、timeEntries、notifications欄位
- [x] 側欄「任務列表／我的任務」在非任務頁顯示的計數拿掉, 那是舊整包資料的長度, 現在永遠是0
- [x] 驗證：build（主程式287KB→270KB）、完整browser-check 11項（第一次有一個首頁待辦流程偶發失敗, 重建資料庫重跑通過）、composables、api、bundle、home、settings、workspaces
- [ ] 截圖：側欄計數拿掉會讓有選專案的非任務頁截圖有差異, 跟這輪其他畫面一起給Josh核准
