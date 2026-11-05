# Markdown速記與共用編輯

日期：2026-11-05（Asia/Taipei）。使用者已選小待辦採「一整塊Markdown速記」，不另外輸入標題；卡片描述及留言共用同一編輯方式。

## 寫作與呈現

- 小待辦直接輸入多行，Enter換行，按加入待辦／交辦才保存。首行產生180字內的清單／通知摘要，完整原稿另存，不因摘要截短而丟失內容。
- 共用MarkdownEditor：原生textarea、粗體／斜體／清單／核取清單／連結／程式碼工具、編輯／預覽、字數提示。Ctrl／Cmd+B、I套格式；最近一次格式操作可復原／重做。中文組字期間不攔截格式快捷鍵。
- Markdown原稿與呈現分開：編輯時看原始語法，預覽及閱讀時顯示格式。支援GFM表格／刪除線／核取清單，核取方塊唯讀，不取代Todo完成狀態或Issue子任務。
- 待辦全文及卡片描述上限6000字，留言2000字；原有草稿、離開保護、版本衝突、重試與授權沿用。
- 標題保持簡短純文字；待辦轉卡預設帶摘要及完整原稿，使用者仍可在正式表單調整。

## 儲存與相容

`todos.content`為可空TEXT新欄位。新速記以此保存完整Markdown，title只供摘要，note留舊格式相容；舊title／note資料不回填、不清空。前端編輯舊資料時將標題及說明組成一塊內容，保存後才寫content。

新API接受createTodo／updateTodo.content，舊title／note輸入仍有效；只改舊摘要不清除已存在的完整原稿。舊note輸入則依明確提交的標題／說明重建內容。原稿沿用原交易、revision與requestId，不新增繞過參與者權限的入口。

卡片描述與留言改用保留空白的驗證，避免一般trim破壞縮排程式碼、硬換行或首尾原稿；長度與必要內容仍由後端驗證。後端不保存預覽HTML。

新增migration的down會先把content留在歷史相容欄，再移除SQL欄；重新up搬回正式欄位並清理暫存。舊版程式不會顯示新原稿，但回退後再升級仍保留全文。完整關聯儲存回退／回填也測試原稿不遺失；正常業務使用明確SQL欄位。

## 套件與安全呈現

採Marked 18.1.3解析Markdown及DOMPurify 3.4.17清理輸出，版本鎖在package-lock。Marked官方明確說明不自行清理HTML，因此所有閱讀／預覽統一經清理入口。[Marked使用與安全文件](https://marked.js.org/)、[DOMPurify官方文件](https://github.com/cure53/DOMPurify)。

- 原始HTML顯示為文字，不接受任意HTML／事件／SVG／iframe。
- 輸出只允許文字格式、清單、唯讀checkbox、程式碼、表格及安全連結；javascript／data等危險連結不啟用。
- 圖片語法先顯示連結，不自動下載外部圖片；不加入附件上傳。外部連結加noopener／noreferrer。
- 格式工具只改原稿，不自動寫入；預覽與已保存內容共用MarkdownContent，不各自實作HTML入口。

CKEditor也有Markdown輸出能力；本輪依速記及既有表單流程採原稿＋格式工具／預覽，保留未來換成其他編輯器的資料彈性。[CKEditor Markdown文件](https://ckeditor.com/docs/ckeditor5/latest/features/markdown.html)。

加入依賴時npm audit發現既有Vite7.3.6告警，本輪同步更新同版本線7.3.7，依賴稽核回到0項；不執行force upgrade或更換工具鏈。

## 驗證與進度

- 後端42項／839個斷言通過，全部使用隔離記憶體SQLite；新增全文保存、長首行摘要、舊摘要寫入、版本／重試／隱私、轉卡預設、縮排與硬換行、欄位及完整儲存往返遷移。
- 專用真實API瀏覽器檢查已通過：格式工具／復原／重做／中文組字、Enter換行、待辦多行內容保存／重載／修改／轉卡、描述及留言、安全HTML／URL、沒有自動外部圖片請求，1440／768／390px無整頁溢位。
- 完整browser-check、UIUX及F01–F04可用性回歸通過，包含交辦／退回／通知、草稿／背景更新／版本衝突、卡片／看板／ACL、手機入口。production build及前後端Docker映像重建通過。
- 已人工檢視1440／768／390px速記與詳細頁、390px編輯／預覽截圖；保留現有TaskTracker配色、產品及導覽。工具列可換行、按鈕44px、長程式碼／表格在內容區捲動，無整頁溢位。此為兩個UI／UX skill的單元件／app-screen檢查，沒有宣稱上游inspector全部規則通過，也不代表真人可用性研究。
- 測試使用獨立API／暫存SQLite及假帳號，不在業務資料庫寫測試內容。本機先停止寫入，備份到`/data/backups/before-markdown-final-20261105.sqlite`，在副本預演後升級；前後業務DTO、帳號及records歷史雜湊一致，既有3筆待辦保留，SQLite外鍵／integrity均正常，沒有Seed reset。備份只留本機volume，不提交。
- 本機frontend／backend／Redis／WebSocket健康，queue運行，Markdown來源編譯與API健康檢查200；前端入口仍為`http://127.0.0.1:5174`。

驗證入口：`frontend/tools/markdown-check.js`（需E2E_API_URL）、既有browser-check／uiux-check及Laravel Feature測試。暫存日誌以`/tmp/tasktracker-markdown-`為前綴；畫面在忽略的`frontend/artifacts/markdown/`，不提交產物或資料庫。
