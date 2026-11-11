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

## 效能分析skills（2026-11-06）

使用者已選定「Addy主診斷＋ECC補量測」，正式專案入口：

| Skill | 入口 | 責任 |
| --- | --- | --- |
| Addy performance-optimization | [SKILL.md](performance-optimization/SKILL.md) | 瓶頸診斷、單一假設實驗、正確性與重測 |
| ECC benchmark | [SKILL.md](benchmark/SKILL.md) | 頁面／API／build基準與回歸比較 |

效能工作先讀[本地配置](../../docs/performance-skills.md)，再讀Addy主流程，按需使用ECC量測模式；Laravel／Vue實作仍依原有skills。僅專案內相對symlink，原文與必要references／MIT LICENSE固定版本保存，來源及逐檔雜湊見[performance-sources.json](../../skills/performance-sources.json)。Addy保留上游目錄階層以支援skill目錄外的共用reference，不另啟用同庫其他skills。沒有安裝gstack、ECC其他工具或全域hooks。

安裝／可讀取不代表本輪已執行效能分析、壓測或修復問題；新入口未必立即加入既有對話的技能選單，仍可直接讀SKILL.md。後續對話依專案AGENTS及本目錄發現入口。


## Hallmark／Unslop（2026-11-11）

| Skill | 入口 | 用途 |
| --- | --- | --- |
| Hallmark | [SKILL.md](hallmark/SKILL.md) | 已安裝，實際使用時依明確範圍選新頁面／audit／redesign流程 |
| Unslop | [SKILL.md](unslop/SKILL.md) | 本輪主要去AI化掃描，依signs／真實畫面與五行方向提出修改 |

僅本專案入口，原文及LICENSE／署名保留於skills/vendor；來源見[manifest](../../skills/anti-slop-sources.json)。先讀[配置](../../docs/anti-slop-skills.md)，使用者產品／品牌／Vue決策優先；未授權直接實作時，Unslop要求先交審查方向再確認。安裝不代表已改UI或全面通過審查。
