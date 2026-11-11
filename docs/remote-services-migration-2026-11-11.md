# 外部 MySQL／Redis 切換

日期：2026-11-11（Asia/Taipei）。使用者授權遷移全部現有資料並切換連線，之後追加外部 Redis 與服務／環境隔離設定。

## 2026-11-12現況

已完整反向同步回本地MySQL／Redis，當前設定與驗證見[本地切換](local-services-return-2026-11-12.md)。下文為歷史外部模式，不可直接切回當作最新資料。

## 原外部設定

| 服務 | 位置 | 用途 |
| --- | --- | --- |
| MySQL 8.0.46 | 129.203.169.36:3306／task_tracker_testing | 全部業務資料、Session、migrations |
| Redis | 129.203.136.170:6379，ACL user josh | Cache／鎖、Queue；Reverb scaling 頻道預留 |
| Web | http://127.0.0.1:5174 | 本機前端入口維持原址 |

MySQL帳號為josh；兩台是不同主機。秘密在忽略的`.local/remote-mysql/password`與`redis-password`，目錄0700／檔案0600，以唯讀掛載提供給API／Queue／Reverb，沒有寫進Git、image或前端。根目錄忽略的`.env`透過`COMPOSE_FILE=docker-compose.remote-db.yml`選擇外部服務模式；無此檔案的新環境仍使用原`docker-compose.yml`本機服務。可由根目錄[環境範例](../remote-services.env.example)設定，密碼另行安全移轉。

MySQL使用TLS加密。原先缺少可驗證的CA，自動審批拒絕未經授權的停用驗證設定；使用者隨後明確授權**此測試環境暫不驗證伺服器憑證**。只有忽略的本機`.env`設`TASKTRACKER_DB_SSL_VERIFY=false`，範例與Compose預設均驗證。後續取得CA時放入秘密目錄，設定`TASKTRACKER_DB_CA_FILE=/run/tasktracker-app/ca.pem`並改`TASKTRACKER_DB_SSL_VERIFY=true`，重建長駐服務後確認連線。這項暫時例外不能複製為正式部署預設。

Redis目前由使用者設定的TCP端點提供，未宣稱TLS；帳密不等於傳輸加密。正式部署可再按網路方案選私有網路／隧道或TLS端點，本輪不修改遠端主機防火牆或服務安裝。

## MySQL資料搬移

1. 確認目標為空，保留來源MySQL與原APP_KEY／Reverb金鑰volume；新增TT-211追蹤並收錄部署看板。
2. 停止frontend／API／Queue／Reverb，完整mysqldump備份33張表與DDL、索引／外鍵。未執行seed、重設密碼或改變原有業務權限。
3. 匯入目標，逐表依欄位內容產生列雜湊，排序後聚合比對。JSON正規化物件鍵順序，保留陣列順序與資料型別；其餘欄位逐值核對。不是只比較筆數。
4. 等待TLS確認期間曾恢復舊服務；最終切換前再次停寫，先確認目標仍完全等於本輪已匯入快照，才在交易中同步來源最新內容。最終33張表／13,367筆一致（含9 users、3 workspaces、4 projects、310 issues、7 boards、174 time_entries）。310張卡包含本輪TT-211／212；後續正常操作可增加或修改資料。
5. `.env`切換外部Compose；API／Queue／Reverb同一組DB參數與秘密。Laravel migrations全為Ran，實際PDO連到指定庫，TLS cipher非空；原本機MySQL停止但不移除volume。

備份與逐表雜湊只存`.local/backups/remote-mysql-2026-11-11/`，包括初次`source.sql`、最終`final-source.sql`與`final-verification.json`；均0600，不提交資料快照。Session在服務啟動後會自然變動，不應再用舊全庫快照覆寫正在使用的新庫。

## Redis使用規則

- `TASKTRACKER_REDIS_NAMESPACE=tasktracker:local`對應`REDIS_PREFIX=tasktracker:local:`；服務與環境由env指定，不同部署環境須使用不同值，例如`tasktracker:staging`／`tasktracker:production`。
- Cache再加`CACHE_PREFIX=cache:`，實際key如`tasktracker:local:cache:<key>`；Queue為`tasktracker:local:queues:default`及其reserved／delayed等key。Cache與Queue可用同一Redis DB 0，DB編號只是輔助，隔離主要依prefix。
- Session仍在MySQL，切換Redis不搬移登入Session。Cache不搬移，依業務版本與有效權限重建；原本機Queue確認沒有待消費、reserved或delayed工作才切換，沒有丟棄排隊工作。
- Cache tags是`Cache::tags([...])`，用於同一環境內分組失效，不是`Redis::tags()`，也不能替代key前綴。現行業務不為了隔離而強制改成tags；已驗證Redis store支援tagged get／put與限定tag清除。
- 禁止對共享Redis使用`FLUSHDB`／`FLUSHALL`，包括可能清全DB的`cache:clear`／`Cache::flush()`；prefix不會讓這些命令變成單服務清除。只刪明確key或限定tags。遠端ACL仍應依實際服務key與必要命令限制，prefix本身不是安全授權邊界。
- Reverb scaling目前未啟用；未來啟用時頻道使用`${TASKTRACKER_REDIS_NAMESPACE}:reverb`，Pub/Sub頻道不因Redis DB編號而隔離。其帳密同樣讀秘密檔，避免另外留下明碼來源。
- 外部Redis模式不啟用`local-redis` profile；本機fallback才設`COMPOSE_PROFILES=local-redis`並清除外部Redis host／帳號／密碼檔設定。原本機Redis volume保留，未flush。

## 驗證與限制

- API真實AI登入、卡片讀取、五個看板入口通過；帳號仍為TaskTracker Project Maintainer／Workspace一般成員。
- 33張表完整內容比對通過；MySQL使用者／卡片數、TLS cipher及實際DB_HOST讀回確認。
- 隔離memory SQLite回歸66 tests／2,483 assertions通過，5個需要獨立MySQL Compose的案例明確skip；沒有在遠端主庫跑RefreshDatabase或seed，也未把memory結果當遠端壓測。
- 兩個長駐服務切換後，真實Chrome重新登入並開啟五個看板通過；原本機MySQL／Redis皆已停止，所有現用應用服務healthy／running。兩份PHP設定Pint檢查通過。
- 初次Redis TCP逾時；使用者完成網路設定後ACL PING成功。遠端prefix／tags讀寫預檢通過，原本機Queue／reserved／delayed數皆0後，已同步切換API／Queue／Reverb；最終讀回確認default／cache連線皆為129.203.136.170、ACL user josh與tasktracker:local:；遠端Queue確實消費、WebSocket握手通過。
- 本機程式跨網路查詢遠端MySQL，真實getWorkspace可達數秒至十餘秒；本輪不宣稱低延遲或額外改寫查詢。之後程式部署位置與資料庫距離會影響體感。

## 回復原則

外部模式的`docker compose up -d`會沿用根`.env`，不再自動啟動舊MySQL。不要用`--remove-orphans`或`down -v`清理舊資料與秘密volume。

原`docker-compose.yml`仍可明確選用本機模式，但**舊MySQL在切換後不再更新**，不能直接切回就當作無損回復。若新庫已有寫入，須先停寫、備份新庫，經核對後將最新資料反向同步到本機，才切換原Compose；保留相同APP_KEY與必要Session配置。Redis切回同樣先停止生產新Job並排空工作，再同步API／Queue設定，不跨環境共用namespace。
