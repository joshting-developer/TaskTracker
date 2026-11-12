# TT-224 慢請求與Session鎖生命週期

2026-11-12。先在獨立MySQL／四worker HTTP重現，再修正；不是把10秒改成更長的有限TTL。

## 重現與結果

測試router僅可在testing、tasktracker_test帳號／schema及專用測試前綴下啟動，不加入正式routes。真實Guzzle CookieJar／CSRF、三個帳號、兩工作區；慢路由在Session載入後等待12秒，第10.5秒同Session切換工作區。

| 場景 | 原StartSession block(10,10) | 修改後 |
| --- | --- | --- |
| 切換請求等候 | 0.123秒 | 約1.6秒，等原請求完成 |
| 慢請求結束後Session | 被覆寫回A | 保持新B |
| 另一請求無法即時取得鎖 | 框架TTL行為 | 有界等候，409 CONFLICT且不進路由 |
| 舊分頁X-Workspace-ID=A、目前B | 原有契約 | 409，未更新卡片 |
| 慢保存期間另一帳號撤專案資格 | 原有交易重驗 | 403／404，標題及revision保持 |
| 持鎖程序終止 | 有TTL鎖 | DB連線關閉後自動釋放，下次可取得 |
| 交易內刻意失鎖 | 無明確失鎖檢查 | CONFLICT，測試寫入rollback |

原先是風險推論，本輪已實際重現舊Session覆寫；基準保存在[JSON](../.ecc/benchmarks/session-lock-2026-11-12.json)。`TASKTRACKER_SESSION_BASELINE=1`僅用於舊實作的重現測試，不是產品開關；正常驗收不設此值。

## 實作與邊界

- AppServiceProvider以Container將框架StartSession綁到GuardedStartSession。保留框架Session啟動、Cookie、CSRF、保存與路由blocking；未新增產品路由或替代Auth。
- SessionLockFactory在**現有MySQL寫入PDO**先取得GET_LOCK；沒有額外PDO、資料表或Redis命名空間。鎖名稱以app／env／DB／Cookie名稱／Session ID雜湊，符合MySQL名稱長度上限且不含明文Session ID。
- 鎖在讀Session前取得，完整持有至保存完成才finally RELEASE_LOCK。GET_LOCK的timeout是等待上限，不是持有租期；路由waitsFor仍為10秒。SQLite僅隔離快測時沿用原框架TTL鎖，不能以SQLite證明此MySQL修正。
- SessionLease以IS_USED_LOCK／CONNECTION_ID重驗原寫入連線持有權；ApiExecution在既有交易執行前／後驗鎖，保存Session前再驗。失鎖拒絕提交，保留User→Workspace鎖順序、permission＋scope、revision、receipt與afterCommit。
- finally釋放錯誤時主動關閉PDO並寫不含秘密的簡短log，避免蓋掉原始業務失敗；斷線由MySQL回收連線鎖。沒有用無TTL的Redis永久鎖或全庫清理。
- HTTP等候失敗回既有409 CONFLICT，不自動重送業務。若交易已commit但回應／Session保存時斷線，仍可能結果不確定；先讀回、以原requestId核對，不承諾跨DB commit與Cookie回應的原子性。

## 成本與部署要求

這是修正資料一致性，不是降低SQL次數：一般ApiExecution請求多GET_LOCK、交易前／後驗鎖、保存前驗鎖、RELEASE_LOCK，共約5次輕量SQL；csrf-cookie等無業務交易請求約3次。無原始業務列掃描、無全Workspace讀取／新增輪詢，也未新增鎖表寫入。仍增加SQL RTT並占用既有worker／PDO等待；本輪本地MySQL不等於遠端拓樸容量驗收，TT-184容量／弱網及TT-151正式HTTP runtime仍open。

同Session刻意保持序列；不同帳號／Session不共用鎖，測試的另一Owner可在慢請求期間撤權。長請求仍會拖延同Session其他請求，超等待上限要重試；根本上的API縮小與智慧輪詢仍依其他卡片處理，不能因此結案TT-180／219／226。

MySQL命名鎖要求連線固定到同一主庫；不能把此設計原樣套到交易池化代理／多個獨立可寫主庫。發布時需停止接新請求、排空或終止舊worker，讓所有HTTP worker一併載入新middleware；舊cache TTL鎖與新DB命名鎖不互斥，不能宣稱混版rolling update安全。本機與隔離服務均整個重啟HTTP；Queue／Reverb亦依後端規範重啟，但未在正式雲端部署。

## 驗證與工具

- 原生MySqlSessionConcurrencyTest：2／34，四worker慢請求／切換、等待拒絕、跨空間、撤權、程序退出與rollback；程序用獨立process group，finally只終止本次server／client並清除專用暫存檔。Cookie輸入0600，不輸出或提交。
- 原生MySqlApiTest＋MySqlConcurrencyTest：20／542，含User／Workspace鎖、工時24h、版本／重播與撤權重驗。
- 最後原生Session＋大量工時：3／107。
- 完整隔離memory suite：87通過／3848 assertions；25項MySQL專用測試依護欄略過，其中本輪相關23項已在隔離MySQL另測，未把略過當原生通過。
- 真實Chrome＋隔離HTTP：workspace-switch測試通過，包括兩工作區、私有專案／待辦、草稿、舊分頁409、重試與三尺寸。
- Pint在一次性無網路、明確lint標籤及可寫工作目錄的容器執行；原服務程式碼掛載唯讀，直接exec Pint不能寫入。本輪補做TT-182三檔格式修正（無業務變更），並重跑原生大資料與完整memory測試；不以先前失敗的Pint指令當通過證據。
