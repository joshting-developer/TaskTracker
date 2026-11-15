# 執行紀錄

- 已確認 TT-193 已指派 Codex, 實際開工才 claim；前兩張功能卡已待整批部署
- 本次將原候選卡收斂為 Tags, 通用批次編輯仍未排程

- Josh 最新指定：Season 0 完整發布為 v1.0.0, 正式驗收後恢復目標帳號 GPT 串接, 不在 Tags 未完成前切換工作
- Tags 第一輪 SQLite／MySQL 與既有公開讀寫／異動回歸 38 項、1453 assertions 通過
- 前端新增管理頁、獨立定義編輯、可分頁選取與差量套用, 列表／看板顯示名稱而非只看色點；修改草稿接既有離開保護

- 完整後端 520 項、20,185 assertions 通過；R2 專用測試依環境門檻另跑, 不冒稱這輪已測
- 新舊 UI 三尺寸檢閱, 新標籤畫面共 15 張以原門檻獨立比對通過；無遮罩或放寬參數
- 共用 CLI 實際以隔離 PAT 驗 CRUD、差量、AND 篩選、看板篩選與刪除 receipt 重播, 結束撤銷測試 PAT
- 修正完整回歸發現的操作紀錄 middleware、OpenAPI 範例、PHPDoc 及歷史 migration 外鍵／日期相容
- 新增 TT-393／394／395 已收錄 Bug 與 Season 0；除 GPT 外全數完成才發 v1.0.0, GPT 另外部署。本卡正式套標與發布仍待整批收尾

- 回到本卡補發布前索引：隔離 MySQL 修前 EXPLAIN 的 possible_keys 為空, 只會掃 PRIMARY；因為關聯預載只有 issue_id 條件, 既有 workspace／project 前綴無法直接使用
- 新增獨立 migration 補 issue_id／tag_id 索引, 不修改卡片或標籤資料；兩種引擎測試檢查實際索引左前綴
- 隔離 MySQL 修後 EXPLAIN 已能選用 issue_tags_issue, 並實際選用該索引；現有合成資料量小, 不宣稱延遲倍率或正式 p95
- 補索引階段雙引擎 Tags、歷史儲存及 PHPDoc 檢查通過；不以原先 520 項全套結果替代最終發布回歸
