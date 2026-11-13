# 註冊信箱驗證（TT-319）

新帳號註冊後只建立pending Session, 驗證完成才登入工作區。既有帳號預設不需補驗證, 不回填或偽造email_verified_at；已註冊的Email仍由唯一鍵拒絕重新註冊

帳號建立與建立自己的工作區是兩個操作。既有成員新增自己的工作區另由TT-326承接, 本卡沒有增加這個入口

## Session API

正式機器契約：[auth-session-openapi.json](../backend/resources/auth-session-openapi.json)。這些是Session＋CSRF介面, 不使用PAT, 也不是外部整合的公開業務API

| 操作 | 回應／限制 |
| --- | --- |
| POST /api/auth/register | verificationRequired、email、expiresAt、resendAt、resendAfterSeconds, 不回登入身分 |
| POST /api/auth/login | 舊帳號／已驗證帳號回身分, 需驗證的新帳號回pending資訊, 不偷偷重寄 |
| POST /api/auth/current | 恢復pending或已登入身分, 不讀整個Workspace |
| POST /api/auth/verify-email | code或token擇一, 成功建立Session並回最小身分 |
| POST /api/auth/resend-verification | pending Session限定, 選填新email, 撤銷舊碼／連結及尚未交付的信件 |

驗證端點10次／分鐘, 重寄端點5次／分鐘, 另有帳號60秒冷卻。前端使用伺服器提供的剩餘秒數計算倒數, 不依賴使用者電腦與伺服器時鐘一致, 不輪詢API

## 驗證規則與資料

- 驗證碼為7位英數, 排除0、1、O、I、L, 不分大小寫, bcrypt雜湊保存
- 啟用token為32隨機bytes的hex字串, SHA-256雜湊保存, 不存明碼
- 同一challenge有效30分鐘, 5次錯碼後需重寄, 任一方式成功後兩種憑證都失效
- local666僅在APP_ENV=local／testing且有有效pending challenge時通過, production／staging拒絕
- 以User鎖序列化驗證、重寄與修改Email, 錯誤次數在回傳錯誤前提交, 不因例外回滾次數
- 信件沿用TT-189加密outbox與commit後Queue, Worker寄前檢查當次challenge；重寄／改信箱撤銷舊事件, SMTP預連線後再重驗
- 已交給SMTP的信件無法追回, 但舊碼與連結已失效。結果不明仍依TT-189記uncertain且不補寄
- 邀請目前是手動複製連結, 因此受邀的新帳號同樣驗證, 舊帳號接受邀請維持原行為

啟用連結格式為APP_URL/app/#/activate/<token>。GET不修改帳號, 必須按啟用按鈕POST；token只在fragment與POST body, HTTP操作紀錄不記body或Cookie。非local／testing環境要求APP_URL使用HTTPS

## Migration與驗證界線

兩支增量migration只建email_verifications、mail_deliveries.verification_id與users.email_verification_required, 不seed、不重設、不回填既有驗證時間。新註冊由Service明確設email_verification_required=true

自動測試全部是隔離合成資料與Mail fake／沒有寄信Worker的隔離瀏覽器環境, 不發真信。Josh後續以自己的新註冊操作確認驗證信；TT-189的一封診斷信授權不延伸為本卡測試信授權

既有業務測試fixture已明確走verify-email與local666, 不改正式register API自動放行。專項測試保留rawRegistration驗證pending及安全規則
