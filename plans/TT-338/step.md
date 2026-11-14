# TT-338 進度

- 已讀Josh原紙條與新增卡片, 先處理原分頁無法同步登入問題與單一連結流程
- 發現AuthGate只保留本地pending畫面, 未監聽啟用分頁完成；後端current亦優先回pending資料而不檢查本人是否已在另一分頁啟用

- 啟用畫面與信件已改單一連結, 正式契約也移除code；local/testing fixture相容值沒有產品輸入欄位
- 真瀏覽器確認同瀏覽器雙分頁啟用同步與重整, 無關瀏覽器401及token重播400
- 後端第一輪415項／17918斷言通過, 1項既有skip；新增兩個引擎的跨帳號測試後正在回歸, 不沿用舊輪作最後驗收
- 已依持續授權檢視並保存81張候選基準, 正式比對進行中；完整瀏覽器回歸依序執行, 不與同資料庫的後端測試並跑

- 不同瀏覽器的Cookie／storage不共享情境實測通過, 原pending分頁可在focus後恢復；偽造storage訊號不放行
- 最後補的跨帳號案例需要以HTTP logout清除Laravel測試Session, 已修正fixture邊界；SQLite 12項／128斷言通過, 仍待同一套完整MySQL回歸結束
- Session OpenAPI官方3.1離線Schema通過, AuthGate審美靜態lint為0 FAIL／0 WARN；保留既有品牌與版面
- backend/phpunit.xml另有TT-335相關改動, 本卡不暫存或提交
- 最新後端完整回歸：共417項／17954斷言, 1項既有skip, 其餘通過, 包含SQLite與隔離MySQL；Pint通過
- 正式視覺比對在PHPDoc修整後再次通過81張, approved／environmentMatches均true, 基準門檻沒改
- 60支前端批次：33通過、15環境／前置條件不足、12失敗或逾時；清單在artifacts/tt338/browser-classified.json, 交TT-336／TT-334追蹤, 不宣稱整體回歸通過
- 冒煙（routes／API／composables／build／真瀏覽器註冊啟用與側邊欄）通過；正式本人工時20分鐘已填, 不計無人等待
