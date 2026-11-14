# 執行紀錄

- Josh已同意Codex接手, 卡片已指派本人並inprogress, 原管理帳號不變
- 現有Fly release_command使用App共同Secrets, 若只替換runtime帳號會讓migration失敗；高權限密碼也不能直接放共同Secrets
- 改為CI持有migration憑證, 一次性無HTTP Machine使用精確版本映像, 成功後才部署HTTP

- 獨立MySQL驗runtime CRUD成功／DDL拒絕, migrator DDL成功, 兩者跨系統庫／GRANT拒絕
- 正式兩個新帳號已建立並驗TLS、有限讀取與SHOW GRANTS, 原管理帳號及資料未改
- 正式映像以stdin專用帳號在新隔離schema完整跑30支migration成功, runtime env提供無效帳密仍可執行, 證明用的是stdin那次憑證
- 實際Fly兩個短暫Machine用合成stdin字串驗成功／非零退出與清理, 最後migration Machine數0, 未在正式庫執行migration
- GitHub production已保存MIGRATION_DB_USERNAME／PASSWORD, Fly只更新runtime的DB_USERNAME／PASSWORD
- 相同正式digest的HTTP Machine更新完成, 正在讀回實際連線；主服務可能已自動停止, 以正常HTTP喚醒後再驗, 不把未啟動當憑證失敗
- 編排5項單元檢查、actionlint與正式runtime含五頁冒煙通過
- 正常HTTP喚醒後讀回正式Laravel為tt_runtime, 無migration密碼, SHOW GRANTS為schema-only DML, migration名稱有限查詢通過
- 自己的正式PAT讀卡正常；現用私有Secrets快照同步, 舊憑證只留私有回復檔
- 新migration鏈需下一次整批tag才有正式驗收, 本卡交付後先review並清楚保留此條件
