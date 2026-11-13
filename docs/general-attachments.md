# 一般私有附件（TT-295）

任務完整頁的「任務資訊 → 附件」可上傳及下載PDF／ZIP, 只在開啟該分頁時讀目前Issue的metadata, 每頁20筆, 不預載其他任務或整個Workspace

## 格式與配額

圖片的PNG／JPEG／WebP及25MP限制不變, 原Markdown上傳只接受圖片。一般附件另限PDF／ZIP, 預設每件10 MiB、每卡10件、工作區256 MiB；同時仍受原本每卡20件與工作區1 GiB總配額約束, pending及刪除保留期間仍占用容量

一般檔案不能當成Markdown圖片引用, 也不提供伺服器解壓、內容執行或預覽。PDF依Josh決定先只下載, ZIP同樣只下載

## 安全掃描政策

本版採download-only-structural政策：後端檢查實際MIME與副檔名, PDF檔頭與結尾, ZIP以只讀及一致性檢查開啟, 最多1000個目錄項目、宣告解壓總大小100 MiB、單項壓縮比100倍。拒絕加密、symlink與越界路徑, 不解壓entry內容

這是格式／資源濫用檢查, 沒有病毒掃描引擎, 不宣稱檔案無惡意內容, 也不聲稱已驗證PDF內部物件語法。PDF與ZIP下載一律attachment disposition、nosniff、no-store及sandbox, 不因未帶download=true而內嵌。若未來要提供預覽或宣稱掃毒通過, 必須另加隔離掃描與隔離預覽用例, 不能直接放寬這份政策

ZIP只讀及目錄欄位依PHP官方文件：[open](https://www.php.net/manual/en/ziparchive.open.php)、[statIndex](https://www.php.net/manual/en/ziparchive.statindex.php)

## API與失敗

Session與PAT沿用同一套上傳、綁定、授權、版本及requestId。OpenAPI 0.18.0擴充Attachment MIME、下載content與limits.general, 原mimeTypes仍只列圖片格式, 避免Markdown上傳誤收PDF

上傳成功後才用當初選檔時的Issue revision綁定；版本衝突保留pending與檔案, 使用者更新任務後可明確依目前版本重試。重試上傳沿用同一requestId, 成功且綁定後才清空草稿。未綁定的取消檔由原本24小時TTL清理, 不立即刪除其他資料
