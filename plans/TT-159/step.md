# TT-159 進度

- 卡片已估120分鐘並指派codex／inprogress, 目前唯讀盤點
- 指定金鑰只在原位置使用, 不複製進repo／卡片／log
- OCI兩台SSH與sudo可用；先備份設定／證書／規則, 未讀出私鑰或帳密
- MySQL原CA簽含IP SAN的新證書, TLS重載後持久化require_secure_transport=ON；Redis先用自有空實例驗TLS, 再將原實例改TLS-only, ACL與資料保留
- 兩服務重啟active／enabled後, PDO／Predis驗證CA及名稱仍成功；錯CA／錯名稱／Redis匿名拒絕, 原帳密不變
- 防火牆修改遭自動審查拒絕, 沒有執行；Josh明確選先保留規則, 已另建TT-327 open未指派並收部署看板
- Fly沒有TaskTracker App, 不擅自建立／分配付費資源；Fly端到端與啟停恢復保留未驗收, 等TT-323可測後續接
- Laravel新增Predis TLS及default／cache驗證、3秒連線／讀取timeout；配置／分層／PHPDoc共7項67斷言通過, 實測與CA掛載文件已整理
- 原生實測追加確認MySQL及Redis明文連線均被拒絕, TLS正確CA成功、錯CA／錯主機名拒絕；未在遠端seed／壓測／FLUSH
- TLS設定方式已同步TT-321並修正該卡過期的Redis明文與密碼輪替描述；CA掛載與未驗收項目見docs/oci-service-tls-2026-11-13.md
- 本階段準備提交, 整卡仍須Fly App建立後的端到端／故障恢復驗證；防火牆另在TT-327待處理, 不以局部完成結案

## 2026-11-14 續驗

- Fly自動停止→HTTP喚醒200約3323ms, 隨後MySQL TLS讀取與Redis驗證PING成功
- 錯CA、Redis錯名稱拒絕；僅用自有loopback停滯服務驗超時, 不停OCI、不改防火牆
- 發現PDO連線上限未涵蓋mysqlnd握手讀取, 原版等到測試listener八秒關閉, 不能按三秒PASS
- php.ini補mysqlnd.net_read_timeout=3, Fly獨立CLI同設定實測MySQL3005ms、Redis3014ms拒絕並可重連OCI
- 等最後一次tag部署後再驗正式FPM, 先review保留驗收項；來源限制TT-327維持待辦
- v0.3.0上線後正式MySQL嚴格TLS cipher／Redis PING及真正fpm-fcgi讀取逾時3秒通過, 臨時內部探針已刪除
- 原驗收中的來源限制、資料埠不公開及輪替仍在TT-327／TT-330, 本卡保留待驗收並明列未完成, 不宣稱整體網路要求達成
