# TT-189 背景寄信

- 已讀最新卡片及共用Laravel／資料存取規範, 預估180分鐘並領取；TT-319維持open
- 使用Laravel Mail與單一MAIL_*配置, SMTP_*僅作舊環境fallback；verify與send共用設定, 不另留一套密碼
- 交易式outbox：唯一事件鍵、加密內容、本人／收件資格、狀態、次數、期限及安全錯誤碼, Job只帶outbox ID
- API不等SMTP；只在commit後排非同步Job, broker不可用時保留pending並由重派命令／排程恢復
- 最多3次重試確定未寄出的連線失敗；SMTP送出中斷或worker於sending階段中斷時記uncertain, 不自動補寄。SMTP無跨資料庫exactly-once, 不假稱可以保證
- 寄前重驗帳號、地址與目前有效資格；驗證信需尚未驗證／未過期。TT-319再補challenge當次有效性
- 測試用Mail fake及可替換連線邊界, MySQL真交易／重播／失敗回歸；真信只在明確授權與既有安全限制允許後寄一封給Josh, 不發給其他人
- Josh已確認實際收件, 完成驗證／提交後結案, 不以SMTP接受代替收到信
