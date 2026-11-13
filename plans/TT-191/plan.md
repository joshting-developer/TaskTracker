# TT-191 私有附件基礎

- Codex已開始, 原預估360分鐘保留。採local private disk, 只開Issue圖片附件；PNG／JPEG／WebP, 預設10MiB、每卡20張、本人pending20張、Workspace1GiB均由設定提供
- multipart upload採短交易保留uploading metadata與配額→交易外Storage IO→短交易重驗資格轉pending；相同requestId／checksum重試可恢復, 失敗保留TTL供重試與冪等清理
- bind／list／content／delete分責, 權限與租戶SQL限定；pending只本人, active需Issue可讀；bind驗Issue revision並同交易追加版本／歷史, 物件不進SQL
- Controller／Request／Service／Repository／Model／Resource與建構子DI, 不公開storage key；內容同源經當下授權、nosniff／private no-store, 不用公開bucket
- Storage fake與隔離MySQL驗MIME偽造、大小／配額、跨租戶／撤權、重播、失敗／補償、TTL清理與保留政策；R2只預留設定, 部署另卡291

- 本卡基礎驗收完成：48項2967assertions、真HTTP10MiB邊界／private disk、Nginx語法、限定Pint與離線OpenAPI驗證通過；本人35分鐘已填。現用DB／正式映像／scheduler／R2部署另計, UI仍由287及282接線
