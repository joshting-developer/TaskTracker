# TT-242 專案ACL讀取拆分

日期：2026-11-12。只調整既有Project成員管理讀取與前端接線，不新增自訂角色或改授權產品規則。

## 邊界與契約

管理頁以四個Session／Workspace限定GET取代getProjectMembers與getWorkspace預載：

| API（/api/v1/projects/{project}） | 資料與觸發 |
| --- | --- |
| /acl | 當次ACL revision及可授予角色permission交集；進頁／明確重載時讀 |
| /acl/members | 有效成員的userId、name、email、source、roleId、canChange；SQL搜尋及分頁 |
| /acl/candidates | 同Workspace有效候選id、name、email；選人操作才搜尋／分頁 |
| /acl/history | 此Project授權歷史；展開才查詢，20筆分頁 |

管理資料每頁預設20、最大100，依姓名／user ID排序；歷史依position／ID倒序。Project資格每次在ApiExecution交易內重新驗證；一般最小member-options仍只有姓名及ID，不能取得這裡的信箱／授權歷史。

ProjectAclReadRepository僅做SQL：有效Workspace及Project條件先過濾，當頁指派以Eloquent預載批次取得。ProjectAclReadService以有效permission判管理資格、可授予角色交集與逐筆canChange，角色ID僅解析permission。Workspace繼承來源保留，繼承成員不提供移除／調整入口；寫入仍走原set／removeProjectMember的交集、revision、requestId及交易保護。

ProjectMembers頁只保留當頁成員及候選；選到跨頁成員仍保留已選參照。授權歷史的目標姓名只批次解析當頁最多100位，不為顯示名字讀候選全集。候選與歷史不塞進metadata；切頁／搜尋不更換編輯草稿的ACL基準版本，409保留輸入，明確重載才捨棄草稿。scope改變或卸載取消請求；失權時清私有資料並由App重新驗權。

## 八項資料存取審查

1. 管理metadata、成員摘要、候選及歷史各自獨立；一般姓名選項不附管理email。
2. 每個集合有固定上限、頁碼與排序tie-breaker；前端成員及歷史均呈現真實分頁。
3. 有效Workspace／Project資格、姓名／email搜尋及分頁在SQL，不先讀全員後PHP篩選。
4. Project成員頁改用auth/current＋單Project殼；不為管理一個Project載Workspace卡片／工時／通知。
5. 未開歷史不讀audit_events，未選人不讀候選；metadata不含這些集合。
6. 當頁指派批次預載，20與26位成員的SQL次數相同；COUNT／深OFFSET及文字搜尋仍有資料量成本，不宣稱固定延遲。
7. 人造105候選、26有效成員、25歷史驗證欄位／20＋6與100＋6分頁、Project隔離、403／404／409、繼承與撤權。查詢log確認摘要不讀audit_events及無逐列SQL增加。
8. 其他效能卡仍按各自範圍驗收；舊RPC供既有測試／工具相容的退役由TT-180等持續追蹤，不以此卡宣稱全庫讀取都完成。

## 驗證紀錄

- 同一資料範圍測試在memory與獨立MySQL：2 tests／108 assertions通過；既有ProjectAclTest 7 tests／243 assertions通過。
- 完整memory：79 passed／3670 assertions，13個native skips。
- 完整獨立MySQL：92 passed／4055 assertions。Chrome實際26位成員20＋6、跨頁編輯、409草稿保留、歷史20＋7、搜尋、撤權及三尺寸通過，bulkReads=0。
- Vite build通過，仍有既有大chunk警告；Pint10個PHP檔通過。
- 最後歷史姓名補強另重跑雙引擎focused 2／108、Chrome完整流程及composables手動載入分頁單元檢查通過。
- 未對本地／遠端業務庫seed或壓測；沒有flush共享Redis；不把本地MySQL結果當遠端RTT或正式p95。
