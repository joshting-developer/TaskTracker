# TT-354 進度

- 已讀卡片與既有通知流程, 領卡前先填預估工時
- 既有通知可見性同時限制工作區、收件人與紙條參與者, 批次更新必須沿用這個交集
- 新增Session專用POST /api/v1/todo-notifications/read-all, 固定requestId輸入及updated結果, 不擴大PAT契約
- 以建構子注入交易／Service／Repository, 重播前重驗當前成員資格；既有workspace寫入鎖序列化同空間寫入
- 共用通知可見查詢做單次SQL UPDATE, whereNull保留既有已讀時間, 不讀全集或逐筆更新
- SQLite／隔離MySQL共4案例158斷言通過：跨頁23筆、其他收件人／空間／不可見紙條、舊key重播後保留新通知、失去資格拒絕及租戶標頭驗證
- 真瀏覽器已驗3筆通知只送1次POST, 面板改已讀、未讀數歸零與按鈕停用；三尺寸候選待精確比較
- 三尺寸精確比較通過, 未讀徽章已直接驗DOM歸零；smoke/build通過
- 相關API文件已同步, 未新增migration或正式資料操作；待統一tag後確認線上功能
