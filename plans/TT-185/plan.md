# TT-185 第一版註冊政策

- 已讀最新卡片與Claude交辦, TT-159先回open待Fly條件後才領取；預估90分鐘
- 正式預設關閉公開註冊, TASKTRACKER_PUBLIC_REGISTRATION開關；local／testing照常開放, 有效邀請仍可註冊且須走TT-319驗證
- 後端先擋直接繞過畫面的無邀請註冊, 不新增帳號／工作區／寄信；既有帳號登入及接受其他工作區邀請維持
- 匿名單一布林policy介面不讀DB／建Session, AuthGate依結果顯示註冊入口, 載入失敗不意外開放註冊；不透露白名單信箱或秘密
- 忘記密碼先查重並建功能卡, 本卡不實作。正式Owner初始化沿TT-323資料移轉／受控操作, 不用DemoSeeder或臨時開放註冊
- 同步Session OpenAPI／產品與部署文件, memory＋隔離MySQL、真Chrome、視覺比較後提交及回填工時；需要Josh決策發小待辦
