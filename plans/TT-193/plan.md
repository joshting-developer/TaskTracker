# TT-193 專案與任務標籤

- 預估 240 分鐘, 先完成 Tags, 不實作通用批次編輯
- 專案定義 id／name／color／description, 管理用 project.tags.manage, 套用用 issue.write, 不靠名稱作 ACL
- 單張卡片最多 20 個標籤, 每專案最多 200 個有效定義；定義清單有界、穩定排序, 卡片標籤預載避免 N+1
- 定義改名保持 ID, 刪除採保留歷史的停用定義, 不批改所有卡片或大量建立歷史；列表只顯示有效標籤
- Web 與 PAT 共用 Service／Repository, revision／requestId、撤權後重播、跨租戶與差量增刪都須驗證
- 標籤管理、列表／看板／完整頁／編輯、SQL 篩選、公開 OpenAPI／範例同步
- 部署後依 Season 清冊套用實際標籤, 再更新共用 CLI／使用 Skill 的分類與未來任務排除政策
- 最後跑完整回歸、整批 tag 部署, 不逐卡發布

## 邊界

TagService 管理規則與授權, TagRepository 管理限定專案的存取；Controller 只適配 Session／PAT, 相依均由建構子注入。Vue 選擇器持有草稿, 管理頁持有定義編輯, 不將整個工作區載入作候選
