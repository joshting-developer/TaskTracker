# 本批發布前驗證

範圍是TT-159、TT-344、TT-320、TT-346、TT-353與TT-354, 依Josh要求只打一個tag發布。每卡提交保留, main CI不部署

## 已通過

- 後端SQLite與隔離MySQL全套435案例、18319斷言, 1個既有環境條件略過；不包含正式資料庫壓測
- TT-353通知連續定位只顯示一組分頁, Tab與查詢scope一致, 不殘留建立成功提示；Markdown原稿、編輯、重開及轉卡流程通過
- TT-354通知批次已讀6案例203斷言, 包含容器解析不得查SQL、跨頁23筆、本人／租戶／參與資格、舊請求重播後保留新通知及撤權
- 完整視覺比較使用原核准的Chrome157, 三尺寸通過。預設Chromium153與基準不同造成原生驗證提示差異, 該輪不算通過；沒有放寬比較門檻或重建基準
- 前端69支檢查中, 64支已執行並通過, 包含6支重測。首輪後端全套與瀏覽器共用隔離schema造成看板建卡失敗, 分開重測後通過；寄信及註冊依各自要求配置隔離拓樸後通過
- 綜合browser檢查修正舊測試登入時誤填「確認密碼」為Email, 邀請註冊時的確認密碼仍保留, ACL／撤權／衝突／手機／工時／看板／完整頁流程通過
- actionlint、Fly配置與migration拒絕保護通過；正式30個migration已套用, 本批無schema異動
- 正式資料已另外保存0600私有single-transaction備份, 沒有還原或改動資料；APP_KEY、PAT、SQL與附件key未納入Git

## 沒有執行的專用檢查

5支需要不同拓樸, 沒有把它們記為通過：production-smoke需要獨立Nginx/FPM runtime（tag CI另跑映像smoke）；r2-attachment與issue-pagination依賴舊13089專用fixture；realtime依賴13024 Reverb服務（正式廣播已停用）；time-analysis-seed依賴seed帳號（獨立time-analysis與member-time流程已跑）

這些限制不表示正式R2或即時服務已驗收。R2仍須本批上線後, 用同一映像短暫停寫、清點補複製、切目標及逐筆下載核對；正式HTTP／登入／資料服務也須發布後實測。TT-159正式FPM三秒設定同樣待新版驗證

## 發布與回退

新GHCR套件尚不存在, 首次tag須實測private可見性、固定digest轉推Fly與production Secret資格。工作流程任一步失敗都不能宣稱發布完成, 不改公開套件、不跳過契約CI、不用latest

R2先維持來源完成程式tag部署, 再以同一版本的Machine設定短暫停寫並切換, 不另外打版本。來源完整保留, 不刪物件、不覆寫資料庫。回退先看目前資料相容性, 不直接還原舊備份
