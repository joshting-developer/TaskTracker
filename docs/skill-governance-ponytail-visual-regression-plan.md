> 2026-11-12更新：Skill正文已集中Side Project共用庫，舊專案路徑為相容連結；本文原存放位置視為歷史。載入與優先順序依根AGENTS「Skill來源與適用範圍」，產品設定見project-engineering-bindings.md。Ponytail預設lite，不裝全域hook／plugin。

# TaskTracker Skill 治理、Ponytail 導入與前端視覺回歸門檻

> 建立日期：2026-11-12（Asia/Taipei）
> 狀態：待 Agent 執行的工作規格；**本文件建立不代表已安裝、清理或啟用任何 Skill／Hook，也不代表前端驗收已通過**。
> 專案：TaskTracker（Vue 3＋Vuetify 4＋Vite、Laravel 13；本機 MySQL／Redis）。
> 使用者要求：① 引入 Ponytail；② 整理 Skills，同一份內容不得有不必要的實體副本；③ 確保對應 Skill 真的被 Agent 使用，尤其避免修改功能後畫面／RWD／元件排版意外改變。
> 執行順序：**先盤點 → 再去重／導入 → 再建立觸發與驗證 → 獨立驗收**。非本次授權的產品重構不得順手做。

## 0. 現況與判斷（執行前須重新驗證）

截至 2026-11-12，已從 GitHub main 讀取根 AGENTS.md、backend/AGENTS.md、.agents/skills/README.md、skills/README.md、TaskTracker Development Standards、既有配置／來源 manifests 與前端 package.json。這是文件／部分程式檢查，**尚未在完整本地工作樹執行 recursive find、readlink、inode、hash 或各 Agent 的實際載入測試**。

- 多數 Skill 目前是「skills/vendor 或 skills/自有規範保存原始內容；.agents/skills 提供相對 symlink 入口」。**同一個 Skill 在兩條路徑可見，不等於兩份實體檔案**。先查清連結指向，再談刪除，不能直接刪掉 .agents/skills。
- 目前已見 TaskTracker 自訂工程規範、ECC Laravel、Laravel Architecture、Vue Best Practices、UI UX Pro Max、Design Taste、Addy、ECC Benchmark、Hallmark、Unslop；另有 cognitive-walkthrough、usability-testing 只保留 vendor 副本、非自動啟用。不同用途的 Skills 不因部分建議相似而合併。
- sources.json、development-sources.json、performance-sources.json、anti-slop-sources.json 等保存上游固定版本、hash、來源；README 和 docs/ 保存使用說明。這些**metadata／索引／原始內容**角色不同，不應一概視為重複。
- 根 AGENTS.md 已規定開發、review、測試使用 TaskTracker Development Standards，且 UI 變更有 UI／UX Skills；但「文件有寫」≠「Codex／Claude 每次真的載入」≠「修改後真實畫面沒有退化」。
- 已存在 frontend/tools/browser-check.js 與多種 browser／composable 檢查；package.json 使用 playwright，但不宜假設已具備可強制擋下視覺差異的 golden screenshot 比對。請盤點既有圖片／截圖／CI 才能確認。
- 已確認 backend/AGENTS.md 存在，frontend/AGENTS.md 在目前 GitHub main 未找到。可評估建立**精簡前端指引入口**，不複製另一整套規則。
- 既有 docs/performance-skills.md、docs/anti-slop-skills.md 與本地 Skill 已有分工及優先序。重整要保留使用者的產品決策：Vue／Vuetify、原品牌色、資料密集型 app screen、Session／CSRF、permission＋scope、revision／requestId、MySQL、安全與資料隔離。

## 1. 工作一：完整盤點與「單一實體來源」整理

### 1.1 盤點範圍

掃描根目錄、skills/**、skills/vendor/**、.agents/skills/**、.claude/**（若存在）、**/AGENTS.md、CLAUDE.md（若存在）、各來源 manifest、README／docs 中的 Skill 入口；同時檢查本機 global／使用者目錄是否有同名 Skill **只記錄是否可能干擾，不讀取或套用專案外規範、不任意改動外部目錄**。

產出機器可讀 inventory（例如 docs/skill-inventory.json，若不需保留巨大明細可保留摘要＋可重跑工具）與人工報告，逐個 Skill 記錄：

- canonical 名稱、用途、觸發條件、來源 repo／固定 commit／授權。
- 唯一實體來源路徑、所有入口（symlink／adapter／文檔索引）、是否已啟用。
- 實際 Git blob mode（symlink 應為 120000）、readlink／realpath、SHA-256、檔案大小；辨識相同內容但不同路徑和失效／循環 symlink。
- 哪些是合法的上游必需 reference／LICENSE、哪些是退役或未啟用資料，哪些才是真正可刪的重複副本。
- 來源 manifest 中的 path／hash 是否和磁碟一致，以及所有引用是否仍可解析。

可參考的本機檢查（視實際環境調整，不依賴單一 find 輸出作結論）：

~~~sh
git ls-files -s .agents/skills skills
find .agents/skills -type l -print
find skills -name SKILL.md -print
find .agents/skills -type l -exec sh -c 'for p do printf "%s -> " "$p"; readlink "$p"; done' sh {} +
~~~

### 1.2 預期整理原則

- **每個被採用的 Skill 只有一份 canonical 實體內容**；建議自有 Skill 在 skills/<name>/，第三方固定版本在 skills/vendor/<name>/；.agents/skills/<name> 只保留相對 symlink，若工具確實不支援 symlink 才用小型 adapter，**不得複製完整 SKILL.md**。
- 同一上游樹中的 LICENSE／引用檔案若為授權或相對路徑需要而保留，應標記為合法例外；不可為了 hash 去重破壞授權、上游階層或參考引用。
- 已停用／僅研究的 Skill 不要自動刪掉；按「啟用／僅參考／歷史封存」區分。真正冗餘內容先列 diff、引用影響與回退方案，再在明確範圍內移除。
- 維持一份權威「Skill 登錄／選擇矩陣」（可由現有 .agents/skills/README.md 擴充），其他檔案僅放**短入口與連結**，不重複維護大段使用規則。原本記錄來源／hash 的 manifest 可以依類別保留，不必為了形式強迫合併成巨大 JSON。
- 不覆寫 vendor 上游原文來調整 TaskTracker 流程；本地適配規則放在專案自有 Skill 或 AGENTS。
- 完成後驗證所有 symlink 仍可解析、frontmatter 有效、references 相對連結可讀、manifest hash 正確、git diff 無意外刪除；保留 Git 歷史。

## 2. 工作二：以專案本地方式引入 Ponytail

官方唯一主要來源：https://github.com/DietrichGebert/ponytail 。先查官方 README／INSTALL.md／skills/ponytail/SKILL.md、LICENSE，以及固定 commit。**不要從同名鏡像、二次打包或不明安裝腳本直接執行。**

推薦執行方案（先做 A，不同方案不可疊加雙重注入）：

**A. 與 TaskTracker 原本模式一致的專案本地 Skill**
- 將官方需要的 SKILL.md、必要 references／LICENSE 固定於 skills/vendor/ponytail/（保留官方可核對的 commit、來源 URL、hash）。
- 建立 .agents/skills/ponytail 相對 symlink；更新登錄表及來源 manifest，設定用途和觸發範圍。
- 對 Codex 與 Claude 各開一個**新 session**實際驗證可讀 Skill；若某宿主不會自動發現 symlink，採用最短可用的 project-local adapter／明確引用。不能只以檔案存在宣稱自動載入成功。
- 預設在新功能設計／程式碼 review 採 **lite** 原則，優先既有元件、Vuetify、Laravel 內建、平台原生能力；複雜重構需另取得工作範圍，不自動以 ultra 模式改全庫。
- Ponytail 不得把「減少程式碼」凌駕於既有 Laravel 分層、必須的 DI／PHPDoc、權限／多租戶、交易、revision、防重送、XSS 防護、測試、無障礙、UI 一致性或資料保存之上。

**B. 若要使用官方 plugin hooks／slash commands**
- 官方 Claude Code／Codex plugin 安裝與可用 hooks 可能依宿主版本、使用者層級設定生效。**不得在未確認 scope、可執行 script、設定寫入、隱私和解除安裝方式前，直接把 plugin 裝到全域／使用者環境或信任 hooks**。
- 先實測是否支援只限本專案啟用；若不支援，保留 A 的專案本地手動／顯式載入即可。Hooks 不是畫面回歸驗證的替代品。
- 若 B 真正啟用，檢查是否與 A／既有 AGENTS 重複注入；只能有**一條實際生效的 Ponytail 指令來源**。記錄 Codex 與 Claude 的啟用、停用、測試方法與差異。

## 3. 工作三：把 Skill 使用「掛到任務流程」，不是只有 README 列名

### 3.1 設定可驗證的選擇矩陣

建立一份唯一規則入口，並讓 Codex／Claude 都能從既有根 AGENTS.md 走到此入口；需要時由精簡 frontend/AGENTS.md、CLAUDE.md（如宿主確有需要）等**指標文件**提供同一規則，不複製長文。先確認宿主實際會載入哪些檔案，不猜測 Claude 會自動讀 AGENTS.md 或所有 Skills。

| 工作類型／變更範圍 | 必讀／必用 | 執行前必做 | 完成門檻 |
| --- | --- | --- | --- |
| 所有產品程式／review | TaskTracker Development Standards | 讀 AGENTS、相關產品契約、對應卡片及原有實作 | 差異審查＋按風險驗證 |
| frontend/src/**、Vue composables | Vue Best Practices；適用時 Ponytail | 確認現有 Vue／Vuetify 元件和狀態 owner | build、對應 browser／composable 回歸 |
| **任何可能改變 UI 的變更**，包含功能、模板、CSS、元件 props／loading／empty／error／權限顯示、共用資料流 | UI UX Pro Max＋Design Taste（app-screen 規則）；Vue Best Practices；必要時 Unslop | 先檢查原版畫面與布局／RWD 的不變項 | **必須通過第 4 節的真實瀏覽器視覺門檻** |
| 明確要求 UI redesign／去 AI 化 | 視任務使用 Hallmark／Unslop，仍保留 UI／UX＋Vue | 保留品牌、流程與原功能，區分審查和實作授權 | 比對改動前後、互動與可用性 |
| backend/app/**／Laravel API | ECC Laravel Patterns＋Laravel Architecture＋本地 DI／PHPDoc／資料存取規範 | 檢查授權、交易與現有 Service | PHP 測試；API／MySQL 按需真實驗證 |
| API 資料載入／輪詢／效能 | 本地 data-access；Addy 為主，ECC Benchmark 補量測 | 先建立 baseline，界定呼叫範圍 | 實測 SQL／rows／bytes／HTTP／瀏覽器，不以 Skill 名稱當證據 |
| 新增套件／抽象／重構的提議 | Ponytail＋既有架構 Skill | 優先查已有依賴／標準函式庫 | 清楚記錄為何需要新依賴及所保留的測試 |
| skills/**、.agents/skills/**、Agent 指引／hooks | 本計畫與 Skill 登錄規則 | 比對 canonical／引用／來源授權 | symlink、hash、載入 smoke test |

UI 的規則以**是否可能影響使用者看見／操作的畫面**判斷，不只看是否改了 .vue 或 .css；反過來說，純 PHPDoc／純文件不強制啟動整套 UI 審查。

### 3.2 讓「真的讀過」可以被驗證

- Agent 開工先輸出（可寫入 plans 與卡片回報）選用 Skill、讀取的**實際 SKILL.md 路徑**、與本卡的關聯及重要不可違反規則；不可只宣稱「已使用所有 Skills」。
- 完工回報對應：使用哪個 Skill 處理了哪類風險、是否未採用某一建議及原因、實際執行的測試、視覺比對截圖／差異證據、未驗證項目。
- 在可行的本地工具／CI 增加 deterministic gate：由 git diff 路徑＋任務標籤決定至少應跑哪些檢查。**僅靠 Agent 自述不是通過門檻**；缺少視覺基準或登入環境時，要明確報 blocked／not verified，不能標綠。
- 驗證 Codex 與 Claude 分別對「只改文字」「調整任務表單」「純後端 API」「增加套件」四類模擬任務，是否準確挑選 Skill；重開新 session，避免只測舊對話暫存 context。
- 如果 Skill 互相衝突，優先序：使用者當次確認需求 → 專案安全／產品契約／本地規範 → 框架專責 Skill → Ponytail 最小化建議 → 純審美偏好。由 Agent 在回報中解釋衝突，不自行改需求。

## 4. 核心：前端視覺回歸防線（本需求最重要的驗收）

**不能把 npm run build 通過、API 回應正確、E2E 只檢查元素存在，當成「畫面沒有跑掉」。必須用真實瀏覽器及可比較的畫面驗證。**

### 4.1 使用既有 Playwright 流程，不另建一套產品測試平台

- 先盤點 frontend/tools/browser-check.js、其他 Playwright 腳本、登入 helpers、現有截圖／測試資料。優先共用它們的隔離 Laravel、真實帳號、固定 seeded 狀態與 navigation helpers。
- 引入可重跑的 visual baseline／screenshot compare（使用現有 Playwright 能力；如果真的需要新增 @playwright/test 或 image comparator，先提出選型、依賴成本和可替代方案）。**不要假設目前 playwright 套件本身已提供 assertion snapshot API**。
- 使用固定 viewport、裝置倍率、locale／Asia/Taipei、字型、資料、時鐘、瀏覽器版本及背景請求穩定條件；必要時關閉非必要動畫，等資料與 font ready 再截圖。敏感資訊遮罩；只提交經確認的合成資料 baseline。
- 當前並非所有頁都需要一開始全覆蓋；先選風險最高、最常改的頁面和狀態，保留逐步擴充機制。

### 4.2 首批基準畫面

至少包含 desktop 1440×900、tablet 768×1024、mobile 390×844；依現有介面真實斷點可調，但必須記錄理由。

最低必查情境：登入、首頁／個人待辦、專案列表、專案任務列表（篩選開／關）、看板（空／多卡／拖曳後）、Issue 詳細頁、Issue 編輯面板／Dialog、工作區切換與權限差異、載入中／空資料／錯誤／驗證提示；挑選畫面時以目前產品真正存在的操作狀態為準，不憑想像造假頁面。

每張測試需有穩定 ID、fixture／權限、viewport、步驟與 approved baseline。元件本身未改，但共用 CSS、容器、spacing、font、資料投影改動時，必須重新測受影響的關聯頁。

### 4.3 阻擋條件與人工確認

- 只要可見 UI 被觸及，至少跑**受影響流程的行為測試＋前後視覺比對**；共享 layout／theme／CSS 改動須擴大到主要頁與三種 viewport。
- 產出 before／after／diff 圖與 summary（哪一頁、哪個 viewport、差異範圍、是否預期）。固定 baseline 只能在 UI 變更獲明確確認後更新，**不得直接執行 update-snapshots 把 regression 洗成 PASS**。
- 文字、動態日期、游標與非確定資料要以可重跑 fixture／遮罩處理，避免廣泛提高 pixel tolerance 導致真回歸被忽略。差異閾值可依頁面建立，但**標準先以人工核准的實際樣本校準**，不要憑空指定百分比當品質保證。
- 擋下元素被遮住、溢出、錯位、對比／焦點退化、按鈕無法操作、modal 逃逸焦點、mobile 橫向溢出、權限錯顯、表格欄位消失或使用者未要求的顯著視覺變動；可容許細微字型抗鋸齒差異，但必須有紀錄。
- 載入／空／錯誤／權限 denied 狀態不能只截 happy path。若視覺測試因環境缺失未跑，回報為未驗證；不要在 commit／卡片留言稱「UI 已驗收」。
- 在 CI 或 merge gate 可用時，至少把 visual runner 和 baseline 檢查掛上（若 repo 沒 CI，先提供可本地一鍵執行的指令與清楚退出碼）。自動化視覺比對**輔助**人工審查，不代表任何畫面變化都必須禁止。

## 5. 分階段執行、交付與驗收

1. **Phase A — 盤點（只讀）**：完整目錄清單、canonical→entry 圖、真正重複檔案名單、失效引用、目前 Codex／Claude Skill 掛載現況、現有前端 screenshot／CI 缺口。先報告，不能見到兩條路徑就刪。
2. **Phase B — 去重／Ponytail**：刪除有證據的重複實體副本（或保留合法例外）、修復相對連結與來源 manifest；引入固定版本 Ponytail 專案本地入口；測兩個 Agent 新 session 載入。避免同時以 plugin 與 vendor 重複注入。
3. **Phase C — 強制選用規則**：落地唯一 Skill 登錄／觸發矩陣與精簡的 Codex／Claude／frontend 指引入口；加載入 smoke test／可重跑腳本；用四種模擬任務驗證。文件未載入時不能視為通過。
4. **Phase D — 視覺回歸**：沿用現有 Playwright 登入與人造 seed，做首批多尺寸 approved screenshot baseline、diff／報告與一鍵測試。刻意製造可回復的 spacing、overflow、隱藏按鈕等回歸，確認 gate **真的會失敗**；還原後確認通過。其他業務測試不得退化。
5. **Phase E — 獨立審查／交接**：回報 inventory、刪除／保留清單、Agent 載入證據、frontend 回歸 before／after／diff、測試指令與真實結果；更新必要入口與文件，對未完成項目開卡，不假裝全部合格。

### 必要驗收條件

- [ ] 沒有無原因的多份實體 Skill 副本；合法相對 symlink／上游參照仍完整。
- [ ] Ponytail 有可追溯固定來源、版本、LICENSE、單一 canonical 路徑及可用的專案入口。
- [ ] Codex 與 Claude 在**新 session** 都能依任務載入適用 Skills；不只在 README 出現名字。
- [ ] 每種代表性任務的 Skill 選擇、路徑與使用證據可查，不會因衝突消掉專案安全限制。
- [ ] 修改一般前端功能但意外造成 UI 位移時，visual gate 能重現並顯示 FAIL。
- [ ] 桌面／平板／手機基準與受影響流程可穩定重跑；預期 UI 更新需要可追溯核准。
- [ ] backend unit／feature、frontend build／browser、跨租戶、revision、API 契約與資料安全未因整理 Skills 退化。
- [ ] 不新增全域 Skill、未審查 hooks、無關框架依賴、正式／主資料庫 seed 或秘密洩漏。
- [ ] 各階段有獨立結果／測試／差異回報，只有真正完成的項目才標完成。

## 6. Agent 開工與專案作業約束

接手 Agent 應先讀根 AGENTS.md、TaskTracker Development Standards、.agents/skills/README.md、skills/README.md、本文件及當階段相關來源。依根 AGENTS 的 TaskTracker 自我追蹤流程，先查是否已有相關卡，避免重複建卡；缺卡則先建立**尚未指派、狀態 open** 的工作卡。開始處理特定卡片時才按自己的 Agent 帳號 claim／改進行中，測試通過才結案；工作有進度需回填卡片。

每階段在既有 branch 的 plans/<branch>/plan.md／step.md 記錄需求、假設、Diff、驗證結果與待續事項；按專案規則完成可獨立驗證的本機 commit，**不自行 push**（需另行授權）。不用全域工具、外部主庫破壞性測試、重寫 Git 歷史、額外 clone 或不必要的全庫重構。

**明確非目標**：本文件只規劃 Skills 治理／Ponytail 與 UI 視覺驗收，不順便改色、重設計 TaskTracker、重寫全套 Vue／Laravel、替換既有 Service／Repository、調整商業邏輯或以刪除必要測試換取較短程式碼。

## 7. 主要參考（執行時重查）

- [TaskTracker root AGENTS](../AGENTS.md)
- [專案 Skill 入口](../.agents/skills/README.md)
- [Skills 與來源紀錄](../skills/README.md)
- [專案自有工程規範](../skills/tasktracker-development-standards/SKILL.md)
- [現有驗證門檻](../skills/tasktracker-development-standards/references/verification.md)
- [現有 UI 配置](../docs/anti-slop-skills.md)
- [現有前端回歸腳本](../frontend/tools/browser-check.js)
- [2026-11-06 前後端重構紀錄](../docs/refactoring-2026-11-06.md)
- [Ponytail 官方](https://github.com/DietrichGebert/ponytail)、[README](https://github.com/DietrichGebert/ponytail/blob/main/README.md)、[INSTALL](https://github.com/DietrichGebert/ponytail/blob/main/INSTALL.md)
