# TT-285 草稿離開保護改成登記制, 並抽出hash網址處理

- 來源是TT-179評估的決策2、3, 接在TT-284刪死碼之後
- 離開保護：各草稿owner自己登記dirty／saving, App只問registry, 之後新增可編輯畫面不用改App
- 導覽：hash網址的解析和組成抽成純函式, 格式和既有深層連結完全不變
- 工作區Session這次不抽, 理由寫在docs/app-coordination-evaluation-2026-11-13.md
- 不引入Router／Pinia／全面TS
