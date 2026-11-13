# TT-278 任務搜尋

完整卡號自動等值, 如tt-12只找TT-12。尾端*明確指定卡號prefix, 如TT-1*。其他文字維持大小寫不敏感字面contains；text:TT-12可找描述／標題提到卡號的內容, 不把%_當萬用字元

列表／增量、候選與看板的卡號選路同步。列表文字包含描述與承辦者；候選只有標題／卡號, 看板只在已載入摘要欄位篩選, 不為搜尋額外載入全描述或活動

## 真MySQL量測

MySQL 8.4.13, tasktracker_test內本工具專屬合成表, 1k／10k／50k列。每項5次暖機＋30次取樣, 以下是SQL呼叫p95毫秒, 不是HTTP／正式部署p95；只比較卡號查詢路徑, 不宣稱一般文字已變快

| 卡數 | 舊contains COUNT | 新exact COUNT | 舊contains data | 新exact data |
| --- | --- | --- | --- | --- |
| 1000 | 1.123 | 0.179 | 1.123 | 0.131 |
| 10000 | 11.334 | 0.188 | 12.978 | 0.176 |
| 50000 | 106.504 | 0.333 | 62.394 | 0.227 |

EXPLAIN ANALYZE卡號等值使用既有Workspace／Project／identifier唯一索引, 沒有新增正式索引／欄位, 也沒有新增索引寫入成本。prefix仍可能匹配很多卡片, COUNT與data成本依實際選擇性成長, 不是固定1列

## FULLTEXT決策

隔離ngram size=2在50000列測「中」與「100%_!」都回0, 字面contains各50000；「會員」兩者50000, API也出現stopword／token差異。不能直接替換原本短字、英文與標點搜尋。官方說明：[MySQL ngram parser](https://dev.mysql.com/doc/refman/8.4/en/fulltext-search-ngram.html)

ngram候選50000列匯入 2039.93ms；單次1000列更新一般 63.39ms、ngram 61.71ms。這組更新噪音不足以宣稱額外成本或效能優勢, 不保留正式FULLTEXT或變更資料庫全域token設定。一般contains仍線性掃同專案, 此限制明列, 沒有冒稱整體搜尋都已優化

## 範圍與驗證

沿用可信Workspace／Project where、SQL篩選分頁、穩定排序／cursor, 原本identifier參數仍明確等值。一般頁面不讀全Workspace, 詳細／活動與圖片按需；沒有新輪詢、快取或有損查詢截斷

memory與MySQL測中文／英文／單字／標點／字面%_、完整卡號／prefix／text:、同Project與跨Project、公開PAT及無LOWER全文掃描路徑。真瀏覽器驗列表SQL與看板本地結果一致, 官方Schema、共用48張candidate／compare與build另存artifacts/tt278

未對現用DBseed／壓測或部署。診斷工具只建立tt_search_probe_隨機表並在finally清理, 不讀現用卡片；原始EXPLAIN／p50／p95保存於忽略產物, 報告只收合成摘要
