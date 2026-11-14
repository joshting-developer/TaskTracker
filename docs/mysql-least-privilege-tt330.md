# TT-330 MySQL應用帳號權限

## 帳號與憑證界線

保留原管理帳號與密碼, 不撤銷其他服務的資格。正式新帳號都只授權task_tracker, 要求TLS, 不含全域管理或GRANT OPTION

| 用途 | 權限 | 憑證位置 |
| --- | --- | --- |
| HTTP runtime | SELECT、INSERT、UPDATE、DELETE | Fly App的DB_USERNAME／DB_PASSWORD |
| migration | 同schema DML與CREATE、ALTER、DROP、INDEX、REFERENCES | GitHub production環境的MIGRATION_DB_USERNAME／MIGRATION_DB_PASSWORD |

schema名稱中的底線在GRANT中跳脫, 不把底線當萬用字元。兩個帳號沒有跨庫、建立使用者或重新授權能力

## 發布流程

原本Fly release_command使用App共用Secrets, 無法把migration權限和HTTP真正分開。因此改為tools/fly-migrate.py在HTTP部署前建立一次性、無service、無重啟的Machine, 使用同一個已驗證版本映像

migration憑證只透過SSH標準輸入送到docker/production/migrate.php, 不放命令列、Machine env、Fly App Secrets或PHP config cache。程序只在記憶體覆寫該次MySQL連線, 先purge再執行migrate。CI不轉印原始SSH或框架錯誤, 避免秘密進日誌

成功才部署HTTP；失敗與逾時會清理同次Machine並阻止部署。建立回應不確定時不重試建立, 以唯一名稱找回本次Machine清理, 不刪其他程序。若清理失敗必須檢查該次唯一名稱, 不宣稱已完成

一次性Machine保留600秒自動退出／銷毀作程序中斷的後備, 正常流程在finally立即刪除。這是短暫migration執行資源, 不建立常駐服務或固定出口

## 實測

- 獨立MySQL：runtime CRUD成功, DDL拒絕；migration建表／索引／改表／刪表成功, 兩帳號跨庫及GRANT都拒絕
- 正式新帳號：TLS驗CA與名稱、讀migration名稱成功, 系統庫讀取拒絕, SHOW GRANTS無全域或GRANT OPTION；未對正式庫試建資料表／帳號
- 正式映像在全新隔離schema以stdin帳號執行完整30支migration成功, 原runtime env提供無效帳密仍可由僅該次stdin憑證正確執行
- 編排測試涵蓋成功、非零退出、SSH逾時、建立回應不確定的唯一名稱回收、錯registry／非版本映像及migration先於HTTP
- 補上TT-355新公開頁的Docker build素材與正式runtime冒煙路徑, 本機Vite build不能取代映像驗證

## 目前發布限制

新編排與stdin runner會在下次整批tag映像一起發布, 不為本卡新增版本。舊v0.3.0映像沒有runner, 不可用新編排重跑舊tag後假稱相容；缺runner會失敗關閉, 不會跳過migration後繼續部署

原管理帳號仍保留作復原, 只能由已授權管理者使用, 不回填App。若runtime切換異常, 以受控舊Secrets回復相同映像, 不還原或覆蓋業務資料

參考：[MySQL權限](https://dev.mysql.com/doc/refman/8.4/en/privileges-provided.html)、[Fly Machines API](https://fly.io/docs/machines/api/machines-resource/)、[Fly Secrets](https://fly.io/docs/apps/secrets/)

## 正式切換結果

相同v0.3.0映像digest更新runtime Secrets後, 正常HTTP喚醒成功。實際Laravel載入tt_runtime, SHOW GRANTS只有該schema的DML, 沒有migration密碼, 最小migration名稱查詢成功, 自己的公開PAT亦可讀卡

新版本migration鏈已在全新隔離schema和真實Fly stdin／失敗清理驗證, 但正式新版本migration仍要等下一次tag。這個差異保留到卡片待驗收, 不宣稱新發布鏈已完成正式發版

私有現用Secrets快照已同步runtime帳號, 舊快照只留受限的.local/tt330作憑證回復, 不回復業務資料
