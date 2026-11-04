# Cognitive Walkthrough：檢查使用者在哪一步卡住

調研日期：2026-11-04（Asia/Taipei）。這是評估介紹, 沒有執行 TaskTracker 新一輪走查。

**推薦用途：拿一個具體目標, 逐步檢查使用者能不能發現入口、理解動作並辨識結果。** 這比只看畫面是否漂亮更貼近「流程卡卡、找不到東西」的問題。

## 來源與版本

- 上游：[45ck/hci-review-skill 的 cognitive-walkthrough](https://github.com/45ck/hci-review-skill/tree/970a57d1a2084642252963bfc9e00352bbc39e59/.agents/skills/cognitive-walkthrough)
- Skill 名稱：`cognitive-walkthrough`；repo 星數快照 **19**, 不是此 skill 的使用成效或全站排名。
- 固定 commit：`970a57d1a2084642252963bfc9e00352bbc39e59`；API 顯示 repo 最後推送 `2026-04-11T01:59:25Z`。
- 本機：[原始 SKILL.md](vendor/cognitive-walkthrough/SKILL.md)／[MIT LICENSE](vendor/cognitive-walkthrough/LICENSE)／[來源與逐檔校驗](ux-research-sources.json)。
- 保存該 skill 子目錄的 SKILL、`agents/openai.yaml`, 加 repo 根 LICENSE；不複製整個 HCI pack, 不註冊 `.agents/skills`, 不安裝全域。

## 它實際怎麼做

上游要求每個步驟檢查四件事：使用者會形成正確目標嗎？注意得到正確動作嗎？看得懂動作與目標的關係嗎？做完後的回饋能理解嗎？每步留下目標、可見提示、可能動作、四題判斷、風險與建議；另找隱藏前提、誤導標籤、死路與復原入口。

這些是**上游提供的評估程序**。它不是一套自動操作瀏覽器的程式, 沒有自帶測試帳號、Chrome driver、量測腳本或真人資料。輸出預設在 `docs/hci/cognitive-walkthrough.md`, 可以由使用者指定其他位置。

## 對 TaskTracker 的價值

例如目標是「剛交接給我的小事變複雜了, 想安排到團隊的正式工作裡」：

| 要問的事 | 值得觀察的介面訊號 |
| --- | --- |
| 知道去哪裡找這件事嗎？ | 首頁／通知／目前專案的差異是否可辨識 |
| 能發現轉換入口嗎？ | 「更多」底下的轉卡入口是否容易被忽略 |
| 理解轉換後會發生什麼嗎？ | 是否知道要選專案、來源待辦會保留, 不會變成兩件獨立工作 |
| 知道操作完成了嗎？ | 保存後的位置、正式卡片編號、來源狀態是否形成完整回饋 |

這只是**建議走查題目**, 不代表上述位置現在有 bug。尤其首頁剛改為專案／待辦分區, 應分別測「找專案」與「找小待辦」, 而不是認定新布局已同時解決兩者。

## 與現有兩個 UI／UX skill 的分工

Pro Max 可補鍵盤、表單與可讀性規則；Taste 可補畫面層級與密度。Cognitive Walkthrough 則把檢查串成一條有起點、終點的任務：**入口 → 決策 → 操作 → 回饋 → 返回／中斷後繼續**。

畫面雜亂應記成「哪些資訊讓這個任務的動作難以被發現」, 不直接變成「所有頁面移除某個元件」。同樣, 多一步確認有時能避免誤操作, 不能只用點擊次數判好壞。

## 限制與採用建議

- 原 skill 很精簡, 沒有完整證據分級、嚴重度量表或研究招募設計；這些要由本專案的 [驗證方案](../docs/可用性驗證方案-2026-11-04.md) 補上。
- `disable-model-invocation: true` 與 `agents/openai.yaml` 的 `allow_implicit_invocation: false` 表示上游設定偏向明確呼叫。不能把它描述成放入目錄就會自主持續巡檢。
- `$ARGUMENTS` 是上游呼叫寫法；在本專案可明確指定任務、角色、網址與起始狀態。不是安裝 PHP／Vue 套件, 也不需要照搬整個上游 agent 環境。
- 即使 AI 用真實瀏覽器完成任務, 「第一次使用者會不會猶豫」仍是推論。AI 已知道程式與功能位置, 不能宣稱是完全陌生的新手。
- 原文／MIT 授權與結構已核對, 沒有取得這個 skill 能提高真實使用者成功率的獨立證據；repo 星數也不能提供這種保證。

建議先選 3–4 個目前真的要做的任務走查, 每個疑點附可見畫面與重現路徑, 再交由真人試用驗證。不要直接以模擬 persona 的感受、想像的停頓秒數或整體分數決定改版。
