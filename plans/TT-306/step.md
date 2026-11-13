# TT-306 步驟

- [x] 後端：sort多接受created、新增direction（asc／desc）；列表排序與異動清單desc分支（cursor比較反向, 沒更新時間的排最後）
- [x] 測試：IssueSortTest（SQLite＋隔離MySQL）四種排序×兩方向、每頁1筆翻完一致、不合法參數400、異動清單desc、換方向拿舊cursor回400
- [x] OpenAPI 0.12.0：列表sort／direction、異動清單direction；check_openapi、契約測試通過
- [x] 文件：query-api-v1、HTTP契約、Agent指南（增量同步用預設舊到新）
- [x] 前端：排序選單改「更新時間／建立日期／優先級／到期日」, 方向按鈕（可及名稱「目前新到舊，切換為舊到新」、鍵盤可用、手機44px）；看板的前端排序同一套規則；排序存在分頁
- [x] 驗證：issue-sort-check三尺寸、board-ux、board-performance、composables、api、routes；完整browser-check 9項過, 1項卡在小待辦轉卡對話框找不到「類型」, 是Codex進行中的TT-288（IssuePanel未提交修改）造成, 跟這張無關
- [ ] 截圖基準：列表的排序列畫面有變, 但目前工作區有Codex未提交的IssuePanel修改, 現在拍會把它一起當成基準；等TT-288提交後再拍候選、比對並更新基準
