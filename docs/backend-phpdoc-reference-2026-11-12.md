# TT-261 測試與 migration PHPDoc 補齊

2026-11-12。接續 [TT-235 盤點](backend-phpdoc-2026-11-12.md) 的參考範圍。本卡補註解及檢查範圍，不修改產品功能、測試斷言、migration schema 或歷史資料升級邏輯。

## 範圍與結果

| 範圍 | 檔案／類別／方法 | 修改前 | 修改後 |
| --- | --- | --- | --- |
| 原 required：app、seeders、factories、tools、測試支援與基底 | 258／250／846 | 0 個問題檔案 | 0 |
| reference：Feature、Unit、migration、routes、config、bootstrap | 100／87／241 | 33 個問題檔案、116 項 | 0 |

本次缺漏分布：方法無 docblock 73、缺 return 21、缺摘要 13、類別無 docblock 6、缺參數說明 3。原始清單為 TT-235 當時的 32 檔114項，這次以目前程式重新盤點，不沿用舊數字。

- 測試摘要逐一說明驗證目標，例如鎖後撤權、每日工時競態、原始簽章重播、Epic／子任務、私有待辦、匿名文件與正式 Schema；不是批次套「測試功能」等空文案。
- 輔助方法依實際簽章補 param／return；HTTP helper 回 TestResponse，不誤寫業務 array。只回定位的 Project／Issue／Todo fixture 與讀回完整資源的 helper 分開說明。
- migration 說明實際建立／移除的表或索引。依賴轉關聯、feature 轉 task、ACL 升級的 down 不會猜測逆轉，明寫需升級前備份；沒有將空的 down 說成會還原資料。
- 核對其餘 migration 既有說明，保留關聯表回退先匯出、Markdown 原稿保存、狀態回退保留與稽核欄位回退等語意。
- routes／config／bootstrap 未發現需補 PHPDoc 的自有具名函式／類別；匿名 route／設定 closure 不捏造為類別方法。產生的 bootstrap/cache 不屬自有來源。

## 回歸門檻

`tests/Unit/PhpDocCoverageTest.php` 擴大到全部 tests、database/migrations、routes、config、bootstrap，保留原 app／seeders 檢查，排除 bootstrap/cache 產物。既有缺漏 fixture 的負面測試及斷言不改。

CLI 仍保留 required／reference 分組以便歷史數據比較，reference 現在同樣要求零缺漏，並非可忽略的例外。完整來源檢查：

```sh
cd backend
php tools/audit-phpdoc.php --list --all
php artisan test --filter=PhpDocCoverageTest --do-not-cache-result
```

tokenizer 門檻驗證摘要／標籤及參數名稱的存在；不代表 PHP 型別推論或註解語意的完整證明。用途、nullable、回傳與回退說明另對照實際實作核對。

## 不改行為的證據

補註解後，33 個受影響檔案去除 comment／docblock／whitespace 的執行 token 與起始版本完全相同。Pint 隨後調整既有單行格式、import 與 trait 順序；以既有 PHP Parser 解析 import，排除註解／位置，對無 adaptation 的相同 trait 集合排序後比較 AST，33 檔沒有執行節點差異。沒有忽略 assertion 或 migration 的任何可執行節點。

唯一刻意改動的測試執行邏輯是 PHPDoc 門檻的來源目錄與產物排除；業務測試、資料庫 migration 均無邏輯改動。沒有安裝新分析套件。

## 驗證

- 限定異動檔 Pint 通過；audit `--list --all` 的 required／reference 均零缺漏。
- memory 完整 suite：155 passed、66 skipped、7,855 assertions；skip 為另行執行的 MySQL／環境條件，未算成 memory 通過。
- 擴大門檻並排除 bootstrap/cache 後，PhpDocCoverageTest 再測2項通過。
- 原生 MySQL 8.4.13 完整 suite：65 tests、2,128 assertions 通過。獨立 `tasktracker-codex-docs` Compose／localhost:13069／tasktracker_test 帳號及 schema，512MB 測試程序限制，實測峰值155MB。
- 未更動前端，無需瀏覽器回歸；未修改產品查詢，不以這些測試宣稱效能提升。
- 主庫未 seed／reset、未 push、未部署；獨立測試 Compose 用畢清理。
