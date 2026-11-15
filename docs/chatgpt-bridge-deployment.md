# 最新帳號更正與暫停

2026-11-15 Josh確認目標是joshting.1999, 並非目前登入的ChatGPT帳號。以下歷史紀錄中的帳號歸屬推定不作為目標帳號驗收證明

TT-350維持blocked, 暫停Plugin部署、重建、所有權修復、安裝、PAT／Secret配置與私人實機驗收。只有回到目標帳號且Josh明確要求恢復, 才重新核對所有者、可見性、實際安裝身分及讀寫操作。舊紙條催促已被新決策取代, 不因一般API可用就宣稱私人Plugin已完成

原交接檔只供閱讀, 不複製進Repo；以下保留操作歷史, 不刪服務或移轉秘密

# TaskTracker Bridge私人發布

2026-11-15／TT-350

- 私人Site：https://tasktracker-private-bridge.consideratecheetah3.chatgpt.site
- 來源：TaskTracker main 1f0799d中的integrations/chatgpt-bridge, 子樹commit 94a252ef3446e44a4c5e5adb9cecaf8f19ae704e
- Site版本1, 私人發布成功, 平台回has_mcp=true。存取政策讀回只有owner一人, 無群組／編輯者／外部訪客, 沒改公開
- 匿名GET /health與/mcp均401。平台安裝查詢回Already installed, 目前對話未出現新工具, 不能推論已實際連線

## 尚未完成

沒有設定TASKTRACKER_PAT, 所以還不能查正式資料。只配置owner可信信箱（Secret）與SideProject workspace ID。專用短效唯讀Token必須由Josh透過安全設定接入, 不貼聊天、不用Agent共用Token、不回退Owner憑證

TT-350保留受阻：待新聊天能呼叫who_am_i、正確空間與可見專案、單卡revision、精準卡號定位、Secret不進前端／工具／log與撤銷拒絕, 才能接受真實唯讀E2E。私人發布成功不代表上述條件已通過

## 後續更新來源

Sites來源helper會初始化另一個Git；依本專案只用原repository的規範, 目前從原repo用git subtree split匯出Adapter, 核對manifest project_id與精確子樹SHA, 只正常push到原生回傳的Site remote, 不force push或另clone。憑證只在記憶體, 透過隱藏stdin與短效環境傳遞, 不放URL／argv／Git

打包沿用官方package-site.mjs, 先本地測試及build, 再核對Site來源HEAD與archive對應commit, 使用原生owner-private發布。不得把TaskTracker其他目錄、歷史機密或資料庫送進Site來源庫

## 2026-11-15 正確帳號重新建立

Josh確認目前登入的joshting.1999為正式擁有者。舊服務在此帳號not found, 不移轉或刪除舊站；新建Site appgprj_6af99efb261c8191941e0e30391c219f, 平台讀回Josh為owner, 唯一allowlist使用者的可信信箱為joshting.1999@gmail.com, 無群組／編輯者／外部訪客。來源manifest改綁新ID, 舊ID只保留此文件沿革

新站https://tasktracker-joshting-bridge.joshting-1999.chatgpt.site 已私人發布版本1, deployment appgdep_6af99f747228819192a2fa18717d572f成功, has_mcp=true, environment revision1。來源子樹03021bf87af41267045c0988e9cc76c1df6485c2, 對應main 8f745a0。只配置owner及工作區Secret, 沒有複用或搬移舊PAT。真實唯讀資料仍待專用Token與可呼叫工具驗收

新Plugin ID為plugin_asdk_app_sites_4d376bf34c9881919b81325cef3f3c8c, 原生安裝查詢回Already installed。發布後讀回owner-only無變更。帶一般識別User-Agent的匿名GET /health及/mcp實測401；Python預設User-Agent被邊緣403, 不將該403當作應用認證結果。當前對話未出現8個工具, 沒有執行真人查卡／撤銷驗證
