# TT-319 註冊信箱驗證

- 預估360分鐘, 本卡以實際功能驗收, 只保留這張進行中
- 沿用共用工程、Laravel嚴格分層／ECC、Vue、UI與Ponytail lite, 保留Session／CSRF及既有權限
- 既有帳號不改驗證時間, email_verification_required只對新註冊設為true, 新註冊先存pending Session, 驗證前不建立登入資格
- User鎖序列化challenge, 英數7碼及高熵連結只存雜湊, 30分鐘單次使用、5次錯誤、60秒冷卻, 重寄／修改地址撤銷舊事件
- GET連結僅顯示確認畫面, POST啟用；token放fragment不進HTTP路徑, 不記日誌
- local666只在local／testing的有效pending challenge通過, 正式環境拒絕
- 沿用TT-189加密outbox與背景寄送, 自動測試只用Mail fake；邀請是手動連結, 新帳號同樣驗證
- 同步Session契約、前端登入／註冊／重載、測試fixture與手機流程, 隔離MySQL／Chrome／核准視覺驗證後提交及填工時
