# TaskTracker 公開介紹頁、登入入口與 SEO 規劃

> 討論日期：2026-11-11  
> 狀態：**規劃／待建立卡片／尚未實作**  
> 用途：供 Codex 閱讀、在 TaskTracker 中建立後續待辦卡片；**本次僅新增規劃文件，不修改應用程式碼**。

## 一、使用者確認的產品方向

1. 網站根目錄 `/` 是**登入頁**，不是公開產品介紹頁。
2. 另外建立一個**公開介紹頁**，暫以 `/about` 作為候選網址（最終名稱實作前確認）。
3. 未登入時，**登入頁與公開介紹頁頂部共用同一個 TopBar**，提供兩個頁面間的切換，例如「登入」與「產品介紹」。
4. **登入成功後 TopBar 消失**，直接進入現有 TaskTracker 應用；不要在登入後的工作區額外顯示行銷導覽。
5. 保留 Vue SPA 作為任務管理介面；不因 SEO 改成全站 SSR / Nuxt。
6. **前後端一起封裝到單一 Fly.io App**：Vite Build Vue 靜態檔，Laravel 提供 API，單一 Docker Image、一次部署；不另外使用 Cloudflare Pages 部署前端。
7. Cloudflare 仍管理自有網域、DNS、CDN、HTTPS；Oracle MySQL / Redis 仍是另行規劃的持久化服務。
8. 目前先完善產品功能與正式部署，**公開介紹頁與 SEO 屬後續開發**。先建卡，不立即開工。

> 注意：原先「`/` 是公開介紹頁、`/app` 是 SPA」只是討論中的初步建議，**已由這次使用者明確改為「`/` 是登入頁、公開介紹頁另設路徑」**；後續不可把過時版本當成定案。

## 二、現況盤點與相容性

根據 2026-11-11 的 TaskTracker 專案 README 與 `frontend/src/App.vue`：

- `frontend/`：Vue 3 + Vuetify 4 + Vite SPA；`backend/`：Laravel 13 JSON API。
- 使用 Hash Routing；現有登入後首頁 `/#/` 是個人小待辦，`/#/projects` 進專案列表，其他專案、看板、Issue 均有深層連結。
- `App.vue` 透過 `authenticated`、`AuthGate` 等處理登入／應用狀態；需先盤點實際 Router 與導覽條件，**不得直接覆蓋既有登入後首頁**。
- 目前 Reverb／輪詢、Fly.io／Oracle 拆分屬另一條部署與同步工作線；本規劃不要求一併實作或變更。

### 建議路由責任

| URL / 情境 | 未登入使用者 | 已登入使用者 | SEO |
| --- | --- | --- | --- |
| `/` | **登入頁**，顯示訪客 TopBar | 進入現有 App 首頁或明確轉往 `/#/`；**不顯示訪客 TopBar** | 登入頁不需要競爭搜尋排名，可 `noindex` |
| `/about`（候選） | **公開產品介紹頁**，顯示訪客 TopBar | 仍可查看公開介紹內容，但**隱藏訪客 TopBar** | 主要可索引頁面 |
| `/#/` | 依現有認證與轉向規則顯示登入入口 | **保留原有個人小待辦首頁** | 不索引 |
| `/#/projects`、其他既有 Hash 路由 | 依現有權限流程導向登入／返回原頁 | 維持既有 SPA 工作功能 | 不索引 |
| `/api/*`、`/sanctum/*` 等 | Laravel API／認證 | Laravel API／認證 | 不索引 |

`/about` 只是建議的**公開頁面候選 URL**，不是要求立刻新增所有路由或全面換掉 Hash Routing。登入後入口以目前 App 實際實作為準，避免憑空新增不同路由系統。

### TopBar 行為規範

- 只屬於「**訪客／公開站點 Layout**」，不要和現有 TaskTracker 登入後的 App Header、側欄混為一談。
- 在 **未登入** 的 `/` 與 `/about` 呈現，選項至少包含「登入」及「產品介紹」，可顯示 TaskTracker Logo；選中頁有清楚狀態。
- 登入成功後，無論目前路徑為何，訪客 TopBar 都**隱藏**；登入後應沿用現有工作區導覽，不需要行銷導覽。
- 若使用者已登入仍直接開 `/about`，公開介紹內容可以繼續閱讀，但訪客 TopBar 不顯示；是否提供「返回工作區」的非 TopBar 入口留待 UI 設計時決定。
- 登出後回到登入頁，依未登入狀態重新出現 TopBar。
- 不要先渲染訪客 TopBar 後才因認證查詢把它移除，避免畫面閃爍；需處理 auth loading / 未登入 / 已登入三種狀態。
- 桌面與手機都能操作，且不應影響登入表單、鍵盤焦點、深層連結及現有工作區布局。

## 三、沒有 SSR，如何讓公開介紹頁被搜尋引擎與社群看見？

**登入後 App 可以維持 Vue SPA；公開介紹頁需提供爬蟲可直接讀取的 HTML。**

建議優先考慮以下低複雜度方案：

- 在**同一個 Fly.io Docker Image** 中，將 `/about` 建成靜態 HTML 或建置時預先渲染（SSG / pre-render）；由 Nginx／既有 Web Runtime 回傳。
- `curl https://正式網域/about` 取得的原始 HTML 應有實際標題、描述、H1、主要產品介紹與 CTA，不必等待瀏覽器執行 JavaScript 才看到正文。
- `vite build` 本身只會編譯 SPA 資產，**不等於自動為所有 Vue 路由產生 SEO 友善的 HTML**。實作時先評估最小改動，不預設導入 Nuxt、SSR 常駐 Node Server 或第二個 Fly App。
- 訪客 TopBar 可在 `/about` 的 HTML 作為公開導覽輸出，再透過前端 auth 狀態於已登入時隱藏；SEO 內容不得依賴私有 API。
- 公開介紹頁與登入頁保持視覺一致（品牌、配色、TopBar）；登入後 App 保持既有介面，不要求重設計。

### 公開介紹頁的最小 SEO 需求

- HTML `lang="zh-Hant"`、明確 `<title>`、meta description、H1、實際產品介紹段落、功能摘要、CTA。
- Canonical URL、Open Graph `og:title` / `og:description` / `og:image` / `og:url`、基本分享卡片 Metadata。
- `robots.txt` 與 `sitemap.xml` **只列公開、可索引的 URL**，不列私有看板、Issue、Workspace 或帳號頁。
- 在 Discord、LINE 等社群分享 `/about` 時，即使分享爬蟲不執行 JavaScript，也應能讀到標題、描述和圖片。
- 驗證手機 RWD、語意化 HTML、無障礙基礎、HTTPS 與正確快取行為。
- **私人資料必須由後端 Session、permission 與 Workspace／Project scope 保護**；`robots.txt` 或 `noindex` 不是權限控管手段。

## 四、部署架構不新增第二套前端

```text
使用者 / 搜尋引擎
        |
 Cloudflare（既有網域 / TLS / CDN）
        |
 Fly.io：單一 App / 單一 Docker Image
        |
  Nginx / PHP Web Runtime
       |--------- GET /           -> 登入頁（訪客 TopBar）
       |--------- GET /about      -> 公開介紹頁（訪客 TopBar + 原始 HTML SEO）
       |--------- GET /#/...      -> 現有 Vue SPA（登入後 TopBar 隱藏）
       |--------- /api/*          -> Laravel API
       |--------- /sanctum/*      -> Laravel Session / CSRF
        |
 Oracle MySQL / Redis（持久化服務，與本規劃相互獨立）
```

> 技術提醒：URL 的 `#` fragment **不會送到伺服器**。上圖的 `/#/...` 是瀏覽器 URL，伺服器實際收到的是 `/`，不能依靠 Nginx 直接解析 fragment。因此將登入頁設於根目錄時，必須特別設計 SPA 載入、訪客登入入口、既有 hash 路由相容方式。若採不同 HTML entrypoint，先處理舊連結與 auth bootstrap。上圖只是責任示意，非現成 Nginx 設定。

- Vue Build 與 Laravel Runtime 仍一次完成，正式環境不開 Vite Dev Server。
- `/about` 可以靜態 CDN 快取；含敏感 Session 或個人資訊的 API 回應不得被不當共用快取。
- Cloudflare/Fly.io 的 Auto Stop、冷啟動，以及搜索爬蟲訪問對喚醒行為的影響，留到部署驗證階段量測。
- 同網域路由需維持原本 CSRF、登入與登出、Cookie、回跳、API 404 行為。

## 五、供 Codex 建卡的後續任務

> 這是**建卡規格**而不是已建卡或已完成的功能。Codex 應先檢查 TaskTracker 既有卡片與需求，查重後才新增。預設狀態為待辦，不應在建卡時直接實作。

### A. 入口與路由相容設計

**內容**：盤點 `App.vue`、`AuthGate`、Hash Routing、認證導頁及 Docker/Nginx 路由；規劃根目錄登入頁與 `/about`，保留 `/#/` 等原本登入後頁面和所有深層連結。

**驗收**：提供路由對照表；重新整理、返回鍵、登出與登入回跳正常；舊分享連結不失效；釐清 fragment 與伺服器路由的分工。

### B. 訪客 TopBar 與登入狀態切換

**內容**：登入頁／公開介紹頁共用訪客 TopBar；登入後 TopBar 消失、使用既有 App 介面，登出後恢復；不把訪客 UI 混入私人工作區。

**驗收**：登入前兩頁切換正常；登入後 TopBar 完全隱藏；Auth Loading 無閃爍；已登入直接開 `/about` 的行為符合規範；手機可操作。

### C. 公開 TaskTracker 介紹頁（靜態 HTML 或預先渲染）

**內容**：公開介紹內容、功能摘要、產品圖像與 CTA；與登入頁保持品牌一致；不依賴登入或資料庫即可取得公開正文。

**驗收**：未登入可開 `/about`；`curl` 原始 HTML 有可理解正文；不暴露私有專案／任務；RWD／無障礙基本檢查通過；產品文案公開前另由使用者確認。

### D. SEO 與連結分享 Metadata

**內容**：Title、Description、Canonical、Open Graph、分享預覽圖片、Sitemap、Robots、必要的 noindex 策略。

**驗收**：Discord/LINE 分享 `/about` 有合理預覽；Sitemap 不含私人頁面；不用 JS 的爬蟲可以讀到公開資訊；不把 robots/noindex 誤用成資料安全措施。

### E. 單一 Fly.io Docker Image 整合驗證

**內容**：在同一個 App 對應登入頁、介紹頁、SPA、Laravel API；不另外開 Pages 或 SSR 長駐服務；確認 Cloudflare 快取與 Fly Auto Stop 沒有破壞可達性。

**驗收**：正式環境一次部署；根目錄、`/about`、既有 hash 連結、`/api/health`、登入、登出、Session、CSRF 及權限流程通過測試；沒有額外常駐 Node SSR。

### F. （可選／後期）搜尋收錄與公開頁量測

**內容**：公開後視需要設定 Search Console、提交 Sitemap、驗證索引結果、檢查外部搜尋可發現性。

**驗收**：只有公開頁被列為可索引；紀錄驗證與修正，不因產品初期低流量先擴大 Analytics 範圍。

## 六、實施順序與限制

1. **這次只新增這份 Markdown**，不調整既有應用程式或部署。
2. 後續請 Codex 讀 `README.md`、`AGENTS.md`、本文件及最新程式，先查重，再將 A–E 建成 TaskTracker 待辦卡，F 可暫緩；依既有 AI 帳號與追蹤規範操作，不使用 Owner 權限繞過限制。
3. 先完成產品核心功能及低成本部署。要開始公開頁時，**先解 A（路由）與 B（TopBar）**，再進行 C、D、E。
4. 不要為追求 SEO 改寫既有 Vue SPA、重做 Laravel API、刪除私人資料或重設使用者的登入／工作區模型。
5. 未來若路由與部署架構已變更，以當時 GitHub 真實狀態為準重新評估；文件為待辦規劃，不是「功能已完成」的宣告。

## 七、相關文件

- [TaskTracker README](../README.md)
- [專案自我追蹤與 AI 建卡規範](tasktracker-self-tracking.md)
- [低成本部署與同步重構計畫](planning/TaskTracker_低成本部署與同步重構計畫-2026-11-11.md)
- [產品討論紀錄](產品討論紀錄.md)
