# TT-296 公開附件API

- 小待辦273423f8-7b08-49eb-aa91-3b31d6496505, 保留預估300分鐘, 只領本卡
- /api/public/v1下新增上傳／限制、單筆metadata、同卡分頁、授權內容與綁定, 不開放儲存key或外部URL匯入
- PAT維持read／write交集, Web與PAT共用AttachmentService、私有儲存及HTTP串流呈現；上傳保留→交易外IO→鎖後重驗完成, 成功receipt／來源只記一次
- metadata／content read, upload／bind write, 待綁定僅本人、跨租戶與跨Project／撤權／到期／刪除均拒絕；既有描述同交易綁定保持
- 正式OpenAPI／Agent docs／範例與獨立公開文件HTTP客戶端同步；隔離MySQL、memory安全與Session回歸, 不啟用正式R2或讀寫現用資料
