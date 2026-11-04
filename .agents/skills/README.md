# TaskTracker 專案 skills

2026-11-03 依使用者要求建立專案入口；原始內容集中在 `skills/vendor`, 這裡使用相對 symlink, 不另存第二份。

| Skill 名稱 | 本目錄入口 | 固定來源 |
| --- | --- | --- |
| ui-ux-pro-max | [SKILL.md](ui-ux-pro-max/SKILL.md) | [來源與校驗](../../skills/sources.json), commit 713eef51 |
| design-taste-frontend（Taste Skill） | [SKILL.md](design-taste-frontend/SKILL.md) | 同上, commit 12af471f |

UI／UX 審查或設計工作才讀取相關 skill。Pro Max 優先查可用性、鍵盤、表單與 Vue；Taste 對本專案採 app-screen 流程, 不套 landing page 的 hero／圖片／動畫要求。使用者要求與 AGENTS.md 優先, 角色權限及已確認產品流程不能被風格建議改寫。

審查要求以真實登入畫面／操作和原始碼證據為準, 分開客觀問題、啟發式警告與審美偏好；不因紫色、深色側欄或工具 FAIL 自動換風格。不為審查新增功能或直接修 UI, 除非使用者另行要求。沿用 Vue／Vuetify, 不照搬上游框架、插件路徑或全域安裝指令。

本輪已明確讀取並使用兩份 skill。此目錄提供後續 agent 的專案 skill 入口, 不代表既有對話的工具清單會立即重新載入；需要時可直接指定上述 SKILL.md。完整背景見 [skills/README.md](../../skills/README.md)。


## 後端開發規範（2026-11-04）

[backend-development-standards](backend-development-standards/SKILL.md) 以相對symlink連到 [專案副本](../../skills/backend-development-standards/SKILL.md)，來源為workspace共用skills，包含六份references。用於Laravel／PHP開發、review與後端測試設計，預設允許正常自動選用；也可明確呼叫`$backend-development-standards`。後續對話是否已載入可用清單，以該次實際清單為準。

TaskTracker副本適配permission＋scope、DomainError、SQLite測試隔離及現有產品契約；來源校驗與修改範圍見 [backend-standards-sources.json](../../skills/backend-standards-sources.json)。舊相容別名backend-coding-style／clm-backend-structure不另外安裝。只接入規範，不代表已重構或現有程式均符合規範；不改全域skills或共用來源。
