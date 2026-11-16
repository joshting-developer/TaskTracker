# Fly.io 部署

公開 repository 保留 `fly.toml.example`、正式映像 Dockerfile 與部署工具。真正的 App 名稱、資料服務位址和環境識別放在被忽略的 `fly.toml`, 不提交正式設定

## 首次設定

複製 `fly.toml.example` 為 `fly.toml`, 填入自己的 App、HTTPS 網址、MySQL／Redis 位址與資料庫名稱。沿用已建立的 Fly App, 不因缺設定檔而重建；資料服務必須驗證 TLS 憑證與主機名稱

應用程式帳密、APP_KEY、R2、SMTP 與 CA 內容使用 Fly Secrets, 不放 TOML 的 env、build args、Git 或公開日誌。變更既有 APP_KEY 或資料服務憑證前先確認資料相容性與回復方式

## GitHub Actions

在 GitHub 的 `production` Environment 設定：

| Secret | 用途 |
| --- | --- |
| FLY_CONFIG_TOML | 真正 fly.toml 的完整內容 |
| FLY_API_TOKEN | 限目標 App 的部署資格 |
| MIGRATION_DB_USERNAME、MIGRATION_DB_PASSWORD | 限目標 schema 的 migration 帳號 |

workflow 只在部署 job 讀取正式設定, 寫入 runner 暫存檔並限制權限為 0600, 結束後刪除。建置與測試 job 不取得這些 Secrets, 發布工具的原始 Fly／Docker 輸出不轉印到公開日誌

部署方式與重跑條件見 [Tag 發布](tagged-release.md), 執行期設定見 [正式環境](production-environment.md)。未真正發布與驗收前, 不把本機檢查當成正式部署成功

## 本機使用

使用被忽略的 fly.toml, 或以 FLY_CONFIG_PATH 指定私有檔案。設定檢查可執行 `python3 tools/fly_config.py --config fly.toml`, Fly 平台檢查使用 `flyctl config validate --strict --config fly.toml`, 原始輸出也應保存在私有位置

回退必須使用已驗證且相容目前 schema 的舊映像。映像回退不會還原資料庫, 不用舊備份覆蓋上線後的新進度
