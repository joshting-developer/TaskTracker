# TT-280 進度

- TT-277已於Josh核准、13張比較與提交後closed, 本卡實作從該階段結束後開始, 不重複填報工時
- 已新增IssueChildrenRepository與IssueChildrenScope, 以最多100張最小投影批次讀取、更新Epic／revision並追加歷史；Service產生異動事件, Repository建構子DI經原Factory組裝
- 初步隔離MySQL既有回歸18項582assertions通過, 尚無本卡產品commit
- 尚待1／20／100子卡與大量活動成長量測、範圍／revision／rollback／receipt測試、限定Pint與提交
- 已重讀最新AGENTS與共用task-tracker-usage, Codex只保留本卡inprogress；其他指定卡未開工維持open, 待Josh驗收的卡用review

- 新版批次寫入、同交易歷史追加、版本衝突及第二批回滾、清空Epic與重播已實作。初步主回歸40項2379assertions通過, 後續公開寫入／容器／清空分支22項1932assertions通過（套件重疊, 不相加）
- MySQL 1／20／100子卡、各60歷史20留言, 舊新交錯各7次, SQL51→37／374→37／1735→38, 中位數31.10→23.72／130.40→26.10／522.25→33.25ms；非HTTP p95
- 首批100子卡原索引反向取尾端, 歷史實際rows=1 loops=100, 不掃舊JSON。覆蓋索引實驗已移除, 不增加migration
- 看板三尺寸瀏覽器通過。共用Skill新規範的過期雜湊已單筆同步, 共用庫8377be1, integrity通過
- 最後限定Pint／合併回歸與證據檢查通過後再提交, 正式工時／留言／closed後才接下一張191

- 最後合併回歸56項2904assertions通過, 看板真瀏覽器三尺寸通過, 限定9個PHP格式化後完成；完整性索引修復後校驗通過。報告docs/tt280-child-epic-batches.md, 交付前再次依本人卡片範圍產生Skill證據

- 正式本人實際工時22分鐘已填, 不含先前TT-277驗收等待；本卡來源指紋與backend／資料存取／量測／Skill完整性證據由check_evidence.py驗證通過, 同時進行中的卡仍僅本卡
