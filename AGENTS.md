# TaskTracker 開發指引

## 開始工作與資訊分工

1. 讀本文件、README及本次相關子目錄AGENTS；檢查原repository根目錄、分支與既有差異，保留使用者改動。
2. 讀Side Project共用Skill庫的README、PROJECT-BASELINE、registry，再選本次需要的Skill／references；不要載入全部正文。
3. 多步驟先更新 `plans/<卡號>/plan.md`、step.md，按卡片完成、驗證、提交與回填；各Agent只寫自己正在承辦卡號的資料夾, 不共寫plans/main。
4. 專案現況與交接按需查[Memory.md](Memory.md)索引；產品契約查[工程綁定](docs/project-engineering-bindings.md)及功能文件。不要每次開工載入整份歷史。

**AGENTS只維護規範、Skill入口、授權與工作流程。** 產品決策／環境現況／跨任務交接放Memory.md；每卡進度放卡片與plans；細節放專責docs。不得再將已完成卡片摘要、版本沿革或大量專案記憶累加於本文件。產品討論持續更新docs/產品討論紀錄.md，區分已確認、建議與目前實作。

## Skill來源與基本配置

共用根：`/Users/josh/workspace/side-project/skills`，換機可用 `SIDE_PROJECT_SKILLS_ROOT` 指定。使用者2026-11-12已取代原「只用專案內Skill」規則。

- 唯一正文由共用庫維護；`.agents/skills`是發現入口，`skills/`舊路徑為相容連結／來源與歷史索引。缺庫或失效入口須回報，不靜默回退全域同名Skill。
- 所有開發／review／重構／測試使用 `01-common/side-project-development-standards`；框架／UI／效能依registry選讀。通用規則回共用庫更新，只有實際專用Skill才留專案並說明理由。
- Skill選用／檢查以共用registry.json的selectionRules為唯一矩陣；執行 `python3 tools/check-task-skills.py --consumer . --base HEAD --tag implementation`，間接畫面／資料影響加ui／data-access標籤。標籤只能加門檻，不可略過；實際讀取與檢查證據依共用check_evidence.py驗證。
- 框架、UI／UX與效能按矩陣搭配；Ponytail採lite、不啟用全域hook／plugin，安全、嚴格分層、DI／PHPDoc、可及性與驗證優先。品牌／技術棧及產品綁定留consumer文件；缺核准visual baseline不能PASS。使用者已持續授權畫面候選可直接保存為核准基準, 不再逐批詢問；真實瀏覽器、視覺比較與安全驗證仍須執行, 授權不能代替測試通過。
- 使用者→consumer安全／產品契約→共用工程／框架→Ponytail→審美；除明確指定外不讀其他專案／全域Skill。來源／沿革連結不等於授權載入外部規則。
- 寫任何中文文字前（docs文件、卡片描述／留言、plans、給使用者的回覆與報告）先讀 `/Users/josh/workspace/common-skills/josh-writing-style/SKILL.md` 並照它寫, 為使用者2026-11-13明確指定例外；commit訊息仍依下方Git規則。

## API、資料與驗證門檻

HTTP API、Repository、查詢、前端資料載入或輪詢異動，必讀共用工程Skill的references/data-access.md八項審查。列表有界且穩定排序，SQL篩選分頁；詳細／留言／歷史／工時按需讀取，不以整個Workspace作一般頁面預設。測試必驗讀取範圍，不能只比SQL次數。

契約可合理改版／退役，須同步呼叫端、文件、正式機器規格與測試；有效permission＋scope、租戶隔離、資料保存、revision／requestId及重播前授權維持。第三方API／PAT工作先讀docs/third-party-api-documentation-plan.md、api-first-platform.md及query-api-v1.md，不另做工具專屬業務或MCP。

依共用工程references/verification.md選驗證，未測或仍有P1須明列未完成；build、初始化、部署分開回報。只用隔離人造資料，不對現用業務DB seed／RefreshDatabase／壓測，不對共享Redis全庫flush。秘密、帳密、PAT、Cookie、資料庫、截圖與build產物不進Git。只清理本次啟動的程序／容器，不改參考專案。

## TaskTracker卡片流程

所有既有／未來工作先查重並建對應卡片，依主題收錄，待辦先open未指派。專案定位及帳號位置查Memory.md／docs/tasktracker-self-tracking.md；使用共用task-tracker-usage，不因失敗回退Owner或舊共用帳號。

- Codex用自己的帳號先指派再進行中：`python3 tools/tasktracker.py --agent codex claim TT-243`；Claude改`--agent claude`。衝突或他人承辦即停止，不搶領；第二步失敗先讀回。
- 真的開始處理那張才claim；被指派或排入計畫不等於開工。同一Agent同時只留一張進行中，依共用task-tracker-usage自律執行，不靠工具硬擋。
- list／claim／status／comment維持同一Agent；有實質進度即留言已完成、實測、限制與下一步，不只留對話。
- 看板分類、領卡前預估及逐階段本人實際工時，依共用task-tracker-usage的references/classification-and-time.md；不以留言代替正式工時。
- 測試／提交後如實更新：需要Josh認可（視覺核准、驗收、決策）的卡改review待驗收並留言說明要確認什麼；驗收完成才closed，未通過／受阻維持並說明。不因commit自動假定整卡完成；完成一張回填後再領下一張，明確轉換工作或受阻例外亦須記錄。
- AI整合/API只放PAT／Scope／對外REST／契約文件與整合驗收；內部架構／DI／資料載入歸效能或結構優化；一般UI歸實際功能。不把跨主題Epic整包收錄。

## Git與階段交付

- Git操作前依使用者指定讀 `/Users/josh/workspace/piextract/skills/01-common/git-delivery-workflow/SKILL.md` 的「Git工作位置（所有專案）」；commit／PR文字前讀 `/Users/josh/workspace/common-skills/commit-without-ai-attribution/SKILL.md`。這兩者為使用者明確指定例外。
- 直接在原repository操作，不在暫存目錄另建clone／worktree避限制；保護既有差異。
- 每完成可獨立驗證階段立即本機commit，已持續授權；明確stage相關檔案，含plans/<卡號>/plan.md及step.md，檢查staged diff／空白。不套其他專案的plan排除流程。
- TT卡片commit格式 `<卡號> <卡片標題>`；同卡多次提交沿用。無卡才用繁中Conventional Commits。不加AI署名，沿用Git作者設定。
- 不自行amend／squash／重寫歷史或push；遠端另行授權。完成回報commit短碼、實測與限制；未提交說明原因。
