# 效能卡實作：query client與看板接線

日期：2026-11-12。使用者要求完成效能優化並同步前後端／現行規範，優先TT-237～239。後續卡依同一實作紀錄續接；只完成某階段不能結案全部。

## TT-237：HTTP傳輸

集中於frontend/src/api/http.js；既有POST adapter共用相同傳輸，新查詢以GET及Laravel陣列參數。維持Cookie、mutation CSRF、419僅一次重送相同requestId／revision；不自動重試寫入的網路錯誤。依Workspace generation丟棄舊回應，切換取消舊scope請求；caller取消、timeout、網路、錯誤JSON與HTTP／DomainError分開呈現。GET不先打csrf-cookie，不附無關bootstrap。

Node傳輸測試覆蓋上述協定與錯誤，不將fetch stub當業務整合證據。正式接線與隔離Laravel／瀏覽器範圍測試由後續看板階段驗證；尚未完成前卡保持進行中。Vite build通過（原初始MDI資源問題仍由TT-183追蹤）。

## 頁面資料存取目標

看板列表只需目前Project屬性／permission及看板摘要分頁，含動態去重卡數；不取issues全文、members全集、工時與Todo。指定看板以目前Project／Board條件取得有界卡片摘要；編輯、活動與選項另按需取得。事件／備援刷新只作用該scope，草稿保留既有revision，撤權重驗。

目前舊列表／其他頁面仍待依卡遷移；不得把新增API或傳輸層當成已解除getWorkspace／getTodos。
