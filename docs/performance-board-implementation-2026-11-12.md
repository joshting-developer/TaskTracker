# 效能卡實作：query client與看板接線

日期：2026-11-12。使用者要求完成效能優化並同步前後端／現行規範，優先TT-237～239。後續卡依同一實作紀錄續接；只完成某階段不能結案全部。

## TT-237：HTTP傳輸

集中於frontend/src/api/http.js；既有POST adapter共用相同傳輸，新查詢以GET及Laravel陣列參數。維持Cookie、mutation CSRF、419僅一次重送相同requestId／revision；不自動重試寫入的網路錯誤。依Workspace generation丟棄舊回應，切換取消舊scope請求；caller取消、timeout、網路、錯誤JSON與HTTP／DomainError分開呈現。GET不先打csrf-cookie，不附無關bootstrap。

Node傳輸測試覆蓋上述協定與錯誤，不將fetch stub當業務整合證據。正式接線與隔離Laravel／瀏覽器範圍測試由後續看板階段驗證；尚未完成前卡保持進行中。Vite build通過（原初始MDI資源問題仍由TT-183追蹤）。

## 頁面資料存取目標

看板列表只需目前Project屬性／permission及看板摘要分頁，含動態去重卡數；不取issues全文、members全集、工時與Todo。指定看板以目前Project／Board條件取得有界卡片摘要；編輯、活動與選項另按需取得。事件／備援刷新只作用該scope，草稿保留既有revision，撤權重驗。

目前舊列表／其他頁面仍待依卡遷移；不得把新增API或傳輸層當成已解除getWorkspace／getTodos。


## TT-238／239：看板實際接線

新增BoardReadRepository／Service／Controller及BoardIssueScope；Project資格由當次交易內集中驗證，RecordStore組裝收至Factory，禁止將actor保存為singleton狀態。列表只投影看板摘要，每頁上限100、介面20；指定看板卡片每頁100，以EXISTS展開所選Epic且去重。穩定排序、文字LIKE escaping及不可見404／切換409保留。

BoardWorkspace＋useBoardQueries負責看板route與局部載入，App不再先讀整個Workspace／Todo。編輯需詳細與最新revision，活動tab按需分頁；原有拖曳、泳道、Epic、localStorage偏好與密度沿用。頁面隱藏取消請求，草稿／送出中只重驗資格、不覆蓋內容；事件只重讀當前看板。活動共同composable支援留言／歷史／工時，工時permission在SQL限定本人或團隊。

### 八項資料存取檢查

1. 列表只需id／name／revision／Default／卡數；卡片只需識別、標題、狀態、指派、期限與授權聚合。描述／活動不在摘要。
2. 看板、卡片及成員選項上限100，穩定ID tie-breaker；活動20、最大100。明確收錄最多1000，沿用寫入限制。
3. Project、Board、Epic展開、文字搜尋、卡數與工時SUM在SQL；前端看板互動篩選僅對已取得的當前看板摘要，不載其他Project。
4. 看板route移除getWorkspace/getTodos預設載入；其他route仍需後續卡處理。
5. 單卡描述／關聯在開啟時讀；三種活動獨立分頁，選項按需。
6. SQL查詢不隨單頁卡數產生N+1；卡片增加只增加摘要分頁。備援仍30秒、失敗退避最多120秒，可見性停止；全站版本／jitter另由原卡處理。
7. 測試以107張卡驗100＋7不交集，Project／Board／撤權隔離；大描述不改列表回應，摘要不讀活動table；瀏覽器103卡確認真的請求第2頁並完整呈現。
8. TT-180／214～219等原有P1與DOM／bundle／正式runtime／觀測不因本階段通過而關閉。

### 驗證證據與限制

- Node傳輸協定測試通過；真實Chrome＋隔離MySQL的103卡流程通過（29次API回應、bulkReads=0；含建立fixture後才開始計數）。描述與relatedIssueIds儲存後保留，異動／工時只有開tab才取。
- 既有看板瀏覽器回歸通過1440／768／390尺寸、捲動到底、Epic／泳道與拖曳、密度、雙擊／鍵盤／手機編輯及偏好。
- 原生MySQL驗證COUNT去重及Epic自身20＋子卡30＝50分鐘，新增單卡工時分頁；Reader無工時資格回403且摘要沒有spentMinutes，撤權後404。
- 實際本機API→外部MySQL／Redis拓樸正常讀取各1次：5個看板摘要1019 bytes／2203ms；效能板38卡21494 bytes／2334ms。這是去識別化data重編碼bytes與單次端到端觀察，非原始壓縮傳輸大小、非p95／負載驗收。RTT仍高，後續context／SQL RTT及runtime卡須續作。
- 全頁卡片仍逐頁組成當前看板集合，尚未虛擬化；跨HTTP分頁只檢查總數／去重，不是快照。編輯選項第一頁與全站其餘bulk依賴仍有P1，不以本階段宣稱整體效能驗收。

- 本階段最終完整回歸：預設memory 72 passed／3158 assertions、8 native skips；獨立MySQL 80 passed／3262 assertions；Pint20檔及Vite build通過。build仍有大chunk提示，TT-183繼續追蹤。
