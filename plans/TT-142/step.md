# TT-142進度

- 已讀交辦30cdadf8、原卡及背景觸發搜尋, 預估20分鐘並領取
- 確認3個Schedule、2種Job、ProjectChanged廣播、Redis基礎診斷, 正在整理每項的前期執行方式
- 已盤點3個排程、2個Queue Job、私有ProjectChanged廣播與Redis／Session／鎖；現況與Josh指定同步寄信方向分開記docs/background-work-inventory-2026-11-14.md
- 正式停Reverb時ProjectSignals現有guard已不派送；寄信仍需TT-162實作commit後同步及失敗結果。附件1000筆有界清理、90天觀測prune保留, 外部短命令觸發不得假定HTTP流量會跑Scheduler
- 本卡純文件, 查閱實際觸發點與相對路徑, 不重跑全庫或主庫清理；Fly外部排程尚未配置, 不把盤點當部署成功
