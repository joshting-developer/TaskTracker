# TT-399 GPT Plugin：查詢專案詳細、成員與狀態候選

依Josh開工授權, 補get_project、list_project_members及list_project_statuses, 供後續寫入前讀合法候選。60分鐘既有估時保留。先合成測試, 再發布及驗證工具發現。僅修改Adapter, 不碰TT-398其他對話的時區差異；本次明確轉換到Plugin工作, 不搶改另一對話的進度

資料範圍：單Project metadata、成員每頁最多20筆、狀態最多20筆。每次重驗Token身分／工作區, 不快取權限、不預讀其他專案或活動。沿用固定origin、拒絕redirect、10秒／256KiB限制

Skills：task-tracker-usage、side-project-development-standards及engineering／verification／data-access、ponytail lite、plugin-creator:update-plugin、sites:sites及site-mcp-server。沒有產品畫面或Laravel查詢異動, 不套用其他工作樹的UI／框架驗證結果
