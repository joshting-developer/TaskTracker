# TT-332 公開看板API交付

外部工具可用本人PAT查詢看板, 建卡時分類, 或把既有卡片加入／移出看板。Web與公開API共用BoardReadService、ProjectBoards和IssueService, 不另建Agent專用業務

## 契約

OpenAPI 0.21.0新增5個操作：看板摘要列表、單板詳細、看板卡片列表、建立看板、部分更新看板。PATCH接收addIssueIds／removeIssueIds差量, 不接受selectedIssueIds整包覆蓋。單筆建立Issue另可帶boardIds

讀取是read與issue.read的交集, 寫入是write與board.manage的交集。建卡附帶收錄另驗board.manage, 即使相同requestId已有receipt也先驗當下資格。Default虛擬全收不可修改, 已封存看板仍可讀, 不公開封存或還原

Bearer writeOnce是唯一交易owner, 沿用User→Workspace鎖、requestId及來源紀錄。兩個Agent以相同revision修改時後送者409, 重讀後再套差量, 不會蓋掉另一人的收錄。建卡任一看板不合法時卡片、編號、附件綁定及所有收錄一起回滾

## 資料存取審查

1. 列表只需要看板ID、Project、名稱、簡述、revision、封存時間和卡數；單板才讀明確收錄ID, 卡片列表另取摘要, 不帶描述全文／歷史／留言／工時
2. 看板預設20最多100個, 使用中依名稱／public_id穩定排序, 封存依封存時間／public_id；卡片沿既有穩定排序與OFFSET上限
3. 搜尋、Project與Workspace條件、封存篩選、Epic動態展開、去重與分頁都由既有Repository在SQL執行
4. 不讀整個Workspace快照, 不呼叫getWorkspace。寫入只取目標Project／Board和有界差量所需的卡片歸屬
5. 單板最多1000個明確ID, 每次加入／移除各最多200個；建卡最多20板。歷史、留言和工時仍各自按需讀取
6. 由外部工具明確觸發, 沒新增輪詢。列表回應依perPage有界, 單板ID清單也有上限；看板卡數由SQL聚合, 資料成長仍會增加COUNT／搜尋成本, 不宣稱固定延遲或正式部署p95
7. 測試含同Workspace不同Project的無關卡片與看板、跨租戶404、摘要不含卡片內容、分頁完整性、Epic動態收錄、跨專案／已封存／不存在看板的整筆回滾, 不只比SQL次數
8. 本卡未發現未處理P1；不代表其他效能卡或全站瀏覽器回歸全部通過

## 驗證

- 完整後端跑413項, 412項通過、17832 assertions, 1項既有略過；最後追加跨專案／封存案例後, 本卡目標8項、328 assertions再通過
- 正式OpenAPI離線結構及驗證器反例測試通過, 實際HTTP回應也對照正式Schema
- 真實隔離HTTP＋共用CLI：兩個不同人造帳號各自PAT, 分頁查板、建卡同交易分類、更新簡述、加入／移除、兩帳號同時寫入衝突與重讀重試、原receipt重播均通過
- 網頁冒煙與既有看板查詢流程通過, 看板流程50次API請求、bulkReads為0；build通過
- Pint整理本卡12個PHP檔, 共用Skill完整性通過。沒有畫面配置或前端程式異動, 沒重建視覺基準

實測使用Codex隔離MySQL服務13199與記憶體SQLite, 不以SQLite單次耗時代替遠端MySQL或正式部署量測。沒有對現用資料庫建立測試帳號或跑seed

## 共用工具

共用Skill庫提交ea01ffc, 新增boards、board-get、board-issues、board-create、board-update、board-add、board-remove命令。分類指引改成優先使用公開差量收錄, 僅舊部署才依原授權入口處理

仍要先確認部署的OpenAPI有相應operation, 才能使用新命令。CLI不替呼叫者偷換revision, 不自動重試409, 不公開封存／還原或工時寫入
