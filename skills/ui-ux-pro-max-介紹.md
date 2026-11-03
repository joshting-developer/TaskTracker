# UI UX Pro Max：可搜尋的 UI／UX 規則與實作參考

後續更新（2026-11-03）：初次介紹後, 使用者已指定加入 `.agents/skills` 並搭配另一份 skill 審查。實際結果與限制見 [UI／UX 審查報告](../docs/UIUX審查報告-2026-11-03.md)；下文「本輪未審查」指初次收錄階段。


查詢日期：2026-11-03（Asia/Taipei）

它比較像一套「需要某種介面判斷時, 可以搜尋依據的設計參考庫」。對 TaskTracker 而言, 我會優先拿來查操作可理解性、鍵盤與焦點、表單回饋、資料密度及 Vue 實作注意事項, 而不是一開始要求它幫整站換風格。這是本專案適用性的推論, 本輪沒有實際審 UI

## 來源與本機位置

| 項目 | 內容 |
| --- | --- |
| Repository | [nextlevelbuilder/ui-ux-pro-max-skill](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill) |
| 星數快照 | 144,294★, 來源為 [GitHub repository API](https://api.github.com/repos/nextlevelbuilder/ui-ux-pro-max-skill), 非單一 skill 安裝數 |
| 固定版本 | `713eef51b04efc98d6fa565323b495f7c9c89fd4` |
| 原 skill | [固定版本 SKILL.md](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill/blob/713eef51b04efc98d6fa565323b495f7c9c89fd4/.claude/skills/ui-ux-pro-max/SKILL.md) |
| 本機入口 | [vendor/ui-ux-pro-max/SKILL.md](vendor/ui-ux-pro-max/SKILL.md) |
| 授權 | [保留的 MIT LICENSE](vendor/ui-ux-pro-max/LICENSE) |
| 複製範圍 | 核心 skill 子目錄與根 LICENSE, 75 檔約 3.6 MB；其他設計／簡報 skills 與上游 CLI 沒有一併安裝 |

完整來源、雜湊與星數口徑見 [總覽](README.md)及 [sources.json](sources.json)

## 它實際提供什麼

目前固定版本的技能入口列出 119 項 UX guidelines、22 個 stacks、色彩、字型、風格、產品類型、圖表、圖示及動態等可搜尋資料。資料是本機 CSV／JSON, Python 工具透過搜尋與推理規則選出建議。這些數字描述的是上游資料庫範圍, 不代表每一項都適用我們的產品。[原文與工作流程](vendor/ui-ux-pro-max/SKILL.md)

檢查方向有優先順序：無障礙與互動先於風格、字型、動畫等選擇。既有元件的單一問題用明確 domain 查詢, 已知框架再查 stack；只有新頁面或整體方向需要時才產 design system。它也要求檢查結果是否對題, 搜不到時不應硬說已找到依據。[詳細規則](vendor/ui-ux-pro-max/references/quick-reference.md)

它的強項是能把「覺得怪」拆成可以驗證的問題, 例如：關閉側欄後焦點去哪裡、錯誤是否接近欄位、拖曳是否有其他操作方式、狀態是否只靠顏色、手機操作是否被固定工具列遮住。它不會單靠讀這份規則就知道我們現在的 DOM、畫面、真實任務或角色狀態

## 適合怎麼用在 TaskTracker

| 情境 | 建議查核重點 |
| --- | --- |
| 首頁小待辦／交辦 | 輸入名稱與按鈕語意、送出成功／失敗、收件人是否清楚、誤操作與退回回饋 |
| 轉正式卡片側欄 | 必填 Project、錯誤定位、鍵盤焦點、關閉保護、重新載入是否覆蓋草稿 |
| 任務清單／看板 | 篩選條件辨識、密度、排序與狀態文字、拖曳替代操作、窄螢幕捲動 |
| Reader／撤權狀態 | 唯讀是否可理解、不可操作時的原因、刷新前後是否誤導；實際授權仍由 Laravel 測試驗證 |
| 工時填報 | 日期上下文、本人紀錄修改、錯誤提示與輸入保存 |

上表是審查計畫, 不是已發現這些頁面有問題。Vue stack 是相關參考, 不代表此技能有完整 Vuetify 4 元件契約；Laravel stack 也不等於已審過後端 ACL。框架與套件版本仍以我們 lockfile／官方文件為準

## 本機使用方式

從 TaskTracker 根目錄執行。原文有 `${CLAUDE_PLUGIN_ROOT}` 的 Claude 插件範例, 本副本沒有安裝成該插件, 因此改用這裡的明確檔案位置：

```sh
PYTHONDONTWRITEBYTECODE=1 python3 skills/vendor/ui-ux-pro-max/scripts/search.py \
  'focus not obscured' --domain ux --json

PYTHONDONTWRITEBYTECODE=1 python3 skills/vendor/ui-ux-pro-max/scripts/search.py \
  'form validation' --stack vue --json
```

這兩個命令本輪已驗證能讀取本機資料, 不會替產品改碼。Python 3 即可, 這個查詢路徑不需額外 pip 套件。若未來用 `--design-system --persist`, 會產生設計文件；評估既有 UI 時先不要加 persist 或 force, 避免把工具建議直接當成新規格

日後可以這樣下指令：

> 讀取 skills/vendor/ui-ux-pro-max/SKILL.md, 以 TaskTracker 的 Vue＋Vuetify 與既有產品決策為前提, 審查首頁小待辦與轉卡側欄。先只回報問題, 不改碼；依重現步驟與實際畫面列證據, 區分操作障礙、無障礙與風格建議。只有規則而沒有畫面證據的項目列為待驗證

## 限制與融合建議

它是建議資料庫, 不是可用性測試、無障礙認證或自動 UI 掃描器。查到某個風格／產品分類不代表應照單全收；一般 touch 尺寸、字體或對比門檻也需依控制項與規範情境確認, 不能把一個檢查表數值當成全面合規結論

對 TaskTracker, 最有價值的是「把問題說清楚並提供最小修正依據」。若融合 Taste, 可用 Pro Max 定義行為與可讀性檢查, 用 Taste 的 app-screen 觀察補充整體層級與資料呈現。兩者都不能覆蓋我們已選定的專案入口、權限原則或使用者刻意取消的功能
