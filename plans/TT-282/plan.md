# TT-282 描述貼圖與 pending 草稿

- 預估 360 分鐘保留, TT-191／287 已完成, 本卡為 Codex 唯一 inprogress
- 後端：描述內的可信附件與建卡／改卡在同一既有交易綁定, 驗 actor／Workspace／Project／Issue 與 TTL, 回滾和重播不能留下半完成資料
- 前端：只讓 Issue 描述顯式開啟貼圖, 共用能力拆至 upload composable；支援貼上／拖曳／選檔、游標插入、進度／取消／重試, 不破壞純文字／IME／Undo
- 上傳中不得儲存, 離開保護包含上傳狀態；失敗保留原稿與同一 requestId, 未綁定附件依既有 TTL 回收
- 真實 MySQL／Laravel、memory 安全回歸、三尺寸瀏覽器與視覺比較；Josh 已持續授權未來畫面基準直接保存, 不逐批詢問, 仍須實測
