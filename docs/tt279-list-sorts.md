# TT-279 列表排序與深分頁

## 已調整

兩個索引對應最常用的更新倒序與建立倒序, 保留identifier／public_id的穩定次排序。既有issues_change_cursor仍保留, 不刪唯一鍵或FK索引。migration只加索引, down只移除本次兩個索引

一般任務列表的(page-1)×perPage上限10000, 在授權Project之後、Issue COUNT／data之前檢查；超過回400 INVALID_INPUT。Session與PAT共用同一Service, 不影響看板頁面既有分批載入或changes cursor

total／lastPage是真實值, accessibleLastPage=min(lastPage,floor(10000/perPage)+1)。前端只讓使用者翻到accessibleLastPage, 到達界限前就顯示縮小篩選的提示；沒有把總筆數偷偷改成10000。要完整同步使用既有issue-changes cursor, 不用深OFFSET當匯出介面

## 真MySQL量測

MySQL 8.4.13, Codex隔離schema中的隨機專屬合成表, 1k／10k／50k。每項5暖機＋30樣本, 相同SQL／資料／投影, 原始EXPLAIN ANALYZE與SHOW INDEX在artifacts/tt279/mysql-profile.json, 數字摘要在.ecc/benchmarks/tt279-list-sorts.json。以下為SQL p95毫秒, 不是HTTP或正式部署數字

| 列數 | 更新倒序前→後 | 建立倒序前→後 | 深OFFSET前→後 |
| --- | --- | --- | --- |
| 1000 | 1.808→0.839 | 1.486→0.860 | 3.120→2.668 |
| 10000 | 20.249→1.071 | 12.363→0.207 | 70.306→5.385 |
| 50000 | 70.169→0.184 | 69.350→0.250 | 219.402→6.728 |

深OFFSET取min(10000,rows-20), 不把不同資料量的OFFSET當同一頁。新增索引後50k倒序首頁可順索引讀20列, 深頁仍需要略過10000列。COUNT 50k p95為3.741→3.863ms, 沒有改善, 仍需掃符合條件的索引

寫入有成本：各做一次更新1000列, 50k樣本為52.181→143.144ms, 10k為37.691→70.923ms。單次寫入只能顯示新增索引的維護代價, 不是寫入p95／固定倍率。只保留兩個有明顯讀取收益的常用索引, 不為每種排序方向都新增一組

升序仍可能filesort。CASE priority與due NULL排序保留原語意, 50k p95約55／53ms, 沒有宣稱已索引化；複合篩選選擇性、一般contains與COUNT仍可能線性成長。這些是P2剩餘成本, 不是全部SQL效能驗收通過

## 其他高頻資源盤點

實際SHOW INDEX有確認FK隱含索引, 不是只讀migration猜缺少哪些鍵

| 範圍與排序 | 實際既有鍵／判斷 |
| --- | --- |
| boards workspace＋project, name或archived_at＋public_id | 有boards_project_unique及created_by FK；名稱／封存排序仍filesort, 不憑空加兩個未量測索引 |
| todos workspace＋assignee／created_by, state, updated_at DESC＋public_id | 已有assignee＋state及created_by FK, 並非完全無索引；排序未完整覆蓋 |
| time_entries workspace＋project＋issue, work_date DESC＋public_id | issue_id FK及member＋work_date已存在；單卡排序未完整覆蓋 |
| audit_events entity_type＋entity_id＋stream, position DESC＋id DESC | 既有唯一鍵可反向讀取, workspace條件再檢查 |
| integration_audits actor_user_id＋workspace, id DESC | integration_audit_owner_page已完整支援反向讀取, 不重複加索引 |

其他資源的EXPLAIN使用隔離schema的空合成scope, 只能確認查詢形狀與可選鍵, 不能拿0列計畫宣稱正式大量資料很快。本卡修正已證實的Issue排序瓶頸, 沒有任意替低頻表堆索引

## 資料存取與驗證

呼叫端只拿當Project列表摘要20列, SQL scope／where／排序／分頁保持, 詳細／留言／工時不混入。不新增輪詢、全Workspace讀取或快取, Resource只組已查好的分頁資訊。HTTP驗過界不查Issue、PAT／Session一致、cursor可繼續使用, 真MySQL檢查索引方向與重複鍵

真Chrome建立10025張本次專屬合成卡, 實際回total10025／lastPage502／accessibleLastPage501, DOM僅20列, 下一頁正常, 第502頁回400。純計算測試涵蓋perPage20／100／7與空集合。舊page999空頁測試已改為界限內501空頁＋999拒絕, 沒有移除公開Schema驗證

未正式部署或對現用DB seed／壓測。候選、比較與build各自記錄, 不將編譯等同部署；新migration僅在本次隔離環境實測
