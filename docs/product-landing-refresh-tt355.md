# TT-355 產品介紹頁

## 頁面分法

Josh本次要求直接執行看效果, 取代原卡先提案等待確認的步驟

| 路徑 | 介紹範圍 |
| --- | --- |
| `/` | 專案管理平台總覽、四個面向、工作情境輪播 |
| `/features/tasks/` | 任務列表、主題看板、Epic／子任務與關聯 |
| `/features/collaboration/` | 紙條速記、交辦與回覆、轉成正式任務 |
| `/features/time/` | 專案投入、成員明細、每日趨勢 |
| `/features/integrations/` | 個人Token、有效權限交集、REST與OpenAPI |

參考[Wazuh首頁](https://wazuh.com/)按面向分組、每組列功能的結構, 未沿用品牌、文案或客戶評價

首頁各面向列三個重點, 輪播一次呈現一個功能情境。所有介面片段均標示「功能示意 · 合成資料」, 不是正式客戶資料或實機截圖

## 實作界線

- 五頁都是可直接閱讀的HTML, 共用CSS與小型JavaScript, 沒有為介紹頁載入Vue工作區
- 保留首頁舊分享連結與登入提示轉址；已登入的人可直接閱讀功能分頁, 再回工作區
- 各頁有獨立標題、description與canonical, build產生五頁sitemap, 沒設VITE_SITE_ORIGIN時移除需要絕對網址的metadata
- 正式nginx既有`$uri/index.html`可服務四個分頁, 不需要把任意路徑改回首頁
- 不新增業務查詢、列表或輪詢, 只保留單次既有Session身分檢查, 不讀Workspace／Project／任務資料
- 繁中先沿用既有語系；TT-308尚未實作的多語系不在此卡宣稱提供

## 設計審查

使用共用工程、Ponytail lite、design-taste-frontend、ui-ux-pro-max及consumer選出的Vue規範

ROUND 1

- Seen in one second：專案管理平台, 可由四個面向繼續閱讀
- Idea：把同一張卡的規劃、交辦與工時放進可翻閱的工作情境
- Live thing：可用按鈕或鍵盤切換三個情境, 不自動播放
- Layout words：hero=文字與專案片段 / features=四欄面向 / scenarios=單一工作情境 / start=整塊深色行動區
- Scores：design 6.5 · usability 8 · creativity 6.5 · content 8 → overall 7.1
- Caps applied：沒有照片, 不因為移除裝飾就給高分；目前仍是以產品資訊為主的介紹頁
- 修整：白底導覽繼承白字已修正；看板欄名被輪播標題樣式放大已修正；桌面導覽／頁尾已對齊正文邊界

ROUND 2

- Seen in one second：平台類型與主要操作清楚, 導覽在手機也讀得到
- Live thing：滑鼠、Enter／Space、區域方向鍵與無JS閱讀皆已驗
- 剩餘限制：目前用合成介面示意, 不是產品操作影片；沒有虛構客戶、評價或成效數字

設計工具原始結果4 FAIL／5 WARN保留於本機artifacts, 四項例外逐一記錄：紫色是既有品牌要求；不為圖片數塞無關照片；不為動畫數增加裝飾性動態；1/3是輪播位置, 不是無意義小標。套用明確例外後0 FAIL／5 WARN, 不代表工具無條件通過

其餘WARN：淺暖灰底沿用本次色面；介面片段有自己的小字尺寸；沒有合適影片；工具的邊界／英文標題偵測不完全適用中文字與不同導覽長度, 已以三尺寸實圖核對。按鈕邊線保留作操作辨識, 不是裝飾分隔線

## 驗證

- `landing-features-check.js`：五頁×1440／768／390、導覽對比、唯一metadata、功能連結、鍵盤輪播、無JS與子頁登入狀態
- `guest-pages-check.js`開發與正式build：真實隔離Laravel註冊／登入／分享連結、五頁sitemap與canonical
- `npm run test:smoke`：路由、API client、composables、build、隔離註冊與側邊欄
- 共用視覺回歸新增四頁, 93張擴為105張, 來源指紋加入feature HTML與Vite設定, 比較器不變
- 私有／合成截圖留artifacts, 不提交Git；只提交核准紀錄與檔案雜湊

這批先提交供Josh看效果, 正式站不因push main自動更新, 未打tag部署
