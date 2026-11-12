# Side Project Skill共用化與記憶分工

2026-11-12／TT-268，接續TT-267只讀盤點。使用者已確認共用路徑與AGENTS／Memory分工，取代原project-only來源規則。

## 交付範圍

共用repository：`/Users/josh/workspace/side-project/skills`。12份原TaskTracker Skill完整搬移，加上既有task-tracker-usage與固定版Ponytail，共14份登記。分類為01-common、02-backend、03-frontend、04-design、05-performance；2份可用性研究Skill仍手動選讀，12份提供發現入口。

- 01-common：side-project-development-standards、task-tracker-usage、Ponytail。
- 02-backend：ECC Laravel Patterns、Laravel Architecture。
- 03-frontend：Vue Best Practices。
- 04-design：UI UX Pro Max、Design Taste、Hallmark、Unslop、Cognitive Walkthrough、Usability Testing。
- 05-performance：Addy Performance Optimization、ECC Benchmark。

自有工程Skill由tasktracker-development-standards泛化更名為side-project-development-standards；Laravel Architecture移除TaskTracker固定環境及產品條件。嚴格分層、DI、PHPDoc、資料存取八項與安全門檻保留。具體Session／PAT契約、資料庫、時區、角色／狀態及每日上限由consumer自行定義，TaskTracker保留於[工程綁定](project-engineering-bindings.md)。

TaskTracker `.agents/skills`與原`skills` vendor路徑皆為相容連結，沒有第二份正文。共用庫缺失須明確失敗，不能默默讀全域同名版本。換機先取得兩個repository，再以共用庫tools/link_consumer.py明確選入口；預覽不寫入，--apply不覆蓋不同來源。Skill Git版本需與consumer紀錄相符；單獨clone TaskTracker不會帶入共用庫。

## Ponytail與來源

只採[官方Ponytail](https://github.com/DietrichGebert/ponytail/tree/96608e078b2d5ea607c5c3b8b5a54d1c1fba6846)的Skill模式，固定commit `96608e078b2d5ea607c5c3b8b5a54d1c1fba6846`；保留MIT LICENSE、README、INSTALL來源文件，不執行安裝器／全域plugin／hooks。共用baseline配置lite，必要PHPDoc、分層、安全、可及性與驗證優先。

原來源manifests保留；共用sources的tasktracker-*為移轉前來源快照，canonical-files.json驗現有完整公開檔案。搬移映射另記原路徑／canonical路徑／hash。上游358檔hash與搬移前完全一致，兩組重複LICENSE依法保留。Addy與Ponytail的Skill外父層references／LICENSE亦保留，不單搬SKILL.md。

## AGENTS與Memory

根AGENTS收斂為47行，只放規範、Skill入口、授權與操作流程；backend亦收斂。產品決策、環境現況、已完成卡片沿革完整遷[Memory.md](../Memory.md)，加現行摘要及歷史過期提示；按需讀索引，不每次載入全部歷史。每卡進度留卡片／plans，細節留專責docs。共用baseline與工程reference要求後續consumer採相同分工。

CLAUDE／frontend及backend入口只指向現行AGENTS與共用庫，不複製整份規則。原TaskTracker自有Skill舊名稱僅相容路徑，不是另一個啟用入口。

## 驗證與限制

實際通過：

- 共用tools/validate_library.py：14 Skill frontmatter、379檔canonical hash、358原vendor搬移hash；owned references及12個consumer啟用入口均無錯。
- tools/test_library.py：2項隔離負向測試；壞引用、hash不符、alias缺失／衝突、未知Skill會被攔下；預覽不寫入。
- TaskTracker tools/skill-inventory.py：14正文、0壞入口；原358個manifest檔案0缺失／0差異。
- 來源／diff／秘密排除及Python語法檢查。純Skill／文件異動，不啟動業務測試、不碰主庫。

尚未完成：Codex與Claude全新工作階段的讀取證據（現有工具清單更新不是證明）、TT-269完整觸發矩陣與四類案例、TT-270核准視覺baseline及TT-271獨立驗收。Claude CLI目前不在PATH；本輪不擅自安裝host或宣稱已驗證。TT-268雖已完成可獨立提交的搬移階段，整卡仍保留進行中直到原驗收條件補齊，不將Epic假結案。

共用庫階段commit：`53bb5a4`。上游原文匯入仍含其既有尾端空白，為保持原始hash未重排；自有規範／工具／索引的staged diff空白檢查通過。

## 2026-11-13 TT-268宿主可讀性補驗

Codex與既有VSCode Claude native binary各啟動全新、唯讀、不持久化session，停用hook／MCP或使用ignore-user-config。兩者實際讀根AGENTS、共用baseline、registry與Ponytail；Claude四次Read全部成功。Codex亦驗canonical與lite優先順序。這是明確引用的可讀性，不宣稱宿主自動發現。

Claude讀完後觸及額度限制，最終報告沒有完成，因此不能用這次結果聲稱TT-269四案例已通過；可讀性仍由實際成功tool_result證明。[去敏證據](evidence/skill-host-read-2026-11-13.json)記命令／路徑／結果與本地原始trace hash，完整原始trace留忽略artifacts。TT-268原可讀性驗收已滿足，後續四案例與視覺由269／270續辦。
