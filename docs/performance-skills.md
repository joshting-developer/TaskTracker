> 2026-11-12更新：Skill正文已集中Side Project共用庫，舊專案路徑為相容連結；本文原存放位置視為歷史。載入與優先順序依根AGENTS「Skill來源與適用範圍」，產品設定見project-engineering-bindings.md。Ponytail預設lite，不裝全域hook／plugin。

# TaskTracker 效能分析skills配置

日期：2026-11-06（Asia/Taipei）。使用者已選定「Addy主診斷＋ECC補量測」。這輪完成專案內安裝及規範配置，沒有啟動業務重構或壓測。

2026-11-09更新：使用者已選主庫MySQL 8.4 LTS；歷史SQLite量測保留為原引擎證據，不能當作MySQL基準。新增效能量測須記引擎／版本與隔離DB；鎖與並行依InnoDB實測，禁止用memory SQLite推論。遷移見[MySQL紀錄](mysql-migration-2026-11-09.md)。

## 入口與分工

| Skill | 專案入口 | 原文與固定版本 |
| --- | --- | --- |
| Addy performance-optimization | [.agents/skills/performance-optimization](../.agents/skills/performance-optimization/SKILL.md) | [本地原文](../skills/vendor/addyosmani-agent-skills/skills/performance-optimization/SKILL.md)，上游commit `301b02b12bff98e4688e6984af6556802322bbde` |
| ECC benchmark | [.agents/skills/benchmark](../.agents/skills/benchmark/SKILL.md) | [本地原文](../skills/vendor/ecc-benchmark/SKILL.md)，上游commit `b38be6a7f6350964d59328b52f0624e1d1d552d1` |

效能任務先讀本文件，再用Addy決定量測面向、瓶頸與實驗；需要建立／比較頁面、API或build基準時搭配ECC對應模式。只查單一SQL時不機械跑全站Lighthouse、build與所有benchmark。一般功能開發不因安裝本組合就自動變成效能專案。

架構與實作仍沿用[Laravel／Vue配置](development-skills.md)。使用者要求、產品契約及AGENTS優先；Addy決定診斷流程，ECC補量測格式，不以兩份skill的不同範例改動框架、API或授權方式。

## 專案適配

| 項目 | 本專案採用方式 |
| --- | --- |
| Laravel／N+1 | 先用DB query log、重複SQL形狀與資料量成長對照；保留Controller／Service／Repository責任，不直接抄JS／Prisma示例 |
| SQLite | 用適用的 `EXPLAIN QUERY PLAN`及實際計時評估索引；鎖競態需獨立磁碟SQLite／兩連線，不以單連線`:memory:`推論多人容量。不抄PostgreSQL的Seq Scan／EXPLAIN ANALYZE或connection pool配置 |
| Vue／Vuetify | runtime／DOM／bundle依實際瀏覽器證據；實作沿用Composition API與vue-best-practices，不引入React.memo／useMemo／React元件 |
| API | 保留Session／CSRF、X-Workspace-ID、permission＋scope、revision／requestId。效能改善不能靠略過授權、變更回應語意或少做驗證 |
| Redis | 先量昂貴讀取，再設scope完整的key、資料版本、commit後失效與故障行為。新鮮授權、每日24h上限及防重複提交不能只信有TTL的快取 |
| 瀏覽器 | 使用既有隔離Playwright登入路徑與可用量測工具；缺少MCP／trace／RUM時列未量到的項目，不假裝用HTTP或截圖測到了互動INP |
| 效能目標 | 上游範例的KB、ms、Lighthouse分數、100次／10並行不是使用者已確認的SLO或自動執行參數。依本次問題明確訂工作負載與預算 |
| 基準／產物 | 可沿ECC `.ecc/benchmarks/` 保存經去識別的統計基準；原始HAR、payload、cookie、token、帳密、DB／截圖不進Git。真正產生檔案時再按內容決定追蹤與忽略，不先產生空目錄或改ignore |

## 量測與驗證順序

1. 定義用例、操作者有效權限、資料量、期間、環境與主要指標；區分純Repository、領域服務、HTTP、瀏覽器及併發測試。
2. 建baseline，記錄commit、命令、程序／worker配置、冷暖cache及樣本數；SQL次數、回應大小與時間分開報告。
3. 按證據挑一個瓶頸、一次驗一個假設；比較相同輸出與相同功能。測試失敗不能靠刪除斷言換取效能提升。
4. 同條件重跑，記錄中位數／差異範圍及失敗實驗；若差異落在雜訊內，不宣稱加速。只撤回本輪可歸因的無效效能實驗，不動使用者既有變更或改寫已提交歷史。
5. 驗證tenant隔離、撤權／作者限制、revision／receipt與相關業務測試；再依主要指標設有意義的回歸門檻，不因本輪安裝建立新測試套件。

現有[隔離取樣工具](../backend/tools/profile-reads.php)只測領域服務與記憶體SQLite，能作SQL成長基準；其單次時間不是HTTP p95或正式容量。HTTP併發要區分相同Session的blocking與多個帳號／Session的負載。受保護頁面必須等實際資料及互動就緒，不能只測登入頁或SPA殼。

效能操作使用人造seed及獨立測試服務，不對使用者資料庫執行破壞性測試或混入壓測資料；沿用AGENTS的隔離／外部sentinel及Docker標籤要求。沒有真人RUM時，只回報lab／synthetic結果，不能宣稱正式使用體驗已達標。

## 安裝範圍、來源與更新

- 兩份上游SKILL.md保持原文，連同MIT LICENSE及必要references共6檔。來源URL、commit、Git blob SHA-1與SHA-256見[performance-sources.json](../skills/performance-sources.json)。
- Addy存於 `skills/vendor/addyosmani-agent-skills/`，保留 `skills/performance-optimization/` 與根 `references/` 的相對階層；主skill與pattern均能讀到[共用checklist](../skills/vendor/addyosmani-agent-skills/references/performance-checklist.md)。沒有複製或啟用同倉庫其他skills。
- ECC只收 `skills/benchmark/SKILL.md`及根LICENSE，保存在 `skills/vendor/ecc-benchmark/`。不隨範例安裝canary-watch、browser-qa、CI、MCP、hooks或全域工具。
- `.agents/skills`使用相對symlink，名稱保持原文 `performance-optimization`與`benchmark`。既有對話未必即時更新可選技能清單，仍可按本文件直接讀取；後續對話從專案入口發現。
- 不安裝gstack，不建立第三個融合skill；本文件負責兩份上游與專案契約的配置。
- 更新時先看上游差異與LICENSE，再下載明確commit、校驗引用完整性及全部檔案雜湊。不得自動追main或順便更新現有Laravel／Vue skill。

選型沿革見[研究報告](performance-skills-survey-2026-11-06.md)；上輪瓶頸證據見[效能review](performance-review-2026-11-06.md)。選定與安裝不代表已修復報告中的問題。

## 本輪驗證

驗證6檔固定來源與hash、兩入口symlink／frontmatter、Addy跨目錄references、相關JSON及文件連結；純skills與文件不跑業務測試、不改runtime／資料庫、不重啟Docker。既有Laravel／Vue／UI skills均保留。
