# TT-357 把上線前的自動操作測試跑完整

## 範圍
依Josh紙條授權開始, 正式預估180分鐘。只用本地合成資料, 優先免費工具；各Agent獨立schema及後端服務, 不啟動舊主庫寫入, 不新增付費資源

## 階段
1. 盤點所有前端檢查的環境需求與現有runner, 先重現抽屜等待問題
2. 補最小分組runner與必要環境設定, 修正測試時序及寫死埠；保留所有授權與隔離斷言
3. 依序驗一般瀏覽器、不同寄信／註冊模式、分頁／附件、Realtime、seed及正式映像；後端全套與瀏覽器不共用schema並行寫入
4. 寫清楚通過、失敗與缺少條件, 提交、回填工時與卡片, 完成後重新讀紙條再接後續

## 使用規範
共用side-project-development-standards與verification、task-tracker-usage及classification-and-time、Ponytail lite；Vue工具依vue-best-practices, 如涉及產品畫面／PHP再補對應Skill。測試環境依本專案tasktracker-test-environments

## 發布界線
本卡先完成本地驗證, 不把build或略過當成部署。已讀發布紙條Josh最新回覆「目前交代你的工作弄完就可以再部署」, 收尾後可統一發布；本卡尚未開始正式站異動
