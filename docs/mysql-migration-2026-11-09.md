# 本機MySQL切換

日期：2026-11-09。使用者已選本機與部署使用MySQL，本輪採官方`mysql:8.4` LTS，隔離實測版本8.4.13。PostgreSQL同樣可用，本專案沒有必須採用其特定功能的需求，不同時維護兩個正式引擎。

## 執行方式與資料

- `docker compose up -d --build`啟動新環境；mysql只在容器內網3306，沒有主機published port。
- mysql-secrets以無網路的一次性PHP程序，原子產生256-bit隨機密碼。root與app分開volume，API／Queue／Reverb只掛app密碼唯讀檔；沒有root連線或Git／image內固定密碼。Compose根據app實際連線／SELECT 1判斷健康。
- MySQL資料在mysql-data；密碼在mysql-root-secret／mysql-app-secret；舊laravel-data仍保存APP_KEY、Reverb金鑰、seed設定與原SQLite。`down`保留資料，**不要用`down -v`清除這些volume**。秘密遺失不能重新生成後直接連既有MySQL，須走受控密碼復原。
- Laravel新增pdo_mysql，保留pdo_sqlite供來源匯入／歷史測試。MySQL使用InnoDB、utf8mb4_bin及UTC；時間採DATETIME(6)，Repository轉換成既有ISO 8601 DTO，不變更工作日期Asia/Taipei。

## 舊SQLite離線轉移

切換前停止frontend／API／Queue／Reverb，先用SQLite `VACUUM INTO`在舊volume建立一致備份，保留來源。啟動MySQL後用新image執行migrate及下列指令，確認成功才啟動整組服務：

```sh
docker compose run --rm --no-deps backend php artisan tasktracker:import-sqlite /data/tasktracker.sqlite
docker compose run --rm --no-deps backend php artisan tasktracker:import-sqlite /data/tasktracker.sqlite --verify-only
docker compose up -d
```

這些是既有Laravel SQLite的轉移指令；舊Node格式仍用import-legacy。Importer要求目標MySQL各來源表完全無資料；只允許一次匯入者，所有插入同交易，外鍵檢查保持啟用。按照外鍵拓樸建立資料，Issue的Epic／父項先留null再同交易補回，避免來源ID順序假設。任何型別／外鍵／核對錯誤完整回滾，不覆蓋來源或既有MySQL。

保留帳號與原password hash、內外ID、revision、歷史、關聯、工時、小待辦、邀請、receipt及既有Session。migrations／新transaction_guards由新schema建立，不用舊migration紀錄蓋掉；其他表逐筆匯入，逐表比較筆數及正規化SHA-256。JSON僅忽略物件鍵順序，保留陣列順序、值與型別；日期轉UTC。`--verify-only`不寫入。實作按表載入記憶體，適合目前本機資料量，不當成大型線上庫零停機方案。

匯入後SQLite不再同步MySQL新寫入。保留舊檔不代表能無損回退新資料；MySQL開始有新寫入後，回退需另做資料搬移。新環境不自動從舊SQLite匯入，避免啟動時意外覆蓋。

## 交易保護

- 寫入在ApiExecution同一交易，先鎖User，再鎖目前Workspace，之後才讀取新鮮資格／Project ACL／revision／receipt與工時總量。MySQL採REPEATABLE READ，首次業務一致性讀在取得鎖之後，避免等待時已建立舊snapshot。
- Workspace鎖涵蓋同空間寫入，故同一人每日上限、跨卡片關聯、編號與receipt不依賴「工時列已存在」。不同Workspace可並行，保守維持同Workspace序列化；本輪沒有宣稱專案內高寫入吞吐量。User鎖也保護跨Workspace個人資料版本。
- 註冊／登入／接受邀請先鎖transaction_guards的accounts固定列，避免空帳號／新資格競爭，再按User／Workspace順序鎖定。這是低頻帳號流程鎖，不是所有API全域鎖。
- 唯讀API維持同一交易快照，不取得Workspace寫鎖；SQLite的DEFERRED＋query_only子類僅保留快測與舊引擎相容，不套到MySQL。MySQL本輪沒有新增資料庫層read-only enforcement。
- Session blocking、CSRF、X-Workspace-ID、permission＋scope、作者限制與afterCommit事件不變。不自動重跑包含Session／Auth副作用的交易；死鎖／失敗回滾，依原requestId重新提交仍需當次授權。
- CLI匯入及seed是離線維護操作，不與使用者寫入併跑；直接呼叫領域服務不是另一條可略過上述鎖的HTTP入口。

InnoDB鎖與隔離語意依[官方locking reads](https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html)及[隔離層級](https://dev.mysql.com/doc/refman/8.4/en/innodb-transaction-isolation-levels.html)，查證2026-11-09。

## 驗證

預設`docker compose exec backend php artisan test --do-not-cache-result`仍強制memory SQLite，不能清空主MySQL。真實MySQL由專用Compose啟動，使用不同project、帳號、網路及tmpfs，沒有主volume：

```sh
docker compose -p tasktracker-mysql-check -f docker-compose.mysql-test.yml up -d
docker compose -p tasktracker-mysql-check -f docker-compose.mysql-test.yml exec backend php artisan test --do-not-cache-result
# 只清理這個隔離project
docker compose -p tasktracker-mysql-check -f docker-compose.mysql-test.yml down -v
```

完整71 tests／2550 assertions通過（原SQLite66項＋MySQL5項）。MySQL證據包含UTF-8／微秒／DTO、工時上限與receipt、不同程序同時填時、同revision只能一個保存、相同requestId只建一次、等待鎖期間撤權後拒絕寫入；匯入測晚期失敗回滾、來源hash不變、完整核對、重匯入拒絕與內容變動偵測。JSON物件鍵順序差異使用嚴格型別且保留list順序的比較，不降低資料斷言。

隔離MySQL真實瀏覽器：完整任務／看板／ACL／小待辦流程、多工作區／邀請／跨分頁409、三種工時分析／撤權／三尺寸均通過。PHP Pint與Compose config通過；主服務切換／持久化／基礎設施驗證於完成後補記。沒有兩引擎容量benchmark或正式部署成功宣稱。
