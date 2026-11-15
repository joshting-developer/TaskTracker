# TT-153 受控遷移與本批正式升級

估90分鐘, 沿用既有卡片不重複建卡。先完成本批5項新增migration, 再由整批版本發布卡負責tag與正式功能驗收

1. 只讀正式migration清單與schema, 固定待執行五項清單；確認皆新增欄位／表且舊映像可用
2. 新完整私有備份, 還原到本次專用無公開埠MySQL副本；正式不seed／reset
3. 副本跑同一批migration的up／down／up, 核對原業務筆數與既有欄位內容未變、外鍵與預期新欄位, 演練失敗時先停發布並修復DDL而不覆蓋新業務資料
4. 以migration專用帳號執行正式同一批遷移, 不給HTTP帳號DDL權限；不在每次HTTP冷啟動跑migration
5. 核對正式遷移清單、舊程式讀取與登入正常, 文件／工時／提交／卡片回填後, 重新讀紙條才接版本發布

本次五項為password recovery guards、account profile requests、invitation decision/sender、member status events。workspace creation requests也尚未套用, 以正式清單核對後一併納入

只新增欄位／空表, 可線上執行, 不以現用資料做產品測試。副本僅用於本次備份還原／遷移演練；SQL、帳密與備份全部留忽略私有目錄
