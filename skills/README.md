# TaskTracker Skill入口

唯一正文來源為 `/Users/josh/workspace/side-project/skills`（換機可用 `SIDE_PROJECT_SKILLS_ROOT` 配置）。

先讀該庫README、PROJECT-BASELINE與registry.json，再依任務載入Skill。分類：01-common共通／任務追蹤／Ponytail，02-backend Laravel，03-frontend Vue，04-design UI／UX，05-performance效能。

本目錄僅保留相容索引／來源與連結，不另存Skill副本；`.agents/skills`為自動發現入口。唯一的專案專用Skill是`.agents/skills/tasktracker-test-environments`（測試埠、隔離環境與驗收分層），正文就在該處，不是共用庫連結。TaskTracker特定框架、時區、權限、API契約及隔離測試見根AGENTS與docs/project-engineering-bindings.md。

Ponytail固定來源、原文與LICENSE在共用庫；採lite，不啟用plugin／hook，不凌駕安全、PHPDoc、嚴格分層及驗證。兩份可用性研究Skill保留手動選讀，沒有機械地全部啟用。

2026-11-12更新取代舊project-only存放規則；歷史來源manifest保留，舊vendor路徑透過連結仍可驗證。搬移報告見docs/shared-skills-migration-2026-11-12.md。
