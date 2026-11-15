# Pavenro — Season 1 預備品牌圖檔

這裡是尚未啟用的 Pavenro 品牌圖檔，**不可提前替換 TaskTracker 正式網站、favicon、manifest 或 SEO**。正式切換由 Season 1 任務統一處理。

## 用途對照

| 檔名 | 用途 |
| --- | --- |
| pavenro-mark-source.svg | 標誌主檔；以向量為唯一基準，不用從整張展示圖裁切當正式 logo |
| pavenro-logo-horizontal.svg | 淺底網站 Header、產品介紹頁 |
| pavenro-logo-horizontal-inverse.svg | 深底 Footer、深色介面 |
| pavenro-favicon-16.svg | 16×16 瀏覽器小圖示，Season 1 可另匯出 PNG |
| pavenro-favicon-32.svg | 32×32 瀏覽器分頁圖示，Season 1 可另匯出 PNG |
| pavenro-favicon-48.svg | 48×48 Windows / 桌面捷徑，Season 1 可另匯出 PNG |
| pavenro-apple-touch-180.svg | 180×180 Apple Touch Icon 原始檔；正式應產生 PNG |
| pavenro-app-icon-192.svg | 192×192 PWA Manifest 來源；正式應產生 PNG |
| pavenro-app-icon-512.svg | 512×512 PWA Manifest 來源；正式應產生 PNG |
| pavenro-og-1200x630.svg | 1200×630 社群 Open Graph 來源；正式應產生 PNG/JPEG |

## Season 1 啟用驗收

1. 統一檢查 Logo 與原先已討論的藍紫漸層 P＋勾選符號視覺方向；核准後固定版本。
2. 以同一版 SVG 匯出實際 PNG：16、32、48、180、192、512，以及 OG 1200×630，產生 multi-size favicon.ico。小尺寸需真人眼驗收（16×16 勾選圖形是否辨識）。
3. 更新 Cloudflare Pages 介紹頁和 Fly.io App 的 logo、favicon、apple-touch-icon、webmanifest、OG/Twitter 圖片；不要在 Season 1 前改任何實際引用。
4. SEO 檢查：兩站圖示連結不 404、公開圖片可匿名取得、適當 cache headers、OG / Twitter 1200×630 URL 可抓、中文和英文頁面的 title/description/canonical/hreflang 正確。
5. 同步更新 Pavenro 產品名；執行三尺寸 UI、深色／淺色、手機安裝圖示、社群分享預覽、Google 搜尋外觀測試。
6. 若更名與 Cloudflare Pages 切換採分次上線，使用暫存版本資產並避免混用 TaskTracker / Pavenro 品牌。

備註：目前此提交提供**各用途 SVG 原始圖片**，並非已完成 PNG/ICO 輸出或正式啟用；不得將其當作 Season 1 品牌上線驗收完成。
