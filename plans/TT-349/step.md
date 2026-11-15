# 執行紀錄

- 已重新讀AGENTS與共用工程、Ponytail lite、資料存取及Sites MCP／Identity規範
- 正式專案363張卡已分頁盤點, 本批先接GPT唯讀工具, 不搶他人卡或重啟deferred卡
- 無新紙條；已claim TT-349, 僅此卡inprogress。先用合成HTTP回應, 不傳正式PAT
- 實作8種唯讀MCP工具及2026-07-28 discover／工具列舉, 另支援initialize。固定origin、owner可信標頭、每次REST身分／空間／read scope重驗, 不跨請求cache
- 單頁最多20筆, OFFSET最多10000, 16KiB工具輸入與256KiB上游回應, 10秒逾時及redirect拒絕。卡號精準定位再取單卡, 不做Workspace全集或自動翻頁
- 32項合成及契約測試通過, 包含8工具、實際過濾／分頁、401／403／404／409／429、逾時、惡意正文、Secret遮蔽、錯誤無重試及Worker ExecutionContext相容；build後匯入產物工具列舉也通過
- 初查發現直接把handle作Worker.fetch會把平台ExecutionContext當transport, 已改明確入口包裝並補實際三參數回歸測試
- 共用skill integrity通過；資料存取八項審查見artifacts/tt349/data-access-review.md。不把合成耗時當正式p95, 實際發布與連線另TT-350
- 收到Josh兩張新紙條：介紹頁也需英文及共用語系偏好, 評估更顯眼切換入口, 與管理後台同批發布。先記錄, 本卡階段提交後建對應卡片
