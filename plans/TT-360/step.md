# 執行紀錄

- 重現：`todos` 拿CSRF token回403, curl用 `Python-urllib/3.13` 打 `/api/csrf-cookie` 回 `error code: 1010`, 換User-Agent就是200
- `/api/public/*` 用同樣的User-Agent沒被擋, 所以共用庫PAT工具正常, 這次用它開卡和領卡
- 補上固定User-Agent後, `--agent claude todos` 讀到3張紙條, `--agent codex list` 和 `--agent claude list` 都讀得到卡片；這次只有讀取, 沒有用這支工具寫入
- 沒有自動測試覆蓋這支工具, 驗證就是實際連正式站讀取
