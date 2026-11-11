# 規則來源與維護邊界

整理日期：2026-11-12（Asia/Taipei）。本地編寫，隨TaskTracker提交，不是新下載的上游skill或原始碼合規報告。

| 原來源 | 收錄位置／仍保留的職責 |
| --- | --- |
| 根AGENTS「開發規則」 | [共通工程](engineering.md)：JS風格、資料傳輸、安全、非同步；GAS限制標明歷史／另行授權才適用 |
| 根AGENTS「介面文案」 | engineering.md：簡潔事實文案、業務描述保留、手冊另行指示 |
| 根AGENTS「API資料存取架構檢查」八項 | [data-access.md](data-access.md)，完整保留八項及真實MySQL／拓樸限制，AGENTS保留直接引用 |
| 根AGENTS「驗證與交付」 | [verification.md](verification.md)：文件／風險測試／隔離／真實流程／交付分開 |
| 根AGENTS其他章節 | 產品決策、部署／資料現況、Git授權、追蹤卡、skills來源政策仍留AGENTS；本skill不複製帳號／秘密、地址、版本與歷史現況 |
| [DI／PHPDoc重構規格](../../../docs/backend-di-phpdoc-refactor-plan.md) §1～3 | [dependency-injection.md](dependency-injection.md)：穩定依賴、Context／Factory／生命週期、合理new例外、循環與按需初始化 |
| 同文件 §4 | [phpdoc.md](phpdoc.md)：繁中標準docblock、param／return／throws／泛型／shape與實際簽章一致 |
| 同文件 §5～7 | verification.md與DI／PHPDoc reference：全面掃描、實際隔離驗收與交付數據；原文件保留一次性施工階段、當時缺口和待實作狀態 |
| 同文件 §6對API相容的要求 | engineering.md：DI任務不得暗改API；獨立且已授權的效能/API分責可以改版。兩種任務邊界明確，不能解讀成永久禁止退役getWorkspace |
| [Laravel Architecture](../../laravel-architecture/SKILL.md)及references | 原有分層／命名／Repository回傳／Resource／交易與測試層次維持單一來源，只將DI／PHPDoc細節連到本skill |

新增工程規則集中修改本skill；Laravel架構專責修改laravel-architecture，上游vendor保留原文。變更規則時同步入口與受影響文件，保留來源／決策沿革，避免複製出兩份不同正文。
