# 公開入口與路由

2026-11-13。SEO看板TT-205～208交付；規劃來源docs/public-login-landing-seo-plan-2026-11-11.md。決策由使用者授權採Claude建議。

## 路由表（TT-205）

維持Hash Routing；`#`之後不送伺服器，伺服器只看到路徑。

| 網址 | 未登入 | 已登入 | 伺服器 |
| --- | --- | --- | --- |
| `/`（含`/#/…`深層連結） | 登入頁＋訪客TopBar；hash保留，登入後readHash直接進入原深層連結 | App（不顯示訪客TopBar） | `try_files`→`/index.html` |
| `/#/invite?token=…` | 註冊／登入並接受邀請，完成後換成`#/` | 接受邀請流程 | 同上 |
| `/about/` | 靜態公開介紹＋訪客TopBar（原始HTML含完整正文） | 內容照常可讀，TopBar隱藏，CTA改「回到工作空間」 | 靜態目錄索引`$uri/index.html` |
| `/about` | 301→`/about/`（相對Location，唯一網址） | 同左 | `location = /about` |
| `/robots.txt`、`/sitemap.xml` | 靜態檔（TT-208） | 同左 | `try_files $uri` |
| `/api/*` | Laravel；未登入401 | Session／CSRF與permission照舊 | FastCGI |
| 其他路徑 | SPA fallback | 同左 | `/index.html` |

- 登出：App清除scope並把hash換成`#/`，回到登入頁。重整、瀏覽器返回、舊分享的`/#/projects/...`與`/#/issue/<id>`維持既有行為（未改readHash）。
- 不新增第二套前端、不做SSR／常駐Node、不改API。`/about/`以Vite多頁建置成同一映像的靜態HTML（TT-207）。
- 驗證：以nginx 1.27容器載入正式設定（PORT替換、API改固定狀態）實測：`/`→SPA、`/about`→301 `Location: /about/`、`/about/`→介紹頁、`/projects/x`→SPA、`/api/*`→後端，`nginx -t`通過。

## 訪客TopBar（TT-206）

- `GuestTopBar.vue`＋`src/guest-topbar.css`：品牌（回`/`）、「產品介紹」(`/about/`)、「登入」(`/`)，目前頁以`aria-current="page"`與底線標示；連結觸控高度44px、鍵盤焦點框可見。
- 只在未登入的AuthGate與`/about/`出現；App在驗證完成前不渲染AuthGate，所以登入狀態載入時不會先閃出訪客導覽。登入後App無訪客TopBar；登出回到登入頁再出現。
- `/about/`使用相同HTML結構與CSS檔（TT-207），已登入時隱藏並把CTA改成「回到工作空間」。
- 驗證：`tools/guest-pages-check.js`（隔離API）驗目前頁、Tab順序、≥44px、三尺寸無橫向溢出、登入後TopBar消失且保留`#/projects`深層連結。登入頁在核准視覺基準（01-login／02-validation）內，新TopBar造成預期差異，需人工核准後才更新基準。

## 公開介紹頁（TT-207）

- `frontend/about/index.html`為Vite第二個HTML入口，建置成`dist/about/index.html`（同一映像，Dockerfile已COPY）。原始HTML即含title、description、H1、功能摘要、開始步驟與CTA，不需執行JS；圖像為以SVG繪製的看板示意（非偽造截圖）。文案只描述現有功能，使用者授權採Claude建議定稿，之後可再調整。
- `about.js`只呼叫`/api/csrf-cookie`與`/api/auth/current`判斷是否登入：已登入加`signed-in`隱藏訪客TopBar並把CTA換成「回到工作空間」；失敗一律視為訪客，不讀私人資料。載入期間以`auth-pending`暫時隱藏TopBar（3秒保底解除）；無JS時不加class，TopBar正常顯示。
- 驗證：`VITE_SITE_ORIGIN=http://127.0.0.1:5175 E2E_API_URL=… node tools/guest-pages-check.js`驗原始HTML內容、無JS可讀、不含私人資料、已登入隱藏TopBar與CTA切換、三尺寸無橫向溢出。

## SEO與分享Metadata（TT-208）

- `/about/`：`lang="zh-Hant"`、title、description、`robots index,follow`、canonical、Open Graph（type／locale／site_name／title／description／url／image 1200×630／alt）與`twitter:card`。
- 絕對網址由建置環境`VITE_SITE_ORIGIN`（例如正式Fly網域）填入；未設定時移除canonical／og:url／og:image並不產生sitemap，避免寫死錯誤網域。正式部署（TT-155）須以`--build-arg VITE_SITE_ORIGIN=https://<正式網域>`建置。
- `og-image.png`：`frontend/tools/render-og-image.js`以HTML繪製後存`frontend/public-assets/og-image.png`，建置輸出到`/og-image.png`；內容為產品示意，不含真實資料。
- `robots.txt`：允許`/about/`、拒絕`/api/`，有origin時附Sitemap。`sitemap.xml`只列`/about/`；不列Workspace／Issue／帳號／API。
- `/`（登入頁＋App殼層）加`noindex, follow`：沒有公開正文，不參與排名。robots／noindex不是權限控管，私人資料仍由Session／permission／scope保護。
- 建置調整：兩個HTML入口時關閉modulepreload polyfill、TopBar樣式由about.css以`@import`內嵌，避免與App共用chunk，登入頁維持單一JS（bundle-check通過）。
- 驗證：正式build＋preview跑`guest-pages-check`（E2E_PREVIEW=1）驗原始HTML metadata、robots、sitemap只含公開頁、og:image為PNG、`/`為noindex。Discord／LINE實際分享預覽需公開網域才能測，留待TT-155部署驗收。
