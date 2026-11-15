# TT-395 本地註冊驗證信

- 預估 40 分鐘, 已確認 tasktracker-local-dev 使用 preview, 未配置可收信的 SMTP, 所以註冊一直停在驗證頁
- 僅為獨立本地開發 Compose 加 Mailpit 測試信箱, 綁 127.0.0.1:8025；SMTP 只在此 Compose 內部使用, 不設定外寄或轉送
- 沿用正式信箱驗證流程與 TLS 要求, 產生本機專用憑證並讓本地 PHP 信任它, 不放寬正式 TLS、不啟用驗證捷徑
- 私鑰及信件保留於被忽略的本機路徑／專用 volume, 不讀正式 SMTP 或舊備份；既有待驗證帳號可重寄, 不重設資料
- 實際用 5174 的獨立合成帳號驗註冊、信箱收件、啟用連結、原分頁恢復, 不跑 seed 或修改正式資料
- 重啟的範圍只有本次 tasktracker-local-dev；正式工作進度仍在線上, 全部 Season 0 非 GPT 完成才統一部署

## 來源

Mailpit 官方 Docker 與 SMTP／憑證文件：
https://mailpit.axllent.org/docs/install/docker/
https://mailpit.axllent.org/docs/configuration/smtp/
https://mailpit.axllent.org/docs/configuration/certificates/
固定官方版本 v1.33.1： https://github.com/axllent/mailpit/releases/tag/v1.33.1
