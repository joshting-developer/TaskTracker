# TT-297 後續修正（11/13）

- [x] 拍截圖候選時發現：已登入的人點舊的 `/#/projects/...` 連結, 首頁有兩段轉址（head的舊hash轉址、landing.js的已登入轉址）會互搶, landing.js轉到 `/app/` 時沒帶hash, 搶贏就把深層連結弄丟
- [x] 修正：head轉址時設旗標, landing.js看到就不再轉；landing.js自己轉時也保留hash
- [x] guest-pages-check加「已登入開舊hash連結保留深層連結」, 重複3次抓競態；dev和build都通過
- [x] 截圖測試（tools/visual/flows.spec.js）的深層連結改直接開 `/app/#/`, 舊網址轉址由guest-pages-check負責驗
