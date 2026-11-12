---
name: tasktracker-development-standards
description: Apply TaskTracker project-owned engineering rules when implementing, reviewing, refactoring, or testing backend, frontend, APIs, repositories, data loading, polling, DI, and PHPDoc; also use when maintaining these rules. Complements local Laravel, Vue, UI/UX, and performance skills without replacing them.
---

# TaskTracker 開發規範

本skill集中本專案自訂的長期工程規則，來源為根／後端AGENTS與[DI／PHPDoc重構規格](../../docs/backend-di-phpdoc-refactor-plan.md)。只在本專案維護，不安裝全域。規範生效不代表既有程式已合規，也不會自動啟動原規格中的全庫重構。

## 開工與適用範圍

1. 先讀根[AGENTS](../../AGENTS.md)、受影響子目錄指引與當次需求，確認授權、既有差異及對應追蹤卡。專案決策／環境現況仍由AGENTS與相關設計文件記錄，不複製到skill當永遠有效的現況。
2. 所有實作／review讀[共通規則](references/engineering.md)與[驗證交付](references/verification.md)。修改API、Repository、查詢、前端載入或輪詢，**必須**另讀[資料存取審查](references/data-access.md)。
3. 後端新增／重構依賴或註解，另讀[DI與生命週期](references/dependency-injection.md)及[PHPDoc](references/phpdoc.md)。Controller穩定服務依賴與Service的Repository依賴必須採建構子注入；業務資料一律經Repository，包含簡單CRUD，完整嚴格分層由Laravel Architecture維護。Request／路由／當次Context與框架handle例外依DI reference區分，不再套用寬泛HTTP方法注入例外。依當次範圍執行，不把單一功能修改擴張成全庫整頓。
4. 依下表讀專責skill，不為符合本skill重寫vendor或引入新框架。文件／skill整理只驗證規則與引用，不跑業務測試或修改服務。

## 與既有skills的分工

| 入口 | 分工 |
| --- | --- |
| 本skill | TaskTracker共通工程／安全限制、DI生命週期、PHPDoc、資料存取與交付門檻 |
| [ECC laravel-patterns](../vendor/laravel-patterns/SKILL.md) | Laravel框架慣例與實作模式；Laravel慣例仍以ECC為基礎 |
| [Laravel Architecture](../laravel-architecture/SKILL.md) | 專案分層、依賴方向、命名、Repository回傳、Request／DTO／Resource與測試層次，不重複發明另一套架構 |
| [Vue Best Practices](../vendor/vue-best-practices/SKILL.md) | Composition API、SFC、reactivity、資料流與composables；保留既有JavaScript及Vue／Vuetify決策 |
| [Addy performance-optimization](../vendor/addyosmani-agent-skills/skills/performance-optimization/SKILL.md)＋[ECC benchmark](../vendor/ecc-benchmark/SKILL.md) | 依[本地配置](../../docs/performance-skills.md)量測、定位、單一假設實驗及重測；本skill的資料存取審查是必要門檻，不能被技能分數取代 |
| UI／UX skills | 視覺、可用性與實際畫面流程依根AGENTS指定的兩份skills；不能改寫產品決策或以樣式建議取代權限 |

使用者當次指示與專案明確決策優先；本skill具體化專案約束，不取代ECC的框架慣例。若發現矛盾，先按任務範圍指出並統一文件，不擅自用範例覆蓋安全規則。不得把本skill與Laravel Architecture的互相連結當成重複讀取的循環：各讀一次，只按需載入reference。

## 完成前

以[驗證交付](references/verification.md)檢查範圍、依賴／註解、資料讀寫、授權與真實使用流程；列出未完成項目和證據限制。原全庫DI／PHPDoc任務若被明確要求實作，才按原規格建立全檔清單、分階段驗收；核心鏈完成不等於全庫完成。

來源與移轉對照見[規則來源](references/sources.md)。新訂或變更規範集中更新本skill及專責skill；AGENTS保留入口，避免維護兩份長篇正文。
