# TT-287 私有附件 Markdown 顯示

- 預估 240 分鐘, 開工前已讀回；只領本卡
- Markdown 僅辨識 attachment:UUID, 沒有 Project context 時保持文字, 不自動抓外部圖片
- renderer 產清理後 DOM, Vue 將可信占位轉成 AttachmentImage；圖片元件負責 lazy、取消、釋放 blob、錯誤、放大／下載
- HTTP 層維持 Workspace generation 與當下授權, 併發上限 2、每份內容最多 20 張；僅接 Issue 描述與編輯預覽
- 真實隔離 Laravel／MySQL＋三尺寸瀏覽器, 含外部追蹤／XSS／跨租戶與撤權；畫面待 Josh 核准後才結案
