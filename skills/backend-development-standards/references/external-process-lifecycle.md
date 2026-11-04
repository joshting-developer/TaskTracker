# 外部程序生命週期

適用於 Laravel command、Symfony Process、worker、browser、converter 或其他由測試／程式啟動的外部程序。

## 所有權

- 啟動程序的 scope 必須負責關閉；跨 method 傳遞時明確標示 owner。
- 設定合理 timeout，區分啟動失敗、執行失敗、逾時與 cleanup 失敗。
- `finally` 或 teardown 必須處理仍在執行的 process、暫存檔與鎖。
- 不要以全域 `pkill` 或廣泛刪檔當正常 cleanup；只清理本次建立且可識別的資源。

## 測試

- 測試 happy path、非零 exit、timeout 與中途 exception。
- assertion 應涵蓋 exit code、穩定輸出 contract 與必要副作用。
- 測試結束後確認沒有 orphan process；若 runner 本身異常，先修復 runner health，再判定產品失敗。
