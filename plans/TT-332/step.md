# TT-332 進度

- 已讀交辦、卡片正文與空留言／交接分頁, 核對通知與AI整合/API簡述／收錄
- TT-331已review, 本卡預估180分鐘後指派並claim, 同時只有一張進行中
- 正在盤點既有ProjectBoards、BoardReadService、Bearer writeOnce及Issue建立收錄的同交易邊界

- 公開看板三種讀取入口已接既有SQL分頁, Default與Epic動態收錄保持一致
- 已補建立／部分更新與收錄差量, 建卡boardIds同交易回滾, 重播前重驗board.manage
- SQLite與隔離MySQL目標回歸32項1695 assertions通過, 含正式OpenAPI真實HTTP契約；完整測試與CLI整合尚待驗證
- 共用CLI／Skill已提交ea01ffc, 真實隔離兩帳號PAT並行收錄、衝突後重讀重試和receipt重播通過
- 完整後端跑413項, 412項通過、17832 assertions, 1項原本略過；追加跨Project與封存回滾案例後本卡8項328 assertions通過
- OpenAPI結構／驗證器反例、網頁冒煙、build及既有看板查詢通過, 50次請求且bulkReads為0
- 資料範圍、交易owner、驗證與能力界線見docs/public-board-api-delivery-2026-11-14.md；本卡沒有畫面變更, 不重建基準
- 正式工時已填本人18分鐘, 不含無人等待；目標驗收完成, 提交後回填卡片及完成原交辦
