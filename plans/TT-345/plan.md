# TT-345 Laravel公告修補

- 官方公告GHSA-63hm-g96r-2m2q確認13.40.0修補Concurrency Process輸出注入；目前13.37.0, app／routes沒有Concurrency呼叫, 不推論產品已可直接被攻擊
- 只更新laravel/framework到13.40.0與必要相依, 不忽略audit、不擴大其他套件升級
- 使用既有共用工程、Ponytail lite、Laravel Patterns／Architecture；不改API、分層或業務邏輯
- Composer先不執行產品scripts, 避免碰現用環境；Docker隔離MySQL與memory SQLite驗後端全套, 冒煙驗主要瀏覽器
- 更新composer後視覺harness指紋也會變, 必須核對差異與重新綁定基準, 不放寬比較參數
- 逐階段實際工時、commit與push, 正式修補等下次整批tag部署, 不宣稱線上已更新
