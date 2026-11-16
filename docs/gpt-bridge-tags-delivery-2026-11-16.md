# GPT Bridge 標籤工具

TT-411。程式 45363de 已 push, 第 4 版已部署到 https://tasktracker-joshting-bridge.joshting-1999.chatgpt.site

新增 list_tags、get_tag、create_tag、update_tag、delete_tag、update_issue_tags, 對應正式 OpenAPI0.24.0 的六個 Tag 操作, 合計 47 工具。標籤定義保留 description, 查卡／列表／增量保留 tags 並衍生 tagIds。貼標只使用 addTagIds／removeTagIds, 保留其他標籤, 沿用 revision、requestId、PAT scope 及簽章確認

83 項 Adapter 測試、build 與正式契約覆蓋通過。用 Codex 本人 PAT 唯讀驗新 Adapter 的標籤清單及單卡投影通過；部署後原生 Plugin get_issue(TT-193) 確認回傳 Season 0 的 tags／tagIds, revision16。沒有建立測試用正式標籤或批次更動卡片

部署 appgdep_6afab548d5108191bb0e5d4a73300d50, 來源 subtree 11da7463a7d3c7381eab4bb942140066f0eab34f, env revision20。owner-only 存取及 Secret 保留。官方封裝僅暫存清理遭 EPERM, 產物已核對且原生 save 確認兩個檔案

目前對話的工具清單未刷新, 六個新工具的 Host 載入與寫入體驗需新對話確認, 不宣稱完整 Host 驗收完成；TT-411 保持 review。新對話沿用既有 Plugin, 先查標籤定義與卡片現有標籤再差量操作
