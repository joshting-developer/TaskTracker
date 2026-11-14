# TT-328 忘記密碼

## 流程
登入頁提供忘記密碼, 統一回覆不揭露帳號；信件以高熵連結進入重設表單, 需輸入並確認至少10字的新密碼, 不自動登入

## 安全與分層
Controller → Service → Repository, 穩定依賴建構子注入
沿用password_reset_tokens表, 每Email一筆SHA256與時間, 30分鐘到期／60秒冷卻, 重寄撤銷舊token與尚未寄出的outbox
SMTP使用既有加密outbox與回應後寄送, 固定200ms Timebox降低帳號枚舉差異, IP限流仍生效
成功後輪替帳號session epoch、刪既有DB sessions、撤銷PAT, 避免舊請求寫回Session後恢復登入；重設不取代信箱驗證
既有帳號epoch預設0, 舊Session缺值也視為0, 不把所有既有登入登出

## 資料存取
單次Email唯一鍵帳號與token, 按User鎖序列化申請／重設, 不讀Workspace／Project／活動；回應只有accepted或reset旗標, 不含token／信箱存在資訊

## 驗證
memory SQLite及隔離MySQL：有效／錯誤／過期／重播／重寄／限流／交易rollback／舊登入與PAT撤銷／信件失敗不洩漏
隔離真瀏覽器三尺寸填申請與重設、無自動登入及舊密碼失效；真信不寄, 機密不進截圖／Git
