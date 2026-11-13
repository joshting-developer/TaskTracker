# TT-291 進度

- 已領卡, 確認R2空值範例與metadata現有, S3 adapter尚未安裝
- 已裝Flysystem S3 3.35.3／AWS SDK 3.403.5, lock新增5依賴且原套件未升版；私有R2配置與Compose轉交、TLS／有界timeout／重試完成
- 缺值／不安全URL／錯誤driver／關閉TLS在metadata保留前拒絕；每筆disk讀取保留, 本機附件不自動遷移
- 真R2隔離Bucket測Session／PAT上傳與串流、重播只PutObject一次、跨租戶404、未簽名S3讀取400、軟刪／到期清理, 3項105斷言通過
- 本機／公開附件、設定與故障注入、舊disk保存、依賴／註解／Schema回歸32項3109斷言通過；timeout與TLS失敗是注入, 不冒稱供應商故障實測
- 真Chrome選圖、Markdown預覽、建卡、重載與三尺寸通過；手機截圖等導覽動畫收合後才拍, 本次物件finally清理, 早先清理字串故障亦補清並驗不存在
- 共用48張既有approved基準比較與build、Compose靜默格式、映像／Git不含secret檢查通過, 原始證據artifacts/tt291
- 依最後Josh決定共用金鑰／分Bucket, 沒碰正式Bucket、沒自動切現用disk或搬本機附件；既有7則Symfony安全公告另開TT-318收部署、open未指派
- 詳見docs/tt291-r2-attachments.md；提交後填本人正式工時與卡片／小待辦, 不把本卡成功當全專案安全驗收
