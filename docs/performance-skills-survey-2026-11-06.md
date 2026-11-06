# GitHub 效能分析 skills 選型

調研日期：2026-11-06（Asia/Taipei）。本輪只研究，未安裝、啟用或執行候選skill。

## 建議

**TaskTracker 優先選 Addy Osmani 的 `performance-optimization` 作診斷主流程，ECC `benchmark` 作基準與回歸比較的補充。gstack `benchmark` 暫不優先。**

這是依內容與本專案瓶頸作的選型判斷，還沒有實際套用三者跑專案，不視為效果實測。星數排名與適用性排名不同。

## 排名口徑與查證

GitHub星數屬於整個repository，不屬於其中一個skill；不能把大型技能庫的星數當成這份skill的使用人數或成效證據。

本次先以GitHub／公開搜尋找候選，再用GitHub REST API核對 `stargazers_count`、預設分支與完整tree，確認有真正的 `SKILL.md`，並讀原文與必要references。每個來源庫選一個代表，按來源庫星數排序。納入的是Web／API／資料庫的效能量測、診斷或回歸檢查；排除純AI token成本、競品品牌benchmark、原生Apple App與科學GPU計算。

以下為**本次查證符合範圍的候選來源前三名**，不是GitHub全站已窮舉的絕對前三。數字是查詢當下快照，之後會變動。來源版本、API URL與內容SHA-256見[研究來源紀錄](performance-skills-sources-2026-11-06.json)。

| 星數排序 | 來源／代表skill | 倉庫星數 | 主要用途 | TaskTracker適合程度 |
| --- | --- | ---: | --- | --- |
| 1 | [ECC／benchmark](https://github.com/affaan-m/ECC/blob/b38be6a7f6350964d59328b52f0624e1d1d552d1/skills/benchmark/SKILL.md) | 298,239 | 頁面、API及開發流程的基準／回歸比較 | 適合補量測流程，診斷細節較少 |
| 2 | [gstack／benchmark](https://github.com/garrytan/gstack/blob/673a336bc447061c445a53ff44f50486556053a0/benchmark/SKILL.md) | 140,312 | 實際瀏覽器的頁面載入與資源回歸 | 適合前端載入追蹤，現在整合成本較高 |
| 3 | [Addy Osmani／performance-optimization](https://github.com/addyosmani/agent-skills/blob/301b02b12bff98e4688e6984af6556802322bbde/skills/performance-optimization/SKILL.md) | 113,630 | 前後端瓶頸診斷、量測與驗證 | **最符合目前需求** |

精確星數依各來源[GitHub API：ECC](https://api.github.com/repos/affaan-m/ECC)、[gstack](https://api.github.com/repos/garrytan/gstack)、[Addy](https://api.github.com/repos/addyosmani/agent-skills)取得，沒有用第三方排行榜或網頁四捨五入數字。

## 三個候選的內容與限制

### 1. ECC `benchmark`

涵蓋瀏覽器載入指標、API延遲分位數、回應大小、build／test時間及前後對照，預設把JSON基準保存在`.ecc/benchmarks/`。文件是工作流程，並不是已附一套適配Laravel登入與SQLite的壓測工具。[原文](https://github.com/affaan-m/ECC/blob/b38be6a7f6350964d59328b52f0624e1d1d552d1/skills/benchmark/SKILL.md)

對我們有用：將既有`profile-reads.php`的SQL數／資料量與後續HTTP測試結果放入一致比較；補重複量測、回歸門檻與條件紀錄。既有ECC Laravel規範也容易共存。

需要補足：它的API範例是100次請求與10並行，不能直接當本專案正式驗收值。相同Session會受route blocking序列化，應分開測同Session行為與多帳號負載；固定seed、權限、冷暖cache及程序配置。100個樣本的p99也不代表穩定容量。私有登入頁不應只量到登入畫面就宣稱看板完成載入。

判斷：**適合做量測規範，不能單靠它找到每工時14條SQL的原因。**

### 2. gstack `benchmark`

實際讀瀏覽器navigation／paint／resource資料，具基準保存、資源明細、趨勢與重複取樣比較。它的具體蒐集流程偏頁面載入；不能因README提到Web Vitals，就假定已量到看板拖曳的INP或所有SPA互動。[固定版本原文](https://github.com/garrytan/gstack/blob/673a336bc447061c445a53ff44f50486556053a0/benchmark/SKILL.md)

對我們有用：追蹤前端載入、JS／CSS大小、請求數及資源瀑布；不限定React，Vue頁也可用。

限制：依賴gstack啟動工具、Aside或其headless browser fallback，並有自己的狀態／報告目錄與初始化流程。只複製`benchmark/SKILL.md`不足；倉庫有Codex適配，但要選正確host入口及相依，不直接照Claude路徑執行。它也沒有Laravel SQL次數或SQLite鎖的專用診斷。[來源倉庫](https://github.com/garrytan/gstack)

判斷：**若未來要採整套gstack工具鏈可考慮，現在為了效能review引進它不划算。**我們已有隔離Playwright登入流程，應先沿用。

### 3. Addy Osmani `performance-optimization`

內容直接處理N+1、無上限讀取、索引計畫、快取鍵與失效，並要求同條件重測、區分雜訊、保留或撤回實驗、記錄失敗方案。[主skill](https://github.com/addyosmani/agent-skills/blob/301b02b12bff98e4688e6984af6556802322bbde/skills/performance-optimization/SKILL.md)

對我們最有用：能把上一輪「授權重查、全量bootstrap、PHP聚合、Redis何時值得加」串成可驗證的優化工作；其[optimization-patterns](https://github.com/addyosmani/agent-skills/blob/301b02b12bff98e4688e6984af6556802322bbde/skills/performance-optimization/references/optimization-patterns.md)與[performance-checklist](https://github.com/addyosmani/agent-skills/blob/301b02b12bff98e4688e6984af6556802322bbde/references/performance-checklist.md)有快取依賴、請求合併及前端量測補充。

限制：示例偏JavaScript／React與其他SQL引擎，並非Laravel專用。應以Laravel Repository／交易規範實作，Vue細節沿用既有Vue skill；SQLite查詢計畫不能照抄PostgreSQL語法。範例budget不是使用者已確認的SLO，正式真人資料缺席時只能說完成lab驗證。

判斷：**最適合作為主skill，但必須保留本專案租戶隔離、即時撤權、每日工時上限及防重複提交契約。**之後若安裝，主檔會引用skill子目錄外的`references/performance-checklist.md`，必須一起保存並維持／調整相對路徑，不能只裝單一SKILL.md。

## 其他查證候選

| 來源 | 星數快照 | 未列前三的理由 |
| --- | ---: | --- |
| [obra/superpowers](https://github.com/obra/superpowers) | 313,339 | 完整tree有systematic-debugging等通用流程，沒有獨立效能分析／benchmark skill；不能用通用debugging冒充本題專用skill |
| [anthropics/skills](https://github.com/anthropics/skills) | 187,998 | 查到的20份SKILL.md沒有獨立效能分析skill；webapp-testing是功能測試用途 |
| [K-Dense-AI/scientific-agent-skills](https://github.com/K-Dense-AI/scientific-agent-skills) | 56,284 | GPU科學運算方向不符Laravel／Vue；生物資料profiling不是Web效能分析 |
| [sickn33/agentic-awesome-skills](https://github.com/sickn33/agentic-awesome-skills) | 49,257 | 有performance-engineer與performance-profiling，星數低於前三；前者廣而偏能力清單，後者偏瀏覽器量測 |
| [wshobson/agents](https://github.com/wshobson/agents) | 42,255 | 有SQL optimization與Python效能專用skill，星數較低；Python工具不適用我們 |
| [github/awesome-copilot](https://github.com/github/awesome-copilot) | 40,870 | 星數低於前三，未進入完整內容決選 |
| [VoltAgent/awesome-agent-skills](https://github.com/VoltAgent/awesome-agent-skills) | 36,916 | 索引／整理來源，星數不能轉給每個被列出的上游skill |
| [vercel-labs/agent-skills](https://github.com/vercel-labs/agent-skills) | 33,625 | React／Vercel專用方向，星數也較低 |
| [davila7/claude-code-templates](https://github.com/davila7/claude-code-templates) | 33,158 | 有performance相關副本，來源星數較低，不把重包裝當額外上游聲望 |
| [alirezarezvani/claude-skills](https://github.com/alirezarezvani/claude-skills) | 29,393 | 有performance-profiler，星數較低；後續需要專用工具時可再比 |
| [cloudflare/skills](https://github.com/cloudflare/skills) | 3,291 | web-perf是有價值的瀏覽器專項候補，非本次星數前三 |

ECC舊名`everything-claude-code`目前轉向`ECC`；sickn33舊名`antigravity-awesome-skills`轉向`agentic-awesome-skills`，同庫只計一次。ECC的`benchmark-methodology`實際是競品定位評分，本次已讀並排除；沒有把所有benchmark名字都當效能工具。

## 對 TaskTracker 的採用方式（提案，尚未啟用）

| 工作 | 建議組合 | 我們的驗證方式 |
| --- | --- | --- |
| 查N+1與授權重查 | Addy＋既有Laravel兩份skill | SQL成長對照＋ACL／跨租戶／撤權測試 |
| 拆全量資料與工時聚合 | 同上，ECC量測格式補充 | 固定資料量、回應大小、SQL／記憶體與相同輸出 |
| Redis快取評估 | Addy快取checklist＋既有交易規範 | 新鮮授權、scope key、commit／rollback、失效與故障回源 |
| 看板拖曳／大量卡片 | Addy＋既有vue-best-practices | 已登入看板操作trace、DOM數與主執行緒耗時 |
| 避免後續效能退化 | ECC benchmark | 同條件多次比較；功能正確先過，再看效能差異 |

優先順序仍沿用[上一輪效能review](performance-review-2026-11-06.md)：批次化唯讀授權→拆全量讀取／調整交易→報表SQL聚合與分頁→前端索引→有證據才加快取。skill是協助量測與推理的指引，不會自動代替profiler或修好系統。

只挑一個：**Addy**。希望搭配：**Addy主診斷＋ECC基準比較**，避免同時全套載入造成重複規則。ECC與gstack都叫`benchmark`，若日後同時裝要以不同專案入口避免撞名。

## 本輪驗證與交付

已核對三個來源的即時API星數、固定commit及原始SKILL.md，閱讀Addy兩份references、gstack模板與授權；三個來源皆為MIT。只保存來源metadata與分析文件，沒有執行上游安裝、壓測、瀏覽器接管或變更現有skill入口，沒有改動業務程式。文件工作不跑業務測試；檢查來源欄位、相對連結、JSON及diff空白。
