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
