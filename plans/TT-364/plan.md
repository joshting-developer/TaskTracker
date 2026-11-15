# TT-364 管理後台

先做獨立管理登入及只讀統計API, 再做/admin畫面。管理者不沿用Workspace Owner, 單一帳號由環境提供名稱與密碼雜湊, 未配置時拒絕登入

## 階段

1. 固定統計定義、認證與資料範圍, 完成後端及隔離測試
2. 獨立管理頁、期間切換、趨勢與有界工作區列表, 真實瀏覽器及視覺驗證
3. 與TT-365／366整批部署, 驗正式登入與資料, 不在正式站建立測試資料

## 驗證

讀共用工程、Laravel Architecture／Patterns及資料存取、DI、PHPDoc規範。前端依矩陣讀Vue、UI／UX、Design Taste及測試環境Skill

認證測未配置／錯誤密碼／一般Owner與PAT拒絕、Session輪替／過期／換密碼撤銷、CSRF與限流。統計測零值、期間邊界、最多30日、游標20筆、無內文及無N+1, 使用memory SQLite和隔離MySQL
