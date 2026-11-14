# TT-344 進度

- 單支PublicAttachmentsTest在128MB通過6項132斷言, 另6項MySQL因明確關閉被skip, peak88MB
- 開始原128MB全套重現, 尚未更改正式PHP記憶體上限

- 原128MB全套在finfo再配置約7MB時重現OOM, PHPUnit每10測試強制GC仍失敗, 沒採用無效配置
- 僅CI測試程序設512MB, 完整SQLite組430項／12884斷言, 161項依原隔離旗標skip, peak127MB；原生MySQL另驗, 不冒稱全部430皆通過
- 正式FPM192MB不變；附件無效／超大先拒絕再finfo, 新增不可讀路徑＋超大尺寸的資源邊界測試
- 192MB附件回歸18項367斷言通過, 17項原生環境skip, peak90MB；修正測試stub消除不必要mock notice

- 完整腳本首輪發現php -d套在artisan外層不會傳給其子PHPUnit, 仍128MB失敗；已改直接呼叫vendor/bin/phpunit, 不把外層CLI旗標誤當測試生效

- 直接PHPUnit後全套431項（270通過／161原有skip）、12885斷言、peak129MB；MySQL契約18項683斷言通過
- 超過原OOM點後曝出舊Python fixture未完成信箱啟用, 建Project得到401；為明確E2E_ISOLATED＋允許loopback埠的合成fixture補啟用, 不讓公開範例讀Session或把正式驗證略過

- 完整tools/api-contract-ci.sh最終exit0：SQLite270通過／161原有skip、12885斷言, MySQL契約18項683斷言, 下載範例、獨立客戶端、公開附件checksum、文件五頁三尺寸與限流皆通過
- 自動審查首輪拒絕main push, 理由未看到當下推送授權；已核對7a5a22e明訂持續push且main不部署, 先完成本機commit再核對遠端推送
