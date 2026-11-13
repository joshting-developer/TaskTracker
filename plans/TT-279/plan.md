# TT-279 列表排序與深分頁

- 保留預估300分鐘, 只領本卡；使用共用工程、Laravel分層、Addy／Benchmark與Vue／UI Skill
- 先讀實際SHOW INDEX及EXPLAIN, 在專屬合成表測1k／10k／50k；量測候選排序索引與寫入成本, 不對現用DB測試
- 保留穩定卡號／公開ID排序與增量keyset索引, 不為CASE／NULL盲目堆索引
- 列表限制OFFSET為10000, 總筆數不造假, 另回可翻頁界限；Session／PAT／前端／正式Schema同步, 完整擷取改既有changes cursor
- 真MySQL回歸、瀏覽器分頁與共用視覺比較後提交, 正式工時與卡片／小待辦回填
