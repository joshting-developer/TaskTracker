# TT-328 忘記密碼

登入頁提供「忘記密碼」, 申請後一律顯示相同結果, 不透露信箱是否存在

- 重設連結30分鐘內有效, 使用一次即撤銷；重寄需間隔60秒, 舊連結與尚未寄出的舊信一併失效
- 新密碼至少10字, 必須再次確認；重設不會自動登入, 也不會將未驗證信箱變成已驗證
- 重設後撤銷該帳號全部Session及PAT, 其他帳號不受影響；既有Session缺少版本欄位時視為0, 升級本身不會強制所有人登出
- 使用既有加密outbox, 回應送出後才派送, 不因同步SMTP等待時間透露帳號存在；資料庫保存失敗也回相同結果, 僅記不含信箱或憑證的錯誤類別

## API與安全

POST `/api/auth/forgot-password` 接受email, 每IP每分鐘5次；POST `/api/auth/reset-password` 接受email、token、password及password_confirmation, 每IP每分鐘10次。兩者皆要求CSRF, 不接受PAT或自訂redirect

正式契約為 `backend/resources/auth-session-openapi.json` 1.5.0。連結使用固定APP_URL及fragment, 不以HTTP Host產生網址；正式環境要求HTTPS。token為256-bit亂數, 重設表只保存SHA256, 含原文的outbox內容入庫前加密、交付或撤銷後清除

Controller → Service → Repository, 依Email唯一索引查一個帳號及一筆重設資料, 以User列鎖避免重設／重寄競爭, 不讀Workspace、Project、任務或歷史。撤銷只篩選該User的Session及PAT。200ms最低處理時間降低枚舉差異, 不代表遠端資料庫故障時能保證完全等時

## 發布與驗證

新增migration只在隔離測試執行, 正式環境等整批tag時先跑migration再切換程式。沿用既有SMTP設定, 本次不寄真信

測試證據放git忽略的artifacts/tt328, 包含SQLite／隔離MySQL重設與撤銷、交易失敗回滾、資料讀取範圍與限流, 以及Chrome的申請→重設→舊密碼拒絕→新密碼登入及三種寬度檢查

安全設計參考[OWASP Forgot Password Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html)
