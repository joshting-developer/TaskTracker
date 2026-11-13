# TT-276 看板Epic估時查詢

一般Task不需要計算Epic子卡估時, 原本卻在每張卡投影時執行相關子查詢。一萬卡的MySQL執行計畫顯示, 20張一般卡會掃描子表一萬列20次

這次改為先取有界當頁, 只有當頁真的出現Epic才用同Workspace／Project與當頁Epic IDs做一次group by加總。一般Task不查這段SQL；沒有子卡的Epic回0, 單卡詳細也用同一入口。沒有新增索引或更改API欄位, 原有工時資格、排序與receipt沒有改動

## 驗證

- 專用tasktracker-codex-perf／MySQL8.4.13／tasktracker_test, 一萬張合成Task, 不接主庫
- 相同fixture交錯執行舊／新Repository, 排除前兩次暖機後各九次。20筆中位數43.96→12.84ms, 100筆172.84→25.45ms, 卡片IDs一致。一般卡不再有epic_child投影子查詢, 查詢數維持4次
- 新增memory與MySQL共用案例驗當頁Epic限定ID／scope、0子卡／Task與Bug估時、單卡詳細、無關Project與列表無估時查詢；相關後端34項／1530assertions通過
- 真Laravel看板瀏覽器流程test:board通過, Pint修正trait順序後通過。畫面資料／互動未變, 未更新visual baseline, 不宣稱重做新版UI驗收
- 原始benchmark與log留忽略artifacts/tt276, 可分享的合成SQL證據見[evidence](evidence/tt276-epic-estimate-comparison.json)

這是Repository實驗, 不包含HTTP／網路／大量工時／正式p95。既有列表排序、搜尋及有權工時聚合成本仍由TT-277／278／279等卡處理, 不代表整體效能已結案
