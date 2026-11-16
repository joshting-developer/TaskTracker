# 執行紀錄

- 已重新核對 Season 0 與最新卡片, TT-393／394 程式已提交待整批發布, TT-395 本地修復與 TT-363 既有正式品牌驗收已結案
- TT-193 補 issue_id／tag_id 索引並以隔離 MySQL EXPLAIN 驗證；三個加法 migration 尚未在正式執行
- 發布條件依 Josh 最新指示：Season 0 非 GPT 全數開發驗收完成才部署 v1.0.0, GPT 另外發布
- 正式唯讀一致備份已存私有 0600 SQL, 以無網路、無公開埠的獨立 MySQL 還原成功並核對雜湊及 47 表；未執行正式 migration
- 首次全套前置檢查因 Codex 測試容器停止而失敗, 只恢復自己兩個測試服務。第二輪後端 522 項／20,187 assertions 通過, 一般前端受先前管理固定日期 router 的跨日 Cookie／CSRF 影響而停止
- 將一般回歸恢復標準 artisan serve 真實時鐘, 固定日期只留專屬管理畫面使用；保留失敗紀錄後重新全套, 不把環境失敗改成通過
- 對還原副本預演三個加法 migration 通過, users／workspaces／issues／comments／time_entries 筆數保持不變, 標籤定義仍空；原正式庫尚未修改
- 已清理本人本次無網路還原容器, 私有 SQL 與雜湊 manifest 保留；不保存資料副本到公開測試產物

- 162 張整批正式比較通過, Chrome 157／同環境、threshold 0.02／maxDiffPixels 0 不變, 退化注入驗證進行中
- 正式套標計畫依交接原文保留 Season 0／1／2 與「暫不執行」名稱, 不擅自改名 Future；先保存清冊, 待正式 Tags API 上線後才執行, 不假稱已套
- 四筆最新 main 契約 CI 均成功, 包含本批功能及 Bug 提交；尚未建立版本 tag 或部署
- 全部 162 張正式比較已通過；spacing／overflow／hide-action 三種注入均產生真正 diff 並被拒絕, 原樣與還原也通過。舊圖封存於私有目錄, 未放寬閾值
- release guard／archive／migration runner／retry 的測試與 actionlint 通過；Git 秘密掃描 418 個提交未發現洩漏
- Docker 恢復後另由既有 helper 恢復 tasktracker-local-dev, 5174／3002／8025 及原資料保留, 沒有 seed 或重啟舊主庫
- 使用者中斷後原回歸程序已停止, seed-roles 雖留下完成文字但未記錄 exit code, 不算已驗收。已確認無原程序, 只清理本人的 seed 容器與殘留鎖, 重跑 seed 後接 realtime／r2／runtime；既有通過組別不全部重跑
- 共用 Skill 完整性檢查另抓到 TT-193 CLI 更新後漏同步三份 canonical hash, 已補清冊並重新通過, 共用庫 commit 3594c7a；未改驗證器或 vendor, 先前未核對錯誤的證據不再沿用
