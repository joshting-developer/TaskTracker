# TT-267 Skill搬移前盤點

2026-11-12。使用者更新：Side Project共用庫為`/Users/josh/workspace/side-project/skills`，取代舊project-only政策。這一階段只讀盤點，搬移及泛化由TT-268接續。

機器資料：[inventory](skill-inventory-before-shared-2026-11-12.json)，重跑`python3 tools/skill-inventory.py --output <檔案>`。只掃來源／symlink／公開來源manifest，不讀私有憑證或執行上游script。

- 12份Skill正文、10個啟用入口、2份手動研究（cognitive-walkthrough／usability-testing）；入口均為Git mode120000相對symlink，沒有失效連結，canonical已各一份。
- 358份上游manifest檔案的SHA-256一致、無遺失；上游來源／固定commit／LICENSE保存在既有五份source manifests，不把README或metadata當成重複Skill。
- 兩組相同內容皆為授權：ECC Laravel與Benchmark各自MIT LICENSE、Hallmark LICENSE與UPSTREAM-LICENSE。保留各上游必要路徑及可追溯性，不因hash相同刪掉授權。
- Addy的Skill會引用套件根references，搬移時須保存`skills/performance-optimization`與根`references`相對階層。其餘vendor的scripts／data／references／LICENSE整包保存，不只搬SKILL.md。
- 自有Laravel Architecture、TaskTracker Development Standards需抽掉TaskTracker固定ID／日期／部署沿革與產品專用要求，整理成共用Laravel規範與Side Project工程規範；產品決策留TaskTracker AGENTS／docs，避免泛化時刪掉嚴格分層、安全或資料存取八項。
- 既有共用庫有task-tracker-usage及私有PAT設定，工作樹乾淨；不覆蓋其內容或搬動憑證。分類預計01-common／02-backend／03-frontend／04-design／05-performance，必要vendor套件保持階層。

## 入口與載入證據

根／backend AGENTS與backend CLAUDE有舊project-only及暫緩重構歷史敘述，需更新為共用庫入口／按任務選用。原TaskTracker`skills/`及`.agents/skills/`可保留短symlink相容入口，沒有正文副本。新機須有共用庫與入口校驗，缺庫明確失敗，不回退到未知全域版本。

Codex CLI可找到；Claude CLI不在PATH。這是存在性檢查，未宣稱任一宿主新session已載入；TT-268／269必須補真實證據，無法取得Claude執行環境時保留未驗證。全域同名可能干擾的存在性列於inventory，不讀其內容、不改全域設定。

## 視覺回歸缺口

既有Playwright、真Laravel隔離帳號／資料、navigation helpers、browser-check及多種功能測試能沿用；現有截圖存忽略的artifacts。尚無approved golden與自動像素差異阻擋流程，不能把截圖存在或build成功當視覺驗收。

TT-270需固定三尺寸、字型／時間／資料、代表互動／empty／error等狀態，保留candidate／baseline核准與差異證據。缺少核准baseline須FAIL／未驗證，不自動update掩蓋退化。既有CI為api-contract，整合前先確定隔離fixture與執行環境。

本輪沒有安裝Ponytail、搬移、執行全域hooks、重構產品或操作主資料庫。原12個Skill、入口、manifest及Git歷史完整保留，後續搬移以本inventory逐檔比對。
