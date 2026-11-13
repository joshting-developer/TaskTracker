## TT-277 工時分析讀取範圍

- 移除全Workspace專案／成員／Project ACL及歷史載入, 改SQL授權子查詢；摘要限制2000個成員×專案配對, 明細獨立分頁且不重算配對
- GET摘要／明細／搜尋選項分開, 前端遷移並保留日期與歷史成員規則；以memory及隔離MySQL驗讀取範圍, 三尺寸瀏覽器確認分析與明細
- 篩選沿用既有PagedSelect, 不重做圖表；畫面差異仍需核准, 不以測試通過代替視覺核准

- 已拆GET摘要／明細／兩種選項, 前端改PagedSelect, 手機遮擋與樣式撞名已修並加幾何檢查
- 隔離後端28項1037assertions、分析／成員明細真瀏覽器三尺寸、build／api／composables、Pint與Skill integrity通過；benchmark與範圍審查見docs/tt277-time-report-reads.md
- 人工畫面核准待回覆, 不改closed；視覺檢閱http://127.0.0.1:5292, 僅合成資料。明確交接後可先接TT-280, 不因本地commit假定整卡完成

- Josh已核准13張畫面, 固定合成資料後依既有policy比較通過, 8400像素退化注入確實失敗並清舊成功產物。本人後續驗收工時8分鐘已填, 合計33分鐘；0026607為實作提交, 本階段保存approval及重播比較, 結案後接280
