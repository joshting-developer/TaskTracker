# UI／UX 與可用性 skills

初次查詢與下載：2026-11-03；流程／可用性補充：2026-11-04（Asia/Taipei）

這裡最初保存兩個上游 UI／UX skill 的完整副本, 供 Josh 選擇或討論如何融合成 TaskTracker 的 UI／UX 審查流程。初次收錄僅下載與介紹；同日使用者追加要求啟用兩者並審查專案。現在已建立 [.agents/skills 入口](../.agents/skills/README.md), 原文仍維持固定版本, 不另建融合 skill。審查與 UI 改版分開處理。

## 新增的流程／可用性評估副本（尚未啟用）

使用者進一步想知道流程會不會卡住、畫面太雜或功能難找, 本輪選兩個互補 skill：

| Skill | 介紹 | 核心用途 | 狀態 |
| --- | --- | --- | --- |
| cognitive-walkthrough | [個別介紹](cognitive-walkthrough-介紹.md) | 沿任務逐步檢查目標、入口、動作意義與回饋 | 僅 [vendor 副本](vendor/cognitive-walkthrough/SKILL.md) |
| usability-testing | [個別介紹](usability-testing-介紹.md) | 設計真人試用、任務腳本、觀察與報告 | 僅 [vendor 副本](vendor/usability-testing/SKILL.md) |

本輪按任務適配選取, **不宣稱是星數前兩名**。兩者分別來自 19★ 與整庫 1,030★ 的 repo（2026-11-04 官方 API 快照）, 星數不能當使用效果。固定 commit、授權、候選及 7 檔校驗見 [ux-research-sources.json](ux-research-sources.json)。沒有加入 `.agents/skills` 或全域設定, 也沒有執行新一輪 UX 審查／真人測試。

怎麼與現有兩份配合、TaskTracker 建議測哪些任務、AI 與真人證據怎麼區分, 見 [可用性驗證方案](../docs/可用性驗證方案-2026-11-04.md)。

## 已啟用的畫面／介面 skills

| Skill | GitHub stars 快照 | 介紹 | 上游原文 |
| --- | ---: | --- | --- |
| UI UX Pro Max | 144,294 | [介紹與 TaskTracker 適用性](ui-ux-pro-max-介紹.md) | [SKILL.md](vendor/ui-ux-pro-max/SKILL.md) |
| Taste Skill v2 | 108,756 | [介紹與 TaskTracker 適用性](taste-skill-介紹.md) | [SKILL.md](vendor/taste-skill/SKILL.md) |

初步建議：Pro Max 提供可查證的可用性與實作檢查項目, Taste 的 app-screen 章節補視覺層級、資訊密度與實際渲染觀察。若之後融合, 應先訂共用審查輸出與衝突優先序, 不把兩份原文直接接在一起。使用者已指定兩者搭配審查；下列分工採本專案需求優先

## 「星數前兩名」的口徑

GitHub 的星數屬於 repository, **不是單一 SKILL.md 的使用人數或品質分數**。本輪選「主題以 UI／UX agent skills 為主、有實際 SKILL.md、非 fork」的 repository, 按 GitHub 官方 API 的 `stargazers_count` 比較。搜尋引擎只用來找候選；數值以 API 快照為準

這是本輪能核對到的專用 UI／UX skill repo 前兩名, 不是 GitHub 官方分類榜或保證涵蓋全站的完整普查。若將綜合型 skill 庫也納入, 排序會不同, 因此不把下面的高星候選默默省略：

| 候選 | Stars | 本輪處理 |
| --- | ---: | --- |
| [mattpocock/skills](https://github.com/mattpocock/skills) | 310,257 | 綜合工程技能, 非專用 UI／UX skill repo, 不納入此排名 |
| [anthropics/skills](https://github.com/anthropics/skills) | 186,781 | 多領域 skill 集合；frontend-design 是其中一個 skill, 不能將整庫星數說成它的單獨星數 |
| [nextlevelbuilder/ui-ux-pro-max-skill](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill) | 144,294 | 選取, 複製核心 ui-ux-pro-max skill, 不連同其他附加 skills 一起安裝 |
| [voltagent/awesome-design-md](https://github.com/voltagent/awesome-design-md) | 125,276 | 設計參考資料庫；檢查的完整 tree 沒有 SKILL.md, 不當成可直接使用的 agent skill |
| [Leonxlnx/taste-skill](https://github.com/Leonxlnx/taste-skill) | 108,756 | 選取, 複製目前的 Taste v2 skill |
| [pbakaus/impeccable](https://github.com/pbakaus/impeccable) | 100,509 | 有審查方向, 但本輪星數低於前兩名, 先列候選不複製 |
| [emilkowalski/skills](https://github.com/emilkowalski/skills) | 53,045 | 設計與工程 skills, 本輪星數低於前兩名, 先列候選不複製 |

精確數值、官方 API URL、查詢條件、上游 commit 與逐檔校驗見 [sources.json](sources.json)。第三方排行榜與 GitHub 網頁的四捨五入數字沒有用來決定精確星數

## 目錄與來源保留

```text
skills/
  README.md
  ui-ux-pro-max-介紹.md
  taste-skill-介紹.md
  sources.json
  ux-research-sources.json
  vendor/
    ui-ux-pro-max/      原 skill、references、Python scripts、CSV／JSON data、上游 LICENSE
    taste-skill/       原 skill、app-screen 規則、Node scripts、library、assets、上游 LICENSE
    cognitive-walkthrough/  2026-11-04 新增, 尚未啟用
    usability-testing/      2026-11-04 新增, 尚未啟用
```

UI UX Pro Max 固定 `713eef51b04efc98d6fa565323b495f7c9c89fd4`, 上游路徑 `.claude/skills/ui-ux-pro-max`；Taste 固定 `12af471f627f866411b4bd785b19646d3672784c`, 上游路徑 `skills/taste-skill`。兩者各補原 repository 根目錄的 MIT LICENSE, 原內容未改寫；前者 75 檔, 後者 40 檔, 合計約 6 MB, 包含必要資料與原有測試／參考, 沒有複製整個 repository 或建立巢狀 Git

副本放在 `skills/vendor`, 已用相對 symlink 加入 `.agents/skills/ui-ux-pro-max` 與 `.agents/skills/design-taste-frontend`；沒有寫入全域 skills。當次對話明確讀取原文使用, 不宣稱已熱更新 agent 技能清單。Taste 的 frontmatter 名稱是 `design-taste-frontend`, 本機保留較好辨認的資料夾名 `taste-skill`

上游程式、規則、研究結論及 style 禁用名單都是待評估的第三方內容, 不覆蓋使用者需求或本專案規範。原文中的插件路徑、全域安裝、下載圖片或套用樣式流程沒有自動執行。外部圖片與字型各有其來源條件, 上游 MIT 授權不代表所有外部資產都使用 MIT

## 建議怎麼比較或融合

| 審查面向 | Pro Max 的用途 | Taste 的用途 | TaskTracker 的取捨 |
| --- | --- | --- | --- |
| 可用性與無障礙 | 查鍵盤、焦點、標籤、錯誤回饋及狀態辨識 | 觀察文字、密度與實際渲染 | 有實際操作／量測證據才下結論, 不把風格偏好寫成合規要求 |
| 資訊與視覺層級 | 檢查一致性、導覽及 responsive 規則 | 觀察畫面主要問題、重複資訊、留白與資料對齊 | 優先支援處理 Issue／小待辦的效率, 不為好看加總計卡 |
| Vue＋Vuetify 實作 | 可用 Vue stack 資料補充 | 多框架適用, 不需照抄其 CSS 基底 | 保留既有元件與語意, 不換 React／Tailwind 或重寫授權 |
| 視覺方向衝突 | 可選多種色彩、風格與動態 | 有較強烈的 house rules／禁用色彩等偏好 | 使用者已確認的需求與產品行為優先；既有品牌不是工具一項 FAIL 就必須換色 |
| 實際體驗 | 規則查詢不等於看過畫面 | inspector 可產多尺寸截圖, 仍是啟發式規則 | 需在隔離 Laravel／測試帳號登入後看真正畫面, 再人工操作確認 |

若採融合流程, 建議先用同一份隔離資料, 分開產出兩份審查結果, 再去重。每個問題固定記錄「畫面與角色 → 重現步驟 → 證據 → 使用者影響 → 嚴重度 → 規則來源 → 最小修改建議」。衝突時先處理資訊可見性與誤操作, 接著可用性／無障礙, 最後才是審美偏好。原 Skill 的數值門檻與掃描分數不能直接等同 WCAG 符合性或使用者滿意度

建議首批情境是首頁速記／交辦、待辦轉正式卡片、Project 清單與看板、讀者任務詳情及工時填報。保留我們已確認的專案入口、角色只是權限組合、視圖／甘特圖暫緩等產品決策；這些是本輪審查範圍；結果另記日期報告, 不把工具規則當成已驗證事實

## 初次收錄的驗證與更新方式

- 115 個上游檔案的 Git blob SHA-1 與固定 tree 一致, 另記 SHA-256；沒有漏檔、額外產物或 symlink
- Pro Max 的 UX 查詢與 Vue stack 查詢可讀本機資料, 分別有 3／1 個結果
- Taste `draw.mjs --app --list kinds` 可讀 app library；`inspect.mjs` 通過 Node 語法檢查, **沒有啟動瀏覽器或宣稱登入頁內審查已通過**
- 自寫文件／設定的空白檢查通過；上游副本保留原有 46 處空白提示, 以逐檔雜湊確認未自行格式化, 不把這些提示說成產品錯誤
- 不安裝 npm／pip 套件, 不改產品原始碼、資料庫、Seed 或全域設定

更新時先另行查 API／選 commit, 重新複製對應 skill 子目錄與 LICENSE, 重新計算清單並閱讀上游差異。不要以「追最新版」直接覆蓋之後可能建立的本機融合規則；應將本機規則另存, 保持 vendor 可比對

## 首次搭配審查（2026-11-03）

已用兩者完成 [TaskTracker 審查報告](../docs/UIUX審查報告-2026-11-03.md)。Pro Max 提供鍵盤／錯誤等 UX 檢索, Taste 用 app-screen 規則、source lint 及已登入頁面的上游量測／判斷模組；實際檢查隔離 Laravel 與 production preview 的 44 組畫面, 並核對轉卡／工時／看板狀態保存。尚未改產品 UI 或建立融合 skill。完整 inspector 的無 session 限制、啟發式誤報與未覆蓋情境均在報告註明。
