# TT-359 工作區成員停用與恢復

原估時0, 尚未實作前補180分鐘並回open再領取。只處理特定Workspace成員資格, 不停用全平台帳號或Owner

## 實作
- 新用例Controller → Service → Repository, HTTP WorkspaceTransaction擁有交易與User→Workspace鎖；每次重播前驗目前管理permission、目標角色及禁止自行停用
- 保留disabled_at與專案ACL, 共用role_revision避免角色／狀態互相覆寫。永久事件表保存操作者、狀態、版本與時間, receipt與變更同交易
- 成員管理頁提供啟用／已停用篩選及確認, 停用者可明確恢復。列表SQL有界搜尋排序, 新指派不含停用者, 舊卡顯示停用狀態
- Session／PAT業務API即時重驗資格。前端下一次身分同步清除舊scope, 零資格走TT-358, 不恢復舊邀請

## 驗證
SQLite／MySQL驗越權、Owner／本人保護、跨租戶、版本衝突、重播、交易回滾、其他空間與卡片／留言／工時保留、既有PAT拒絕及恢復後原ACL。真Chrome雙帳號驗啟用→停用→無工作區→恢復, 三尺寸與畫面比較。只用本地隔離資料, 不打tag或部署
