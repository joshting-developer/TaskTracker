# TT-159 Fly與OCI加密連線

- 已讀Claude交辦與Josh授權, TT-325已review後才領取本卡, 預估120分鐘
- 先用指定SSH金鑰唯讀盤點兩台主機的MySQL／Redis版本、TLS、服務及來源限制；不輸出秘密
- Redis先備份設定再開驗證伺服器身分的TLS, MySQL取得CA並確認正式連線需驗證憑證；不改密碼、不刪資料、不FLUSH
- 優先採可驗證CA的TLS, 來源規則要保留既有消費者；Fly若沒有固定出口IP, 明確記限制而非把任意來源當完成
- 驗證重啟恢復、錯CA／名稱拒絕、快速超時及最小連線；不在現用庫跑seed或壓測
- Laravel配置及CA掛載／Secrets方式同步TT-321, 需要Josh決策的用待辦回報, 本機資料源不切回舊遠端庫

## 2026-11-14 Fly續驗

- 本批順序TT-159 → TT-344 → TT-320 → TT-344結果複查 → TT-346 → TT-353／354, 最後統一打一個tag發布, 不逐卡發版
- 先驗目前Fly映像與OCI TLS及錯CA／名稱拒絕, 不改服務密碼或防火牆
- 用Fly CLI程序自己的loopback暫時listener模擬MySQL握手／Redis讀取停滯, 三秒超時後回收child與socket, 不對正式服務斷網
- 只停啟HTTP Machine驗自動喚醒, 不換映像或部署, 保留正式資料與設定
- 來源收斂仍依Josh明確決策留TT-327, 本卡不宣稱防火牆已完成
