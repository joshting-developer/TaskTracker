# 本地開發環境

## 啟動與登入

```sh
python3 tools/local-dev.py up
python3 tools/local-dev.py seed
python3 tools/local-dev.py status
```

開啟 http://127.0.0.1:5174。四種角色的合成帳號與隨機密碼記錄於 `.local/local-dev-credentials.json`, 不提交 Git。seed 可重跑且保留已修改資料, 只在第一次建立合成工作空間

## 隔離範圍

| 項目 | 本地開發設定 |
| --- | --- |
| Compose 名稱 | tasktracker-local-dev |
| 設定檔 | docker-compose.local-dev.yml |
| 前端／API／WebSocket | 127.0.0.1:5174／3002／8080 |
| 資料庫 | 專用 MySQL 8.4, 僅 Compose 內部 mysql:3306 |
| 快取／Queue | 專用 Redis, 僅 Compose 內部 redis:6379 |
| 附件 | 專用 laravel-storage volume, 不使用 R2 |
| 郵件 | 本機 Mailpit 捕捉信箱, SMTP 維持 TLS, 不向外寄送 |
| Redis namespace | tasktracker:local-dev |
| 秘密與資料 | 全部使用 tasktracker-local-dev 前綴的獨立 volume |

啟停工具明確指定 Compose 檔案、專案名稱與空的插值環境檔, 不讀根目錄 `.env`。原 `tasktracker` MySQL 仍是唯讀備份, 不解除唯讀、不 seed 或匯入正式資料。本地帳號不搭配正式 PAT 使用

前端掛載目前 frontend 原始碼, 修改後由 Vite 更新。後端 app、routes、resources、config、migration 與 seeders 掛載目前原始碼；改套件、Dockerfile 或容器內程序時重新執行 up

## 本機驗證信

開啟 http://127.0.0.1:8025 查看本地註冊、邀請及重設密碼信件。註冊後從這裡點啟用連結, 仍走正常的信箱驗證流程。之前卡在驗證頁的本機帳號可按「重寄啟用信」, 不需要刪除帳號或重新 seed

信箱 UI 只綁 127.0.0.1, SMTP 不對主機開埠, 也沒有設定任何轉送。即使使用真實信箱地址註冊, 信件仍只存在此本機信箱, 不會寄到外部。正式站不受這組設定影響

啟動工具第一次產生 `.local/local-mail` 的專用憑證與私鑰, PHP 只信任掛載的公開憑證, 保留 SMTP 的 TLS 與憑證驗證。不要將私鑰或信件 volume 提交 Git；只保留既有本機資料, 不讀正式 SMTP 密碼

採固定 [Mailpit v1.33.1](https://github.com/axllent/mailpit/releases/tag/v1.33.1)；STARTTLS 與憑證設定依[官方文件](https://mailpit.axllent.org/docs/configuration/smtp/)

## 停止與重開

```sh
python3 tools/local-dev.py down
python3 tools/local-dev.py up
```

停止預設保留所有本地 volume, 重開後既有帳號、資料、附件與密碼仍在。工具不提供刪除 volume 或覆寫舊資料的選項

```sh
python3 tools/local-dev.py logs
curl --fail http://127.0.0.1:5174/api/health
```

5174／3002／8080 被其他服務占用時先確認來源, 不停止其他 Agent 或其他專案的程序。Claude 的 5198／13198、Codex 的 5199／13199 仍依專用測試 Skill 運作, 不改用這組日常開發環境跑破壞性測試

## 環境現況

TT-370 起恢復的是全新的隔離開發服務, 不是先前轉往正式站的資料來源。正式工作進度、紙條與工時仍只回填線上 TaskTracker

## 本地驗證

```sh
cd frontend
node tools/local-development-probe.js
```

此工具固定連到 127.0.0.1:5174, 只使用 local-dev 專用合成帳號。它不參與使用 5198／5199 的自動化檢查, 避免 CI 或其他 Agent 誤寫日常開發資料

```sh
cd frontend
node tools/local-registration-probe.js
```

本地註冊 probe 每次用新的合成帳號, 從 Mailpit 的實際信件開啟啟用連結, 驗證原分頁恢復登入。重跑不刪資料或重新 seed；不使用測試驗證捷徑或正式 SMTP
