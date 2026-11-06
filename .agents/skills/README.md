# TaskTracker 專案 skills

2026-11-03 依使用者要求建立專案入口；原始內容集中在 `skills/vendor`, 這裡使用相對 symlink, 不另存第二份。

| Skill 名稱 | 本目錄入口 | 固定來源 |
| --- | --- | --- |
| ui-ux-pro-max | [SKILL.md](ui-ux-pro-max/SKILL.md) | [來源與校驗](../../skills/sources.json), commit 713eef51 |
| design-taste-frontend（Taste Skill） | [SKILL.md](design-taste-frontend/SKILL.md) | 同上, commit 12af471f |

UI／UX 審查或設計工作才讀取相關 skill。Pro Max 優先查可用性、鍵盤、表單與 Vue；Taste 對本專案採 app-screen 流程, 不套 landing page 的 hero／圖片／動畫要求。使用者要求與 AGENTS.md 優先, 角色權限及已確認產品流程不能被風格建議改寫。

審查要求以真實登入畫面／操作和原始碼證據為準, 分開客觀問題、啟發式警告與審美偏好；不因紫色、深色側欄或工具 FAIL 自動換風格。不為審查新增功能或直接修 UI, 除非使用者另行要求。沿用 Vue／Vuetify, 不照搬上游框架、插件路徑或全域安裝指令。

本輪已明確讀取並使用兩份 skill。此目錄提供後續 agent 的專案 skill 入口, 不代表既有對話的工具清單會立即重新載入；需要時可直接指定上述 SKILL.md。完整背景見 [skills/README.md](../../skills/README.md)。


## 開發skills（2026-11-06）

| Skill | 入口 | 責任 |
| --- | --- | --- |
| laravel-patterns | [SKILL.md](laravel-patterns/SKILL.md) | ECC Laravel基礎，架構慣例衝突時優先 |
| laravel-architecture | [SKILL.md](laravel-architecture/SKILL.md) | 專案分層、命名、回傳、交易及測試明確約束 |
| vue-best-practices | [SKILL.md](vue-best-practices/SKILL.md) | Vue 3 Composition API、SFC、reactivity、資料流與composables |

均為相對symlink，指向skills/vendor原文或skills/laravel-architecture本地規範，不重複套用。舊backend-development-standards已移除，不再啟用。配置與來源見[開發skills配置](../../docs/development-skills.md)及[校驗](../../skills/development-sources.json)。本輪重構暫緩，沒有變更產品程式；不能把已安裝說成現有程式已全面合規。
