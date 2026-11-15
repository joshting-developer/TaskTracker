# TT-363 全入口品牌引用

預估120分鐘。TT-362資產已交付6b00f3a, 所有畫面仍保留現有導覽與紫色品牌

1. 靜態公開頁／功能頁及App head使用同源版本favicon／Apple／manifest, App與guest用無狀態BrandMark元件；移除重複CSS幾何
2. Vite明確emit manifest／裝置icon／fallback favicon, nginx驗MIME與版本快取, 不增加Service Worker
3. OG製圖改讀master但保留1200×630與原內容／版型
4. 三尺寸隔離真實瀏覽器驗所有入口資產／鍵盤／溢位, build及正式Docker runtime驗MIME與圖示檔。產品視覺候選依持續授權核准後比較, 保留舊基準
5. 提交push並回填本人實際工時, 與後台／公開頁多語系同批發布；未正式發布前保留部署後驗收未完成
