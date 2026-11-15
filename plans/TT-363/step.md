# 執行紀錄

- 已讀TT-363與品牌交辦文件、Vue四份核心reference及根／frontend AGENTS。BrandMark為無狀態圖片元件, 不加入資料載入或新的共享state
- 沿用TT-362 SVG master及版本圖示, 不把64～1024歸檔PNG全塞進正式頁面。公開HTML仍無JS可讀, 不改功能文案或權限
- 全入口preview及dev真實瀏覽器均通過：6個guest入口、App同源SVG、6個head icon／Apple與manifest、三尺寸／鍵盤；沒有Service Worker, 不宣稱離線PWA
- 發現manifest只在generateBundle輸出會使dev 404, 已共用staticAssets讓dev／preview／build提供相同bytes與MIME, 補dev實測通過
- 最終正式格式隔離映像已重建, Nginx實際7入口圖示200／image MIME／hashed immutable、manifest MIME與no-cache、版本裝置圖200均通過。發布CI的runtime smoke也納入同檢查
- 視覺sourceRoots新增frontend/app及public-assets, 避免head或SVG／OG改了卻沿用舊來源指紋。原核准162張及approval已備份.local/tt363-visual-before
- 首輪候選誤用bundled Chrome153, 原基準為Chrome157；已確認before/after差異有環境因素, 不當正式比較證據。保留診斷報告並改回核准Chrome執行檔重跑, 不提高threshold或maxDiffPixels
- 改回Chrome157後候選與原基準環境完全一致。對162張逐檔做不更新原圖的比較, 35張完全相同、127張差異均在y<90的預期品牌列, 沒有紅色差異延伸至內容區；diff範圍報告保存在artifacts/tt363/diff-bounds.json
- 實際檢閱桌面登入／看板及手機公開入口等候選後, 依Josh持續授權封存舊bundle並核准新162張, threshold0.02與maxDiffPixels0不變。原樣、spacing／overflow／hide-action退化注入與還原全部達預期, 正式compare 6流程／162張全部通過且environmentMatches=true
- 冒煙routes／API client／composables／build與真實側欄導覽通過。視覺工具Unit5項中4通過、1因host PHP依賴條件略過, 同工具隔離fixture guard另以真PHP驗5項18斷言通過
- 只補manifest／裝置metadata, 沒有Service Worker, 沒有宣稱已驗Apple／Android真機安裝或離線能力。正式站尚待與後台／公開頁多語系整批部署後驗favicon及快取
