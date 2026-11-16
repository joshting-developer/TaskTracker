# 專案文件

這裡只保留啟動、部署、資料搬移與驗收工具的必要說明。產品設計、API 背景、討論及歷次交付文件集中到 idea 文件庫, 不在此保留舊連結

- [啟動與測試](development.md)
- [Fly 部署](fly-deployment.md)、[Tag 發布](tagged-release.md)
- [資料搬移](mysql-transfer-runbook.md)、[受控遷移](controlled-migrations.md)
- [視覺回歸](visual-regression.md)
- [正式 OpenAPI](../backend/resources/openapi.json), 開發者指南由網站 `/api/public/docs` 提供

contracts 與 visual-baselines 是工具讀取的規格／核准設定, 隨產品版控。evidence 是本機掃描、Skill 與驗收報告, 已 ignore, 不上 GitHub
