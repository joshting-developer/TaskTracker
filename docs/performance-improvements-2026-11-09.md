# 效能改善紀錄

日期：2026-11-09。依[複掃報告](performance-scan-2026-11-09.md)分階段改善，保留產品及API契約。

## 階段一：唯讀資格批次化

ProjectAccess提供只在單次唯讀用例內使用的快照，WorkspaceQuery與WorkspaceTimeReport每次讀取重新建立；沿用同一permissions判斷，沒有Redis ACL快取或寫入授權捷徑。Project／Member只讀一次，Default看板共用已讀Project；issue ID用Set式索引，避免工時逐筆線性搜尋。

五輪同工具、同資料、同query log配置比較：

| 場景 | 原SQL | 改善SQL | 改善中位ms |
| --- | ---: | ---: | ---: |
| seed.workspace | 3635 | 55 | 10.97 |
| seed.report2025 | 71 | 22 | 4.16 |
| plus200.workspace | 6435 | 55 | 12.96 |
| plus200.report2025 | 71 | 22 | 4.31 |
| plus200.24members.workspace | 7235 | 55 | 13.63 |

資料回傳JSON bytes維持原基準；SQL數不再隨工時／成員數增加。這是領域服務證據，HTTP及看板會於後續階段用相同隔離流程重測。原始統計見[改善基準](../.ecc/benchmarks/performance-improvements-2026-11-09.json)。

驗證：原61項／2416 assertions baseline通過；新增兩項驗證四角色與既有逐筆權限等價、重用Query後移除／停用資格生效、Owner／Developer增加200筆工時仍回完整資料且SQL不成長。完整63項／2458 assertions通過，修改檔Pint格式檢查。既有跨租戶、撤權重試、交易、Seed／匯入回歸保留。

後續階段與剩餘項目於完成時更新。
