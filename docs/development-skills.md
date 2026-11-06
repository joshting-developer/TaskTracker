# Laravel／Vue開發skills配置

日期：2026-11-06（Asia/Taipei）。使用者已暫緩重構，本輪只配置skills與文件，未修改產品程式、依賴、測試、資料庫或Docker。

## 安裝與來源

| Skill | 專案入口 | 原始內容 | 固定commit |
| --- | --- | --- | --- |
| ECC laravel-patterns | [.agents/skills/laravel-patterns](../.agents/skills/laravel-patterns/SKILL.md) | [官方GitHub](https://github.com/affaan-m/everything-claude-code/tree/b38be6a7f6350964d59328b52f0624e1d1d552d1/skills/laravel-patterns)，skills/vendor/laravel-patterns | b38be6a7f6350964d59328b52f0624e1d1d552d1 |
| Vue Best Practices 19.1.0 | [.agents/skills/vue-best-practices](../.agents/skills/vue-best-practices/SKILL.md) | [官方GitHub](https://github.com/vuejs-ai/skills/tree/198f0cd8f7f9c0a3d9b566ccc16de6677519ee78/skills/vue-best-practices)，skills/vendor/vue-best-practices | 198f0cd8f7f9c0a3d9b566ccc16de6677519ee78 |
| Laravel Architecture | [.agents/skills/laravel-architecture](../.agents/skills/laravel-architecture/SKILL.md) | skills/laravel-architecture，本地編寫 | 隨本專案commit |

上游固定子目錄逐檔下載，包含原文、必要references與根LICENSE（MIT）；ECC共2檔，Vue共24檔。原文未改寫，Git blob SHA-1與SHA-256見[來源校驗](../skills/development-sources.json)。專案入口以相對symlink指向同一份內容，不安裝到全域、不建立巢狀Git、不執行上游腳本或hooks，不順便安裝整套ECC其他skills。

## 優先序與配置

1. 使用者當次要求、已確認產品契約及授權／資料安全限制優先。
2. Laravel架構與慣例以ECC為基礎；本地Laravel Architecture補足ECC未明訂的邊界。與舊後端規範衝突時採ECC，不讓兩套skill同時生效。
3. Vue結構與reactivity依vue-best-practices；畫面可用性／設計仍搭配既有ui-ux-pro-max與design-taste-frontend。

| 項目 | ECC基礎／本地配置 | 舊規範處理 |
| --- | --- | --- |
| 層次 | 薄Controller、Service協調／Action單一用例、必要Repository、Model、Resource | 不強迫所有CRUD都加Action與Repository，也不准Controller查DB |
| 命名 | Laravel layout；新資源Controllers採複數，Requests採Store／Update，Action以handle為公開入口；DB複數snake_case、anonymous timestamp migration | 舊class與RPC不批次改名；不用CLM特例 |
| Repository | 只資料存取；Model／typed Collection／Paginator，聚合用typed Data DTO | 不以Repository執行商業決策、權限放行或組Response |
| API | FormRequest驗輸入、typed Data DTO承載結構化用例；成功回應經Resource或既有ApiResponse | 保留RPC／ok-data／camelCase與DomainError；不抄ECC示例換envelope或Sanctum |
| 交易 | 頂層Service或Action唯一owner，afterCommit副作用 | 現有ApiExecution／RequestReplay是相容邊界，不巢狀重包或移除重試授權 |
| 測試 | PHPUnit、Unit驗純規則、Feature驗HTTP／實際DB／隔離／交易；依變更風險補齊 | 移除舊action_Result_Test_case硬性命名，沿用test_snake_case，不機械每class配兩檔 |
| 保留補充 | 中文有效docblock、清楚條件、SQLite測試隔離、tenant／author條件、程序生命週期 | 僅移轉仍適用約束；不帶CLM macro、Kibana、PDF或安裝包要求 |

完整規範見[層次與命名](../skills/laravel-architecture/references/layers-and-naming.md)及[契約、交易與測試](../skills/laravel-architecture/references/contracts-and-testing.md)。

Vue上游建議Composition API＋script setup＋TypeScript；本專案已採前兩者，現有JavaScript暫保留，TypeScript優先用於後續新功能／明確授權的遷移，不因skill安裝全庫轉檔。Router／Pinia／Options API／JSX相關skill只有實際需求才另行選取；本輪不安裝。真正開始Vue實作前，依上游讀reactivity、sfc、component-data-flow與composables四份核心references；本輪沒有Vue程式實作。

## 舊skill退役與歷史

已移除`.agents/skills/backend-development-standards`入口與`skills/backend-development-standards/`完整副本；本地Architecture承接仍有效的架構、API、安全與測試約束。原來源校驗移到[skills/history/backend-standards-sources.json](../skills/history/backend-standards-sources.json)，其中local_path／source_path僅是2026-11-04歷史，不作載入或驗證現存檔案指令。舊內容仍在Git歷史，不修改workspace共用來源／全域skill。產品討論中的舊名稱保留為沿革，不代表繼續啟用。

root／backend AGENTS、backend CLAUDE／README及skills索引已改指向新入口。現有程式仍有RecordStore array DTO、共用JsonOperationRequest、直接new服務等相容邊界；安裝skill不代表已消除它們。後續重構範圍、遷移步驟與驗證等使用者再次授權後處理，本輪不另外啟動。

## 驗證與更新

本輪檢查固定來源Git blob與本地SHA-256、全部skill symlink、Vue四份核心references、local skill frontmatter／metadata及相對文件連結；確認沒有產品程式或依賴變更。純規範工作不跑業務測試，不重啟服務。

未來更新先比對官方commit與授權、查看差異，再更新vendor及來源清單；不覆蓋本地Architecture，不自動追main。上游新規則若與產品契約衝突，先記錄並處理相容方案，不直接變更業務行為。

## 後續重構授權（2026-11-06）

使用者已恢復重構，前述「本輪只安裝／尚未授權」為安裝階段沿革。實作範圍、責任、相容邊界及驗證見[前後端架構重構紀錄](refactoring-2026-11-06.md)。上游vendor skills保持原樣，本地Architecture僅同步授權階段說明；不因此宣稱全庫已合規。

## 效能工作搭配（2026-11-06）

使用者另已選定Addy performance-optimization為主診斷、ECC benchmark補量測；專案入口與Laravel／Vue／SQLite適用邊界見[效能skills配置](performance-skills.md)。不取代本文件的分層／契約及Vue規範，上游副本分別固定版本；效能skill安裝本身不啟動新一輪重構。
