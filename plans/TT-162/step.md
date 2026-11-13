# TT-162進度

- 已讀30cdadf8交辦全文、TT-142實際盤點與最新工程／測試環境規範, 填預估120分鐘並領取
- 正在調整commit後同步寄送與驗證狀態回應, 不以Queue sync設定繞過現有明確非同步guard
- 同步模式在afterCommit呼叫交付, 連線失敗直接failed, 傳送不確定保留uncertain不自動補寄；pending Session回應刷新提交後deliveryStatus, 前端顯示可重寄錯誤
- 已拔mail分鐘排程, queue仍是可選開發模式；新增maintenance CLI供外部定時觸發既有有界附件／每日90天觀測清理, cache互斥與非0失敗退出, 沒有HTTP維護後門
- 專項47項404斷言及新增rollback／uncertain與Realtime測試14項143斷言通過（均fake）, 正在完整雙引擎回歸
- Fly App／Machine仍未配置, 外部定時觸發／喚醒／監測／費用尚未實測；本機fly0.0.475缺最新ssh選Machine參數, 不交付假定可用的雲端腳本, 後續要明列剩餘驗收
- 真Chrome以inline＋log mailer實測未寄出提示、pending重整保留、冷卻／local驗證及三尺寸通過；5199／13199冒煙通過, 沒寄真信。最後專項57項525斷言通過, Pint通過
- 完整403項17093斷言通過（1項既有skip）在最後current讀取避免重複查詢前執行, 之後專項涵蓋該改動；不重複宣稱全部frontend/tools通過
- 已交辦Josh Fly外部執行條件, 使用推薦CI短命令方向, 不新增常駐機器、不產生付費資源。完成程式階段後需保留TT-162剩餘部署條件, 不直接closed
- 69張第一次正式compare因驗證畫面倒數使用真server sent_at、瀏覽器固定時鐘而出現數字差異（162pixels）, 不是產品布局變動。視覺fixture將該匿名pending回應的時間對齊固定時鐘, 真實冷卻仍由HTTP／Chrome測試驗證；不遮罩畫面或放寬像素門檻, 保留失敗產物
- 修正視覺fixture時鐘後, 最終69張正式compare通過, approved／environmentMatches皆true；新增未寄出三尺寸, 舊基準與失敗封存, 門檻維持
- 因共用登入異動另啟動frontend/tools完整瀏覽器回歸59支（inline專項已獨立通過）, 使用5199／13199與queue合成fixture。仍執行中, 目前有舊專用埠guard／API最小回應斷言失敗, 不視為全部過關；先提交已驗獨立程式階段, 卡片維持進行中追完結果
- 程式階段已提交aa868b4, 正式工時22分鐘已填；卡片維持進行中追完完整瀏覽器結果
- 全量腳本實測找到兩個註冊fixture遺漏：account-settings的受邀成員走畫面註冊但沒驗證、attachment-view的第二租戶錯用第一個context做驗證。只修測試caller, 不放寬產品驗證／租戶隔離；待全量結束再用5199依序重跑
- 另補issue-history-order畫面註冊的OTP步驟、board-query舊button選擇器改現有sidebar link；都是舊fixture／導覽契約遺漏, 不改產品。guest-pages第一次因未提供VITE_SITE_ORIGIN移除OG標籤而失敗, 待正確測試設定重跑；其餘專用R2／正式runtime／舊13089 guard交接環境範圍
- 全量59支已結束：29支首次通過、5支修正fixture／設定後重跑通過、17支環境受阻、8支仍失敗；完整清單與失敗位置見docs/tt162-browser-regression-2026-11-14.md, 不宣稱全量通過
- 後續查重後建TT-336並確認收錄結構優化, 交辦Claude 444a850a；已存在字級問題仍由TT-334追蹤, 不降安全／可讀性／請求預算門檻
- 本次只補4支測試caller, 5支真Chrome重跑均通過, 產品來源未再修改；69張正式視覺證據仍對應程式階段
