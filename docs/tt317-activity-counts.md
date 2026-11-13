# TT-317 活動總數與單卡工時

## 行為

詳細DTO新增activityCounts.comments／history／time。數字為SQL COUNT, 詳細頁和抽屜初始就能顯示真實總數, 分頁只載20筆也不會顯示20當全部。讀到活動分頁後用total校正, 按載入更多不讓數字隨已載入集合變動

useIssueActivity共用總數, id／revision或活動總數改變時清理過期分頁, 保留跨卡取消與generation防護。工時新增可能不改Issue revision, 因此活動總數也納入快取失效條件；純粹重新取得相同數字不清分頁。數字未知時不冒充0

## Josh本輪確認的權限

有該卡片issue.read即可看這張卡片所有成員的有效工時, 包含日期、姓名、時數與說明。Epic包含所屬卡片, 作廢紀錄不計。修改／作廢仍限本人且需timeEntry.writeOwn, 月曆／工作區分析不擴權。這取代TT-317原本「卡片工時還要另外有工時讀取權」的驗收敘述

HTTP資格仍經WorkspaceTransaction／BearerTokenSession→IssueReadService→IssueReadRepository。卡片不可見、跨專案／工作區或撤權時不回總數或內容；PAT仍需read scope與當下卡片權限, 不以角色名稱放行

## 資料存取八項

1. 呼叫端只需指定卡片的屬性與三個總數, 活動內容另查
2. 詳細WHERE限定Workspace、Project與卡片公開ID, 只一張；活動每頁上限100, 畫面每頁20與穩定排序維持
3. COUNT與既有SUM都在SQL, 不把全部活動拉進PHP來數
4. 不新增getWorkspace、全專案卡片載入或輪詢
5. 列表／看板DTO不加活動總數；只有單卡詳細新增聚合, 留言／歷史／工時仍按需讀
6. 詳細SQL多三個聚合投影, 不增加逐列查詢。留言用issue_id前導唯一鍵, 歷史用entity_type／entity_id／stream前導唯一鍵, 工時沿既有限定條件。COUNT成本會隨該卡片有效活動成長, 不是固定時間；沒有宣稱整體效能改善或正式p95
7. memory／MySQL驗21留言／21歷史／有效工時, 實際活動頁20筆而總數21, 詳細只多數字不帶content／event_data／note；增加同專案其他卡片30筆留言後原DTO完全相同。Reader能看他人工時, Reader／Developer都不能修改或作廢他人紀錄, 月曆／分析仍拒絕, 撤權後404
8. 本卡修Bug與確認單卡權限, 不代表全專案效能／安全驗收。既有依賴公告仍由TT-318追蹤

## 契約與驗證

正式OpenAPI 0.16.0的IssueDetail、範例、Agent指南及query文件同步。Web與PAT用同Resource, 沒新增專用聚合端點或工具專屬流程。產品決定另記docs/產品討論紀錄.md

真Chrome以21留言／22歷史／21工時的合成卡確認初始數字、其他頁籤按需載入、更多分頁、加留言與填工時後更新, 詳細頁與抽屜共用；三尺寸與完整48張共用基準另存artifacts/tt317。沒有對現用資料seed、壓測或改使用者的既有工時
