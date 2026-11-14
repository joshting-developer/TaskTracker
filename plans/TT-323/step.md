# TT-323 進度

- 已完成TT-321正式設定與TT-342共用Skill, 重新看紙條無交辦, 本卡由Josh依本次交辦轉Codex領取
- 新版Fly CLI v0.5.10已由Josh核准登入；orgs show曾顯示personal, 但實際apps create --org josh-90回不存在, 原推論不足以確認目標組織
- 首次Fly remote build與commit固定版本的方案已發Josh紙條；TT-320尚未完成, 不宣稱CI已交付
- 組織不符已發Josh紙條, 暫不在其他組織建App；其餘設定與資料庫唯讀準備繼續
- Josh授權決策先採建議方案, 已在同一帳號personal組織建立tasktracker-josh, 沒有另建組織；原josh-90差異已發紙條
- 正式task_tracker唯讀查得0表, MySQL CA／主機身分驗證及Redis TLS PING通過；秘密僅暫存私有目錄與Fly staged Secrets
- 自訂網域目前無DNS, 先用Fly HTTPS網址作APP_URL與邀請入口, ALLOWED_ORIGINS只含Fly及目標網域；DNS完成後改回正式網域, SEO build仍使用目標網域
