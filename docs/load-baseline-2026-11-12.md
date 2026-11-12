# 多worker／弱網負載與容量基準（TT-184，2026-11-12）

以TT-151的正式runtime映像（Nginx＋PHP-FPM，**不是**`artisan serve`）連隔離MySQL 8.4量測；不是memory SQLite。原始數據：[load baseline](../.ecc/benchmarks/load-baseline-2026-11-12.json)。

## 環境與方法

- 主機macOS 8 CPU，Docker Desktop VM 4 CPU／7.7 GiB。
- 容器`tasktracker:prod-local`，FPM 6 children；MySQL `tasktracker-claude-test`（tmpfs）。
- 負載產生器與資料庫在**同一台主機**：Playwright `APIRequestContext`，每個虛擬使用者一個獨立Session。
- 資料：500張卡（5 Epic），每張300字描述。
- 讀取混合：personal-version、看板version、任務列表20筆、看板卡片頁100筆、單卡、Project，依序循環。
- 每個並行度跑20秒，事前以最高並行度暖機5秒不計；延遲為client端量到的完整回應時間。
- 重跑：`PROD_BASE_URL=… LOAD_COLD_RESTART_CMD="docker restart <container>" node frontend/tools/load-baseline.js`，可調`LOAD_LEVELS`／`LOAD_SECONDS`／`LOAD_CARDS`。

## 並行讀取（暖cache）

| 並行 | 吞吐 | 錯誤 | p50 | p95 | p99 | 最慢endpoint p95 |
|---:|---:|---:|---:|---:|---:|---|
| 1 | 67 rps | 0 | 12 ms | 25 ms | 43 ms | 看板卡片頁 33 ms |
| 4 | 230 rps | 0 | 13 ms | 36 ms | 69 ms | 看板卡片頁 49 ms |
| 8 | 209 rps | 0 | 29 ms | 88 ms | 181 ms | 看板卡片頁 119 ms |
| 16 | 162 rps | 0 | 76 ms | 216 ms | 328 ms | 看板卡片頁 276 ms |

並行4達到吞吐峰值，之後排隊、延遲上升，吞吐反而下降。FPM改為12 children重量（並行4／8／16：157／172／150 rps，p95 49／101／249 ms）沒有改善，表示瓶頸是VM的CPU（負載產生器、Chrome、MySQL、PHP共用4 CPU），不是worker數。小機器上的FPM children不必比CPU多太多，預設6維持。

## 寫入正確性（並行）

- 同一張卡、同一revision，8個Session同時`updateIssue`，跑5輪：每輪恰好1筆成功，其餘35筆409；最後revision等於初始revision＋5，沒有遺失更新。
- 8個Session各並行建3張卡（共24張）：全部成功，識別碼不重複。

## 冷啟動

`docker restart`到`/api/health`成功3.55秒（含Docker停止與啟動，不是Fly Machine的冷啟動）。重啟後第一個看板卡片頁66 ms，之後中位數31 ms（OPcache與連線暖機）。

## 弱網頁面（Chrome模擬RTT 150 ms、1.6 Mbps下行、750 kbps上行）

| 500卡看板，到全部卡片出現 | 時間 | 第一個卡片頁responseEnd |
|---|---:|---:|
| 冷快取（停用HTTP快取） | 3.88 s | 2.50 s |
| 暖快取（JS／CSS已快取） | 2.39 s | 1.24 s |

暖快取時主要花在5個卡片頁（約310 KB JSON）。可再改善的方向：卡片頁gzip效益、首批卡片頁先渲染（TT-256已分批掛載，但仍等5頁收齊才交換列）。

## 限制

- 本機Docker實驗室樣本：負載產生器與服務同機、MySQL在同一VM，沒有跨雲RTT。**不是正式部署容量、p95承諾或SLO。**
- 所有虛擬使用者使用同一帳號的不同Session；多帳號與權限分布、實際寫入比例還沒有量。
- 正式拓樸（Fly東京＋OCI MySQL）的RTT與冷啟動，要部署後依TT-154／159／163重量。
