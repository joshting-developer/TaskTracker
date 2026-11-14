# TT-332 公開看板API

- 沿Controller → Service → Repository與Bearer鎖後當下權限, 共用既有看板／Issue用例, 不建立工具專屬業務
- read：同Project看板摘要分頁（含封存篩選）、單板metadata與最多1000明確收錄ID、看板卡片分頁
- write＋board.manage：建立、部分更新名稱／簡述、加入／移除差量收錄；Default不可寫, 不公開封存
- Issue建立可帶有界boardIds, 權限或板不存在／已封存時整筆rollback, 不默默略過
- revision衝突409後重讀再套差量, 同requestId重播前仍重驗資格, 不盲覆整包收錄
- 同步OpenAPI、開發者／Agent文件、共用Skill與CLI；驗證真PAT建卡分類讀回、撤權與跨租戶、SQLite與隔離MySQL、讀取範圍與回應上限
- 按獨立階段提交、填本人正式工時、卡片留言交接；不push
