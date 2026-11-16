# TaskTracker

TaskTracker 是多人協作的任務管理平台, 提供專案、任務看板、紙條與工時管理

[前往正式站](https://task-tracker.josh-ting.com), 查看功能介紹或直接使用

## 本機啟動

安裝 Docker 與 Docker Compose 後, 在專案根目錄執行：

```sh
docker compose --profile standalone up -d --build
```

- 網站：<http://127.0.0.1:5174>
- 測試信箱：<http://127.0.0.1:8025>, 從信件連結完成註冊啟用

獨立模式會啟動應用程式、MySQL、Redis 與 Mailpit, 帳密與本機憑證自動產生, 資料保存在 Docker volumes

```sh
docker compose --profile standalone down
```

停止時保留資料。已有共用資料服務的開發者可使用同一份 Compose 的 `shared` 模式, 連線設定與測試方式見[開發說明](docs/development.md)

## 開發用外部 Skills

以下是開發時選用的外部 Skills, 啟動網站不需要安裝：

- 程式：Ponytail（lite）、Laravel Patterns、Vue Best Practices
- 介面：UI UX Pro Max、Design Taste、Hallmark、Unslop
- 效能：Performance Optimization、Benchmark
