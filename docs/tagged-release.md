# Tag發布流程（TT-320）

## 目前範圍

每張卡獨立commit與驗證, main push只跑契約CI。Josh在2026-11-14要求這批工作全部整理好後只打一個tag一起部署, 不按卡片逐一升版

新增Tagged release workflow只收v開頭的版本tag, 版本格式例如v0.3.0, 必須指向當時main HEAD。先驗秘密掃描與完整契約CI, 再建linux/amd64正式映像, 用合成APP_KEY與隔離容器驗首頁、登入、SPA、health、匿名OpenAPI及註冊政策, 成功後才匯出同一映像供部署工作使用

映像標記版本與原始碼commit, 不用latest；已存在Fly版本拒絕覆寫。建置工作用docker save匯出已測映像, 以保留1天的Actions artifact傳遞, 同時核對archive SHA256、Docker image ID、commit與版本標籤。Job summary留下commit及映像識別, 不含環境秘密

## 權限

驗證工作只讀程式碼；建置工作沒有packages:write或正式Fly／資料庫／Redis／R2憑證。production環境的部署工作只用App範圍Fly deploy token, 下載同次workflow產出的受驗證archive；token不進build arg或映像

部署工作核對archive及映像識別後轉推私有Fly registry固定版本標籤, 然後部署同一映像, 不重新build。映像不再推GHCR, Fly也不保存GitHub讀取Token

## 資料保護與失敗

目前自動發布只允許全部migration都已在正式庫執行的版本。部署前只讀正式migrations名稱, 與候選程式檔案比對；有待執行migration就停止在推Fly與部署之前, 另做可還原備份和遷移方案。保留release command, 不跳過失敗或自動seed／reset

這批只改程式與配置, 不包含schema異動。若日後需要自動做schema備份, 必須另設計私有加密與可還原驗證, 不把SQL上傳公開CI產物

release concurrency序列化且不中途取消前一個發布；任何驗證失敗都不部署。CLI固定0.5.10、Actions固定commit；Secrets名稱只用FLY_API_TOKEN, 真值由私有輸入保存到GitHub production環境, 不貼卡片或文件

## 本機與正式驗收

- actionlint及Shell語法檢查
- migration guard：既有schema通過、新schema拒絕、空目錄或壞snapshot拒絕
- 本機正式runtime合成容器smoke, 不使用正式帳密；本機架構與CI linux/amd64分開記錄
- Gitleaks歷史掃描只忽略LegacyImportTest既有固定scrypt測試向量的精準fingerprint, 不排除整個tests資料夾
- 最後整批tag實際執行後, 才能宣稱映像交付／Fly整條流程驗收完成；workflow檔與本機smoke不等於已發布

目前仍需遠端main CI與最終tag驗收。現行Laravel低風險公告由TT-345另追, 秘密掃描通過不代表依賴完全沒有安全公告；首次部署到現在的回退與外部服務來源限制仍依各自卡片驗收


## 首次發布失敗與同tag續跑

v0.3.0第一次執行的秘密掃描、契約CI與Linux runtime smoke通過, 但GHCR推送後可匿名取得manifest, private檢查失敗, Fly部署被跳過。沒有正式Secrets進入映像, 來源repo也公開, 但私有映像條件仍未達成, 不把這次執行算成功

修正後改用Actions artifact傳遞已驗證映像, 只在部署工作拿App範圍Fly token推私有registry。workflow_dispatch可指定既有版本續跑, 不移動或重打tag；所有用於build／契約測試的來源都解析到該tag固定SHA, 部署腳本則從main取得修正後的編排。新的tag仍須指向main HEAD

清理工作只接受首次失敗產出的精確digest與兩個標籤, 若套件多了其他版本就拒絕刪除, 不廣泛清空帳號套件。實際清理與同tag續跑結果須另行確認
