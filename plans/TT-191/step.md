# TT-191 進度

- 已實作private local disk與附件metadata表, 嗅探PNG／JPEG／WebP、10MiB／2500萬像素、pending／每卡／Workspace配額, limits與upload／bind／Issue list／content／delete內部API
- upload以短交易保留uploading→交易外IO→重驗資格轉pending, requestId／checksum恢復；bind／delete維持Issue版本／歷史／receipt, content每次驗當下資格且不回私有key
- pending24小時、刪除物件保留30天、有界每批100／每次1000的冪等清理與失敗重試；有附件歷史的Issue拒絕永久刪除, 不撞外鍵500
- 隔離MySQL migration及memory／MySQL安全／生命週期通過, 真HTTP私有disk驗bytes串流、10MiB精確邊界、偽MIME、跨租戶與未登入拒絕。48項2967assertions合併回歸通過, 最後格式化後重跑
- 已驗public disk錯配拒絕、撤權／切換Workspace、IO及finalize失敗可恢復、bind失敗回滾、110個無關附件不影響目標分頁、清理失敗下次可重試
- Nginx語法通過, PHP／代理上限同步10M／12M；正式映像未建置, 現用DB未套migration, R2與正式scheduler未啟用, 不宣稱部署完成
- 契約docs/contracts/attachments-v1.openapi.json, 設計／八項審查／保存備份政策docs/attachments-v1.md；只有本卡基建, 287／282尚未claim

- 最後合併回歸48項2967assertions通過, 真HTTP10MiB邊界／private disk通過, 限定23個PHP格式化、Nginx -t及Artisan cleanup註冊通過；內部OpenAPI用既有.local/openapi-validation離線驗官方固定3.1 Schema

- 正式本人實際工時35分鐘已填, 預估360分鐘保留；不把R2部署或後續圖片UI算進已完成。最後Skill證據檢查後本機提交, 回填closed再接287
