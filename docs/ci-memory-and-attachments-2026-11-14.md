# 契約CI記憶體與附件資源檢查（TT-344）

## 原因與處理

原CI以PHP預設128MB執行整套測試。相同隔離環境單跑PublicAttachmentsTest, 6項132斷言通過、峰值88MB；長程序全套在附件MIME辨識再申請約7MB時耗盡。PHPUnit每10項強制垃圾回收仍重現, 沒有保留無效配置

改用512MB只套在tools/api-contract-ci.sh的兩個PHPUnit命令, 不改正式PHP-FPM的192MB、Fly512MB規格或HTTP測試服務設定。完整SQLite組峰值127MB, 430項中269通過／161因原生MySQL或環境條件skip, 12884斷言；原生MySQL另跑, 不是把skip當通過

## 附件入口

AttachmentUploadData先驗上傳有效性、路徑與各類型最大值, 才讓finfo讀內容；判斷格式後仍驗該格式獨立上限、圖片尺寸、PDF／ZIP結構與checksum, 不採前端MIME或放寬ACL

新增資源邊界測試給超大檔與不可讀路徑, 必須直接得到輸入錯誤, 不能先開檔嗅探。原生隔離MySQL附件組在192MB上限通過18項367斷言, 峰值82MB；SQLite同組峰值90MB

這些是合成附件測試, 不等於已證明任意惡意檔都固定使用相同記憶體, 也不是Fly整機併發容量測試。完整測試上限增加不直接增加正式請求用量, 提前拒絕則可避免已知無效大檔進昂貴辨識

## 驗收範圍

完整tools/api-contract-ci.sh及main GitHub Actions仍須實跑；失敗不跳過附件測試。依Josh要求, main push只做CI, 正式程式等整批一個tag發布；沒有把本機通過當成線上已更新

完整腳本進一步確認Artisan會另開PHPUnit程序, php -d若只套在artisan外層仍回128MB, 因此CI直接用php -d memory_limit=512M vendor/bin/phpunit。最終全套SQLite組431項（270通過／161原有skip）、12885斷言, 峰值129MB, 已超過128MB上限；原生MySQL契約18項683斷言另通過

走過OOM後另發現舊Python fixture未跟上信箱啟用, 建Project得到401。只對E2E_ISOLATED=1及明確loopback測試埠的合成帳號補啟用；下載公開範例仍只得到PAT, 不取得Session或測試捷徑
