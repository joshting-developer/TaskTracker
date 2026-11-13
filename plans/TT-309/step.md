# TT-309 步驟

- [x] 後端：boards.description（migration、預設空字串）、寫入驗證最多300字、更新沒帶時保留原值、列表／單看板／Default回傳
- [x] 前端：編輯視窗加簡述和字數提示；列表兩行截斷＋tooltip；看板頁完整顯示；按鈕改「編輯」（可及名稱「編輯看板」）
- [x] 測試：BoardDescriptionTest（SQLite＋隔離MySQL）、Board相關21支、Pint、PHPDoc（我的部分0問題）、board-description-check三尺寸、board-list-summary、board-archive、board-ux、api、完整browser-check 11項（一次任務詳細頁偶發失敗, 重跑通過）
- [ ] 共用task-tracker-usage Skill改分類規則
- [ ] 本機開發庫migrate後補現有看板簡述
- [ ] 依簡述重新歸類卡片, 清單給Josh
- [ ] 截圖給Josh核准（看板列表不在截圖比對範圍, 已存artifacts/tt309）
