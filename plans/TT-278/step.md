# TT-278 進度

- 已查最新小待辦並領卡, 預估300分鐘保留
- 先確認完整卡號exact／prefix與一般contains退路, 不直接替換成會改短字／標點結果的FULLTEXT
- 已完成卡號exact／明確prefix／text:退路, 前後端與OpenAPI 0.14.0同步
- 22項memory／MySQL測試共3176斷言通過, 真Chrome列表與看板搜尋、composable檢查及build通過
- MySQL 8.4.13合成1k／10k／50k各30樣本, 50k data p95 contains62.394ms→exact0.227ms, 使用既有唯一索引
- ngram實測會漏單字／標點／API, 不採用；一般contains仍線性掃描, 沒有宣稱全部搜尋已改善
- 完整48張候選已依持續授權保存基準, 桌機與手機人工檢視, 正式compare與證據檢查另存artifacts/tt278
- 無正式部署、無現用DB seed或壓測；提交後填本人正式工時並更新卡片與小待辦
