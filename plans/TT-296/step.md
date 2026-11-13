# TT-296 進度

- 已讀最新卡片與公開API／附件契約, 只領本卡, 預估300分鐘保留
- 主要風險：multipart重試與交易外IO、重播前授權、pending作者限制及不洩漏storage key
- 六個公開附件操作已接線, 共用AttachmentService／Transfer與安全串流；write完成receipt與來源去重, IO階段不持有DB交易
- 真MySQL的PAT與Session附件12 tests通過；memory／MySQL、契約／路由／匿名文件擴大34 tests通過
- 獨立客戶端匿名下載文件與Python範例, 在空目錄以PAT上傳→補送→建含圖卡→metadata→binary SHA256核對通過
- 格式／正式Schema／CI入口與文件收尾中, 未操作現用資料／正式R2
- 最終後端58 tests／4521 assertions、PHPDoc／依賴9 tests通過, 補充舊附件／子卡測試與migration純註解
- Web共用上傳真瀏覽器回歸與TT-310三尺寸視覺比較通過, 未驗正式R2／部署
