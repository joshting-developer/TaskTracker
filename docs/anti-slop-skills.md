> 2026-11-12更新：Skill正文已集中Side Project共用庫，舊專案路徑為相容連結；本文原存放位置視為歷史。載入與優先順序依根AGENTS「Skill來源與適用範圍」，產品設定見project-engineering-bindings.md。Ponytail預設lite，不裝全域hook／plugin。

# Hallmark與Unslop配置

日期：2026-11-11。依使用者要求安裝於本專案，沒有全域安裝、installer／hook或新增產品依賴。

| Skill | 入口／來源 | 固定版本／授權 |
| --- | --- | --- |
| Hallmark | [.agents入口](../.agents/skills/hallmark/SKILL.md)／[Nutlope/hallmark](https://github.com/Nutlope/hallmark) | `7e213cb6feb4ef82733bd66a0600b2e54ca0994c`／MIT |
| Unslop | [.agents入口](../.agents/skills/unslop/SKILL.md)／[heydamir/unslop](https://github.com/heydamir/unslop) | `97daa3eb365670ea3f87ed85356cbfcee18105a5`／CC BY 4.0 |

選擇UI導向的heydamir/unslop，不使用其他同名文章編輯skill。Unslop作者為[Damir Sokolovsky（@heydamir）](https://heydamir.design/unslop)，保留原文署名、LICENSE與CITATION。全部原文／references／scripts及必要素材固定版本，逐檔Git blob／SHA-256／大小／URL見[來源manifest](../skills/anti-slop-sources.json)。Hallmark 188檔、Unslop 16檔；入口為相對symlink，不重複複製。

## 適用範圍

- 本輪Hallmark只安裝；依使用者指定，以Unslop掃描TaskTracker既有介面。後續只有實際使用Hallmark時才執行其對應flow，不因安裝就改成新品牌／landing page。
- 本專案是app screen，沿用Vue／Vuetify、現有功能與段落順序、permission／scope及產品決策。使用者已選紫色品牌、可用完整寬度、首頁只留小待辦、列表與看板分開，這些不因風格建議改寫。
- Unslop負責去AI化的主要診斷；既有Pro Max補可用性、Taste補app-screen視覺檢視、Vue skill補程式結構。不重複跑四套整站重設計或用警告數決定改色。
- 有色標記若代表專案識別／卡片狀態／數據系列，屬業務資訊；不能當裝飾直接刪除。使用者輸入的描述／Markdown／emoji不屬於清理系統文案的範圍。
- Hallmark的網站hero字數／置中限寬、Unslop的方向／token是上游建議，不授權縮小資料工作區、移除鍵盤焦點、強制替換產品Logo或建立不需要的hero／圖像／動畫。
- 不探查Together等服務的秘密，不上傳私有畫面到生成服務，不為skill裝新工具；本輪瀏覽器只接獨立MySQL人造seed。

## 掃描與確認

依Unslop：先讀signs.md，檢查真實登入畫面與程式，標明可見問題、未命中的舊CSS與審美偏好；判斷heavy／light／no slop，再提出Product／Character／World／Moments／Signature五行方向。

[Unslop SKILL.md](../skills/vendor/unslop/SKILL.md)的Run it第4步明定：

> Show the audit and the direction to the user and wait for their answer before editing

使用者已明確說直接做時可略過等待；否則本輪先交可檢視的審查與修改清單，再確認方向，安裝與掃描不等於已修改UI。這是skill明文流程，不是另加部署或安全審批。

上游scripts已閱讀後才可執行；目前兩個Unslop腳本只是選用的snapshot／story工具，對已有原始碼的app優先使用專案Playwright登入流程。未要求故事影片，不製作影片、不安裝ffmpeg。

校驗備註：Unslop上游LICENSE保留檔尾空白行；`git diff --check`僅此一項提示，為保持原文與manifest一致不修改授權檔。其餘本輪檔案空白檢查通過。
