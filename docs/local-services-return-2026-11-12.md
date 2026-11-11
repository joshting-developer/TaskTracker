# 開發環境切回本地 MySQL／Redis

日期：2026-11-12。使用者授權先將外部最新MySQL資料同步回本地，再切換MySQL／Redis並繼續效能工作。追蹤TT-247。

## 原因與證據

本機PHP跨網路查外部MySQL／Redis，正常單次看板摘要1019 bytes耗時2203ms、38卡摘要21494 bytes耗時2334ms。卡片寫入常等待數十秒，增加實作、驗證與狀態核對時間。等待本身不直接消耗模型token，但反覆poll／重試及交接會增加工具與對話成本；未將此描述為精確token計費量測。

切換後同一正常AI資格：登入／選工作區整段384ms、看板摘要同1019 bytes／49ms，效能板因新增本次遷移卡變39卡22008 bytes／30ms。各只取樣一次，無暖／冷cache對照；卡片筆數有差異，不能當p95或嚴格加速倍率。這是開發拓樸調整，不代表已修完高SQL數、N+1、全量API或完成正式容量驗收。

## 無損同步

1. 先建立TT-247並完成其他正在進行的卡片狀態更新；確認遠端本服務Queue ready／reserved／delayed全部0。
2. 啟動全新本地volume，暫停frontend／API／Reverb寫入，再確認Queue全0後停止worker。
3. 外部MySQL完整mysqldump（含DDL／索引／FK／trigger／routine／event，單交易、不含mysql系統帳號）。私有備份remote.sql為2,951,293 bytes／33表，檢查完整結尾與SHA-256。
4. 匯入前再次確認目標tasktracker schema為空，禁止覆蓋非空庫。新本地MySQL8.4.13與外部8.0.46逐表列內容雜湊一致；JSON物件鍵排序正規化、陣列順序與型別保留。33表／5,818筆全部相同：9 users、3 workspaces、4 projects、346 issues、8 boards、174 time_entries、3269 sessions。
5. 根忽略.env選docker-compose.yml加.local/local-volumes.yml；重建API／worker／Reverb／frontend，APP_KEY及Reverb秘密沿用原volume。實際PDO為mysql:3306／tasktracker，Redis default及cache皆redis:6379／DB0，namespace為tasktracker:local:。

備份、來源連線與驗證manifest只放忽略的.local/backups/local-services-2026-11-12，目錄0700／檔案0600。不提交資料、Cookie、密碼或原始payload。不是用舊本地庫直接覆蓋新進度，也沒有執行seed或重設帳號。

## Volume與環境

- 現用全新MySQL：tasktracker_mysql-local-20261112。
- 現用全新Redis：tasktracker_redis-local-20261112；不搬舊cache／lock，切換前工作已排空。
- 原tasktracker_mysql-data／redis-data、外部兩台服務及歷史備份完整保留，未執行down -v或共享Redis flush。
- 根Compose對新環境仍預設原本volume名稱；本機以忽略的local-volumes override固定新volume，其他機器不能依此檔名假設資料已存在。
- Docker Compose v2.15.1不會僅因volume名稱override變更而重建服務；本次檢查實際Mounts後以force-recreate修正，匯入前已確認新掛載。後續移轉同樣必須查實際volume，不能只看config成功。

工作區／Project／permission＋scope、revision、requestId及歷史保留。仍禁止對現用業務庫執行測試seed／RefreshDatabase；測試沿用獨立tasktracker-perf-test及tasktracker_test schema。

## 切換驗證

Compose配置與全部服務健康通過；tasktracker:check-infrastructure確認Redis連線、實際Queue派送／消費及WebSocket升級連線。主庫只做正常登入與唯讀資料核對，沒有seed／測試資料。後續應用回歸仍使用獨立隔離服務。

## 回復限制

外部庫在本次切換後不再接收本機寫入，不能直接把.env換回remote模式。若未來要外部部署，須再次停寫、備份並把本地新進度正向同步至已核對的目標，驗證後再切。私有remote.env僅是設定備份，不保證外部資料仍是最新。
