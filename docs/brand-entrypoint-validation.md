# 品牌圖示接線與驗證

2026-11-15／TT-363, 資產來源見[盤點](brand-assets-inventory.md)

## 已接線

- App側欄與GuestTopBar共用無狀態BrandMark.vue, 公開首頁與四個功能頁也讀同一SVG master。移除App／guest兩份CSS三柱幾何, 保留原導覽、文字名稱與紫色
- 六份HTML head統一版本ICO、16／32／48 PNG、SVG favicon與Apple180, Vite以hash路徑輸出, 不把icon內嵌成data URI
- 裝置192／512與maskable512使用版本檔名, manifest指向/app/, root favicon.ico保留一日fallback。只描述App metadata, 沒有Service Worker或離線能力
- dev、preview與build共用staticAssets, 解決manifest／裝置icon在開發模式404。正式nginx對hash及版本檔案immutable, manifest no-cache且application/manifest+json
- OG圖仍1200×630、保留原內容與構圖, 製圖腳本改讀master, 不用favicon取代整張分享圖。郵件純文字與一般功能icon未改

## 實測

| 範圍 | 結果 |
| --- | --- |
| Codex隔離Laravel／MySQL、真實Chrome | dev與preview的六個guest入口、App、同源mark、head圖示200及MIME、manifest解析、鍵盤、三尺寸無橫向溢位通過 |
| 正式格式隔離Docker映像 | nginx實際七入口icon、hash快取、manifest MIME及no-cache、版本裝置圖與fallback200通過, runtime既有公開契約檢查也通過 |
| 前端冒煙 | routes／API client／composables、Vite build及真實側欄連結與草稿保護通過 |
| 全站視覺 | 同Chrome157的162張比較通過, 六個行為流程通過 |
| 新舊差異審查 | 35張不變, 127張差異都在預期品牌列y<90, 未延伸到內容區 |
| 比較器校準 | 原樣／三種退化注入／還原均達預期, 沒有提高threshold或maxDiffPixels |

原162張與核准紀錄封存.local/tt363-visual-before, 新候選依Josh持續授權檢閱核准。首輪誤用bundled Chrome153的比較只留診斷, 不作驗收；正式結果使用與舊核准相同的Chrome157。來源指紋新增frontend/app及public-assets, 避免HTML head或圖片異動後仍接受舊證據

目前尚未發布TaskTracker新版, 正式網址、CDN與使用者既有favicon快取要在整批部署後再驗。只驗manifest與browser metadata, 未驗Apple／Android真機安裝, 不宣稱完整PWA
