# TT-317 進度

- 已查小待辦與Bug看板, TT-317是唯一未結案卡
- 首次估時因缺expectedRevision未套用, 已讀最新revision補填90分鐘；尚未實作前修正完成
- Josh確認卡片詳情看其他人的工時合理, 採單卡issue.read範圍, 修改本人限制與其他報表權限維持
- 詳細SQL新增三個聚合投影, 列表／看板DTO不加欄位；正式OpenAPI 0.16.0與範例／指南同步
- 共用活動composable採初始COUNT＋分頁total, 超過20筆不顯示載入長度；工時總數改變即失效過期分頁, 不依賴Issue revision必須變更
- Reader可讀單卡他人工時, Reader／Developer都不能改或作廢他人紀錄；月曆／分析仍403, 撤權後詳情／工時404
- 22項memory／真MySQL測試3348斷言通過, 前端composable檢查與build通過
- 真Chrome21留言／22歷史／21工時驗初始數字、更多分頁、加留言／工時後更新、詳細頁／抽屜及三尺寸；首次腳本未等待抽屜第二頁導致20對22斷言競態, 補等待後全流程通過
- 共用48張候選／approved比較另驗, 不更改容忍度；未對現用資料seed或新增輪詢／全Workspace讀取
- Claude交辦回歸：ProjectAclTest的舊403／缺spentMinutes依Josh新規則更新；MySQL小fixture可選新created索引, 改驗possible_keys仍含cursor且明確cursor路徑無filesort, 保留實際HTTP有界／無OFFSET斷言, 沒強迫正常查詢加hint或宣稱大量資料計畫已重測
- 擴大回歸38項3963斷言通過, 含Claude交辦ACL與MySQL cursor測試；活動頁籤實際數字差異已檢視並依持續授權保存基準
- 其他Agent同時修改公開通知契約, 本卡只stage自己IssueDetail schema／範例與0.16.0版本, 工作目錄保留對方0.17.0通知變更, 不覆蓋他人內容
