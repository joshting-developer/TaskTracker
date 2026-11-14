# TT-323 進度

- 已完成TT-321正式設定與TT-342共用Skill, 重新看紙條無交辦, 本卡由Josh依本次交辦轉Codex領取
- 新版Fly CLI v0.5.10已由Josh核准登入；orgs show曾顯示personal, 但實際apps create --org josh-90回不存在, 原推論不足以確認目標組織
- 首次Fly remote build與commit固定版本的方案已發Josh紙條；TT-320尚未完成, 不宣稱CI已交付
- 組織不符已發Josh紙條, 暫不在其他組織建App；其餘設定與資料庫唯讀準備繼續
- Josh授權決策先採建議方案, 已在同一帳號personal組織建立tasktracker-josh, 沒有另建組織；原josh-90差異已發紙條
- 正式task_tracker唯讀查得0表, MySQL CA／主機身分驗證及Redis TLS PING通過；秘密僅暫存私有目錄與Fly staged Secrets
- 自訂網域目前無DNS, 先用Fly HTTPS網址作APP_URL與邀請入口, ALLOWED_ORIGINS只含Fly及目標網域；DNS完成後改回正式網域, SEO build仍使用目標網域
- Fly映像git-0f1f1b4／digest 6d8d17c91e5c2c6e98842249c0747ab808778ab7108b9ea888938624f7b5fca7已發布, release migration正常退出；基本HTTPS／三尺寸登入頁與Machine內MySQL TLS／Redis TLS通過
- Redis第一輪因Secrets未引用導致特殊字元解析異常, 改成引用編碼後同映像重新套用通過, 未換服務密碼
- 隔離全套測試預設128MB兩次不足, 只提高Codex測試容器至512MB並清掉自身累積fixture後, 429項／18155斷言通過、1項既有skip；正式runtime記憶體設定未改
- 發版基本驗證完成後卡片改review, Codex轉TT-341執行Josh當次已授權的停寫／備份／逐表核對搬移；不宣稱自訂網域DNS已完成

## DNS完成後續

- Josh紙條確認DNS已設, 已讀TT-323留言；Fly憑證Issued、DNS A紀錄與正式網域HTTPS health均通過
- 本卡從review回open再claim, 只處理網域切換；正式庫部署前另存私有SQL備份, 不再搬一次資料
- APP_URL切正式網域, 同一既有digest重新套用設定, 保留Fly網址作相容入口, 之後核對Session／PAT與私有工具baseUrl
