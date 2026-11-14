# 正式API工具被Cloudflare 1010攔截

2026-11-15 TT-326開發中, 原先可用的Python標準PAT工具在匿名讀取 `/api/public/openapi.json` 時得到403, Cloudflare明確回覆 `error_code: 1010`、`browser_signature_banned`。尚未送出Token, 不是Laravel帳號或PAT權限被拒絕

正式站健康檢查及curl匿名GET規格回200, 但不能因此偽裝User-Agent、改用其他帳號、走不同主機或攜帶PAT繞過Cloudflare限制。需由站台管理者確認Browser Integrity Check／相關規則, 允許合法第三方API客戶端, 保留Laravel認證、Scope、限流與租戶隔離

## 尚未回填的進度

TT-326目前已由Codex領取, 預估90分鐘, 後續實作及測試記錄在plans/TT-326與artifacts/tt326。這次403發生在規格發現前, 尚未送出進度留言, 不存在不確定的寫入結果

正式工具恢復後先讀目前身分、紙條／通知及卡片revision, 再補留言、正式實際工時與最終狀態。未恢復前不宣稱已回填卡片或填工時, 不另寫舊本地業務庫

修復完成需從原Python工具直接讀公開規格、驗本人PAT身分, 再以正常流程更新卡片；不用放寬整站防護或停用Laravel權限。此文件只是交接, 尚未建立對應正式卡片

## 已恢復

Josh停用該DNS紀錄的Cloudflare proxy後, 公開DNS先更新為Fly正式IP, macOS系統解析短暫保留舊Cloudflare IP。等待正常DNS快取更新後, 原Python工具以原網址、原User-Agent及原Codex PAT直接驗身分成功, 沒有指定替代IP或換帳號

已重新讀取紙條與通知, 補登TT-326實作／測試留言、18分鐘本人實際工時與review待驗收。這次停用proxy由Josh操作, 沒有另外修改Cloudflare或主機防火牆規則
