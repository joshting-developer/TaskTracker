# 執行紀錄

- 已查363張既有卡及TT-362全文, 沿用資產前置卡, 估90分鐘後claim, TT-350因安全連線明確blocked
- 來源CSS有細微差異：App為5px寬、17／24／12px高、3px間距；guest為4px寬、15／20／10px高、3px間距。以App權威來源建立master, 後續TT-363共用避免再分歧
- Vite publicDir=false, 現只有og-image.png由generateBundle輸出, 不能直接假定新資產會被正式build收錄。引用與emit由TT-363處理
- 已完成唯一SVG master、SVG favicon、16／32／48 PNG與同尺寸ICO frame、Apple180、Android192／512、maskable512及透明64～1024輸出, 全部從同一幾何產出, 沒有勾勾或新品牌色
- 真實Chrome解碼12張PNG及ICO, 尺寸／非空有色像素／maskable半徑40%安全圓通過。深淺底16／32／48實際檢閱可分三柱, 原生尺寸及4倍像素圖均保留
- 資產gallery先發現跨viewport重用page的截圖不穩定, 改各尺寸獨立page並等字型及兩個繪製frame, 沒有提高容忍或改產品基準。舊初次approval留artifacts, 新候選實際檢閱後依Josh持續授權核准
- 三尺寸資產基準連續兩次逐檔SHA比較通過；重新產出所有16個來源資產後雜湊全部一致, 再比對仍通過。既有162張產品基準未修改
- 位置／用途／保留與替換表見docs/brand-assets-inventory.md。OG現有紫色三柱及1200×630構圖保留, 但製圖腳本手寫mark由TT-363改讀master
- routes／API client／composables及Vite build通過。此階段沒有改產品畫面或head, 新asset尚未emit進dist, 不宣稱頁面引用或正式部署完成；由TT-363接續
