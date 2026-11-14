# OCI MySQL／Redis TLS（TT-159）

## 已完成

- MySQL 129.203.169.36:3306：沿用原CA, 更換含公用IP、127.0.0.1及localhost名稱的伺服器憑證, `require_secure_transport=ON`持久化；不換使用者密碼或改業務資料
- Redis 129.203.136.170:6379：停用明文port, 同埠只收TLS 1.2／1.3, 沿用原users.acl。用ACL帳密驗證, 不另外要求客戶端憑證
- 兩個服務都已重啟並確認active／enabled, 重啟後原生PHP PDO與Predis仍能驗證CA及主機名稱。MySQL cipher為TLS_AES_256_GCM_SHA384, Redis驗證後PING成功；錯CA、錯名稱均被拒絕, Redis匿名也被拒絕
- 伺服器憑證有效到2028-11-12 UTC；私鑰只留原主機, 不下載到repo。舊設定與憑證位於兩台主機各自的`/var/backups/tasktracker-tls/tt159-*`, 僅root可讀
- CA副本在忽略目錄`.local/tt159-ca/mysql-ca.pem`及`redis-ca.pem`；沒有把CA、帳密或金鑰內容放入Git／卡片
- 本機仍連目前的本地MySQL／Redis, 沒有切回過期的遠端task_tracker_testing

## Laravel設定

正式MySQL使用task_tracker, 以下是設定名稱及掛載路徑範例, 還沒有套用Fly Secrets：

```dotenv
DB_CONNECTION=mysql
DB_HOST=129.203.169.36
DB_PORT=3306
DB_DATABASE=task_tracker
MYSQL_SSL_ENABLED=true
MYSQL_SSL_VERIFY_SERVER_CERT=true
MYSQL_ATTR_SSL_CA=/run/secrets/tasktracker/mysql-ca.pem
DB_CONNECT_TIMEOUT=3

REDIS_CLIENT=predis
REDIS_HOST=129.203.136.170
REDIS_PORT=6379
REDIS_SCHEME=tls
REDIS_SSL_CA=/run/secrets/tasktracker/redis-ca.pem
REDIS_CONNECT_TIMEOUT=3
REDIS_READ_TIMEOUT=3
```

DB_USERNAME／DB_PASSWORD與REDIS_USERNAME／REDIS_PASSWORD由秘密設定提供, 不放這份文件。Predis的CA與主機名稱驗證固定開啟, default及cache都使用上述TLS設定；不要用`REDIS_URL=redis://...`覆蓋TLS連線

Fly可把base64編碼的兩張CA放Secrets, 再用files掛載；這裡只示範檔案與Secret名稱, 沒有建立App或上傳秘密：

```toml
[[files]]
  guest_path = "/run/secrets/tasktracker/mysql-ca.pem"
  secret_name = "MYSQL_CA_BASE64"

[[files]]
  guest_path = "/run/secrets/tasktracker/redis-ca.pem"
  secret_name = "REDIS_CA_BASE64"
```

外部Compose相容模式新增TASKTRACKER_REDIS_SCHEME及TASKTRACKER_REDIS_CA_FILE, CA檔需唯讀掛載在該路徑。現在的本機Compose不需改動

## 尚未完成, 不當作正式連線驗收通過

- **來源限制移到TT-327**：自動審查拒絕只開放目前SSH來源IP的方案, 因為可能阻斷未盤點的消費者；該防火牆命令沒有執行。Josh已明確決定先保留現有規則, 等列齊合法來源及Fly出口IP再收斂
- 本機Fly帳號目前查不到TaskTracker App, 沒有分配付費出口IP。Fly→OCI、Fly啟停後恢復、平台出口固定性及故障注入須等TT-323有實際App後驗證；這次只驗OCI服務重啟與本機原生TLS客戶端
- OCI雲端安全規則尚未改；目前來源暴露仍須TT-327處理, TLS不代表來源限制已完成
- 現有MySQL josh帳號有跨庫管理權限, 這次沒有變更帳號或權限；正式應用專用帳號的最小權限須另行盤點, 不將目前帳號當成最小權限已驗收

## 輪替與回復

輪替伺服器憑證時先保留設定／原證書與私鑰, 使用原CA簽新憑證並保留正確SAN。MySQL可RELOAD TLS, Redis需依既有版本重載或重啟；用實際PDO／Predis驗證後再移除舊備份。CA輪替要先讓所有消費者信任新舊CA組合, 再換伺服器憑證, 最後移除舊CA；CA私鑰不得提供給Fly

回復Redis設定時只還原本卡備份的redis.conf並重啟, 不還原資料目錄／AOF或FLUSH。MySQL若新憑證異常, 可還原備份的server-cert.pem／server-key.pem並重載；舊證書不符合新的主機身分驗證, 因此不能把還原後的連線當成正式TLS驗收

## 依據

- [Redis TLS設定](https://redis.io/docs/latest/operate/oss_and_stack/management/security/encryption/)：TLS-only連接埠與ACL搭配, 客戶端憑證驗證可分開設定
- [MySQL加密連線](https://dev.mysql.com/doc/refman/8.0/en/using-encrypted-connections.html)：自動產生的證書無法滿足VERIFY_IDENTITY, 可更換檔案後重載TLS
- [Fly Secrets與檔案掛載](https://fly.io/docs/apps/secrets/)：以Secret提供base64檔案內容, 不寫進映像或Git

## 2026-11-14 Fly續驗與待發布修正

Fly已掛載兩張CA, 正式MySQL與Redis驗CA／主機名稱成功, MySQL cipher為TLS_AES_256_GCM_SHA384；錯CA均拒絕, Redis錯主機名拒絕。從自動停止狀態經HTTP喚醒約3.32秒, 喚醒後上述連線仍成功, 這是單次驗收而非延遲p95

受控故障只在Fly診斷CLI建立loopback listener, 不修改OCI服務／防火牆或正式連線。Redis接上但不回PONG約3.03秒拒絕；MySQL接上但不送握手時, PDO ATTR_TIMEOUT=3未涵蓋讀取, 原測試直到8秒listener關閉才結束, 不能宣稱原版已有三秒完整超時

正式php.ini補mysqlnd.net_read_timeout=3, 在Fly獨立CLI以相同設定實測MySQL握手停滯約3.01秒、Redis讀取停滯約3.01秒拒絕, 兩者仍可重新連回真正OCI。listener及child由診斷程序回收

依Josh要求, 這個php.ini修正等整批tag一起發布, 沒有逐卡替換線上映像。TT-159先待驗收, 最後發布後須再次核對正式FPM載入值及故障行為；目前不能說線上FPM讀取超時已修好。來源收斂仍由TT-327處理, 不因TLS通過就說3306／6379已對外關閉
