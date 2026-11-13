# TT-287 私有 Markdown 圖片

Issue 描述與描述編輯預覽共用同一個安全 renderer, 只識別 `![替代文字](attachment:UUID)`。留言與待辦尚未接上附件 context, 不因這次調整自動開放圖片

## 分工與限制

- Markdown 先以既有 allowlist 清理, 使用者 HTML 保留為文字；可信的附件占位由 Vue 元件接管, 使用者不能提供 img src、事件或私有路徑
- 讀取只走同源 Session content API, 使用既有 Workspace header／generation／取消機制。外部 HTTP 圖片只產安全連結, data／javascript／SVG／HTML 不會自動載入
- 每份 Markdown 最多 20 個圖片參照, 全頁最多兩個圖片串流, 滾近畫面才載入。伺服器每張 10 MiB／2500 萬像素上限維持, 前端也驗 MIME 與 Content-Length；不把整個 Workspace 或附件清單載入來解析圖片
- 顯示檔名、大小、尺寸與失效替代。圖片寬度不超過容器, 預覽最高 360px、窄螢幕 260px；原圖放大採原生 dialog, 有鍵盤焦點限制、Escape 與焦點還原
- 放大與下載都重新請求, 由伺服器檢查當下資格。沒有 signed URL、全域圖片快取或把物件路徑存回描述；元件卸載、工作區／權限變更會取消串流並釋放 blob
- 已下載到瀏覽器的內容無法追溯收回；重新操作不能用舊 blob 略過授權。這次不做縮圖服務、上傳或草稿綁定, 後兩項接 TT-282

## 資料存取審查

1. 呼叫端只需單張圖片 bytes、檔名與長度, 尺寸由圖片解碼取得
2. 不是列表介面；每份內容 20 個參照、全頁兩個串流, 不新增無界 metadata 列表
3. 內容端點沿用 TT-191 的 Project／Workspace／附件 ID 查詢, 不下載資料集再前端篩選
4. 不呼叫 getWorkspace 或 getTodos, 不加輪詢
5. 只有出現在描述／開啟預覽且接近 viewport 的圖片才讀取, 不載入歷史／留言／工時
6. 每張圖片一次授權讀取, 放大／下載各重驗一次；資料量隨顯示上限成長, 不隨 Workspace 總卡數成長。未量測正式環境 RTT 或 p95
7. 真 HTTP 測試驗無 context／不安全參照不產生圖片, 外部追蹤零請求、20 個參照上限、活躍附件跨租戶拒絕與刪除後重新操作拒絕
8. 待 Josh 核准候選畫面, 視覺門檻目前未完成, 不宣稱整體驗收通過

## 驗證與視覺狀態

隔離 MySQL／Laravel `tasktracker-codex-perf`, backend 13089, Chrome＋Vite 5291。`frontend/tools/attachment-view-check.js` 使用真實註冊、圖片上傳／綁定／串流／刪除 API, 以正式 MarkdownContent／MarkdownEditor 元件做三尺寸 harness, 不是整頁路由驗收

- 1440／768／390 寬度, 圖片與放大視窗不橫向溢位；Escape、Tab、焦點還原、授權下載、失效替代、工作區切換釋放通過
- 既有 markdown-check 回歸通過, 含待辦多行／格式／Undo／IME、預覽、重載／編輯／轉卡、卡片／留言與 HTML／URL／外部圖片負例
- build 通過。修前以 `4f8df80` 中的四個 Markdown／tabs 檔案做唯讀對照, 不切換共享工作樹
- 候選與測試證據在忽略的 `artifacts/tt287/`, 沒有將圖片或憑證加入 Git
- Josh 尚未核准圖片呈現與放大視窗, 所以卡片提交後進 review；正式核准／固定基準比較留待完成
