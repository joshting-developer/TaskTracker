# TT-291 R2私有附件

## 接線與設定

已安裝league/flysystem-aws-s3-v3 3.35.3及AWS SDK 3.403.5, lock新增5個依賴, 既有套件版本未變。沿用Laravel Filesystem與每筆附件storage_disk／storage_key, 不另造上傳服務或公開物件網址

ATTACHMENTS_DISK維持attachments作預設。要用R2時選r2-attachments, 設定R2_ACCESS_KEY_ID、R2_SECRET_ACCESS_KEY、R2_BUCKET、R2_ENDPOINT及R2_REGION=auto。endpoint是Cloudflare全球S3 HTTPS根端點, 不加Bucket路徑、帳密、query或fragment；目前限定32位帳號ID的r2.cloudflarestorage.com, 不支援自訂／司法管轄區端點

R2_CONNECT_TIMEOUT預設3秒（限1～10）, R2_TIMEOUT預設15秒（限1～60）, SDK retries=1。TLS驗證固定開啟, 不提供關閉選項。私有可串流disk設serve=false, 走後端授權內容API；不選瀏覽器直傳, 不需要Bucket CORS或presigned URL

原生Laravel啟動前先依lock安裝vendor, 再載入私有環境設定。Compose已轉交上述R2環境變數, 可用`docker compose --env-file backend/.env ...`讀私有設定；未明確選ATTACHMENTS_DISK=r2-attachments仍用本機。不要把env、金鑰、Cookie或完整Compose config輸出放進Git或卡片

本次只對Codex隔離backend載入R2, 沒有重建現用服務或替現有附件切disk。未來切換預設也只影響新附件；舊本機附件仍依自己的metadata讀取, 沒有自動遷移

## 授權與錯誤

Controller／可信交易邊界→AttachmentService／AttachmentTransferService→AttachmentRepository與AttachmentStorage。物件IO在正常上傳流程的資料庫交易外, 完成後重新驗資格與receipt。Metadata／list與串流共用既有Workspace／Project／作者及PAT scope, Resource不傳disk、key或供應商URL

保留配額前檢查私有設定。R2缺key／secret／region／bucket／endpoint、driver錯誤、非HTTPS或不安全URL、關閉TLS均回STORAGE_ERROR, 不新增uploading紀錄。傳輸失敗回安全訊息；讀取失敗不洩漏SDK exception, 刪除失敗保留可重試狀態。既有requestId／signature與成功重播維持, 不多寫物件

依TT-291最後一則Josh決定, 本機task-tracker-local與正式task-tracker先共用金鑰, 以Bucket區分。這是已接受的取捨, 沒有宣稱金鑰已限縮到單一Bucket；不測正式Bucket403。本次所有寫入與清理僅task-tracker-local, 未碰正式Bucket

## 實際驗證

- 真Laravel＋隔離MySQL＋R2：上傳68 bytes PNG, 同requestId只1次PutObject, Session與PAT讀回相同bytes、私有no-store回應、跨租戶404、綁定／刪除版本、軟刪後拒讀、到期清除後物件不存在
- R2 S3端點對未簽名GET實際回400且不是圖片內容。這只證明該S3端點拒絕匿名讀取, 不能據此宣稱Cloudflare Console的所有公開網域設定已審過
- 成功路徑TLS憑證驗證開啟。Timeout與TLS失敗以傳輸例外注入, 配置關閉TLS也會拒絕；沒有刻意使正式供應商故障或把注入說成外網故障實測
- 真Chrome從描述編輯器選圖、Markdown預覽、建卡並重載, 1440／768／390三尺寸都正常。瀏覽器沒有直接對R2送請求, 只讀應用程式的授權內容；測試物件在finally刪除
- 本機disk既有附件、公開PAT附件、配額／重播／權限／PHPDoc／依賴與Schema回歸32項3109斷言通過；真R2專屬驗收另以TASKTRACKER_R2_TEST=1執行, 不把Storage fake當R2成功
- 共用48張核准基準比較、前端build、Compose靜默檢查另存artifacts/tt291；編譯／隔離初始化與正式部署分開

測試使用專屬schema與隨機新帳號／專案／物件, 只清此次建立的精確物件鍵, 不清Bucket、共享Redis或現用DB。之前瀏覽器清理字串失敗的合成物件也已逐一補清並驗不存在, 結果在cleanup.json

## 成本與限制

單次68 bytes驗收記錄SDK邏輯命令：PutObject 1、HeadObject 2、GetObject 3、DeleteObject 2、ListObjectsV2 1。邏輯命令含URL組裝, 不等同實際HTTP請求或帳單；另有1次未簽名GET, 重播不重複PutObject。未做大量資料／付費壓測或月費推估, 不宣稱帳單成本已固定。清理與重試會增加請求, 後續營運另按實際用量觀察

Composer audit --locked仍報4個既有Symfony套件、7則公告, 本卡未改其版本, 已另開TT-318並收到部署看板。還沒做可利用性判斷與修補, 不能宣稱全專案安全驗收通過；PHP宣告與既有Symfony最低版本的相容性也交該卡釐清

官方依據：[Laravel Filesystem](https://laravel.com/framework/docs/13.x/filesystem)、[Cloudflare PHP S3範例](https://developers.cloudflare.com/r2/examples/aws/aws-sdk-php/)、[S3相容範圍](https://developers.cloudflare.com/r2/api/s3/api/)、[R2認證](https://developers.cloudflare.com/r2/api/tokens/)
