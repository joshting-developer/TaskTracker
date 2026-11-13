# TT-283 步驟

- [x] 後端：看板列表加 `archived=1`（BoardPageQueryRequest）, 已封存依封存時間新到舊；單看板／卡片／版本查詢允許讀已封存；回傳加archivedAt
- [x] 後端：restoreBoard RPC（board.manage、expectedRevision、requestId重播）, 接上路由、授權表、寫入結果和同步訊號
- [x] 舊測試改成新規則：封存看板讀取從404改成唯讀可讀；OtherWriteBoundary加restoreBoard
- [x] 前端：看板列表「使用中／已封存」切換、封存日期、還原按鈕；封存看板頁有提示列、不能新增或拖曳；文案改「封存看板」
- [x] 驗證：BoardArchiveTest與MySqlBoardArchiveTest、Board相關27支在隔離MySQL通過、Pint、PHPDoc audit 0問題；board-archive-check（三尺寸）、board-ux、board-list-summary、api、完整browser-check 11項（有一次首頁待辦偶發失敗, 重建資料庫重跑通過）
- [ ] 截圖等Josh核准
