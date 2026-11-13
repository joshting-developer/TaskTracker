# TT-285 步驟

- [x] 新增 src/ui/leaveGuard.js（provideLeaveGuards／useLeaveGuard）
- [x] 10個owner登記：首頁待辦、轉卡、Token、工作空間設定、個人資料、工時月曆、狀態設定、成員、專案任務頁、邀請；拿掉只給App用的dirty／saving expose和App裡用不到的ref
- [x] canLeave和beforeunload改問registry；beforeunload只看unload: true的owner, 範圍跟原本一樣
- [x] 新增 src/ui/hashRoutes.js和 npm run test:routes（17種網址組回一樣、舊連結、壞編碼）
- [x] 驗證：build、完整browser-check 11項、settings、workspaces、home、composables、time-calendar、poll-drafts、personal-query
