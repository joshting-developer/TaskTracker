# TT-323 首次Fly部署

- Josh本次授權部署到josh-90, 基本服務正常後停寫／備份並同步本地主庫到正式MySQL；需要決策用紙條回報, 先採建議方案
- 原Josh指派的open卡依本次交辦轉Codex再claim, 沿用預估240分鐘；一次只做本卡
- 使用共用fly-io、工程與Ponytail lite；正式設定依TT-321, 資料移轉依TT-341
- 首次先Fly remote build, commit短碼／digest固定映像, TT-320 CI另留待辦；已發Josh紙條說明, 不自行push／tag
- 建App、東京單台512MB、Secrets／CA、migration前備份、正式HTTPS／資料連線最小驗證
- 基本正常後才執行TT-341搬遷, 保留來源與兩端備份, 全表比對、帳號／卡片／附件驗收；不以health取代業務驗收
