# API Contract 與併發資料

## Contract matrix

實作前把每個 action 寫成可檢查的矩陣：

| 欄位 | 說明 |
| --- | --- |
| action | 使用者或 API 動作 |
| input | 必要欄位與格式 |
| trusted source | 後端重新取得的身分、權限與資料 |
| success | HTTP status、response schema、DB 副作用 |
| failure | 穩定錯誤碼／訊息與不得發生的副作用 |
| concurrency | GET 後資料改變時的處理 |
| evidence | Unit、Feature 或 runtime 驗證 |

不要用 issue 文字代替 contract，也不要把「前端目前會送什麼」當成後端可信條件。

## 對外訊息與診斷資訊

- 對外回應使用穩定、可翻譯且不洩漏內部結構的 error code／message。
- exception、SQL、路徑、command output、stack trace 與敏感參數只進受控 log。
- 測試分開驗證 external contract 與 diagnostic log，不讓兩者互相取代。

## Stale data

- 寫入前以可信任 ID 重查目標資料、狀態與逐筆權限。
- 明確定義資料被刪除、移動、停用、權限改變或版本落後時，是拒絕、跳過或部分成功。
- 若允許部分成功，response 必須能對應每筆輸入的結果；不得讓前端依畫面舊資料推測。
- transaction 邊界應符合 contract：整批原子操作與逐筆容錯不能同時含糊存在。
