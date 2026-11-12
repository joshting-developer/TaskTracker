# Skill選用與證據門檻

TT-269／2026-11-13。唯一矩陣為Side Project共用庫registry.json的selectionRules，專案入口不另維護矩陣副本。

開工：`python3 tools/check-task-skills.py --consumer . --base HEAD --tag implementation`。工具讀git diff與未追蹤路徑，輸出canonical SKILL路徑、命中規則與必要檢查。可用--path／--tag模擬；間接UI／權限／loading／資料流影響必加ui／data-access標籤。標籤只能增補；未識別標籤失敗，不能以skip-ui關閉檢查。工具無法推論所有業務影響，review仍需判斷跨層影響並補標籤。

共用tools/check_evidence.py核對矩陣版本、來源檔案hash、選用摘要與每項實際命令／退出碼／產物hash；UI的visual-approved另驗approved及passed。缺失、來源改變、矩陣被縮減、檢查失敗或未核准baseline皆非PASS。不是以Agent聲稱已讀代替證據；它也不是阻止惡意偽造測試紀錄的簽章服務。

本輪共用測試：4項選用（四類／間接UI／文件例外／資料存取／非Vue框架／Hallmark明確指定）、2項證據負向（缺失／未核准visual／失敗／過期來源／竄改矩陣）、原2項來源／入口工具測試均通過。

## 雙宿主新工作階段

Codex與Claude各4個新、唯讀、不持久化session：只改可見文字、任務表單、純機器後端API、新前端依賴。每次先讀AGENTS／共用baseline／registry，再用實際工具讀所選SKILL.md；比對模型JSON名單、成功tool result／命令輸出與lite。8／8通過，[去敏證據](evidence/skill-host-selection-2026-11-13.json)保留選用／讀取名單與原始trace hash，原始trace在忽略artifacts。

Claude先前quota不足，使用者登入另一帳號後依序完成小測試及3項補驗；沒有安裝全域工具或變更hook／MCP。這是明確引用Skill的實際選用驗收，不宣稱宿主自動發現所有外部symlink。根CLAUDE／frontend入口只指向同一規範，Memory未作常駐載入。

重跑：共用`tools/host_skill_smoke.py --host codex|claude --binary <現有執行檔> --consumer <repo> --output <忽略目錄>`；--case可逐項執行。會呼叫宿主模型，需有效登入／額度；失敗不回退帳號，不把部分讀取或模型quota錯誤當成功。

## 和視覺門檻的邊界

本卡完成選用與缺證據拒絕的規則及雙宿主證據；真實產品比較、候選／核准基準及退化注入由TT-270實作。沒有approved visual evidence時check_evidence必失敗，不能以本文件當作畫面驗收。CI／本地的證據應由真實檢查產生，不提交自製PASS佔位結果。

框架由consumer的package.json／composer.json實際依賴偵測；無Vue／Laravel不載入其框架規範。模擬可用--stack；實際consumer不以命令列偽造stack。Hallmark只在明確hallmark標籤使用，不因一般redesign就違反其既有畫面選用條件。最終矩陣8個session已全部重跑，不沿用舊hash。
