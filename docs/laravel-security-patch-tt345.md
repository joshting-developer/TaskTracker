# TT-345 Laravel安全修補

## 調整

依[官方公告GHSA-63hm-g96r-2m2q](https://github.com/advisories/GHSA-63hm-g96r-2m2q), Laravel 13.x低於13.40.0的Concurrency Process可能在特定應用寫法下信任子程序輸出, 修補版本為13.40.0

專案app／routes沒有Concurrency呼叫, 不把此公告解讀成目前產品已有可直接利用的入口

本次只更新`backend/composer.lock`的`laravel/framework`：13.37.0 → 13.40.0, 對應source／dist為`1449bf241017087fbd38c118ecaabef3f6797fdc`。其他套件版本與來源未變, 沒有新增依賴、放寬audit或忽略公告

Composer自動補寫的其他套件published-time欄位已排除, 不把無關metadata混進修補。解析及安裝都在專用PHP容器, 不執行本機產品環境scripts

## 驗證與界線

- 最終lock的正式依賴audit：advisories、abandoned皆空
- 實際隔離後端讀回Laravel Framework 13.40.0
- 後端回歸使用memory SQLite與專用tasktracker_test MySQL, 不碰現用業務DB
- 第一次執行被PHPUnit子程序128MB上限中斷, 未當通過；只在隔離容器透過PHP_INI_SCAN_DIR提供512MB ini, 重跑全套, 沒有修改正式runtime記憶體配置
- 框架版本列入視覺harness指紋, 105張候選與原版沒有版面變更, 重新綁定依賴並用原比較器驗證
- 實際測試結果與命令見plans/TT-345/step.md, 原始輸出留本機artifacts/tt345

修補會隨下一次整批tag發版, push main只跑CI。正式站在部署前仍維持v0.3.0, 不宣稱正式環境已更新

結果：後端434項通過、1項外部S3／R2整合略過, 18,319個斷言；105張正式視覺比較通過。外部物件儲存沒有在此卡重跑, 不把略過當成通過
