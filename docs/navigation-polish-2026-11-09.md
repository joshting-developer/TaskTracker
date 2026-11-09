# 返回導覽整理

日期：2026-11-09。針對使用者指出的看板返回文字處理，使用UI／UX兩份skill與Vue規範，沒有重設整站設計。

原畫面是紫色底線連結，返回與Default標題分兩行；調整為中性色細框按鈕、左箭頭、13px文字與44px觸控高度，和看板名稱同列。專案列表與工時分析的同類返回共用navigation-back，保留各自目的地、文案與既有元件責任；hover／active／focus-visible及reduced-motion完整。

已登入的隔離MySQL畫面於1440／768／390檢視前後截圖：返回與名稱同列，不再有單獨連結行；手機不溢出，看板仍保留局部水平捲動。中性色減少導覽搶走卡片注意力，箭頭與邊界清楚表達操作。未新增介紹或裝飾性圖示。

驗證：production build、既有完整看板UX流程通過；另用真實瀏覽器驗三種返回入口各三尺寸，確認44px高度、無底線、focus outline、Enter可到原目的地、沒有整頁水平溢位或執行錯誤。截圖／快照與量測只留於忽略的frontend/artifacts，未寫主資料。

Skill inspector對已登入DOM／CSS快照原報1 FAIL／3 WARN：edge-text命中可橫捲看板的裁切欄與既有手機14px邊距，不是返回按鈕溢位；明確允許該規則後0 FAIL／2 WARN。兩項保留理由：NAV是使用者專案代碼，維持原大小寫；空看板留白反映實際零張卡，不用假資料填充。快照不是操作證據，表單狀態仍以真瀏覽器為準。

原始碼lint 0 FAIL／6 WARN均為既有規則：eyebrow是既有字級／隱藏樣式；shadow分別為none與真實抽屜；glass命中的是backdrop-filter:none。本輪沒有新增這些視覺效果，也沒有為規避lint改動它們。
