# TT-340 工作空間邀請信與待接受清單

- 邀請建立後經既有加密outbox寄Email, 支援未註冊與既有帳號；保留複製連結作為備用, 不以log mailer冒稱實寄
- 擴充MailDelivery的invitation指標, 寄前重驗邀請期限、消耗、收件地址及邀請者當前workspace.invite資格；不提前建立成員
- 沿既有RequestReplay, 邀請結果加密後保存以安全重試相同requestId；授權先於重播, 同Email新邀請撤銷舊連結, 不重複寄同一事件
- 設定頁新增獨立20筆分頁待接受邀請查詢, 限目前工作區及workspace.invite, 不附token或全Workspace。接受／到期後移出, 正式成員沿原目錄查閱
- 新增必要增量migration與索引, 僅隔離DB套用與測試；主庫只執行具體已驗證的增量migration, 不seed或改既有帳號
- 雙引擎與SMTP fake驗收, UI邀請／接受實測、三尺寸／正式畫面比較、工時與每階段提交
