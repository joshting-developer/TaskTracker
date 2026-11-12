# 開發者文件交付（TT-252）

2026-11-12。對外匿名文件入口為 `/api/public/docs`，沿Laravel單一部署邊界提供，不新增常駐服務。位於API前綴可沿既有反向代理／Vite代理閱讀，不需登入或依賴SPA hash路由。這是公開文件頁，不將產品頁面改成Blade或Inertia。

## 已交付入口

| 路徑 | 內容 |
| --- | --- |
| /api/public/docs | 快速開始與導覽 |
| /api/public/docs/authentication | PAT建立／Scope／Workspace／期限撤銷／權限交集 |
| /api/public/docs/examples | cURL與Python可執行完整流程 |
| /api/public/docs/agents | 工具中立指南、ID／identifier、分頁、衝突與接手 |
| /api/public/docs/contract | 既有HTTP契約，同一Markdown來源 |
| 上述固定slug＋.md（首頁index.md） | 可被Agent讀取的Markdown原文 |
| /api/public/docs/examples.py、examples.sh | 純HTTP範例下載 |
| /api/public/openapi.json | 唯一機器規格，info.version0.4.1，Link describedby與x-developer-documentation-url指出入口 |

頁面／原文／範例都不收PAT或Cookie，不提供Try-it-out，無腳本或永久儲存。讀文件不授予業務API權限或外部Agent HTTP能力。OpenAPI只列已實作功能，刪除／封存／MCP／編排仍不在首版能力。

## 唯一來源與安全

`backend/resources/developer-docs/*.md`提供固定內容，examples以同份workflow.py／workflow.sh嵌入並下載，不另抄一份範例。contract重用public-api-contract.md。DeveloperDocumentation服務只允許固定slug，路徑不由HTTP自由拼接；Controller輸出匿名HTML／原文與固定CSS，沒有帳號／租戶查詢。Markdown原始HTML strip、unsafe links拒絕；HTML採CSP default-src none、僅self CSS、禁止frame／form／base，沒有token表單或script。

範例使用環境BASE_URL／TASKTRACKER_TOKEN／PROJECT_ID與人造標題。必須CONFIRM_WRITE=1才執行實際建卡／更新／留言；Token不打印或寫暫存。cURL版只將非秘密API結果放私有mktemp目錄並trap清理，Python版無CookieJar與原始碼服務匯入。兩者皆驗同key不重複，先查狀態、建卡、卡號精準定位、單卡最新revision、PATCH closed、留言及最後GET，409時停止而非盲目覆蓋。

## 閱讀與UI／UX

採專案本地UI／UX兩份skill；保留TaskTracker名稱與品牌色，以固定5項文件導覽、單欄正文、原文／規格入口呈現，不新增情緒標語、KPI或裝飾。桌面側欄不擋內容，手機變折行導覽；正文字體16px，程式碼14px可在自身區塊橫捲，頁面不水平溢出。提供lang、viewport、單一h1、aria-current、鍵盤skip及可見focus，不依hover才能閱讀。

真實Chrome五頁×1440／768／390尺寸、Markdown原文、無script／form／Cookie及skip鍵盤流程通過；檢視1440首頁與390範例截圖，調整巢狀code縮小問題。截圖只在暫存目錄，不含真實PAT，不提交Git。

## 驗證與後續

- PHP匿名安全測試1通過71 assertions，含5頁HTML／.md、下載、CSS、未知slug／路徑拒絕、零DB查詢及不建立Session；與既有契約合跑8通過617 assertions。
- 實際從隔離公開URL下載Python／Shell到新暫存目錄執行，不引用業務原始碼：兩者建卡一次、PATCH closed、留言一次及重播驗證皆PASS，最終revision3，測後撤銷Token與登出。
- OpenAPI固定官方結構與Pint通過；無新增資料表、業務SQL或輪詢，不以讀文件開整個Workspace。
- 最終完整memory131通過／51原生類略過5806 assertions，本機同部署已載入；無migration／seed／reset主庫。TT-253負責CI防漂移，TT-254負責更嚴格的獨立文件導向驗收；本卡不代替兩者結案。
