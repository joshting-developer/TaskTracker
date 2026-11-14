# MySQL搬遷與回退

本文件是TT-341的搬遷準備, 不代表資料已搬到正式環境。只在TT-323部署成功、Josh當次授權來源／目標與停寫時段後執行。這次沒有操作本地現用庫或正式庫

## 工具與限制

`tools/mysql-transfer.py`使用MySQL8.4的mysql／mysqldump客戶端, 支援原生執行或明確指定自己的client container, 不要求應用程式改用Docker。需要Python3標準函式庫, 不新增應用依賴。與HTTP業務分層無關, 不新增API

支援有明確主鍵的InnoDB資料表, 保留DDL、索引、外鍵、AUTO_INCREMENT、帳號密碼雜湊及所有資料。逐表以主鍵穩定排序, 全欄位雜湊核對筆數與結構, JSON物件鍵排序正規化、陣列順序保留, 數字以明確型別及十進位精度編碼, 不經一般浮點截斷；NULL與二進位保留。整張表的查詢輸出先落私有暫存檔再逐列雜湊, 記憶體不隨整庫筆數增加；暫存磁碟仍需足夠空間

DDL比對只正規化欄位型別後已由COLLATE決定的重複CHARACTER SET宣告, 不移除collation、索引、外鍵或comment。SQL NULL與JSON null分開編碼, 數值先轉二進位文字再雜湊, 避免HEX數值轉換遺失小數

目前TaskTracker沒有view、routine、trigger或event。工具遇到這些物件、非InnoDB、沒有主鍵, 或浮點／空間／vector欄位會停止, 不靜默忽略。MySQL SQL運算建立的高精度JSON DECIMAL可能在標準邏輯dump重新解析時改變精度, 工具會偵測並回退, 不靜默接受；遇到這種資料需另行設計無損搬移或使用相容的實體備份, 不可關掉比對。來源與目標版本／DDL不同時核對可能拒絕, 需先另做相同版本演練, 不能把失敗說成可安全搬移

`--single-transaction`不是持續寫入的搬遷方案。MySQL官方指出備份時仍不能有並行DDL；工具要求兩端停寫, 比對備份前後來源, 匯入前再核對一次來源。參考[MySQL8.4 mysqldump](https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html)

## 私有連線與APP_KEY

設定、option files、APP_KEY檔及bundle只放被忽略的`.local`或受控備份位置, 目錄0700、檔案0600。不得貼進卡片、終端紀錄或Git。mysql option file要自行配置正確host、port、user及password, 遠端依環境補`ssl-mode=VERIFY_IDENTITY`與CA；不得因這次本機隔離測試沒有TLS就關閉正式驗證

設定檔結構如下, 不含實際帳密：

```json
{
  "source": {
    "database": "SOURCE_DATABASE",
    "defaultsFile": "/absolute/private/source.cnf",
    "appKeyFile": "/absolute/private/source-app-key"
  },
  "target": {
    "database": "TARGET_DATABASE",
    "defaultsFile": "/absolute/private/target.cnf",
    "appKeyFile": "/absolute/private/target-app-key"
  }
}
```

`container`可省略, 原生環境直接找PATH中的mysql／mysqldump。指定container時, option file路徑是該容器內的私有檔, APP_KEY檔路徑仍是執行Python的主機路徑。禁止把正式帳密放進Compose測試檔

兩個APP_KEY檔都只放實際金鑰值, 必須完全相同, 工具只記SHA-256, 不輸出金鑰。正式服務也必須真的使用該金鑰, 不能只為了通過檢查建立相同的假檔。保留帳號與PAT資料不等於移轉MySQL登入帳號；目標DB帳密需另外配置

## 發版當天

1. 確認TT-323部署成功及Josh當次授權, 記錄來源、目標、版本、網路／TLS與停寫時段。前一天先以相同schema及引擎版本演練
2. 兩端進maintenance並停止API寫入, 盤點PAT工具、worker、scheduler、Reverb及其他DDL來源；等待已接收寫入完成, 清空或明確交接本服務待處理工作。只操作本服務的Redis namespace, 不flush共享Redis
3. 備份兩端設定、APP_KEY、資料庫與附件。既有正式空服務可能已跑migration, 這仍不是「完全空庫」；不要直接匯入或覆蓋。先保留其完整備份, 在當次授權下另外準備完全空的目標schema。工具本身不提供強制覆蓋／清空選項
4. 若需使用暫存schema驗收, 先用新的明確名稱, 驗證後再於maintenance切換DB設定；MySQL不靠改資料庫名稱當切換手段。正式物理schema要用`task_tracker`時, 需先在當次授權內處理空部署留下的schema與備份, 不能拿現有業務庫當空庫
5. 執行snapshot。它只讀兩端, 建立`source.sql`、`manifest.json`及空目標原始狀態紀錄, 失敗的bundle不可使用, 換新bundle重做
6. 執行apply。必須輸入完全相同的目標資料庫名稱, 取得目標伺服器命名鎖後才寫入；匯入與完整核對前不解除maintenance
7. 執行verify。只有來源／目標全部資料表筆數、DDL與全內容雜湊相同才通過, 不是只抽查幾張表
8. 附件依TT-292搬到R2並完成實際授權下載驗收；本工具只搬MySQL, 不複製本機附件、Redis內容、檔案Session或秘密volume。確認DB內附件鍵與實際物件一致
9. 設定正式APP_KEY與DB連線後重啟必要程序, 先維持寄信／背景派送暫停, 檢查既有outbox與未完成工作, 不把歷史pending mail當作要重新實寄的診斷信
10. 驗證既有帳號登入、PAT權限與撤權、選工作區／專案、讀卡／留言／工時／附件。這些是正式上線驗收, 不以本次隔離工具測試取代。通過後才解除maintenance, 記錄切換點；來源舊庫保留停寫, 不再雙寫

```sh
python3 tools/mysql-transfer.py snapshot --config .local/mysql-transfer/config.json --bundle .local/mysql-transfer/new-bundle --confirm-stopped
python3 tools/mysql-transfer.py apply --config .local/mysql-transfer/config.json --bundle .local/mysql-transfer/new-bundle --confirm-stopped --confirm-target TARGET_DATABASE
python3 tools/mysql-transfer.py verify --config .local/mysql-transfer/config.json --bundle .local/mysql-transfer/new-bundle --confirm-stopped
```


## 中斷、重跑與回退

- apply前目標有任何資料表即拒絕, 不能用`--force`略過
- journal在寫入前保存目標伺服器／schema識別與importing狀態。一般失敗會清掉本次空目標中的已匯入表, 回到空庫；來源不修改
- 若程序被強制終止而留下`.lock`, 先確認原程序及其mysql client已停止, 人工檢查journal與兩端停寫後, 才移除該bundle的鎖。不可拆別人的進行中鎖
- 同bundle重新apply遇importing會停止, 先檢查部分目標是否有新進度, 再明確rollback後重新apply；不因看到中斷標記就自動刪資料。每次真正清理前會另外保存私有目標SQL備份與SHA-256, 備份失敗不清理。已成功applied時只核對並回報已完成, 不重建帳號或重複插入
- 目標有不在快照中的物件時停止清理；需人工調查, 不猜那是不是別人新建的表
- 已成功匯入但還沒放行寫入時可用rollback, 會先確認目標仍和快照完全一致再還原空庫
- 即使還在maintenance, 登入、PAT最後使用時間、Session或背景工作也可能寫入DB；這些驗收做完後若要回退, 同樣要先保存新變動, 不能假設目標仍等於匯入快照
- 正式庫已恢復寫入後, 工具會拒絕自動回退已改變的內容。必須重新maintenance、備份新增進度, 另外規劃回搬或修復；不能直接切回已落後的來源庫

```sh
python3 tools/mysql-transfer.py rollback --config .local/mysql-transfer/config.json --bundle .local/mysql-transfer/new-bundle --confirm-stopped --confirm-target TARGET_DATABASE
```

來源備份、target journal與manifest都需保留在私有備份區。SHA-256只保護意外損壞與比對, 不是簽章, 不可使用來源不明或遭修改的SQL bundle

## 隔離演練

`python3 tools/mysql-transfer-check.py`建立並清理本次專用的兩套MySQL8.4, 無公開埠、無現用volume；使用隨機臨時憑證。來源schema只取Codex隔離測試庫的no-data DDL, 再自行建立合成帳號／資格／JSON／NULL／二進位及外鍵資料, 不搬任何現存帳號或任務

需要先有`tasktracker-codex-test`隔離環境提供目前migration後schema。演練結果只輸出檢查項目與筆數, 原始備份與臨時憑證會清理, 不進Git。正式執行仍要另外授權
