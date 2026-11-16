# 工作區顯示時區

TT-398, 2026-11-16

工作區在一般設定選擇 IANA 顯示時區, 預設 UTC, 可以改成 Asia/Taipei、America/New_York 等。設定套用到通知、紙條、卡片活動、邀請及 Token 到期時間的顯示；切換工作區或登出後不沿用上一個空間的偏好

持久時間以 UTC 儲存, API 仍回傳含 Z 的 ISO 8601。設定不改寫紀錄, 不把舊時間平移。工時工作日與報表期間保留 Asia/Taipei 契約, 不受這個顯示偏好影響

`POST /api/rpc/updateWorkspace` 增加可選 `timeZone` 字串, 省略就保留目前設定；名稱仍必填, revision／requestId／workspace.update 授權不變。無效或 null 時區會被拒絕, 不保存名稱或重播紀錄。`GET /api/v1/workspace/settings` 與 Session 身分的 workspace 多回傳 `timeZone`, 不增加列表、成員或歷史查詢

正式 MySQL 已唯讀確認 global／session 為 SYSTEM, 系統時區 UTC, NOW 與 UTC_TIMESTAMP 一致。紙條交接舊寫入的跨日問題另由 TT-369 修正, 一起隨 v1.0.0 發布。沒有可信原始時間的歷史資料不批量修補, 需要具體樣本再核對
