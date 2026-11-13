# TT-279 進度

- 已讀小待辦、最新卡片與相關排序程式, claim完成, 預估300分鐘
- 已以SHOW INDEX盤點issues／boards／todos／time_entries／audit_events／integration_audits, 保留既有cursor及FK鍵
- 1k／10k／50k各30樣本, 只加updated DESC及created DESC兩索引；50k首頁SQL p95 70.169→0.184ms／69.350→0.250ms, 深頁219.402→6.728ms
- 寫1000列單次52.181→143.144ms, 明列維護成本；升序／CASE／NULL／COUNT尚有線性成本, 不宣稱全部SQL變快
- 任務列表OFFSET上限10000在Service檢查, Session／PAT／前端及OpenAPI 0.15.0同步；總數不截斷, 新增accessibleLastPage
- 26項測試3238斷言通過, 真Chrome10025合成卡驗502真實頁／501可達頁、當頁20 DOM列、換頁與過界400；build通過
- 共用48張候選沒有版面／內容差異, 15張PNG僅RGB通道<=2的既有容忍內差異；保留舊基準並依持續授權保存, 正式compare另驗
- 詳見docs/tt279-list-sorts.md, 沒有正式部署或對現用DB套migration；完成後回填本人正式工時／卡片／小待辦
