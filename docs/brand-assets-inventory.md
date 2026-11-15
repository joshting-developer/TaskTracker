# TaskTracker品牌資產盤點

2026-11-15／TT-362資產前置, TT-363負責產品引用與部署後驗收

| 來源位置 | 實際用途 | 處理 | 後續修改檔案 |
| --- | --- | --- | --- |
| src/styles.css .brand-mark、App.vue | 登入後側欄三柱 | 用此CSS幾何建立SVG master, 引用統一由TT-363處理 | App.vue、styles.css |
| src/guest-topbar.css、GuestTopBar.vue | 登入頁及訪客導覽 | 現有柱寬／高度略有差異, TT-363改同一來源 | GuestTopBar.vue、guest-topbar.css |
| frontend/index.html | 公開首頁靜態品牌與head | TT-363引用同源mark與版本icon | index.html |
| frontend/features/*/index.html | 四個功能介紹頁 | TT-363同步靜態品牌與head | tasks／collaboration／time／integrations頁 |
| frontend/app/index.html | login／App共用HTML殼 | 現無完整favicon／Apple／manifest, TT-363補引用 | app/index.html |
| frontend/public-assets/og-image.png | 1200×630分享圖 | 實際檢閱已有紫色三柱, 保留整張構圖／文案／比例, 不以favicon取代；製圖腳本仍有手寫三柱, TT-363改讀master再重製 | tools/render-og-image.js及og-image.png |
| frontend/vite.config.js | 多入口build與OG輸出 | publicDir=false, 目前只emit OG, 新icon尚未進build, TT-363需明確emit與驗路徑 | vite.config.js |
| docker/production/Dockerfile、entrypoint.sh、nginx.conf | 發布dist與靜態路由 | TT-363驗實際映像路徑與MIME／Cache, 不是產圖即完成 | 必要時nginx.conf |
| backend/resources郵件模板 | 文字標頭 | 保留文字, 不加圖片或追蹤請求 | 無 |
| 狀態、任務、看板等功能icon | 業務操作與資訊 | 保留, 不當品牌一併替換 | 無 |
| PWA／manifest | 現無完整離線App | TT-363僅補metadata與icon時須明列非離線PWA, 不新增Service Worker | 後續manifest及head |

## 新資產

唯一master：frontend/public-assets/brand/tasktracker-mark.svg, 保留#ab9cf3、-8度、App的5px柱寬／3px間距及17／24／12px高, 不加入勾勾或文字

- tasktracker-favicon-v1.svg, 深色底同源三柱
- favicon-v1.ico, 16／32／48三個PNG frame；對應tasktracker-favicon-v1-16／32／48.png
- tasktracker-apple-v1-180.png
- tasktracker-app-v1-192.png、tasktracker-app-v1-512.png
- tasktracker-maskable-v1-512.png, mark限制在中央50%正方形, 實際有色像素驗證落在半徑40%安全圓內
- tasktracker-mark-v1-64／128／256／512／1024.png, 透明底, 依master比例置中, 不拉伸
- assets.json保存每張尺寸、master及PNG雜湊

重製：在frontend執行node tools/generate-brand-assets.js。使用既有Playwright及Chrome canvas, 不新增圖片或字型依賴。產物是產品所需source assets, dist及檢閱截圖仍不進Git

驗證：node tools/check-brand-assets.js --candidate, 檢閱後以Josh持續授權保存--approve, 再--compare。只比較獨立資產gallery及來源指紋, 不修改既有162張產品基準；產品頁面、head引用、安裝／正式映像與CDN另由TT-363驗收
