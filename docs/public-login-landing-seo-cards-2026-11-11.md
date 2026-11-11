# 公開登入／介紹頁／SEO建卡對照

日期：2026-11-11。使用者指示：讀取指定commit、先查重再拆卡；**只建卡，不實作功能**。

## 來源與查重

來源：[規劃文件](public-login-landing-seo-plan-2026-11-11.md)，固定commit `24a34f91bbe7822c186742b1384ed992aa856056`。以`git show`讀取該版本，原文件保持不變。依最後確認：`/`是登入入口，`/about`僅公開頁候選；既有登入後Hash SPA保留。部署規劃改為單Fly App／image承載Vue靜態檔與Laravel，Cloudflare保留DNS／CDN／HTTPS，不另用Pages。

透過taskTrackerAI的既有Session API讀取TaskTracker專案目前204張卡，搜尋標題與描述全文並核對驗收內容：

- `/about`、SEO、Sitemap、Robots、訪客TopBar及公開介紹頁沒有同等卡片，A–D需補。
- TT-200是登入外觀／分析入口／版本資訊的P3候選，與訪客路由、TopBar及公開頁驗收不同，未覆蓋它。
- E與TT-155原本的靜態前端部署／快取責任重疊，沿用原卡改成最新的單Fly整合驗收，不再新增另一張E。
- TT-151已有production runtime／image工作，TT-156已有登入／Cookie／CSRF工作；保留工作與狀態，更新成單Fly／同網域規劃，避免後續依舊部署Pages。
- TT-2部署Epic同步最新方向，舊描述及來源作沿革保留。既有Cloudflare Pages方案不再當成現行目標；智慧輪詢、Oracle持久化等工作線沒有在本輪實作。

## 實際卡片

| 文件項目 | 卡片 | 工作 | 處置 | 狀態 |
| --- | --- | --- | --- | --- |
| A | [TT-205](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/62d62e26-8701-4982-80e6-c2a7acd8682b?detail=1) | 設計根目錄登入入口與Hash深層連結相容 | 新增 | 待辦 |
| B | [TT-206](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/d0157418-f9c3-43bd-a8ed-97ebad90961d?detail=1) | 訪客TopBar共用與登入狀態切換 | 新增 | 待辦 |
| C | [TT-207](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/7c2a6bf8-5d83-41e7-ac8b-9981537417e7?detail=1) | 建立可直接讀取HTML的公開產品介紹頁 | 新增 | 待辦 |
| D | [TT-208](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/518d51ee-ccf3-41b8-a7b5-2ecda831cc87?detail=1) | 公開介紹頁SEO與社群分享Metadata | 新增 | 待辦 |
| E | [TT-155](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/df1d7de7-dc93-4215-b1a8-130f7a009736?detail=1) | 單一Fly App的登入／介紹頁／SPA／API整合驗收 | 沿用 | 待辦 |
| F | [TT-209](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/d35c2df8-bdbe-41cf-82da-90269142ffbc?detail=1) | 選配：公開頁搜尋收錄與量測 | 新增 | 暫緩／待確認 |

A–D歸入現有部署Epic TT-2；F為選配／公開後工作，放既有候選Epic TT-5，低優先。沒有額外建立Epic或功能已完成卡。E的TT-155優先級調為normal；TT-151／156的核心部署及安全工作仍保留原優先級和open狀態。

每張新增卡有範圍、驗收、限制、來源commit／連結及穩定追蹤鍵`tasktracker:public-login-seo:<a|b|c|d|f>:24a34f9`。所有新增／沿用卡均由taskTrackerAI負責；沒有期限或虛構工時。TT-155保留原ID與追蹤鍵；以最新規劃置頂並保留原始需求沿革。

先完成產品核心與低成本部署；公開頁開工時先A、B，再C、D、E。網址、公開文案、是否提供返回工作區入口及靜態頁auth bootstrap方案仍於實作前確認。這些待確認內容已寫入卡片，不在建卡時自行實作。

## 驗證結果

- 實際新增5張：4張open、1張deferred。E沿用TT-155，另同步TT-151／156／2，共4張既有規劃更新。
- 逐筆讀回核對新卡與更新卡的標題、描述全文、狀態、優先級及AI指派；A–E全部open，F deferred。
- 來源追蹤鍵再次查重，只有5筆新增項目；卡片總數204→209。其餘200張既有卡的完整讀回內容及revision與操作前一致。
- 只使用AI Developer，不使用Owner，不操作其他Project；沒有修改應用程式、路由、認證、schema、Docker或雲端部署，沒有安裝套件或執行seed。
- 本輪純卡片／文件，檢查來源、對照、本地連結與diff；不跑產品功能測試或將SEO／部署宣稱驗收完成。初次202卡manifest／匯入結果保持歷史快照，未反向覆蓋live狀態。
