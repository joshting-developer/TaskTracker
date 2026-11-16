# 執行紀錄

- 已填估時並領取, TT-397 轉待驗收, TT-396 保留 open
- 現況通知與紙條 UI 寫死 Asia/Taipei, Repository 日期需核對現用版本與 MySQL UTC 儲存差異

- 正式 MySQL 唯讀確認 SYSTEM／SYSTEM／UTC, NOW 與 UTC_TIMESTAMP 一致；不改 VM 時區或歷史時間
- 工作區時區設為 UTC 預設, 保留工時 Asia/Taipei 工作日；419 個前端選項均確認 PHP 支援, 排除瀏覽器不支援的 Factory
- 完整後端最終 524 通過／20,249 assertions, 1 個 R2 專用略過另組驗證。初輪直接 artisan 的 128MB 設定不足, 已改用既有 512MB runner；另一輪抓到舊升級測試漏套新 migration, 已補正確順序與非 UTC 偏好保存後通過
- 新設定保存、重新整理、衝突保留草稿、一般成員拒絕、UTC／台北同一通知時間與三尺寸通過；九張已檢視核准基準重新擷取比較, threshold 0.02／maxDiffPixels 0 全部零差異
- 已發 Josh 紙條 7ff5d6e8-7a5d-42bd-a9b6-2457f860c3c0；本地獨立 local-dev 已套加法 migration, 未 seed／重設
- 全站初輪候選誤用 Playwright Chrome153, 與既有基準 Chrome157 不同, 不採用；恢復明確系統 Chrome157 重新擷取, 不改比較器或閾值

- 全站 162 張 Chrome157 正式比較通過, environmentMatches=true, 已保存 artifacts/tt398/full-visual-comparison.json；三種退化注入再驗中
- 完整前端中 account-settings 仍引用舊成功文案, 已同步新「工作空間設定已儲存」文字, 等本輪其餘項目結束後重跑該項；不將初輪失敗計為通過
- 另有 TT-350 的三份已提交更新, 確認由其他協作流程完成私人 GPT 唯讀驗收, 保留其 Memory 與卡片狀態, 不重做或覆蓋

- 全部 75 項一般前端檢查已通過, 其中 account-settings 舊文案與 uiux 邀請未等完成已修測試並精準重跑成功, 原始失敗 log 保留, 彙整 artifacts/tt398/general-summary.json
- 全站 162 張正式比較及五種校準結果均通過；三種退化產生真正 diff 並被拒絕, 原樣與還原通過。證據 artifacts/tt398/full-visual-proof.json, 未放寬閾值

## 2026-11-16 接手核對

- 核對 main 未提交修改均屬本卡, 保留已提交的 GPT 串接進度；證據清單排除另一流程的 TT-404 plans, Memory.md 差異僅新增本卡索引
- 六項既有驗證產物雜湊一致, 全站視覺來源指紋有效；重新執行 check_evidence.py 通過
- 本輪重跑 SQLite／隔離 MySQL 時區專項 2 項、62 assertions 通過, 時區 domain、路由、API client 與 composables 通過
- 標準 smoke 在 build 清理 frontend/dist/admin 時遭 EPERM；保留既有目錄的 build 通過, 紀錄 artifacts/tt398/handoff-build.log。系統 Chrome 與預設 Chromium 均遭 macOS MachPort bootstrap_check_in Permission denied, 工作區 TMPDIR 重試仍受阻, 本輪真瀏覽器冒煙未通過
- 不以先前成功產物取代本輪受阻結果, 不修改測試門檻或核准基準；先保存本卡階段交付, 正式 migration／v1.0.0 發布與驗收尚未執行
