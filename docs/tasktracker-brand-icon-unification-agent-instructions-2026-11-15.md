# TaskTracker 品牌圖示統一：Codex／Claude 任務指令

> 日期：2026-11-15（台灣時間）
> 狀態：待 Agent 查重、拆卡、執行與驗收。
> 目的：將使用者已選定的 **紫色三柱品牌圖示**製成標準圖示資產，並統一網站各入口、瀏覽器、裝置安裝及必要的社群資源。
> 本文件是 Agent 可直接執行的任務交辦。**不要以本文件已提交就宣稱圖示已換好，也不要自行預設 TT 卡號。**

## 0. 必須遵守的設計決策

**使用者選定的是目前 TaskTracker 既有的「三根傾斜紫色長條」品牌 mark**，不是先前另外產生的「深色方框、彩色看板、藍色打勾」插圖。不要把兩者混用或自行改成另一款 logo。

目前可直接在 Repository 找到其權威定義：

- `frontend/src/styles.css`：`.brand-mark`，紫色 `#ab9cf3`、三根圓角長條、整組 `rotate(-8deg)`，供登入後側邊欄使用。
- `frontend/src/guest-topbar.css`：`.guest-brand-mark`，相同三柱構圖及紫色，供訪客導覽使用。
- `frontend/src/App.vue`：登入後側邊欄品牌入口 `<span class="brand-mark">…`。
- `frontend/src/components/GuestTopBar.vue`、`frontend/index.html`：訪客品牌入口 `guest-brand-mark`。
- 使用者在目前對話提供的 40×32 圖像是選定圖樣的視覺確認；**Repository 現有 CSS 定義比聊天中的低解析截圖更適合當作可編輯來源**。

請先檢查最新 main、working tree、CSS 實際效果與 `docs/project-engineering-bindings.md`；若有並行 Agent 修改，保留其工作。**所有新圖示都從同一份 SVG／向量 master 產出**，避免 favicon 和介面 logo 在形狀、轉角、顏色上逐漸分歧。

保留品牌目前的紫色與簡潔扁平風格；不新增文字、不加入勾勾、不把三柱改成看板、不要以全新品牌設計取代此需求。小尺寸用深色底或高對比配置以保持可讀性，背景形式須沿用圖示語意，不要增加複雜外框細節。

## 1. 開工程序：先查重、自行拆卡，不假造卡號

1. 依根 `AGENTS.md`、`frontend/AGENTS.md` 及共用 Skill／視覺回歸規範執行；開始任何任務前先查看本人在線上 TaskTracker 的紙條／交辦。
2. 在**正式線上 TaskTracker** 查詢 favicon、品牌圖示、logo、PWA、manifest、Apple touch icon、OG 圖、視覺回歸等關鍵字；確認既有 Issue 和當前狀態，不建立重複卡。
3. 將工作拆為**可獨立驗收**的任務，原則上歸產品 UI／實際功能或前端相關看板，按現有分類規範決定；必要時設定一張追蹤 Epic，非必要不硬造 Epic。
4. 一張卡只負責一個可以驗證的交付：資產產生、頁面／HTML head 引用、UI 共用元件、PWA／Apple 以及跨入口驗收可以分開或合理合併。至少提供具體目的、實際修改範圍、驗收標準、依賴與測試方式。
5. 未指派待辦依規定設 open；只在真的開始處理時 claim。不要把一次文件提交當作實作完成。使用已設定的 Codex／Claude 獨立線上身分及權限，不使用正式帳號跑測試。
6. Codex／Claude 按規範在自己的 `plans/<實際卡號>/plan.md`、`step.md` 記錄進度，分階段 Commit、驗證、回填，遵守最新 push 規範，不搶另一 Agent 的進行中卡。

## 2. 必須先做的使用位置盤點

不能只改 `favicon.ico` 就結案。先從原始碼、建置輸出規則和真正的 HTML 入口逐項搜尋：

- **公開入口** `/`：`frontend/index.html`、`frontend/landing/`、`frontend/src/guest-topbar.css`。
- **登入／App 入口** `/login`、`/app/`：`frontend/app/index.html`、`frontend/src/App.vue`、`frontend/src/styles.css`、`frontend/src/components/GuestTopBar.vue`。
- **功能介紹頁** `/features/…`：檢查各功能頁的 HTML head、共用 guest topbar、社群 metadata。
- **靜態與發布** `frontend/public-assets/`、`frontend/vite.config.js`、`docker/production/Dockerfile`、`docker/production/entrypoint.sh`、必要的 Nginx 靜態檔路由；不能假設專案使用預設 `public/` 或單一 SPA `index.html`。
- **其他品牌引用**：登入頁、空狀態、載入頁、郵件模板、OG 圖片、文件範例、圖示元件、PWA／manifest（若現況未實作，需明確區分「補齊」和「替換」）。
- 搜尋舊 `favicon`、`apple-touch-icon`、`manifest`、`logo`、`brand-mark`、`guest-brand-mark`、`og-image`、硬編碼圖示路徑與 base64 資產。

**盤點時輸出「來源位置 → 實際用途 → 預計替換／保留 → 修改檔案」表**。不要誤將任務列表、看板、狀態等一般功能 icon 當品牌 logo 全部替換。

## 3. 唯一來源與尺寸產物

使用 Agent／設計工具把現有三柱 CSS 幾何精確轉成一份可編輯向量 master（建議 `tasktracker-mark.svg`）。可依展示場景從同一幾何輸出「透明 mark」與「適合裝置磁磚的深底圖」，但不要變成兩種不同圖案。

| 用途 | 必備圖檔／尺寸 | 備註 |
| --- | --- | --- |
| 源檔 | SVG master（向量） | 保留三柱角度、長短、圓角與 `#ab9cf3` |
| Favicon | `favicon.ico`：16、32、48 px | 一個 ICO 多尺寸；明確測 16px |
| Favicon PNG | 16×16、32×32、48×48 | 路徑、檔名依現有建置慣例統一 |
| 可縮放網站 icon | SVG favicon | 支援時由 head 優先引用；ICO 作後備 |
| Apple Touch | 180×180 PNG | 適當背景、圖形居中、留白安全 |
| PWA / Android | 192×192、512×512 PNG | 若新增 manifest，需驗證實際安裝行為 |
| Maskable（建議） | 512×512 PNG | 圖形避開裁切安全區；不可直接把一般圖改標 maskable |
| 品牌資產歸檔 | 64、128、256、512、1024 px PNG | 按需產出；可由 SVG 重製，勿手工放大 40px 截圖 |

若專案還未提供真正的 PWA 註冊／離線能力，**manifest 只描述圖示與 App metadata，不得宣稱已支援完整離線 PWA**；不要因此順便引入 Service Worker、離線快取及不可控的更新機制。

SVG 需能在 HTML 中安全引用，不使用外部字型、JS、外部圖片 URL、動態載入或未經審核的 SVG 元素；PNG／ICO 格式應可被實際解碼，真尺寸、色彩空間、透明背景與非空像素正確。產生腳本或可重複製程也要放在 Repo 中，避免以後只有一套不可維護的手工輸出。

## 4. UI 與 Head 的一致性

- 維持 TaskTracker 文字名稱與現有導航位置，將三柱 mark 的 SVG／元件變成可重用來源；避免登入後側邊欄與公開頁各維護不同高度、角度和顏色。若決定保留 CSS 實作，也必須確保與 master vector 在視覺上相同。
- 讓公開首頁、功能頁、`/login` 和 `/app/` 都有一致的 `rel=icon`、Apple touch 及必要的 `rel=manifest` 引用，並確認 canonical／base path／cache 不會讓某些入口仍顯示舊 logo。
- 現有 `og-image.png` 是 1200×630 的社群分享圖；**先檢查是否真的需要替換其中舊 logo**，保留產品展示、SEO 文案與 1200×630 比例，不要直接用 512px favicon 取代整張 OG 預覽。
- 若郵件模板目前只有文字標頭，無需為了這項任務無故增加圖片、追蹤請求或外部資產。
- SVG／PNG 路徑與 Cache-Control 需考慮 Cloudflare Proxy 與瀏覽器 favicon 快取。變更引用時使用可追蹤的版本檔名或受控快取刷新，不建議全站禁快取；Cloudflare Purge 必須取得部署環境授權。
- 不改動正常業務 API、授權、MySQL、R2、PAT、Fly Secrets 或正式資料庫。

## 5. 技術驗收：不只產圖檔

### 資產檢查

- 所有必備圖檔存在、尺寸與類型正確，單一 SVG master 可重製，顏色和三柱幾何一致。
- **16×16、32×32 視覺驗證**：三柱仍可區分，不能只看 1024px 效果；在深／淺背景下有足夠對比，沒有不合理模糊、形變、裁切。
- 檔案大小合理，無不需要的內嵌字型或外部依賴；不把低解析截圖直接插值放大當 master。

### 實際頁面與瀏覽器

- 用專案規定的**隔離** Laravel／MySQL、Chrome／Playwright，驗證 `/`、`/features/…`、`/login`、`/app/` 的 favicon／資產請求均 HTTP 200，MIME 正確；預設／深色模式（若有）沒有舊 icon。
- 驗側邊欄和 guest topbar 品牌 mark；桌面、平板、手機三尺寸沒有位移／橫向溢位，含鍵盤焦點、aria-label。
- 檢查 manifest／Apple touch；若不支援安裝，明列不適用，不能假裝 PWA 已驗收。
- 檢查 Vite build、正式 Docker 映像中的檔案位置與靜態伺服器路由。**本地打得開不代表正式 Fly 映像有包含圖片**。
- 依 `docs/visual-regression.md` 及目前共用 Skill 執行候選、差異、核准比較；保留原基準與實測證據，不直接洗 baseline 或提高 tolerance。
- GitHub Commit／Push 不等於正式發布；正式站的 Cloudflare CDN／瀏覽器快取、重新整理與 bookmark icon 需部署後另驗。

## 6. 指令給執行本文件的 Agent

**請直接依本規格執行下列流程，不用再向使用者詢問已經選好的 logo：**

1. 讀根／前端 AGENTS 和必要 Skills，檢查最新程式碼／未提交差異。
2. 確認三柱品牌 mark 實際來源，並完成全站圖示引用盤點。**絕不可用先前藍色勾勾看板圖代替**。
3. 使用線上 TaskTracker 按規範查重、自行拆成適當大小的卡片、分類看板、寫入明確驗收與依賴；回報實際建立／沿用的卡號。
4. 依現有 Agent 協作規範逐張領卡實作，產出同源 SVG／各尺寸圖片、修正引用和必要產生工具，分階段執行測試、Commit、Push、回填。
5. 報告**產出的實際檔名與路徑、每個舊引用的處理結果、建置／視覺／瀏覽器驗證、待部署驗收事項**；有失敗或未驗不得當作已完成。
6. 正式部署、新增 PWA Service Worker 或 CDN 清理需另外核對必要權限與操作影響，不得因圖示任務擅自操作。

**完成定義：不只 favicon，而是 TaskTracker 所有使用品牌 mark 的場景都一致；多尺寸來源可維護，且正式映像及瀏覽器有可核對的驗證結果。**
