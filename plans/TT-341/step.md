# TT-341 進度

- TT-334完成後已重讀紙條, 只剩本卡；已填預估180分鐘並領取
- 查證MySQL8.4官方mysqldump：single-transaction僅保證InnoDB一致讀, 備份時不得執行DDL；仍要求兩端停寫與前後雜湊檢查, 不將單交易當成可邊搬邊寫
- 正在製作標準CLI工具與隔離演練, 不碰現用或正式庫
- 已完成標準mysql／mysqldump wrapper與操作手冊；空庫限制、兩端身分／APP_KEY比對、受控journal、目標命名鎖、完整DDL／資料核對, 不增加業務API
- 第一輪mysqldump拒絕mysql專用connect-timeout選項, 已改為只對mysql配置；失敗過程未寫目標。隔離完整schema快照及刻意錯誤SQL的失敗回退已通過, 正在測重跑與來源／目標保護
- 隔離驗證找到標準dump／restore會替TEXT多顯示CHARACTER SET, 但COLLATE已決定相同charset；原始DDL字串誤判差異。已用獨立探測schema確認, 只正規化欄位型別後的等價charset宣告, 不改collation／索引／外鍵／comment, 補自我斷言
- 檢查雜湊時補SQL NULL與JSON null區分, 所有欄位先CAST AS BINARY避免HEX把數值當整數而丟小數；目前產品沒有的浮點／空間／vector欄位先拒絕, 不假稱無損
- metadata改一次批次讀所有欄位及主鍵, 不逐表重查兩份metadata；資料仍逐表串流與逐表核對
- 已在兩套獨立MySQL8.4完成46表完整schema演練；後續JSON精度與中斷保護調整, 再以5表合成資料跑完29個成功／拒絕案例
- 最終演練包含帳號密碼雜湊保留、外鍵、二進位、小數、SQL NULL／JSON null區分、目標新寫入拒絕覆蓋、中斷重跑拒絕及回退前保存備份
- SQL產生的高精度JSON decimal可能被標準dump改變精度, 已實測偵測並拒絕／回退；正式遇到這種資料須另選無損搬運方式, 不放寬比對
- 專案冒煙、Skill完整性與資料存取審查通過；沒有修改產品畫面或業務API, 沒有操作現用或正式資料庫
- 工具準備階段完成；整卡仍等TT-323部署與Josh當次搬移授權, 不宣稱實際搬遷完成
