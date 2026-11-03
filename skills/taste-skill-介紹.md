# Taste Skill v2：視覺判斷與實際畫面的檢查迴圈

後續更新（2026-11-03）：初次介紹後, 使用者已指定加入 `.agents/skills` 並搭配另一份 skill 審查。實際結果與限制見 [UI／UX 審查報告](../docs/UIUX審查報告-2026-11-03.md)；下文「本輪未審查」指初次收錄階段。


查詢日期：2026-11-03（Asia/Taipei）

它偏向「讓介面每個視覺選擇都有理由, 並真的看過渲染結果再交付」。比起列出通用 UX 規則, 它更在意視線先看到什麼、資訊是否有主次、畫面是否只是套一組元件, 以及手機呈現是否還成立。這個定位適合補我們對「太雜、字太淡、區塊重複」的討論, 但它的審美偏好要另外判斷是否適用

## 來源與本機位置

| 項目 | 內容 |
| --- | --- |
| Repository | [Leonxlnx/taste-skill](https://github.com/Leonxlnx/taste-skill) |
| 星數快照 | 108,756★, 來源為 [GitHub repository API](https://api.github.com/repos/Leonxlnx/taste-skill), 非個別指令使用數 |
| 固定版本 | `12af471f627f866411b4bd785b19646d3672784c` |
| 原 skill | [固定版本 SKILL.md](https://github.com/Leonxlnx/taste-skill/blob/12af471f627f866411b4bd785b19646d3672784c/skills/taste-skill/SKILL.md) |
| 本機入口 | [vendor/taste-skill/SKILL.md](vendor/taste-skill/SKILL.md) |
| Skill 真正名稱 | frontmatter 為 `design-taste-frontend`, 內文為 tasteskill v2 |
| 授權 | [保留的 MIT LICENSE](vendor/taste-skill/LICENSE) |
| 複製範圍 | skill、references、scripts、library、assets、evals 及根 LICENSE, 40 檔約 2.4 MB |

完整來源、雜湊與選取口徑見 [總覽](README.md)及 [sources.json](sources.json)

## 要分清網站與工作介面

這個版本將兩者分成不同流程。Landing page、作品集、品牌網站走 SKILL.md 的 page 流程；dashboard、清單、inbox、設定與編輯器走 [app-screens.md](vendor/taste-skill/references/app-screens.md)。TaskTracker 應以後者為主, 不能拿行銷頁的 hero、大圖、轉場或得獎頁評分要求來改任務管理工作區

App-screen 流程先整理真實資料與使用者工作, 確認畫面主要要回答的問題, 再考慮樣式、shell、表格／清單、表單、各種空值與互動狀態。它明確容許工作介面有安靜的分隔線、小標籤與重複面板, 也不要求工作畫面有表演式動態。[App-screen 檢查流程](vendor/taste-skill/references/app-screens.md#27-the-check)

上游說明自己的資料來自網站／產品畫面研究, 也附研究與評分紀錄。但 [README](vendor/taste-skill/README.md) 有區分模型評審與真正 Awwwards 得分；本輪沒有重做其實驗, 不把其評分當成獨立使用者測試或「已證明比其他技能好」

## 它帶來哪些東西

- `draw.mjs --app`：從本機 app library 提供符合情境的方向；既有品牌可保留 palette／type。是方向產生工具, 不是診斷我們當前畫面的必經步驟
- `lint.mjs --app`：掃原始碼中的特定模式。這是靜態線索, 不等於看過執行結果
- `inspect.mjs --app`：以真實瀏覽器量測並產出 1440／768／390 等尺寸的截圖與 findings
- app-screen 參考：shell、資料區寬度、視覺焦點、表格數字對齊、顏色用途、留白與狀態

工具功能以此固定版本 [scripts](vendor/taste-skill/scripts/inspect.mjs) 與 [上游說明](vendor/taste-skill/README.md) 為準。Inspector 的 FAIL／WARN 混有 house-style 偏好及 heuristic, 不能直接當成使用者無法操作或 WCAG 違反的證據

## 適合怎麼審 TaskTracker

| 情境 | 它能補充的觀察 |
| --- | --- |
| 首頁小待辦＋專案入口 | 第一眼是否知道今天要處理什麼, 兩種資料的層級是否清楚, 是否過度占畫面 |
| 任務清單 | 標題、代碼、狀態與負責人是否容易掃讀, 是否有重複資訊或不必要裝飾 |
| 看板 | 密度、泳道／狀態的層級, 卡片內各欄位是否搶同一個視覺重心 |
| 任務詳情／轉卡側欄 | 主內容與屬性欄的比例, 重要操作與說明的主次, 手機是否仍可理解 |
| 工時月曆 | 日期／紀錄／填報面板的主要操作是否清楚, 空值與總計是否易誤讀 |

這些是建議觀察題目, 本輪尚未對實際畫面作出診斷。它也不能代替驗證 Manager、Developer、Reader 等權限場景；隱藏按鈕或介面乾淨不代表後端 ACL 正確

## 本機命令與實際限制

本輪只執行下面的列舉命令, 確認 app library 可讀, 不會產生方向檔或改產品：

```sh
node skills/vendor/taste-skill/scripts/draw.mjs --app --list kinds
```

TaskTracker 的首頁／協作空間可先參考 `workspace`, 任務處理佇列可比較 `support`；是否採用其中一種仍需看實際內容, 不用「dashboard」一詞就自動添加統計圖

完整 inspector 需要 Node 22+ 與已安裝 Chrome／Edge／Chromium, 可用 `CHROME_PATH` 指定瀏覽器；它直接走 CDP, 不需要再安裝 npm 套件。真正產方向或檢查會寫檔, draw 預設也有家目錄歷史, 所以未來應明確設 `--out` 與 `TASTE_SKILL_HISTORY` 到當次可寫的評估目錄

**登入是目前整合的重點**：[CDP helper](vendor/taste-skill/scripts/lib/cdp.mjs) 會啟動暫存 browser profile, CLI 沒有現成的 Laravel session 匯入選項。直接把 TaskTracker 私有路由丟給 inspector, 可能只拍到登入頁；不能把這樣的截圖說成已審首頁／看板。未來要先設計隔離測試帳號登入與畫面取證的銜接, 或用現有 Playwright 整合流程取得正確畫面, 再套用它的檢查規則。不要使用真實帳密或放寬 ACL 來讓工具通過

日後可以這樣要求：

> 讀取 skills/vendor/taste-skill/SKILL.md 及完整 references/app-screens.md, 用 app-screen 規則審 TaskTracker 首頁和任務列表。保留 Vue＋Vuetify 與既有產品決策, 先只回報問題不改碼。每個結論需對應正確角色登入後的實際畫面, 分開標示操作障礙、視覺問題與個人風格偏好, 不因工具分數自行換品牌色或新增 KPI

## 限制與融合建議

它的 house rules 對紫色、漸層、陰影、字型、裝飾等有強烈立場, TaskTracker 目前已有紫色主色與自己的資料工作區取向。不能因為某個工具禁用項就判定整站必須改版；應先看是否造成資訊辨識或操作問題, 真正改視覺方向另作決定

若與 Pro Max 融合, 我會取它的「在真實畫面裡判斷主次、密度、留白與多尺寸效果」, 再用 Pro Max 補具體可用性／無障礙／Vue 實作依據。保留兩份 vendor 原文, 另寫本機審查規則和衝突處理, 會比直接修改上游內容更容易維護
