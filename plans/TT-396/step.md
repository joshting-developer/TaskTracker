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

- seed 第二輪確認是測試在邀請 refresh 完成前點導覽, 被 switchingWorkspace 保護拒絕；補等候既有成功提示後整組通過, 不修改產品或加大 timeout。realtime 與 R2 專用流程亦已通過, runtime 尚在跑
- 看板新增 TT-397／398, 暫停 TT-396 並改回 open, 先依序處理新 Season 0 卡片；發布仍未執行

## 2026-11-16 發布接續

- 重新閱覽 AGENTS、TT-409 與其他 session 的交接；TT-398 已由 3aa73bf 提交, main 最新為 99a7c02, 最新 CI 43801947371 成功。GPT Adapter 版本3另由其他流程交付, 不重做或混部署
- 工作樹乾淨後重新領取 TT-396。以明確 Chrome157／Codex 隔離後端重跑發版 smoke 通過, 先前 macOS 啟動與 dist 權限問題本輪未重現
- 既有 524 後端、75 一般前端（含兩項精準重跑）、七組專用拓樸及162張視覺／退化結果保留, 不因文件提交無限重跑；發布前再備份最新正式資料並預演四個加法 migration（含工作區時區）

- 新版 seed／realtime／R2（瀏覽器及 MySQL 專用）／runtime 全組 exit 0 且清理完成, 不把進行中局部結果算通過
- 最新正式一致備份 5,650,285 bytes, 私有檔雜湊／無網路還原已驗；47 表、4 帳號、1 工作區、550 卡片, 四個加法 migration 預演主要業務筆數不變
- 七張 review 開發卡依使用者持續代審授權逐卡回填驗收後結案, 正式上線承接仍在 TT-396, 不宣稱此時已部署

- 四個正式加法 migration 已使用專用帳號／驗證 TLS 套用成功, 保留最新備份, 無 seed／reset／覆寫。正式變更結果 artifacts/tt396/formal-migrations.json
- 驗收留言初次因漏 expectedRevision 被 API 拒絕, 未修改資料；補最新版本後逐卡重送。原要給 Josh 的兩張方案紙條仍由 Codex 承辦, 已依原授權轉交本人並核對
