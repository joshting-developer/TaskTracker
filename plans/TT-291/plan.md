# TT-291 R2附件儲存

- 已查小待辦及最新卡片, 保留預估180分鐘；只領本卡
- 現有附件已存disk metadata, R2設定與金鑰在backend/.env, 不輸出／不提交；只用task-tracker-local Bucket
- 依Laravel官方安裝Flysystem S3 v3 adapter, 補安全配置／HTTPS與有限timeout, 設定缺失回可理解錯誤且不留下配額紀錄
- 不切換或搬移現用本機附件；隔離MySQL＋Laravel使用R2驗上傳、授權串流、跨租戶拒絕、刪除／cleanup與錯誤, 只清本次隨機物件
- 依共用工程／Laravel／Ponytail及資料存取門檻驗證, 必要瀏覽器與共用視覺後提交、正式工時／卡片／小待辦回填
