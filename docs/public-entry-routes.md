# 公開入口與路由

2026-11-13。SEO看板TT-205～208交付；規劃來源docs/public-login-landing-seo-plan-2026-11-11.md。決策由使用者授權採Claude建議。

## 路由表（TT-297, 取代TT-205的「/ 是登入頁」）

2026-11-13 Josh參考Oracle官網, 決定首頁直接放產品介紹, 登入移到 `/login`, 工作空間移到 `/app/`
工作空間本身還是Hash Routing, `#` 後面的內容不會送到伺服器

| 網址 | 沒登入 | 已登入 | 伺服器 |
| --- | --- | --- | --- |
| `/` | 產品介紹＋訪客TopBar, 原始HTML就有完整內容 | 轉到 `/app/` | `try_files $uri $uri/index.html` |
| `/#/…`（舊的分享連結、`/#/invite?token=…` 邀請信） | 首頁顯示前就轉到 `/app/#/…`, 再落到 `/login#/…` | 轉到 `/app/#/…` | 同上 |
| `/login` | 登入／建立工作空間, 帶 `#/invite?token=…` 時顯示邀請表單 | 同一頁換成 `/app/`, hash保留, 不重新載入 | `try_files /app/index.html` |
| `/app/` | 同一頁換成 `/login`, hash保留, 登入後回到原本要去的頁面 | 工作空間 | `try_files $uri /app/index.html` |
| `/about`、`/about/` | 301到 `/` | 同左 | `return 301 /` |
| `/app`、`/login/` | 301到 `/app/`、`/login` | 同左 | 同左 |
| `/robots.txt`、`/sitemap.xml`、`/og-image.png` | 靜態檔 | 同左 | `try_files $uri` |
| `/api/*` | Laravel, 沒登入回401 | Session／CSRF與權限照舊 | FastCGI |
| 其他路徑 | 404, 避免任意網址都長得跟首頁一樣 | 同左 | `=404` |

- `/login` 和 `/app/` 其實是同一個頁面（`frontend/app/index.html`）, `src/entryPath.js` 等登入狀態確定後才調整網址：已登入放 `/app/`, 要顯示登入表單時放 `/login`；還在連線或出錯時不動網址。登出直接換成 `/login`
- 已登入的人開首頁時, 為了不先閃一下介紹頁, 首頁會先看 `localStorage` 的 `tasktracker:signed-in` 提示。這只是提示, `/app/` 還是會跟後端確認, 失效的話會落到 `/login` 並清掉提示, 不會一直轉來轉去。沒有提示時由 `landing.js` 呼叫 `auth/current` 判斷
- 系統裡產生的邀請連結是用目前網址組的, 現在會是 `/app/#/invite?token=…`；已經寄出去的舊 `/#/invite` 連結由首頁轉址, 一樣能用
- 開發和預覽伺服器（dev／preview）用 `vite.config.js` 的 `entryRouting` 模擬同一套路由, 讓E2E測到的行為跟正式nginx一致
- 驗證：用nginx 1.27容器載入正式設定, 上表每條路由都實測過, 301用相對Location, `/login` 是no-cache, 不存在的路徑回404, `nginx -t` 通過。`guest-pages-check` 在dev和正式build（E2E_PREVIEW=1）都通過, 有測舊hash和邀請連結轉址、登入後回原頁、已登入開 `/` 和 `/login` 會進工作空間、殘留提示不會循環

## 訪客TopBar（TT-206）

- `GuestTopBar.vue`＋`src/guest-topbar.css`：品牌（回`/`）、「產品介紹」(`/`)、「登入」(`/login`)，目前頁以`aria-current="page"`與底線標示；連結觸控高度44px、鍵盤焦點框可見。
- 只在未登入的AuthGate（`/login`）與首頁出現；App在驗證完成前不渲染AuthGate，所以登入狀態載入時不會先閃出訪客導覽。登入後App無訪客TopBar；登出回到登入頁再出現。
- 首頁使用相同HTML結構與CSS檔（TT-207），已登入者直接轉進工作空間（TT-297）。
- 驗證：`tools/guest-pages-check.js`（隔離API）驗目前頁、Tab順序、≥44px、三尺寸無橫向溢出、登入後TopBar消失且保留`#/projects`深層連結。登入頁在核准視覺基準（01-login／02-validation）內，新TopBar造成預期差異，需人工核准後才更新基準。

## 公開介紹頁（TT-207）

- 原`frontend/about/index.html`，TT-297起為網站首頁`frontend/index.html`（樣式與程式在`frontend/landing/`），工作空間殼層移到`frontend/app/index.html`；同一映像，Dockerfile已COPY。原始HTML即含title、description、H1、功能摘要、開始步驟與CTA，不需執行JS；產品示意TT-301起改成HTML組成的看板、待辦、轉任務和工時片段, 手機上字一樣讀得清楚, 都標示是示意畫面（非偽造截圖）。文案只描述現有功能，使用者授權採Claude建議定稿，之後可再調整。
- `landing/landing.js`（原 `about.js`）只呼叫 `/api/csrf-cookie` 和 `/api/auth/current` 判斷有沒有登入, 有登入就轉進 `/app/`（TT-297）, 失敗一律當訪客, 不讀私人資料。判斷期間用 `auth-pending` 先藏起TopBar, 最多3秒；沒有JS時不加這個class, TopBar照常顯示
- 驗證：`VITE_SITE_ORIGIN=http://127.0.0.1:5175 E2E_API_URL=… node tools/guest-pages-check.js`驗原始HTML內容、無JS可讀、不含私人資料、已登入隱藏TopBar與CTA切換、三尺寸無橫向溢出。

## SEO與分享Metadata（TT-208）

- 首頁`/`（TT-297前為`/about/`）：`lang="zh-Hant"`、title、description、`robots index,follow`、canonical、Open Graph（type／locale／site_name／title／description／url／image 1200×630／alt）與`twitter:card`。
- 絕對網址由建置環境`VITE_SITE_ORIGIN`（例如正式Fly網域）填入；未設定時移除canonical／og:url／og:image並不產生sitemap，避免寫死錯誤網域。正式部署（TT-155）須以`--build-arg VITE_SITE_ORIGIN=https://<正式網域>`建置。
- `og-image.png`：`frontend/tools/render-og-image.js`以HTML繪製後存`frontend/public-assets/og-image.png`，建置輸出到`/og-image.png`；內容為產品示意，不含真實資料。
- `robots.txt`：允許`/`、拒絕`/api/`與`/app/`，有origin時附Sitemap。`sitemap.xml`只列首頁`/`；不列Workspace／Issue／帳號／API。
- `/login`與`/app/`（共用殼層）加`noindex, follow`：沒有公開正文，不參與排名。robots／noindex不是權限控管，私人資料仍由Session／permission／scope保護。
- 建置調整：兩個HTML入口時關閉modulepreload polyfill、TopBar樣式由landing.css以`@import`內嵌，避免與App共用chunk，登入頁維持單一JS（bundle-check通過）。
- 驗證：正式build＋preview跑`guest-pages-check`（E2E_PREVIEW=1）驗原始HTML metadata、robots、sitemap只含首頁、canonical為`/`、og:image為PNG、`/login`與`/app/`為noindex。Discord／LINE實際分享預覽需公開網域才能測，留待TT-155部署驗收。
