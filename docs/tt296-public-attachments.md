# TT-296 公開附件API

新增6個純Bearer操作, 詳見唯一正式OpenAPI 0.13.0。metadata／內容／清單需read及即時issue.read, 上傳／綁定需write及issue.write；limits需read及issue.write。PAT不依賴Session或CSRF, 綁定的Workspace不會受瀏覽器目前工作區影響

上傳仍使用Web的AttachmentService → AttachmentRepository與私有Storage。AttachmentTransferService只負責共用保留→交易外IO→重新驗權完成的順序；HTTP邊界保留User→Workspace鎖及Scope重驗。保留階段不寫成功audit, 完成後由writeOnce在同交易保存安全metadata receipt與來源；重播前仍驗資格／附件可用性, 不重複來源或配額

Web與PAT使用同一AttachmentContentResponse呈現安全串流, 不回storage disk／key或簽署URL。bind沿用雙revision與原子歷史更新, 描述中的attachment參照也可在建卡／改卡同交易綁定。未新增公開刪除附件、遠端URL匯入、MCP或工具專屬規則

## 資料存取八項

- 呼叫端只取當次Project／Issue附件metadata或單一授權圖片, 不把binary放進JSON
- 單卡清單固定每頁20筆, SQL限定Workspace／Project／Issue與active, 依id穩定排序；查詢由原Repository執行
- 單筆與內容只讀一個已授權附件, pending限定作者／未過期, active必須仍有同scope卡片
- 不取整個Workspace, 不附留言／工時／歷史；沒有新輪詢或自動附件預載
- 配額沿用既有SQL聚合, IO不持有DB鎖；每次upload有保留／完成兩次鎖後驗權, 不能把它宣稱單次SQL或零成本
- 上傳預設10MiB／2500萬pixels, perIssue20、本人pending20、工作區1GiB、pending24小時；部署可配置, limits為當下來源
- 上傳額外每IP30次／分鐘, 所有公開API仍共用每IP120次／分鐘；讀取按既有公開桶限制
- 真MySQL測試驗跨租戶／Project、作者、撤權後補送、版本與資料保存, 沿用Session清單上限及配額測試；沒有只用SQL次數冒充範圍驗收

## 實測與交付範圍

隔離tasktracker-codex-perf的MySQL／Laravel, resource掛載更新只重建本人測試backend, 未重設MySQL或現用資料庫。memory與MySQL共用安全案例, Session附件回歸、正式Schema／路由／範例漂移及匿名文件測試

獨立客戶端從匿名URL下載Agent文件、OpenAPI與Python範例, 在空目錄用Python -I執行, 只給合成PAT／Project／圖片。它實際上傳、同requestId補送、建含圖Issue、查metadata、下載binary並比SHA256, 無Cookie／SDK／原始碼匯入；Bootstrap帳號與Token由外層隔離fixture提供, 不偽裝成公開自助註冊介面

上傳IO失敗可留下uploading保留供同檔補送, 24小時TTL沿用；IO完成期間撤權會拒絕finalize且不寫成功receipt／audit。PHP測試的私有disk是fake, 獨立HTTP客戶端使用隔離服務真實本機私有disk；不宣稱已驗R2或正式部署

最終受影響後端58 tests／4521 assertions通過；PHPDoc／分層與依賴檢查9 tests通過。補齊先前附件及子卡測試覆寫方法與附件migration的純註解, 不改行為。Web共用上傳重跑完整頁圖片實測, 並與TT-310三尺寸固定基準比較通過；未重跑全產品48張視覺基準或正式R2
